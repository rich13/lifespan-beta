<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Carbon\Carbon;

class ExifExtractionService
{
    /**
     * Extract EXIF data from an uploaded image
     */
    public function extractExif(UploadedFile $file): array
    {
        // First try exiftool for richer metadata (QuickTime, XMP, MakerNotes)
        $exiftoolData = $this->extractWithExifTool($file);
        
        // Try PHP's exif_read_data for basic EXIF (only for supported formats)
        $exif = null;
        $mimeType = $file->getMimeType();
        $extension = strtolower($file->getClientOriginalExtension());
        
        // Only use PHP's exif_read_data for JPEG and TIFF files
        if (in_array($mimeType, ['image/jpeg', 'image/jpg', 'image/tiff', 'image/tif']) || 
            in_array($extension, ['jpg', 'jpeg', 'tiff', 'tif'])) {
            try {
                $exif = exif_read_data($file->getPathname());
            } catch (\Exception $e) {
                \Log::warning('PHP exif_read_data failed', [
                    'filename' => $file->getClientOriginalName(),
                    'mime_type' => $mimeType,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        if (!$exif && empty($exiftoolData)) {
            return [];
        }

        $data = [];

        // Extract all GPS-related data for debugging (only if PHP EXIF is available)
        if ($exif) {
            $gpsData = $this->extractAllGpsData($exif);
            if (!empty($gpsData)) {
                $data = array_merge($data, $gpsData);
            }
        }

        // Extract GPS coordinates with priority order
        \Log::info('Starting coordinate extraction', [
            'filename' => $file->getClientOriginalName(),
            'exiftool_data_count' => count($exiftoolData),
            'exif_data_available' => $exif !== null
        ]);
        
        $coordinates = $this->extractBestCoordinates($exiftoolData, $exif);
        if ($coordinates) {
            $data['coordinates'] = $coordinates['latitude'] . ',' . $coordinates['longitude'];
            $data['coordinate_source'] = $coordinates['source'] ?? 'Unknown';
            \Log::info('Coordinates extracted successfully', [
                'filename' => $file->getClientOriginalName(),
                'coordinates' => $data['coordinates'],
                'source' => $data['coordinate_source']
            ]);
        } else {
            \Log::warning('No coordinates found', [
                'filename' => $file->getClientOriginalName()
            ]);
        }

        // Try alternative coordinate extraction methods (like Apple might use)
        if ($exif) {
            $alternativeCoordinates = $this->extractAlternativeCoordinates($exif);
            if ($alternativeCoordinates) {
                $data['alternative_coordinates'] = $alternativeCoordinates['latitude'] . ',' . $alternativeCoordinates['longitude'];
                \Log::info('Found alternative coordinates', [
                    'primary_coordinates' => $data['coordinates'] ?? 'None',
                    'alternative_coordinates' => $data['alternative_coordinates']
                ]);
            }
        }

        // Extract date taken
        if ($exif && isset($exif['DateTimeOriginal'])) {
            try {
                $dateTaken = Carbon::createFromFormat('Y:m:d H:i:s', $exif['DateTimeOriginal']);
                $data['date_taken'] = $dateTaken->toISOString();
                $data['year'] = $dateTaken->year;
                $data['month'] = $dateTaken->month;
                $data['day'] = $dateTaken->day;
            } catch (\Exception $e) {
                // If date parsing fails, ignore it
            }
        }

        if (!isset($data['date_taken'])) {
            $bestDate = $this->extractBestDateTaken($exiftoolData, $exif);
            if ($bestDate) {
                $data['date_taken'] = $bestDate->toISOString();
                $data['year'] = $bestDate->year;
                $data['month'] = $bestDate->month;
                $data['day'] = $bestDate->day;
            }
        }

        // Extract camera information
        if ($exif) {
            if (isset($exif['Make'])) {
                $data['camera_make'] = $exif['Make'];
            }
            if (isset($exif['Model'])) {
                $data['camera_model'] = $exif['Model'];
            }

            // Extract other useful metadata
            if (isset($exif['Software'])) {
                $data['software'] = $exif['Software'];
            }
            if (isset($exif['ImageDescription'])) {
                $data['image_description'] = $exif['ImageDescription'];
            }
        }

        return $data;
    }
    protected function extractBestDateTaken(array $exiftoolData, ?array $exif): ?Carbon
    {
        $candidates = [];

        $combineIptc = static fn($data) => !empty($data['IPTC:DateCreated']) && !empty($data['IPTC:TimeCreated'])
            ? ($data['IPTC:DateCreated'] . ' ' . $data['IPTC:TimeCreated'])
            : null;

        $candidateFields = [
            'Composite:SubSecDateTimeOriginal',
            'Composite:DateTimeCreated',
            'Composite:CreatedDate',
            'Composite:CreateDate',
            'Composite:SubSecCreateDate',
            'Composite:SubSecModifyDate',
            'Composite:SubSecDateTimeCreated',
            'Composite:DateTimeOriginal',
            'EXIF:DateTimeOriginal',
            'EXIF:CreateDate',
            'MakerNotes:DateTimeOriginal',
            'QuickTime:CreateDate',
            'QuickTime:CreationDate',
            'QuickTime:MediaCreateDate',
            'QuickTime:TrackCreateDate',
            'QuickTime:ContentCreateDate',
            'QuickTime:ModifyDate',
            'XMP:CreateDate',
            'XMP:DateCreated',
            'XMP:MetadataDate',
            'PNG:CreationTime',
            'H264:DateTimeOriginal',
            'File:FileModifyDate',
        ];

        foreach ($candidateFields as $field) {
            if (!empty($exiftoolData[$field])) {
                $candidates[] = $exiftoolData[$field];
            }
        }

        if ($combine = $combineIptc($exiftoolData)) {
            $candidates[] = $combine;
        }

        if ($exif) {
            foreach (['DateTimeOriginal', 'DateTimeDigitized', 'CreateDate', 'DateTime'] as $phpField) {
                if (!empty($exif[$phpField])) {
                    $candidates[] = $exif[$phpField];
                }
            }
        }

        foreach ($candidates as $value) {
            $parsed = $this->parseExifDate($value);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    protected function parseExifDate(string $value): ?Carbon
    {
        $original = trim($value);
        if ($original === '') {
            return null;
        }

        $normalized = $original;

        if (substr($normalized, -1) === 'Z') {
            $normalized = substr($normalized, 0, -1) . '+00:00';
        }

        $normalized = preg_replace('/(\.\d+)([+-]\d{2}:\d{2})$/', '$1$2', $normalized);
        $normalized = str_replace('/', '-', $normalized);

        $normalized = str_replace(['.000000'], '', $normalized);

        $formats = [
            'Y:m:d H:i:s',
            'Y:m:d H:i:sO',
            'Y:m:d H:i:sP',
            'Y:m:d\TH:i:s',
            'Y:m:d\TH:i:sP',
            'Y-m-d H:i:s',
            'Y-m-d H:i:sP',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:sP',
            'Ymd\THisP',
            'Ymd\THis\Z',
            'Ymd H:i:s',
            'YmdHis',
        ];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $normalized);
            } catch (\Exception $e) {
                // continue trying other formats
            }
        }

        try {
            return Carbon::parse($normalized);
        } catch (\Exception $e) {
            if (preg_match('/^\d{8}$/', $normalized)) {
                return Carbon::createFromFormat('Ymd', $normalized);
            }
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized)) {
                return Carbon::createFromFormat('Y-m-d', $normalized);
            }
            \Log::debug('Failed to parse EXIF date', [
                'value' => $original,
                'normalized' => $normalized,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }


    /**
     * Extract GPS coordinates from EXIF data
     */
    protected function extractGpsCoordinates(array $exif): ?array
    {
        $latitude = $this->getGpsCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
        $longitude = $this->getGpsCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');

        if ($latitude !== null && $longitude !== null) {
            // Apply longitude correction for western hemisphere (same as Flickr logic)
            if ($longitude > 80) {
                $longitude = -$longitude;
                \Log::info('Applied longitude correction for western hemisphere', [
                    'original_longitude' => -$longitude,
                    'corrected_longitude' => $longitude,
                    'latitude' => $latitude
                ]);
            }
            
            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source' => 'EXIF'
            ];
        }

        return null;
    }

    /**
     * Convert GPS coordinate from degrees/minutes/seconds to decimal
     */
    protected function getGpsCoordinate($coordinate, $hemisphere): ?float
    {
        if (!is_array($coordinate) || count($coordinate) !== 3) {
            return null;
        }

        $degrees = $this->convertGpsFraction($coordinate[0]);
        $minutes = $this->convertGpsFraction($coordinate[1]);
        $seconds = $this->convertGpsFraction($coordinate[2]);

        $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

        if ($hemisphere === 'S' || $hemisphere === 'W') {
            $decimal = -$decimal;
        }

        return $decimal;
    }

    /**
     * Convert GPS fraction to decimal
     */
    protected function convertGpsFraction($fraction): float
    {
        if (is_array($fraction)) {
            return $fraction[0] / $fraction[1];
        }
        return (float) $fraction;
    }

    /**
     * Extract all GPS-related EXIF data for debugging and analysis
     */
    protected function extractAllGpsData(array $exif): array
    {
        $gpsData = [];
        
        // List of all possible GPS EXIF fields
        $gpsFields = [
            'GPSLatitude', 'GPSLongitude', 'GPSLatitudeRef', 'GPSLongitudeRef',
            'GPSAltitude', 'GPSAltitudeRef', 'GPSTimeStamp', 'GPSSatellites',
            'GPSStatus', 'GPSMeasureMode', 'GPSDOP', 'GPSSpeedRef',
            'GPSSpeed', 'GPSTrackRef', 'GPSTrack', 'GPSImgDirectionRef',
            'GPSImgDirection', 'GPSMapDatum', 'GPSDestLatitude', 'GPSDestLongitude',
            'GPSDestBearingRef', 'GPSDestBearing', 'GPSDestDistanceRef',
            'GPSDestDistance', 'GPSProcessingMethod', 'GPSAreaInformation',
            'GPSDateStamp', 'GPSDifferential'
        ];
        
        foreach ($gpsFields as $field) {
            if (isset($exif[$field])) {
                $value = $exif[$field];
                
                // Clean null bytes and other problematic characters
                if (is_string($value)) {
                    $value = str_replace("\x00", '', $value); // Remove null bytes
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value); // Remove control characters
                }
                
                $gpsData['gps_' . strtolower($field)] = $value;
            }
        }
        
        // Also look for any other fields that might contain GPS data
        foreach ($exif as $key => $value) {
            if (strpos($key, 'GPS') === 0 && !in_array($key, $gpsFields)) {
                // Clean null bytes and other problematic characters
                if (is_string($value)) {
                    $value = str_replace("\x00", '', $value); // Remove null bytes
                    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value); // Remove control characters
                }
                
                $gpsData['gps_' . strtolower($key)] = $value;
            }
        }
        
        // Log all GPS data for debugging
        if (!empty($gpsData)) {
            \Log::info('All GPS EXIF data found', [
                'gps_fields' => $gpsData,
                'total_gps_fields' => count($gpsData)
            ]);
        }
        
        return $gpsData;
    }

    /**
     * Try alternative coordinate extraction methods that Apple might use
     */
    protected function extractAlternativeCoordinates(array $exif): ?array
    {
        // Method 1: Try decimal coordinates if they exist
        if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
            // Check if coordinates are already in decimal format
            if (is_numeric($exif['GPSLatitude']) && is_numeric($exif['GPSLongitude'])) {
                $latitude = (float) $exif['GPSLatitude'];
                $longitude = (float) $exif['GPSLongitude'];
                
                // Apply hemisphere corrections
                if (isset($exif['GPSLatitudeRef']) && $exif['GPSLatitudeRef'] === 'S') {
                    $latitude = -$latitude;
                }
                if (isset($exif['GPSLongitudeRef']) && $exif['GPSLongitudeRef'] === 'W') {
                    $longitude = -$longitude;
                }
                
                return ['latitude' => $latitude, 'longitude' => $longitude, 'source' => 'EXIF Decimal'];
            }
        }
        
        // Method 2: Try different coordinate parsing (Apple might use different DMS parsing)
        if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
            $latitude = $this->parseCoordinateAppleStyle($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
            $longitude = $this->parseCoordinateAppleStyle($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');
            
            if ($latitude !== null && $longitude !== null) {
                return ['latitude' => $latitude, 'longitude' => $longitude, 'source' => 'EXIF Apple Style'];
            }
        }
        
        // Method 3: Check for any other location-related fields
        // Some cameras store location data in different fields
        $locationFields = ['Location', 'LocationName', 'City', 'State', 'Country'];
        foreach ($locationFields as $field) {
            if (isset($exif[$field])) {
                \Log::info('Found location field', ['field' => $field, 'value' => $exif[$field]]);
            }
        }
        
        return null;
    }

    /**
     * Parse coordinates in Apple-style (might handle edge cases differently)
     */
    protected function parseCoordinateAppleStyle($coordinate, $hemisphere): ?float
    {
        // Handle string format that might be decimal
        if (is_string($coordinate)) {
            if (is_numeric($coordinate)) {
                $decimal = (float) $coordinate;
                if ($hemisphere === 'S' || $hemisphere === 'W') {
                    $decimal = -$decimal;
                }
                return $decimal;
            }
        }
        
        // Handle array format (DMS)
        if (is_array($coordinate)) {
            if (count($coordinate) >= 3) {
                $degrees = $this->convertGpsFraction($coordinate[0]);
                $minutes = $this->convertGpsFraction($coordinate[1]);
                $seconds = $this->convertGpsFraction($coordinate[2]);
                
                $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);
                
                if ($hemisphere === 'S' || $hemisphere === 'W') {
                    $decimal = -$decimal;
                }
                
                return $decimal;
            }
        }
        
        return null;
    }

    /**
     * Extract metadata using exiftool for richer data (QuickTime, XMP, MakerNotes)
     */
    protected function extractWithExifTool(UploadedFile $file): array
    {
        try {
            $command = sprintf(
                'exiftool -j -G -a -u -api RequestAll=3 "%s" 2>/dev/null',
                $file->getPathname()
            );
            
            \Log::info('Running exiftool command', [
                'filename' => $file->getClientOriginalName(),
                'command' => $command,
                'file_path' => $file->getPathname()
            ]);
            
            $output = shell_exec($command);
            
            \Log::info('Exiftool output received', [
                'filename' => $file->getClientOriginalName(),
                'output_length' => strlen($output ?? ''),
                'output_preview' => substr($output ?? '', 0, 200)
            ]);
            
            if (!$output) {
                \Log::warning('Exiftool returned no output', [
                    'filename' => $file->getClientOriginalName()
                ]);
                return [];
            }
            
            $data = json_decode($output, true);
            
            if (!$data || !isset($data[0])) {
                \Log::warning('Failed to parse exiftool JSON output', [
                    'filename' => $file->getClientOriginalName(),
                    'json_error' => json_last_error_msg()
                ]);
                return [];
            }
            
            \Log::info('Exiftool extraction successful', [
                'filename' => $file->getClientOriginalName(),
                'fields_count' => count($data[0])
            ]);
            
            // Log all GPS-related fields found
            $gpsFields = [];
            foreach ($data[0] as $key => $value) {
                if (stripos($key, 'gps') !== false || stripos($key, 'location') !== false || stripos($key, 'latitude') !== false || stripos($key, 'longitude') !== false) {
                    $gpsFields[$key] = $value;
                }
            }
            
            if (!empty($gpsFields)) {
                \Log::info('GPS fields found in exiftool output', [
                    'filename' => $file->getClientOriginalName(),
                    'gps_fields' => $gpsFields
                ]);
            } else {
                \Log::warning('No GPS fields found in exiftool output', [
                    'filename' => $file->getClientOriginalName()
                ]);
            }
            
            return $data[0];
            
        } catch (\Exception $e) {
            \Log::warning('Failed to extract metadata with exiftool', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Extract the best available coordinates with priority order
     */
    protected function extractBestCoordinates(array $exiftoolData, ?array $exif): ?array
    {
        \Log::info('Starting coordinate extraction with priority order', [
            'exiftool_fields_count' => count($exiftoolData),
            'exif_available' => $exif !== null
        ]);
        
        // Priority 1: QuickTime location (most accurate for iOS)
        $quicktimeCoords = $this->extractQuickTimeCoordinates($exiftoolData);
        if ($quicktimeCoords) {
            \Log::info('Using QuickTime coordinates', [
                'coordinates' => $quicktimeCoords,
                'source' => 'QuickTime'
            ]);
            return $quicktimeCoords;
        }
        
        // Priority 2: XMP GPS coordinates
        $xmpCoords = $this->extractXmpCoordinates($exiftoolData);
        if ($xmpCoords) {
            \Log::info('Using XMP coordinates', [
                'coordinates' => $xmpCoords,
                'source' => 'XMP'
            ]);
            return $xmpCoords;
        }
        
        // Priority 3: Standard EXIF GPS coordinates from exiftool
        $exiftoolExifCoords = $this->extractExifToolExifCoordinates($exiftoolData);
        if ($exiftoolExifCoords) {
            \Log::info('Using EXIF coordinates from exiftool', [
                'coordinates' => $exiftoolExifCoords,
                'source' => 'EXIF (exiftool)'
            ]);
            return $exiftoolExifCoords;
        }
        
        // Priority 4: Standard EXIF GPS coordinates from PHP
        if ($exif && isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
            $exifCoords = $this->extractGpsCoordinates($exif);
            if ($exifCoords) {
                \Log::info('Using EXIF coordinates from PHP', [
                    'coordinates' => $exifCoords,
                    'source' => 'EXIF (PHP)'
                ]);
                return $exifCoords;
            }
        }
        
        \Log::warning('No coordinates found in any extraction method', [
            'quicktime_tried' => true,
            'xmp_tried' => true,
            'exiftool_exif_tried' => true,
            'php_exif_tried' => $exif !== null
        ]);
        
        return null;
    }

    /**
     * Extract QuickTime location coordinates
     */
    protected function extractQuickTimeCoordinates(array $exiftoolData): ?array
    {
        // Check for QuickTime location in various formats
        $quicktimeFields = [
            'QuickTime:GPSCoordinates',
            'QuickTime:Location',
            'QuickTime:com.apple.quicktime.location.ISO6709',
            'QuickTime:GPSLatitude',
            'QuickTime:GPSLongitude'
        ];
        
        foreach ($quicktimeFields as $field) {
            if (isset($exiftoolData[$field])) {
                $value = $exiftoolData[$field];
                
                // Handle ISO 6709 format: "+50.1028-005.4152+017.1/"
                if (is_string($value) && preg_match('/^([+-]\d+\.\d+)([+-]\d+\.\d+)([+-]\d+\.\d+)\/$/', $value, $matches)) {
                    $latitude = (float) $matches[1];
                    $longitude = (float) $matches[2];
                    
                    return [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'source' => 'QuickTime ISO6709'
                    ];
                }
                
                // Handle separate lat/lon fields
                if ($field === 'QuickTime:GPSLatitude' && isset($exiftoolData['QuickTime:GPSLongitude'])) {
                    $latitude = (float) $value;
                    $longitude = (float) $exiftoolData['QuickTime:GPSLongitude'];
                    
                    return [
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'source' => 'QuickTime GPS'
                    ];
                }
            }
        }
        
        return null;
    }

    /**
     * Extract XMP GPS coordinates
     */
    protected function extractXmpCoordinates(array $exiftoolData): ?array
    {
        $xmpFields = [
            'XMP:GPSLatitude',
            'XMP:GPSLongitude',
            'XMP-exif:GPSLatitude',
            'XMP-exif:GPSLongitude'
        ];
        
        foreach ($xmpFields as $field) {
            if (isset($exiftoolData[$field])) {
                $value = $exiftoolData[$field];
                
                if (is_numeric($value)) {
                    $latitude = (float) $value;
                    
                    // Find corresponding longitude
                    $longitudeField = str_replace('Latitude', 'Longitude', $field);
                    if (isset($exiftoolData[$longitudeField])) {
                        $longitude = (float) $exiftoolData[$longitudeField];
                        
                        return [
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'source' => 'XMP'
                        ];
                    }
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract EXIF GPS coordinates from exiftool output
     */
    protected function extractExifToolExifCoordinates(array $exiftoolData): ?array
    {
        $latitude = null;
        $longitude = null;
        
        // Try to get coordinates from EXIF fields
        if (isset($exiftoolData['EXIF:GPSLatitude']) && isset($exiftoolData['EXIF:GPSLongitude'])) {
            $latStr = $exiftoolData['EXIF:GPSLatitude'];
            $lonStr = $exiftoolData['EXIF:GPSLongitude'];
            
            // Parse the coordinate strings (e.g., "50 deg 6' 14.26\"")
            $latitude = $this->parseCoordinateString($latStr);
            $longitude = $this->parseCoordinateString($lonStr);
            
            // Apply hemisphere corrections
            if (isset($exiftoolData['EXIF:GPSLatitudeRef']) && $exiftoolData['EXIF:GPSLatitudeRef'] === 'South') {
                $latitude = -$latitude;
            }
            if (isset($exiftoolData['EXIF:GPSLongitudeRef']) && $exiftoolData['EXIF:GPSLongitudeRef'] === 'West') {
                $longitude = -$longitude;
            }
        }
        
        if ($latitude !== null && $longitude !== null) {
            return [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'source' => 'EXIF (exiftool)'
            ];
        }
        
        return null;
    }
    
    /**
     * Parse coordinate string from exiftool (e.g., "50 deg 6' 14.26\"")
     */
    protected function parseCoordinateString(string $coordStr): ?float
    {
        // Match pattern like "50 deg 6' 14.26\""
        if (preg_match('/(\d+) deg (\d+)\' ([\d.]+)"/', $coordStr, $matches)) {
            $degrees = (int) $matches[1];
            $minutes = (int) $matches[2];
            $seconds = (float) $matches[3];
            
            return $degrees + ($minutes / 60) + ($seconds / 3600);
        }
        
        return null;
    }

    /**
     * Extract GPS accuracy information if available
     */
    protected function extractGpsAccuracy(array $exiftoolData): ?float
    {
        $accuracyFields = [
            'EXIF:GPSHPositioningError',
            'EXIF:GPSDOP',
            'QuickTime:GPSAccuracy',
            'XMP:GPSAccuracy'
        ];
        
        foreach ($accuracyFields as $field) {
            if (isset($exiftoolData[$field])) {
                $value = $exiftoolData[$field];
                
                if (is_numeric($value)) {
                    return (float) $value;
                }
                
                // Handle fraction format like "5/1"
                if (is_string($value) && strpos($value, '/') !== false) {
                    $parts = explode('/', $value);
                    if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                        return (float) $parts[0] / (float) $parts[1];
                    }
                }
            }
        }
        
        return null;
    }
}
