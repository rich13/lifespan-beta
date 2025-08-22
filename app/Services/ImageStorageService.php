<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;

class ImageStorageService
{
    protected $disk;
    protected $basePath;
    protected $imageManager;

    public function __construct()
    {
        $this->disk = 'r2'; // Use R2 for image storage
        $this->basePath = 'photos';
        $this->imageManager = new ImageManager(new GdDriver());
    }

    /**
     * Store an uploaded image and generate thumbnails
     */
    public function storeImage(UploadedFile $file, string $filename = null): array
    {
        // Verify R2 configuration when actually storing images
        if (!config('filesystems.disks.r2.key') || !config('filesystems.disks.r2.secret')) {
            throw new \Exception('R2 configuration is missing. Please set R2_ACCESS_KEY_ID and R2_SECRET_ACCESS_KEY in your .env file.');
        }
        
        try {
            $filename = $filename ?: $this->generateFilename($file);
            $originalPath = $this->basePath . '/original/' . $filename;
            
            \Log::info('Storing image', [
                'filename' => $filename,
                'original_path' => $originalPath,
                'disk' => $this->disk,
                'original_mime_type' => $file->getMimeType()
            ]);
            
            // Store original file (preserves HEIC format and metadata)
            $originalUrl = Storage::disk($this->disk)->putFileAs(
                dirname($originalPath),
                $file,
                basename($originalPath)
            );

            \Log::info('Original file stored', ['url' => $originalUrl]);

            // Generate and store different sizes (these will be JPEG for compatibility)
            $sizes = $this->generateImageSizes($file, $filename);
            
            return [
                'original_url' => $this->getProxyUrl($originalPath),
                'large_url' => $sizes['large_url'],
                'medium_url' => $sizes['medium_url'],
                'thumbnail_url' => $sizes['thumbnail_url'],
                'filename' => $filename,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'original_mime_type' => $file->getMimeType(), // Preserve original format info
            ];
        } catch (\Exception $e) {
            \Log::error('Image storage failed', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Generate different image sizes
     */
    protected function generateImageSizes(UploadedFile $file, string $filename): array
    {
        try {
            \Log::info('Generating image sizes', ['filename' => $filename]);
            
            // Check if GD functions are available
            if (!function_exists('imagecreatefromjpeg')) {
                \Log::warning('GD functions not available, storing original image only');
                return $this->storeOriginalOnly($file, $filename);
            }
            
            $image = $this->imageManager->read($file);
            
            $sizes = [
                'large' => [1200, 1200],
                'medium' => [600, 600],
                'thumbnail' => [200, 200],
            ];

            $urls = [];

            foreach ($sizes as $size => $dimensions) {
                \Log::info('Processing size', ['size' => $size, 'dimensions' => $dimensions]);
                
                $resized = $image->scaleDown($dimensions[0], $dimensions[1]);

                $path = $this->basePath . '/' . $size . '/' . $filename;
                Storage::disk($this->disk)->put($path, $resized->encode());

                $urls[$size . '_url'] = $this->getSignedUrl($path);
                
                \Log::info('Size processed', ['size' => $size, 'url' => $urls[$size . '_url']]);
            }

            return $urls;
        } catch (\Exception $e) {
            \Log::error('Image size generation failed', [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Fallback to storing original only
            \Log::info('Falling back to storing original image only');
            return $this->storeOriginalOnly($file, $filename);
        }
    }

    protected function storeOriginalOnly(UploadedFile $file, string $filename): array
    {
        // Store the original file in all size directories for compatibility
        $sizes = ['large', 'medium', 'thumbnail'];
        $urls = [];

        foreach ($sizes as $size) {
            $path = $this->basePath . '/' . $size . '/' . $filename;
            Storage::disk($this->disk)->putFileAs(
                dirname($path),
                $file,
                basename($path)
            );
            $urls[$size . '_url'] = $this->getSignedUrl($path);
        }

        return $urls;
    }

    /**
     * Get a signed URL for temporary access to an image
     */
    protected function getSignedUrl(string $path): string
    {
        try {
            // Generate a signed URL that expires in 1 hour
            $url = Storage::disk($this->disk)->temporaryUrl($path, now()->addHour());
            return $url;
        } catch (\Exception $e) {
            \Log::error('Failed to generate signed URL', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to regular URL (will likely not work without custom domain)
            return Storage::disk($this->disk)->url($path);
        }
    }

    /**
     * Get a proxy URL for temporary access to an image
     */
    protected function getProxyUrl(string $path): string
    {
        // This method will be called during image upload, but we don't have the span ID yet
        // We'll store the path and generate the proxy URL later when we have the span
        return $path; // Return the path for now, will be replaced with proxy URL after span creation
    }

    /**
     * Generate a unique filename
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        return Str::uuid() . '.' . $extension;
    }

    /**
     * Delete an image and all its variants
     */
    public function deleteImage(string $filename): bool
    {
        $sizes = ['original', 'large', 'medium', 'thumbnail'];
        $deleted = true;

        foreach ($sizes as $size) {
            $path = $this->basePath . '/' . $size . '/' . $filename;
            if (Storage::disk($this->disk)->exists($path)) {
                $deleted = $deleted && Storage::disk($this->disk)->delete($path);
            }
        }

        return $deleted;
    }

    /**
     * Get the storage disk being used
     */
    public function getDisk(): string
    {
        return $this->disk;
    }

    /**
     * Get fresh signed URLs for an existing image
     */
    public function getFreshUrls(string $filename): array
    {
        $sizes = ['original', 'large', 'medium', 'thumbnail'];
        $urls = [];

        foreach ($sizes as $size) {
            $path = $this->basePath . '/' . $size . '/' . $filename;
            if (Storage::disk($this->disk)->exists($path)) {
                $urls[$size . '_url'] = $this->getSignedUrl($path);
            }
        }

        return $urls;
    }

    /**
     * Refresh signed URLs for an existing span
     */
    public function refreshSpanUrls(\App\Models\Span $span): void
    {
        $metadata = $span->metadata ?? [];
        $filename = $metadata['filename'] ?? null;

        if (!$filename) {
            \Log::warning('No filename found in span metadata for URL refresh', [
                'span_id' => $span->id,
                'span_name' => $span->name
            ]);
            return;
        }

        $freshUrls = $this->getFreshUrls($filename);
        
        // Update metadata with fresh URLs
        $metadata = array_merge($metadata, $freshUrls);
        $span->metadata = $metadata;
        $span->save();

        \Log::info('Refreshed signed URLs for span', [
            'span_id' => $span->id,
            'filename' => $filename
        ]);
    }
}
