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
            // Test the API by getting user info
            $response = Http::get('https://api.flickr.com/services/rest/', [
                'method' => 'flickr.people.getInfo',
                'api_key' => $apiKey,
                'user_id' => $userId,
                'format' => 'json',
                'nojsoncallback' => 1
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['stat'] === 'ok') {
                    return response()->json([
                        'success' => true,
                        'message' => 'Connection successful',
                        'user_info' => $data['person']
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Flickr API error: ' . ($data['message'] ?? 'Unknown error')
                    ], 400);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect to Flickr API'
                ], 500);
            }
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
            // Get photos from Flickr
            $response = Http::get('https://api.flickr.com/services/rest/', [
                'method' => 'flickr.people.getPhotos',
                'api_key' => $apiKey,
                'user_id' => $userId,
                'per_page' => $maxPhotos,
                'format' => 'json',
                'nojsoncallback' => 1,
                'extras' => 'date_taken,date_upload,description,license,owner_name,tags,geo,url_s,url_m,url_l,url_o'
            ]);

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch photos from Flickr');
            }

            $data = $response->json();
            
            if ($data['stat'] !== 'ok') {
                throw new \Exception('Flickr API error: ' . ($data['message'] ?? 'Unknown error'));
            }

            $photos = $data['photos']['photo'] ?? [];
            $importedCount = 0;
            $updatedCount = 0;
            $errors = [];

            foreach ($photos as $photo) {
                try {
                    // Skip private photos if not importing them
                    if (!$importPrivate && $photo['ispublic'] == 0) {
                        continue;
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
        $metadata['flickr_url'] = "https://www.flickr.com/photos/{$photo['owner']}/{$photo['id']}/";
        $metadata['license'] = $photo['license'] ?? null;
        $metadata['is_public'] = $photo['ispublic'] == 1;

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

        // Update location if available
        if (!empty($photo['latitude']) && !empty($photo['longitude'])) {
            $metadata['coordinates'] = $photo['latitude'] . ',' . $photo['longitude'];
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
            'flickr_url' => "https://www.flickr.com/photos/{$photo['owner']}/{$photo['id']}/",
            'license' => $photo['license'] ?? null,
            'is_public' => $photo['ispublic'] == 1,
        ];

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

        // Add location if available
        if (!empty($photo['latitude']) && !empty($photo['longitude'])) {
            $metadata['coordinates'] = $photo['latitude'] . ',' . $photo['longitude'];
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
            ->where('type_id', 'subject_of')
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

        // Remove duplicate spans
        $newSubjectSpans = $newSubjectSpans->unique('id');

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
                // Create subject_of connection
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
     * Create a subject_of connection between photo and subject
     */
    private function createSubjectConnection(Span $photoSpan, Span $subjectSpan): void
    {
        // Check if connection already exists
        $existingConnection = Connection::where('parent_id', $photoSpan->id)
            ->where('child_id', $subjectSpan->id)
            ->where('type_id', 'subject_of')
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
                'connection_type' => 'subject_of',
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
            'type_id' => 'subject_of',
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
}
