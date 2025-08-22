<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Services\ImageStorageService;
use App\Services\ExifExtractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PhotoUploadController extends Controller
{
    protected $imageStorageService;
    protected $exifExtractionService;

    public function __construct(ImageStorageService $imageStorageService, ExifExtractionService $exifExtractionService)
    {
        $this->imageStorageService = $imageStorageService;
        $this->exifExtractionService = $exifExtractionService;
    }

    /**
     * Show the photo upload form
     */
    public function create()
    {
        return view('photos.upload');
    }

    /**
     * Handle photo upload
     */
    public function store(Request $request)
    {
        \Log::info('Photo upload request received', [
            'files' => $request->hasFile('photos') ? count($request->file('photos')) : 0,
            'all_data' => $request->all()
        ]);

        try {
            $request->validate([
                'photos.*' => 'required|file|mimes:jpeg,jpg,png,gif,heic,heif|max:5120', // 5MB max, includes HEIC
                'title' => 'nullable|string|max:255',
                'description' => 'nullable|string|max:1000',
                'date_taken' => 'nullable|date',
                'access_level' => 'required|in:public,private,shared',
            ]);
            
            \Log::info('Photo upload validation passed');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('Photo upload validation failed', [
                'errors' => $e->errors()
            ]);
            throw $e;
        }

        $user = Auth::user();
        $uploadedPhotos = [];

        foreach ($request->file('photos') as $file) {
            try {
                \Log::info('Processing photo upload', [
                    'filename' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'real_path' => $file->getRealPath(),
                    'file_info' => [
                        'name' => $file->getClientOriginalName(),
                        'type' => $file->getMimeType(),
                        'tmp_name' => $file->getPathname(),
                        'error' => $file->getError(),
                        'size' => $file->getSize()
                    ]
                ]);

                // Extract EXIF data
                $exifData = $this->exifExtractionService->extractExif($file);

                // Store the image
                $imageData = $this->imageStorageService->storeImage($file);

                // Determine the date to use
                $dateTaken = null;
                if ($request->filled('date_taken')) {
                    $dateTaken = Carbon::parse($request->date_taken);
                } elseif (isset($exifData['date_taken'])) {
                    $dateTaken = Carbon::parse($exifData['date_taken']);
                } else {
                    $dateTaken = Carbon::now();
                }

                // Build metadata
                $metadata = [
                    'subtype' => 'photo',
                    'upload_source' => 'direct_upload',
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size' => $imageData['file_size'],
                    'mime_type' => $imageData['mime_type'],
                    'filename' => $imageData['filename'],
                ];
                
                // Set creator to the user's personal span (required for 'thing' spans)
                if ($user->personalSpan) {
                    $metadata['creator'] = $user->personalSpan->id;
                }

                // Add image URLs
                $metadata = array_merge($metadata, [
                    'original_url' => $imageData['original_url'],
                    'large_url' => $imageData['large_url'],
                    'medium_url' => $imageData['medium_url'],
                    'thumbnail_url' => $imageData['thumbnail_url'],
                ]);

                // Add EXIF data
                if (!empty($exifData)) {
                    $metadata = array_merge($metadata, $exifData);
                }

                // Add user-provided data
                if ($request->filled('description')) {
                    $metadata['description'] = $request->description;
                }

                // Create the photo span
                $span = new Span([
                    'name' => $request->title ?: $file->getClientOriginalName(),
                    'type_id' => 'thing',
                    'start_year' => $dateTaken->year,
                    'start_month' => $dateTaken->month,
                    'start_day' => $dateTaken->day,
                    'end_year' => $dateTaken->year,
                    'end_month' => $dateTaken->month,
                    'end_day' => $dateTaken->day,
                    'start_precision' => 'day',
                    'end_precision' => 'day',
                    'access_level' => $request->access_level,
                    'state' => 'complete',
                    'description' => $request->description,
                    'metadata' => $metadata,
                    'owner_id' => $user->id,
                    'updater_id' => $user->id,
                ]);

                $span->save();

                // Now that we have the span ID, update the URLs to use proxy routes
                $metadata = $span->metadata;
                $metadata['original_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'original']);
                $metadata['large_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'large']);
                $metadata['medium_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'medium']);
                $metadata['thumbnail_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'thumbnail']);
                $span->metadata = $metadata;
                $span->save();

                // Create "created" connection between user and photo
                $this->createCreatedConnection($span, $user, $dateTaken);

                $uploadedPhotos[] = $span;

            } catch (\Exception $e) {
                \Log::error('Photo upload failed', [
                    'filename' => $file->getClientOriginalName(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to upload ' . $file->getClientOriginalName() . ': ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Successfully uploaded ' . count($uploadedPhotos) . ' photo(s)',
            'photos' => collect($uploadedPhotos)->map(function ($span) {
                return [
                    'id' => $span->id,
                    'name' => $span->name,
                    'url' => route('spans.show', $span),
                    'thumbnail_url' => $span->metadata['thumbnail_url'] ?? null,
                ];
            })
        ]);
    }

    /**
     * Create a "created" connection between user and photo
     */
    protected function createCreatedConnection(Span $photoSpan, $user, $dateTaken): void
    {
        // Get the user's personal span
        $personalSpan = $user->personalSpan;
        if (!$personalSpan) {
            \Log::warning('User has no personal span, skipping created connection', [
                'user_id' => $user->id,
                'photo_span_id' => $photoSpan->id
            ]);
            return;
        }

        // Check if connection already exists
        $existingConnection = \App\Models\Connection::where('parent_id', $personalSpan->id)
            ->where('child_id', $photoSpan->id)
            ->where('type_id', 'created')
            ->first();

        if ($existingConnection) {
            return; // Connection already exists
        }

        // Create connection span (timeless - created connections don't end)
        $connectionSpan = new \App\Models\Span([
            'name' => "{$personalSpan->name} created {$photoSpan->name}",
            'type_id' => 'connection',
            'start_year' => $dateTaken->year,
            'start_month' => $dateTaken->month,
            'start_day' => $dateTaken->day,
            'start_precision' => 'day',
            'access_level' => $photoSpan->access_level,
            'state' => 'complete',
            'metadata' => [
                'connection_type' => 'created',
                'source' => 'direct_upload',
                'timeless' => true
            ],
            'owner_id' => $photoSpan->owner_id,
            'updater_id' => $photoSpan->updater_id,
        ]);

        $connectionSpan->save();

        // Create the connection
        $connection = new \App\Models\Connection([
            'parent_id' => $personalSpan->id,
            'child_id' => $photoSpan->id,
            'type_id' => 'created',
            'connection_span_id' => $connectionSpan->id,
        ]);

        $connection->save();

        \Log::info('Created connection between user and photo', [
            'user_id' => $user->id,
            'personal_span_id' => $personalSpan->id,
            'photo_span_id' => $photoSpan->id,
            'connection_id' => $connection->id
        ]);
    }
}
