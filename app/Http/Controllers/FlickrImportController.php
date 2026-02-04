<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Carbon\Carbon;
use App\Services\OAuth\FlickrServer;
use League\OAuth1\Client\Credentials\ClientCredentials;
use League\OAuth1\Client\Credentials\TokenCredentials;

class FlickrImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Show the Flickr import settings page
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get Flickr user ID from user settings (API credentials are global)
        $flickrUserId = $user->getMeta('flickr.user_id');
        
        return view('settings.import.flickr.index', compact('flickrUserId'));
    }

    /**
     * Store Flickr user ID
     */
    public function storeCredentials(Request $request)
    {
        $request->validate([
            'user_id' => 'required|string|max:255',
        ]);

        $user = Auth::user();
        
        // Store user ID in user metadata (API credentials are global)
        $user->setMeta('flickr.user_id', $request->user_id);
        $user->save();

        return redirect()->route('settings.import.flickr.index')
            ->with('success', 'Flickr user ID saved successfully.');
    }

    /**
     * Test the Flickr API connection
     */
    public function testConnection(Request $request)
    {
        $user = Auth::user();
        
        $apiKey = config('services.flickr.api_key');
        $userId = $user->getMeta('flickr.user_id');
        
        if (!$apiKey || !$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Flickr API key not configured or user ID not set'
            ], 400);
        }

        try {
            // Check if user has OAuth access
            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
            
            if ($hasOAuth) {
                // Use OAuth for authenticated access
                $data = $this->makeOAuthRequest('flickr.people.getInfo', [
                    'user_id' => $userId
                ]);
            } else {
                // Fall back to API key method with timeout
                $response = Http::timeout(10)->get('https://api.flickr.com/services/rest/', [
                    'method' => 'flickr.people.getInfo',
                    'api_key' => $apiKey,
                    'user_id' => $userId,
                    'format' => 'json',
                    'nojsoncallback' => 1
                ]);

                if (!$response->successful()) {
                    $statusCode = $response->status();
                    $errorMessage = match($statusCode) {
                        502, 503 => 'Flickr API is temporarily unavailable (HTTP ' . $statusCode . '). Please try again in a few moments.',
                        429 => 'Rate limit exceeded. Please wait a few minutes before trying again.',
                        401, 403 => 'Authentication failed. Please check your API key and user ID.',
                        404 => 'User ID not found. Please verify your Flickr user ID.',
                        default => 'Failed to connect to Flickr API (HTTP ' . $statusCode . ')'
                    };
                    
                    Log::warning('Flickr API connection test failed', [
                        'status_code' => $statusCode,
                        'user_id' => $userId,
                        'has_api_key' => !empty($apiKey)
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => $errorMessage
                    ], $statusCode >= 500 ? 503 : 400);
                }

                $data = $response->json();
            }
                
                if ($data['stat'] === 'ok') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Connection successful! Connected to ' . ($data['person']['username']['_content'] ?? 'Flickr user'),
                        'user_info' => $data['person']
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Flickr API error: ' . ($data['message'] ?? 'Unknown error')
                    ], 400);
                }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('Flickr API connection timeout', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection timeout: Unable to reach Flickr API. Please check your internet connection and try again.'
            ], 504);
        } catch (\Exception $e) {
            Log::error('Flickr API test failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import photos from Flickr
     */
    public function importPhotos(Request $request)
    {
        $request->validate([
            'max_photos' => 'integer|min:1|max:100',
            'import_private' => 'boolean',
            'import_metadata' => 'boolean',
            'update_existing' => 'boolean',
        ]);

        $user = Auth::user();
        
        $apiKey = config('services.flickr.api_key');
        $userId = $user->getMeta('flickr.user_id');
        
        if (!$apiKey || !$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Flickr API key not configured or user ID not set'
            ], 400);
        }

        $maxPhotos = $request->get('max_photos', 50);
        $importPrivate = $request->get('import_private', false);
        $importMetadata = $request->get('import_metadata', true);
        $updateExisting = $request->get('update_existing', true);

        try {
            // Check if user has OAuth access
            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
            
            Log::info('Flickr import starting', [
                'user_id' => $user->id,
                'flickr_user_id' => $userId,
                'has_oauth' => $hasOAuth,
                'max_photos' => $maxPhotos,
                'import_private' => $importPrivate,
                'import_metadata' => $importMetadata
            ]);
            
            if ($hasOAuth) {
                // Use OAuth for authenticated access
                Log::info('Using OAuth for Flickr API request');
                $data = $this->makeOAuthRequest('flickr.people.getPhotos', [
                    'user_id' => $userId, // Use the stored user ID instead of 'me'
                    'per_page' => $maxPhotos,
                    'extras' => 'date_taken,date_upload,description,license,owner_name,tags,geo,url_s,url_m,url_l,url_o,latitude,longitude,accuracy,geo_is_family,geo_is_friend,geo_is_contact,geo_is_public'
                ]);
            } else {
                // Fall back to API key method
                Log::info('Using API key for Flickr API request');
                $response = Http::get('https://api.flickr.com/services/rest/', [
                    'method' => 'flickr.people.getPhotos',
                    'api_key' => $apiKey,
                    'user_id' => $userId,
                    'per_page' => $maxPhotos,
                    'format' => 'json',
                    'nojsoncallback' => 1,
                    'extras' => 'date_taken,date_upload,description,license,owner_name,tags,geo,url_s,url_m,url_l,url_o,latitude,longitude,accuracy,geo_is_family,geo_is_friend,geo_is_contact,geo_is_public'
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch photos from Flickr');
                }

                $data = $response->json();
            }
            
            Log::info('Flickr API response received', [
                'stat' => $data['stat'] ?? 'unknown',
                'total_photos' => $data['photos']['total'] ?? 'unknown',
                'photos_returned' => count($data['photos']['photo'] ?? [])
            ]);
            
            // Dump the full API response for debugging
            Log::info('Full Flickr API response', [
                'response' => $data
            ]);
            
            if ($data['stat'] !== 'ok') {
                throw new \Exception('Flickr API error: ' . ($data['message'] ?? 'Unknown error'));
            }

            $photos = $data['photos']['photo'] ?? [];
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($photos as $photo) {
                try {
                    // Log detailed information for each photo
                    Log::info('Processing Flickr photo', [
                        'photo_id' => $photo['id'],
                        'title' => $photo['title'] ?? 'N/A',
                        'available_fields' => array_keys($photo),
                        'has_latitude' => isset($photo['latitude']),
                        'has_longitude' => isset($photo['longitude']),
                        'has_lat' => isset($photo['lat']),
                        'has_lon' => isset($photo['lon']),
                        'latitude' => $photo['latitude'] ?? 'N/A',
                        'longitude' => $photo['longitude'] ?? 'N/A',
                        'accuracy' => $photo['accuracy'] ?? 'N/A',
                        'geo_is_public' => $photo['geo_is_public'] ?? 'N/A',
                        'has_tags' => isset($photo['tags']),
                        'tags' => $photo['tags'] ?? 'N/A',
                        'has_description' => isset($photo['description']),
                        'description_length' => isset($photo['description']['_content']) ? strlen($photo['description']['_content']) : 0,
                        'is_public' => $photo['ispublic'] ?? 'unknown',
                        'has_owner' => isset($photo['owner']),
                        'owner' => $photo['owner'] ?? 'N/A',
                        'flickr_user_id' => $user->getMeta('flickr.user_id')
                    ]);
                    
                    // Dump the full photo data for debugging
                    Log::info('Full photo data from Flickr API', [
                        'photo_id' => $photo['id'],
                        'full_photo_data' => $photo
                    ]);
                    
                    // Skip private photos if not importing them
                    if (!$importPrivate && $photo['ispublic'] == 0) {
                        continue;
                    }
                    
                    // Always try to get location data from Flickr's EXIF API first
                    $exifLocation = $this->getLocationFromFlickrExif($photo);
                    if ($exifLocation) {
                        $photo['latitude'] = $exifLocation['latitude'];
                        $photo['longitude'] = $exifLocation['longitude'];
                        
                        Log::info('Got location from Flickr EXIF API', [
                            'photo_id' => $photo['id'],
                            'latitude' => $photo['latitude'],
                            'longitude' => $photo['longitude'],
                            'source' => 'Flickr EXIF API'
                        ]);
                    } else {
                        Log::info('No location data available from Flickr EXIF API', [
                            'photo_id' => $photo['id']
                        ]);
                    }

                    // Check if photo already exists
                    $existingPhoto = Span::where('owner_id', $user->id)
                        ->where('type_id', 'thing')
                        ->whereJsonContains('metadata->subtype', 'photo')
                        ->whereJsonContains('metadata->flickr_id', $photo['id'])
                        ->first();

                    if ($existingPhoto && $updateExisting) {
                        // Update existing photo
                        $photoSpan = $this->updatePhotoSpan($existingPhoto, $photo, $user, $importMetadata);
                        $updatedCount++;
                    } else {
                        // Create new photo span
                        $photoSpan = $this->createPhotoSpan($photo, $user, $importMetadata);
                        $importedCount++;
                    }
                    
                    // Create or update "created" connection between user and photo
                    $this->createOrUpdateCreatedConnection($photoSpan, $user);
                    
                    // Create or update subject connections if tags are available
                    if ($importMetadata && !empty($photo['tags'])) {
                        $this->createOrUpdateSubjectConnections($photoSpan, $photo['tags']);
                    }

                } catch (\Exception $e) {
                    $errors[] = "Failed to import photo {$photo['id']}: " . $e->getMessage();
                    Log::error('Failed to import Flickr photo', [
                        'photo_id' => $photo['id'],
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = "Successfully processed " . ($importedCount + $updatedCount) . " photos";
            if ($importedCount > 0) {
                $message .= " ({$importedCount} imported";
            }
            if ($updatedCount > 0) {
                $message .= ($importedCount > 0 ? ", " : " (") . "{$updatedCount} updated";
            }
            $message .= ")";
            
            Log::info('Flickr import completed', [
                'user_id' => $user->id,
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'total_processed' => $importedCount + $updatedCount,
                'errors_count' => count($errors),
                'has_oauth' => $hasOAuth
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            Log::error('Flickr import failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing photo span with new Flickr data
     */
    private function updatePhotoSpan(Span $existingSpan, array $photo, $user, bool $importMetadata): Span
    {
        // Parse the date taken
        $dateTaken = null;
        if (!empty($photo['datetaken'])) {
            try {
                $dateTaken = Carbon::parse($photo['datetaken']);
            } catch (\Exception $e) {
                // If date parsing fails, use upload date
                $dateTaken = Carbon::createFromTimestamp($photo['dateupload']);
            }
        } else {
            $dateTaken = Carbon::createFromTimestamp($photo['dateupload']);
        }

        // Build updated metadata
        $metadata = $existingSpan->metadata ?? [];
        $metadata['subtype'] = 'photo';
        $metadata['flickr_id'] = $photo['id'];
        
        // Use owner from photo data if available, otherwise use the user's Flickr user ID
        $ownerId = $photo['owner'] ?? $user->getMeta('flickr.user_id');
        $metadata['flickr_url'] = "https://www.flickr.com/photos/{$ownerId}/{$photo['id']}/";
        $metadata['license'] = $photo['license'] ?? null;
        $metadata['is_public'] = $photo['ispublic'] == 1;
        
        // Ensure creator is set (required for 'thing' spans)
        if (!isset($metadata['creator']) && $user->personalSpan) {
            $metadata['creator'] = $user->personalSpan->id;
        }

        // Update image URLs if available
        if (!empty($photo['url_s'])) {
            $metadata['thumbnail_url'] = $photo['url_s'];
        }
        if (!empty($photo['url_m'])) {
            $metadata['medium_url'] = $photo['url_m'];
        }
        if (!empty($photo['url_l'])) {
            $metadata['large_url'] = $photo['url_l'];
        }
        if (!empty($photo['url_o'])) {
            $metadata['original_url'] = $photo['url_o'];
        }

        // Update description if available
        if (!empty($photo['description']['_content'])) {
            $metadata['description'] = $photo['description']['_content'];
        }

        // Update tags if importing metadata
        if ($importMetadata && !empty($photo['tags'])) {
            $metadata['tags'] = explode(' ', $photo['tags']);
        }

        // Update location if available (check for non-zero values)
        if (isset($photo['latitude']) && isset($photo['longitude']) && $photo['latitude'] != 0 && $photo['longitude'] != 0) {
            // Fix longitude sign for western hemisphere (should be negative for US, etc.)
            $latitude = $photo['latitude'];
            $longitude = $photo['longitude'];
            
            // If longitude is positive and greater than 80, it's likely in the western hemisphere
            // and should be negative (this covers most of the US, Canada, etc.)
            if ($longitude > 80) {
                $longitude = -$longitude;
                Log::info('Corrected longitude sign for western hemisphere (update)', [
                    'photo_id' => $photo['id'],
                    'original_longitude' => $photo['longitude'],
                    'corrected_longitude' => $longitude
                ]);
            }
            
            $metadata['coordinates'] = $latitude . ',' . $longitude;
            Log::info('Updated coordinates in metadata', [
                'photo_id' => $photo['id'],
                'coordinates' => $metadata['coordinates']
            ]);
        } elseif (isset($photo['lat']) && isset($photo['lon']) && $photo['lat'] != 0 && $photo['lon'] != 0) {
            // Fix longitude sign for western hemisphere (should be negative for US, etc.)
            $latitude = $photo['lat'];
            $longitude = $photo['lon'];
            
            // If longitude is positive and greater than 80, it's likely in the western hemisphere
            // and should be negative (this covers most of the US, Canada, etc.)
            if ($longitude > 80) {
                $longitude = -$longitude;
                Log::info('Corrected longitude sign for western hemisphere (update lat/lon)', [
                    'photo_id' => $photo['id'],
                    'original_longitude' => $photo['lon'],
                    'corrected_longitude' => $longitude
                ]);
            }
            
            $metadata['coordinates'] = $latitude . ',' . $longitude;
            Log::info('Updated coordinates in metadata (lat/lon)', [
                'photo_id' => $photo['id'],
                'coordinates' => $metadata['coordinates']
            ]);
        } else {
            Log::info('No coordinates available for photo update', [
                'photo_id' => $photo['id'],
                'latitude' => $photo['latitude'] ?? 'N/A',
                'longitude' => $photo['longitude'] ?? 'N/A',
                'lat' => $photo['lat'] ?? 'N/A',
                'lon' => $photo['lon'] ?? 'N/A'
            ]);
        }

        // Update the span
        $existingSpan->update([
            'name' => $photo['title'] ?: "Flickr Photo {$photo['id']}",
            'start_year' => $dateTaken->year,
            'start_month' => $dateTaken->month,
            'start_day' => $dateTaken->day,
            'end_year' => $dateTaken->year,
            'end_month' => $dateTaken->month,
            'end_day' => $dateTaken->day,
            'access_level' => $photo['ispublic'] == 1 ? 'public' : 'private',
            'description' => $photo['description']['_content'] ?? null,
            'metadata' => $metadata,
            'updater_id' => $user->id,
        ]);

        Log::info('Updated existing Flickr photo', [
            'flickr_id' => $photo['id'],
            'span_id' => $existingSpan->id,
            'user_id' => $user->id
        ]);

        return $existingSpan;
    }

    /**
     * Create a photo span from Flickr photo data
     */
    private function createPhotoSpan(array $photo, $user, bool $importMetadata): Span
    {
        // Parse the date taken
        $dateTaken = null;
        if (!empty($photo['datetaken'])) {
            try {
                $dateTaken = Carbon::parse($photo['datetaken']);
            } catch (\Exception $e) {
                // If date parsing fails, use upload date
                $dateTaken = Carbon::createFromTimestamp($photo['dateupload']);
            }
        } else {
            $dateTaken = Carbon::createFromTimestamp($photo['dateupload']);
        }

        // Build metadata
        $metadata = [
            'subtype' => 'photo',
            'flickr_id' => $photo['id'],
        ];
        
        // Use owner from photo data if available, otherwise use the user's Flickr user ID
        $ownerId = $photo['owner'] ?? $user->getMeta('flickr.user_id');
        $metadata['flickr_url'] = "https://www.flickr.com/photos/{$ownerId}/{$photo['id']}/";
        $metadata['license'] = $photo['license'] ?? null;
        $metadata['is_public'] = $photo['ispublic'] == 1;
        
        // Set creator to the user's personal span (required for 'thing' spans)
        if ($user->personalSpan) {
            $metadata['creator'] = $user->personalSpan->id;
        }

        // Add image URLs if available
        if (!empty($photo['url_s'])) {
            $metadata['thumbnail_url'] = $photo['url_s'];
        }
        if (!empty($photo['url_m'])) {
            $metadata['medium_url'] = $photo['url_m'];
        }
        if (!empty($photo['url_l'])) {
            $metadata['large_url'] = $photo['url_l'];
        }
        if (!empty($photo['url_o'])) {
            $metadata['original_url'] = $photo['url_o'];
        }

        // Add description if available
        if (!empty($photo['description']['_content'])) {
            $metadata['description'] = $photo['description']['_content'];
        }

        // Add tags if importing metadata
        if ($importMetadata && !empty($photo['tags'])) {
            $metadata['tags'] = explode(' ', $photo['tags']);
        }

        // Add location if available (check for non-zero values)
        if (isset($photo['latitude']) && isset($photo['longitude']) && $photo['latitude'] != 0 && $photo['longitude'] != 0) {
            // Fix longitude sign for western hemisphere (should be negative for US, etc.)
            $latitude = $photo['latitude'];
            $longitude = $photo['longitude'];
            
            // If longitude is positive and greater than 80, it's likely in the western hemisphere
            // and should be negative (this covers most of the US, Canada, etc.)
            if ($longitude > 80) {
                $longitude = -$longitude;
                Log::info('Corrected longitude sign for western hemisphere', [
                    'photo_id' => $photo['id'],
                    'original_longitude' => $photo['longitude'],
                    'corrected_longitude' => $longitude
                ]);
            }
            
            $metadata['coordinates'] = $latitude . ',' . $longitude;
            Log::info('Added coordinates to metadata', [
                'photo_id' => $photo['id'],
                'coordinates' => $metadata['coordinates']
            ]);
        } elseif (isset($photo['lat']) && isset($photo['lon']) && $photo['lat'] != 0 && $photo['lon'] != 0) {
            // Fix longitude sign for western hemisphere (should be negative for US, etc.)
            $latitude = $photo['lat'];
            $longitude = $photo['lon'];
            
            // If longitude is positive and greater than 80, it's likely in the western hemisphere
            // and should be negative (this covers most of the US, Canada, etc.)
            if ($longitude > 80) {
                $longitude = -$longitude;
                Log::info('Corrected longitude sign for western hemisphere (lat/lon)', [
                    'photo_id' => $photo['id'],
                    'original_longitude' => $photo['lon'],
                    'corrected_longitude' => $longitude
                ]);
            }
            
            $metadata['coordinates'] = $latitude . ',' . $longitude;
            Log::info('Added coordinates to metadata (lat/lon)', [
                'photo_id' => $photo['id'],
                'coordinates' => $metadata['coordinates']
            ]);
        } else {
            Log::info('No coordinates available for photo', [
                'photo_id' => $photo['id'],
                'latitude' => $photo['latitude'] ?? 'N/A',
                'longitude' => $photo['longitude'] ?? 'N/A',
                'lat' => $photo['lat'] ?? 'N/A',
                'lon' => $photo['lon'] ?? 'N/A'
            ]);
        }

        // Create the span
        $span = new Span([
            'name' => $photo['title'] ?: "Flickr Photo {$photo['id']}",
            'type_id' => 'thing',
            'start_year' => $dateTaken->year,
            'start_month' => $dateTaken->month,
            'start_day' => $dateTaken->day,
            'end_year' => $dateTaken->year,
            'end_month' => $dateTaken->month,
            'end_day' => $dateTaken->day,
            'start_precision' => 'day',
            'end_precision' => 'day',
            'access_level' => $photo['ispublic'] == 1 ? 'public' : 'private',
            'state' => 'complete',
            'description' => $photo['description']['_content'] ?? null,
            'metadata' => $metadata,
            'owner_id' => $user->id,
            'updater_id' => $user->id,
        ]);

        $span->save();

        return $span;
    }

    /**
     * Create or update subject connections based on photo tags
     */
    private function createOrUpdateSubjectConnections(Span $photoSpan, string $tags): void
    {
        // Get current subject connections for this photo
        $existingSubjectConnections = Connection::where('parent_id', $photoSpan->id)
            ->where('type_id', 'features')
            ->with(['child', 'connectionSpan'])
            ->get();

        // Parse new tags
        $newTags = array_filter(array_map('trim', explode(' ', $tags)));
        
        // Find spans that match the new tags
        $newSubjectSpans = collect();
        foreach ($newTags as $tag) {
            if (empty($tag)) continue;

            $matchingSpans = Span::where('name', 'ILIKE', "%{$tag}%")
                ->orWhereJsonContains('metadata->tags', $tag)
                ->limit(5)
                ->get();

            $newSubjectSpans = $newSubjectSpans->merge($matchingSpans);
        }

        // Remove duplicate spans and exclude the photo itself (no "features" self-connection)
        $newSubjectSpans = $newSubjectSpans->unique('id')->filter(
            fn (Span $span) => $span->id !== $photoSpan->id
        );

        // Remove connections for subjects that are no longer in the tags
        foreach ($existingSubjectConnections as $existingConnection) {
            $subjectSpan = $existingConnection->child;
            $shouldKeep = $newSubjectSpans->contains('id', $subjectSpan->id);
            
            if (!$shouldKeep) {
                // Remove the connection and its span
                if ($existingConnection->connectionSpan) {
                    $existingConnection->connectionSpan->delete();
                }
                $existingConnection->delete();
                
                Log::info('Removed subject connection due to tag change', [
                    'photo_span_id' => $photoSpan->id,
                    'subject_span_id' => $subjectSpan->id,
                    'subject_name' => $subjectSpan->name
                ]);
            }
        }

        // Create new connections for subjects that aren't already connected
        foreach ($newSubjectSpans as $subjectSpan) {
            $existingConnection = $existingSubjectConnections->first(function($conn) use ($subjectSpan) {
                return $conn->child_id === $subjectSpan->id;
            });

            if (!$existingConnection) {
                $this->createSubjectConnection($photoSpan, $subjectSpan);
            }
        }

        Log::info('Updated subject connections for photo', [
            'photo_span_id' => $photoSpan->id,
            'photo_name' => $photoSpan->name,
            'new_tags' => $newTags,
            'new_subject_count' => $newSubjectSpans->count(),
            'removed_connections' => $existingSubjectConnections->count() - $newSubjectSpans->count()
        ]);
    }

    /**
     * Create subject connections based on photo tags
     */
    private function createSubjectConnections(Span $photoSpan, string $tags): void
    {
        $tagArray = explode(' ', $tags);
        
        foreach ($tagArray as $tag) {
            $tag = trim($tag);
            if (empty($tag)) continue;

            // Try to find existing spans that match this tag
            $matchingSpans = Span::where('name', 'ILIKE', "%{$tag}%")
                ->orWhereJsonContains('metadata->tags', $tag)
                ->limit(5)
                ->get();

            foreach ($matchingSpans as $matchingSpan) {
                // Skip self (no "features" connection from photo to itself)
                if ($matchingSpan->id === $photoSpan->id) {
                    continue;
                }
                $this->createSubjectConnection($photoSpan, $matchingSpan);
            }
        }
    }

    /**
     * Create or update a "created" connection between user and photo
     */
    private function createOrUpdateCreatedConnection(Span $photoSpan, $user): void
    {
        // Get the user's personal span
        $personalSpan = $user->personalSpan;
        if (!$personalSpan) {
            Log::warning('User has no personal span, skipping created connection', [
                'user_id' => $user->id,
                'photo_span_id' => $photoSpan->id
            ]);
            return;
        }

        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $photoSpan->id)
            ->where('type_id', 'created')
            ->first();

        if ($existingConnection) {
            // Update existing connection span with new photo dates
            $connectionSpan = $existingConnection->connectionSpan;
            if ($connectionSpan) {
                // Update metadata to include timeless flag and ensure no end dates
                $metadata = $connectionSpan->metadata ?? [];
                $metadata['timeless'] = true;
                $metadata['connection_type'] = 'created';
                $metadata['source'] = 'flickr_import';
                
                $connectionSpan->update([
                    'name' => "{$personalSpan->name} created {$photoSpan->name}",
                    'start_year' => $photoSpan->start_year,
                    'start_month' => $photoSpan->start_month,
                    'start_day' => $photoSpan->start_day,
                    'start_precision' => $photoSpan->start_precision,
                    'end_year' => null,
                    'end_month' => null,
                    'end_day' => null,
                    'end_precision' => null,
                    'access_level' => $photoSpan->access_level,
                    'updater_id' => $photoSpan->updater_id,
                    'metadata' => $metadata,
                ]);

                Log::info('Updated existing created connection', [
                    'user_id' => $user->id,
                    'personal_span_id' => $personalSpan->id,
                    'photo_span_id' => $photoSpan->id,
                    'connection_id' => $existingConnection->id
                ]);
            }
            return;
        }

        // Create new connection span (timeless - created connections don't end)
        $connectionSpan = new Span([
            'name' => "{$personalSpan->name} created {$photoSpan->name}",
            'type_id' => 'connection',
            'start_year' => $photoSpan->start_year,
            'start_month' => $photoSpan->start_month,
            'start_day' => $photoSpan->start_day,
            'start_precision' => $photoSpan->start_precision,
            'access_level' => $photoSpan->access_level,
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'created',
                'source' => 'flickr_import',
                'timeless' => true
            ],
            'owner_id' => $photoSpan->owner_id,
            'updater_id' => $photoSpan->updater_id,
        ]);

        $connectionSpan->save();

        // Create the connection
        $connection = new Connection([
            'parent_id' => $personalSpan->id,
            'child_id' => $photoSpan->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $connection->save();

        Log::info('Created connection between user and photo', [
            'user_id' => $user->id,
            'personal_span_id' => $personalSpan->id,
            'photo_span_id' => $photoSpan->id,
            'connection_id' => $connection->id
        ]);
    }

    /**
     * Create a "created" connection between user and photo
     */
    private function createCreatedConnection(Span $photoSpan, $user): void
    {
        // Get the user's personal span
        $personalSpan = $user->personalSpan;
        if (!$personalSpan) {
            Log::warning('User has no personal span, skipping created connection', [
                'user_id' => $user->id,
                'photo_span_id' => $photoSpan->id
            ]);
            return;
        }

        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $photoSpan->id)
            ->where('type_id', 'created')
            ->first();

        if ($existingConnection) {
            return; // Connection already exists
        }

        // Create connection span (timeless - created connections don't end)
        $connectionSpan = new Span([
            'name' => "{$personalSpan->name} created {$photoSpan->name}",
            'type_id' => 'connection',
            'start_year' => $photoSpan->start_year,
            'start_month' => $photoSpan->start_month,
            'start_day' => $photoSpan->start_day,
            'start_precision' => $photoSpan->start_precision,
            'access_level' => $photoSpan->access_level,
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'created',
                'source' => 'flickr_import',
                'timeless' => true
            ],
            'owner_id' => $photoSpan->owner_id,
            'updater_id' => $photoSpan->updater_id,
        ]);

        $connectionSpan->save();

        // Create the connection
        $connection = new Connection([
            'parent_id' => $personalSpan->id,
            'child_id' => $photoSpan->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $connection->save();

        Log::info('Created connection between user and photo', [
            'user_id' => $user->id,
            'personal_span_id' => $personalSpan->id,
            'photo_span_id' => $photoSpan->id,
            'connection_id' => $connection->id
        ]);
    }

    /**
     * Create a features connection between photo and subject
     */
    private function createSubjectConnection(Span $photoSpan, Span $subjectSpan): void
    {
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $photoSpan->id)
            ->where('child_id', $subjectSpan->id)
            ->where('type_id', 'features')
            ->first();

        if ($existingConnection) {
            return; // Connection already exists
        }

        // Create connection span (timeless)
        $connectionSpan = new Span([
            'name' => "{$photoSpan->name} features {$subjectSpan->name}",
            'type_id' => 'connection',
            'access_level' => 'public',
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'features',
                'timeless' => true
            ],
            'owner_id' => $photoSpan->owner_id,
            'updater_id' => $photoSpan->updater_id,
        ]);

        $connectionSpan->save();

        // Create the connection
        $connection = new Connection([
            'parent_id' => $photoSpan->id,
            'child_id' => $subjectSpan->id,
            'type_id' => 'features',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $connection->save();
    }

    /**
     * Get imported photos for the current user
     */
    public function getImportedPhotos()
    {
        $user = Auth::user();
        
        $photos = Span::where('owner_id', $user->id)
            ->where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'photos' => $photos
        ]);
    }

    /**
     * Get Flickr OAuth server instance
     */
    private function getFlickrServer()
    {
        return new FlickrServer(
            config('services.flickr.client_id'),
            config('services.flickr.client_secret'),
            config('services.flickr.callback_url')
        );
    }

    /**
     * Start OAuth authorization flow
     */
    public function startOAuth()
    {
        try {
            $server = $this->getFlickrServer();
            
            // Get temporary credentials
            $temporaryCredentials = $server->getTemporaryCredentials();
            
            // Store in session for callback
            session(['oauth_temporary_credentials' => $temporaryCredentials]);
            
            // Redirect to Flickr authorization
            return redirect($server->getAuthorizationUrl($temporaryCredentials->getIdentifier()));
            
        } catch (\Exception $e) {
            Log::error('Flickr OAuth authorization failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return redirect()->route('settings.import.flickr.index')
                ->with('error', 'Failed to start OAuth authorization: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth callback from Flickr
     */
    public function callback(Request $request)
    {
        try {
            $server = $this->getFlickrServer();
            $temporaryCredentials = session('oauth_temporary_credentials');
            
            if (!$temporaryCredentials) {
                throw new \Exception('No temporary credentials found in session');
            }
            
            // Get token credentials
            $tokenCredentials = $server->getTokenCredentials(
                $temporaryCredentials,
                $request->get('oauth_token'),
                $request->get('oauth_verifier')
            );
            
            // Store in user metadata
            $user = Auth::user();
            $user->setMeta('flickr.oauth_token', $tokenCredentials->getIdentifier());
            $user->setMeta('flickr.oauth_secret', $tokenCredentials->getSecret());
            $user->save();
            
            // Clear session
            session()->forget('oauth_temporary_credentials');
            
            return redirect()->route('settings.import.flickr.index')
                ->with('success', 'Flickr OAuth connected successfully!');
                
        } catch (\Exception $e) {
            Log::error('Flickr OAuth callback failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return redirect()->route('settings.import.flickr.index')
                ->with('error', 'OAuth callback failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect OAuth
     */
    public function disconnect()
    {
        $user = Auth::user();
        $user->setMeta('flickr.oauth_token', null);
        $user->setMeta('flickr.oauth_secret', null);
        $user->save();
        
        return redirect()->route('settings.import.flickr.index')
            ->with('success', 'Flickr OAuth disconnected successfully!');
    }

    /**
     * Make OAuth request to Flickr API
     */
    private function makeOAuthRequest($method, $params = [])
    {
        $user = Auth::user();
        $oauthToken = $user->getMeta('flickr.oauth_token');
        $oauthSecret = $user->getMeta('flickr.oauth_secret');
        
        if (!$oauthToken || !$oauthSecret) {
            throw new \Exception('Flickr OAuth not connected');
        }
        
        Log::info('Making OAuth request to Flickr', [
            'method' => $method,
            'params' => $params,
            'user_id' => $user->id
        ]);
        
        $tokenCredentials = new TokenCredentials($oauthToken, $oauthSecret);
        $server = $this->getFlickrServer();
        
        $url = 'https://api.flickr.com/services/rest/';
        $queryParams = array_merge([
            'method' => $method,
            'format' => 'json',
            'nojsoncallback' => 1,
        ], $params);
        
        $headers = $server->getHeaders($tokenCredentials, 'GET', $url, $queryParams);
        
        $client = $server->createHttpClient();
        $response = $client->get($url, [
            'query' => $queryParams,
            'headers' => $headers,
        ]);
        
        return json_decode($response->getBody()->getContents(), true);
    }
    
    /**
     * Extract location data from Flickr's EXIF API
     */
    private function getLocationFromFlickrExif($photo)
    {
        try {
            Log::info('Attempting to get EXIF data from Flickr API', [
                'photo_id' => $photo['id']
            ]);
            
            // Use OAuth if available, otherwise fall back to API key
            $user = auth()->user();
            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
            
            if ($hasOAuth) {
                $data = $this->makeOAuthRequest('flickr.photos.getExif', [
                    'photo_id' => $photo['id'],
                    'secret' => $photo['secret'] ?? null
                ]);
            } else {
                // Fall back to API key method
                $apiKey = config('services.flickr.api_key');
                $response = Http::get('https://api.flickr.com/services/rest/', [
                    'method' => 'flickr.photos.getExif',
                    'api_key' => $apiKey,
                    'photo_id' => $photo['id'],
                    'secret' => $photo['secret'] ?? null,
                    'format' => 'json',
                    'nojsoncallback' => 1
                ]);
                
                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch EXIF data from Flickr');
                }
                
                $data = $response->json();
            }
            
            Log::info('Flickr EXIF API response', [
                'photo_id' => $photo['id'],
                'stat' => $data['stat'] ?? 'unknown',
                'full_response' => $data
            ]);
            
            if ($data['stat'] !== 'ok') {
                Log::warning('Flickr EXIF API returned error', [
                    'photo_id' => $photo['id'],
                    'error' => $data['message'] ?? 'Unknown error'
                ]);
                return null;
            }
            
            if (!isset($data['photo']['exif'])) {
                Log::info('No EXIF data found in Flickr response', [
                    'photo_id' => $photo['id']
                ]);
                return null;
            }
            
            $exifData = $data['photo']['exif'];
            Log::info('EXIF data found in Flickr response', [
                'photo_id' => $photo['id'],
                'exif_count' => count($exifData),
                'exif_tags' => array_column($exifData, 'tag')
            ]);
            
            // Look for GPS coordinates in EXIF data
            $latitude = null;
            $longitude = null;
            $latitudeRef = null;
            $longitudeRef = null;
            
            foreach ($exifData as $exif) {
                $tag = $exif['tag'] ?? '';
                $raw = $exif['raw'] ?? '';
                
                // Handle Flickr's _content wrapper
                if (is_array($raw) && isset($raw['_content'])) {
                    $raw = $raw['_content'];
                }
                
                if ($tag === 'GPSLatitude') {
                    $latitude = $this->parseGpsCoordinate($raw);
                } elseif ($tag === 'GPSLongitude') {
                    $longitude = $this->parseGpsCoordinate($raw);
                } elseif ($tag === 'GPSLatitudeRef') {
                    $latitudeRef = $raw;
                } elseif ($tag === 'GPSLongitudeRef') {
                    $longitudeRef = $raw;
                }
            }
            
            // Apply reference corrections
            if ($latitude !== null && $latitudeRef === 'South') {
                $latitude = -$latitude;
            }
            if ($longitude !== null && $longitudeRef === 'West') {
                $longitude = -$longitude;
            }
            
            if ($latitude !== null && $longitude !== null) {
                Log::info('GPS coordinates found in Flickr EXIF', [
                    'photo_id' => $photo['id'],
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'latitude_ref' => $latitudeRef,
                    'longitude_ref' => $longitudeRef,
                    'latitude_final' => $latitude,
                    'longitude_final' => $longitude
                ]);
                
                return [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ];
            }
            
            Log::info('No GPS coordinates found in Flickr EXIF', [
                'photo_id' => $photo['id']
            ]);
            
            // Try flickr.photos.getInfo as a fallback - it might have GPS data
            Log::info('Trying flickr.photos.getInfo as fallback for GPS data', [
                'photo_id' => $photo['id']
            ]);
            
            try {
                if ($hasOAuth) {
                    $infoData = $this->makeOAuthRequest('flickr.photos.getInfo', [
                        'photo_id' => $photo['id'],
                        'secret' => $photo['secret'] ?? null
                    ]);
                } else {
                    $apiKey = config('services.flickr.api_key');
                    $infoResponse = Http::get('https://api.flickr.com/services/rest/', [
                        'method' => 'flickr.photos.getInfo',
                        'api_key' => $apiKey,
                        'photo_id' => $photo['id'],
                        'secret' => $photo['secret'] ?? null,
                        'format' => 'json',
                        'nojsoncallback' => 1
                    ]);
                    
                    if (!$infoResponse->successful()) {
                        throw new \Exception('Failed to fetch photo info from Flickr');
                    }
                    
                    $infoData = $infoResponse->json();
                }
                
                Log::info('Photo info response', [
                    'photo_id' => $photo['id'],
                    'stat' => $infoData['stat'] ?? 'unknown',
                    'has_location' => isset($infoData['photo']['location']),
                    'location_data' => $infoData['photo']['location'] ?? null
                ]);
                
                if (isset($infoData['photo']['location'])) {
                    $location = $infoData['photo']['location'];
                    $latitude = (float) ($location['latitude'] ?? 0);
                    $longitude = (float) ($location['longitude'] ?? 0);
                    
                    if ($latitude != 0 && $longitude != 0) {
                        Log::info('Got GPS coordinates from photo info', [
                            'photo_id' => $photo['id'],
                            'latitude' => $latitude,
                            'longitude' => $longitude,
                            'source' => 'flickr.photos.getInfo'
                        ]);
                        
                        return [
                            'latitude' => $latitude,
                            'longitude' => $longitude
                        ];
                    }
                }
                
                Log::info('No GPS coordinates found in photo info either', [
                    'photo_id' => $photo['id']
                ]);
                
            } catch (\Exception $e) {
                Log::warning('Failed to get photo info', [
                    'photo_id' => $photo['id'],
                    'error' => $e->getMessage()
                ]);
            }
            
            return null;
            
        } catch (\Exception $e) {
            Log::warning('Error getting EXIF data from Flickr', [
                'photo_id' => $photo['id'],
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Parse GPS coordinate from Flickr EXIF format
     */
    private function parseGpsCoordinate($raw)
    {
        // Handle array format (degrees/minutes/seconds as array)
        if (is_array($raw)) {
            if (count($raw) >= 3) {
                $degrees = $this->convertGpsFraction($raw[0]);
                $minutes = $this->convertGpsFraction($raw[1]);
                $seconds = $this->convertGpsFraction($raw[2]);
                
                return $degrees + ($minutes / 60) + ($seconds / 3600);
            }
            Log::info('Invalid GPS coordinate array format', [
                'raw' => $raw,
                'count' => count($raw)
            ]);
            return null;
        }
        
        // Handle string format
        if (is_string($raw)) {
            // Flickr EXIF format is typically "51.5074" (decimal degrees)
            if (is_numeric($raw)) {
                return (float) $raw;
            }
            
            // Handle degrees/minutes/seconds format like "51 deg 33' 9.27\"" or "51° 30' 26.64\""
            if (preg_match('/(\d+)\s*(?:deg|°)\s*(\d+)\'\s*([\d.]+)"/', $raw, $matches)) {
                $degrees = (float) $matches[1];
                $minutes = (float) $matches[2];
                $seconds = (float) $matches[3];
                
                return $degrees + ($minutes / 60) + ($seconds / 3600);
            }
        }
        
        // Handle numeric format
        if (is_numeric($raw)) {
            return (float) $raw;
        }
        
        // Handle other formats
        Log::info('Unknown GPS coordinate format', [
            'raw' => $raw,
            'type' => gettype($raw)
        ]);
        
        return null;
    }
    
    /**
     * Convert GPS fraction to decimal
     */
    private function convertGpsFraction($fraction)
    {
        if (is_string($fraction)) {
            $parts = explode('/', $fraction);
            if (count($parts) === 2) {
                return $parts[0] / $parts[1];
            }
        }
        return (float) $fraction;
    }

    /**
     * Get user's photosets (albums) from Flickr
     */
    public function getPhotosets()
    {
        $user = Auth::user();
        
        $apiKey = config('services.flickr.api_key');
        $userId = $user->getMeta('flickr.user_id');
        
        if (!$apiKey || !$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Flickr API key not configured or user ID not set'
            ], 400);
        }

        try {
            // Check if user has OAuth access
            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
            
            if ($hasOAuth) {
                // Use OAuth for authenticated access
                $data = $this->makeOAuthRequest('flickr.photosets.getList', [
                    'user_id' => $userId
                ]);
            } else {
                // Fall back to API key method
                $response = Http::get('https://api.flickr.com/services/rest/', [
                    'method' => 'flickr.photosets.getList',
                    'api_key' => $apiKey,
                    'user_id' => $userId,
                    'format' => 'json',
                    'nojsoncallback' => 1
                ]);

                if (!$response->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to fetch photosets from Flickr'
                    ], 500);
                }

                $data = $response->json();
            }
            
            if ($data['stat'] !== 'ok') {
                return response()->json([
                    'success' => false,
                    'message' => 'Flickr API error: ' . ($data['message'] ?? 'Unknown error')
                ], 400);
            }

            $photosets = $data['photosets']['photoset'] ?? [];
            
            // Format the response
            $formattedPhotosets = array_map(function($photoset) {
                return [
                    'id' => $photoset['id'],
                    'title' => $photoset['title']['_content'],
                    'description' => $photoset['description']['_content'] ?? '',
                    'photo_count' => $photoset['photos'],
                    'primary_photo_id' => $photoset['primary'],
                    'created' => $photoset['date_create'],
                    'updated' => $photoset['date_update']
                ];
            }, $photosets);

            return response()->json([
                'success' => true,
                'photosets' => $formattedPhotosets
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch Flickr photosets', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch photosets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import photos from a specific Flickr photoset
     */
    public function importPhotoset(Request $request)
    {
        $request->validate([
            'photoset_id' => 'required|string',
            'max_photos' => 'integer|min:1|max:500',
            'import_private' => 'boolean',
            'import_metadata' => 'boolean',
            'update_existing' => 'boolean',
        ]);

        $user = Auth::user();
        
        $apiKey = config('services.flickr.api_key');
        $userId = $user->getMeta('flickr.user_id');
        
        if (!$apiKey || !$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Flickr API key not configured or user ID not set'
            ], 400);
        }

        $photosetId = $request->get('photoset_id');
        $maxPhotos = $request->get('max_photos', 100);
        $importPrivate = $request->get('import_private', false);
        $importMetadata = $request->get('import_metadata', true);
        $updateExisting = $request->get('update_existing', true);

        try {
            // Check if user has OAuth access
            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
            
            Log::info('Flickr photoset import starting', [
                'user_id' => $user->id,
                'flickr_user_id' => $userId,
                'photoset_id' => $photosetId,
                'has_oauth' => $hasOAuth,
                'max_photos' => $maxPhotos,
                'import_private' => $importPrivate,
                'import_metadata' => $importMetadata
            ]);
            
            if ($hasOAuth) {
                // Use OAuth for authenticated access
                Log::info('Using OAuth for Flickr photoset API request');
                $data = $this->makeOAuthRequest('flickr.photosets.getPhotos', [
                    'photoset_id' => $photosetId,
                    'user_id' => $userId,
                    'per_page' => $maxPhotos,
                    'extras' => 'date_taken,date_upload,description,license,owner_name,tags,geo,url_s,url_m,url_l,url_o,latitude,longitude,accuracy,geo_is_family,geo_is_friend,geo_is_contact,geo_is_public'
                ]);
            } else {
                // Fall back to API key method
                Log::info('Using API key for Flickr photoset API request');
                $response = Http::get('https://api.flickr.com/services/rest/', [
                    'method' => 'flickr.photosets.getPhotos',
                    'api_key' => $apiKey,
                    'photoset_id' => $photosetId,
                    'user_id' => $userId,
                    'per_page' => $maxPhotos,
                    'format' => 'json',
                    'nojsoncallback' => 1,
                    'extras' => 'date_taken,date_upload,description,license,owner_name,tags,geo,url_s,url_m,url_l,url_o,latitude,longitude,accuracy,geo_is_family,geo_is_friend,geo_is_contact,geo_is_public'
                ]);

                if (!$response->successful()) {
                    throw new \Exception('Failed to fetch photos from Flickr photoset');
                }

                $data = $response->json();
            }
            
            Log::info('Flickr photoset API response received', [
                'stat' => $data['stat'] ?? 'unknown',
                'total_photos' => $data['photoset']['total'] ?? 'unknown',
                'photos_returned' => count($data['photoset']['photo'] ?? [])
            ]);
            
            if ($data['stat'] !== 'ok') {
                throw new \Exception('Flickr API error: ' . ($data['message'] ?? 'Unknown error'));
            }

            $photos = $data['photoset']['photo'] ?? [];
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($photos as $photo) {
                try {
                    // Log photo details for debugging
                    Log::info('Processing Flickr photo from photoset', [
                        'photo_id' => $photo['id'],
                        'title' => $photo['title'] ?? 'N/A',
                        'available_fields' => array_keys($photo),
                        'has_owner' => isset($photo['owner']),
                        'owner' => $photo['owner'] ?? 'N/A',
                        'flickr_user_id' => $user->getMeta('flickr.user_id'),
                        'is_public' => $photo['ispublic'] ?? 'unknown'
                    ]);
                    
                    // Skip private photos if not importing them
                    if (!$importPrivate && $photo['ispublic'] == 0) {
                        continue;
                    }
                    
                    // Always try to get location data from Flickr's EXIF API first
                    $exifLocation = $this->getLocationFromFlickrExif($photo);
                    if ($exifLocation) {
                        $photo['latitude'] = $exifLocation['latitude'];
                        $photo['longitude'] = $exifLocation['longitude'];
                        Log::info('Got location from Flickr EXIF API', [
                            'photo_id' => $photo['id'],
                            'latitude' => $photo['latitude'],
                            'longitude' => $photo['longitude'],
                            'source' => 'Flickr EXIF API'
                        ]);
                    } else {
                        Log::info('No location data available from Flickr EXIF API', [
                            'photo_id' => $photo['id']
                        ]);
                    }
                    
                    // Check if photo already exists
                    $existingSpan = Span::where('type_id', 'thing')
                        ->whereJsonContains('metadata->subtype', 'photo')
                        ->whereJsonContains('metadata->flickr_id', $photo['id'])
                        ->first();

                    if ($existingSpan) {
                        if ($updateExisting) {
                            $this->updatePhotoSpan($existingSpan, $photo, $user, $importMetadata);
                            $updatedCount++;
                        }
                    } else {
                        $newSpan = $this->createPhotoSpan($photo, $user, $importMetadata);
                        $importedCount++;
                        
                        // Create connection to user's personal span
                        $this->createCreatedConnection($newSpan, $user);
                    }
                    
                    // Create connection for existing spans
                    if ($existingSpan && $updateExisting) {
                        $this->createCreatedConnection($existingSpan, $user);
                    }
                    
                } catch (\Exception $e) {
                    Log::error('Failed to import photo from photoset', [
                        'photo_id' => $photo['id'],
                        'error' => $e->getMessage()
                    ]);
                    $errors[] = "Photo {$photo['id']}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Import completed successfully",
                'imported_count' => $importedCount,
                'updated_count' => $updatedCount,
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            Log::error('Flickr photoset import failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'photoset_id' => $photosetId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
