<?php

// Simple EXIF extraction test script
// Usage: php test_exif.php path/to/your/image.jpg

if ($argc < 2) {
    echo "Usage: php test_exif.php path/to/your/image.jpg\n";
    exit(1);
}

$imagePath = $argv[1];

if (!file_exists($imagePath)) {
    echo "Error: File not found: $imagePath\n";
    exit(1);
}

echo "Extracting EXIF data from: $imagePath\n";
echo "=====================================\n\n";

$exif = exif_read_data($imagePath);

if (!$exif) {
    echo "No EXIF data found in the image.\n";
    exit(0);
}

echo "All EXIF fields found:\n";
echo "======================\n";
foreach ($exif as $key => $value) {
    echo "$key: " . json_encode($value) . "\n";
}

echo "\nGPS-related fields:\n";
echo "==================\n";
foreach ($exif as $key => $value) {
    if (strpos($key, 'GPS') === 0) {
        echo "$key: " . json_encode($value) . "\n";
    }
}

echo "\nLocation-related fields:\n";
echo "=======================\n";
$locationFields = ['Location', 'LocationName', 'City', 'State', 'Country', 'Address'];
foreach ($locationFields as $field) {
    if (isset($exif[$field])) {
        echo "$field: " . json_encode($exif[$field]) . "\n";
    }
}

// Try to extract coordinates using different methods
echo "\nCoordinate extraction attempts:\n";
echo "==============================\n";

// Method 1: Standard GPS coordinates
if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
    echo "Method 1 - Standard GPS:\n";
    echo "GPSLatitude: " . json_encode($exif['GPSLatitude']) . "\n";
    echo "GPSLongitude: " . json_encode($exif['GPSLongitude']) . "\n";
    echo "GPSLatitudeRef: " . ($exif['GPSLatitudeRef'] ?? 'N/A') . "\n";
    echo "GPSLongitudeRef: " . ($exif['GPSLongitudeRef'] ?? 'N/A') . "\n";
    
    // Try to convert to decimal
    $lat = convertGpsCoordinate($exif['GPSLatitude'], $exif['GPSLatitudeRef'] ?? 'N');
    $lon = convertGpsCoordinate($exif['GPSLongitude'], $exif['GPSLongitudeRef'] ?? 'E');
    
    if ($lat !== null && $lon !== null) {
        echo "Converted coordinates: $lat, $lon\n";
    }
}

// Method 2: Check for decimal coordinates
if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
    echo "\nMethod 2 - Decimal check:\n";
    if (is_numeric($exif['GPSLatitude']) && is_numeric($exif['GPSLongitude'])) {
        $lat = (float) $exif['GPSLatitude'];
        $lon = (float) $exif['GPSLongitude'];
        
        if (isset($exif['GPSLatitudeRef']) && $exif['GPSLatitudeRef'] === 'S') {
            $lat = -$lat;
        }
        if (isset($exif['GPSLongitudeRef']) && $exif['GPSLongitudeRef'] === 'W') {
            $lon = -$lon;
        }
        
        echo "Decimal coordinates: $lat, $lon\n";
    } else {
        echo "Coordinates are not in decimal format\n";
    }
}

function convertGpsCoordinate($coordinate, $hemisphere) {
    if (!is_array($coordinate) || count($coordinate) !== 3) {
        return null;
    }

    $degrees = convertGpsFraction($coordinate[0]);
    $minutes = convertGpsFraction($coordinate[1]);
    $seconds = convertGpsFraction($coordinate[2]);

    $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

    if ($hemisphere === 'S' || $hemisphere === 'W') {
        $decimal = -$decimal;
    }

    return $decimal;
}

function convertGpsFraction($fraction) {
    if (is_array($fraction)) {
        return $fraction[0] / $fraction[1];
    }
    return (float) $fraction;
}
