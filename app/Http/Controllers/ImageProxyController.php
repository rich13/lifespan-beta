<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;
use App\Models\Span;

class ImageProxyController extends Controller
{
    /**
     * Proxy an image from R2 storage
     */
    public function proxy(Request $request, $spanId, $size = 'medium')
    {
        // Find the span
        $span = Span::findOrFail($spanId);
        
        // Check if user has access to this span
        if (!$span->isPublic() && !auth()->check()) {
            abort(403, 'Access denied');
        }
        
        if (!$span->isPublic() && auth()->id() !== $span->owner_id) {
            abort(403, 'Access denied');
        }

        // Get the filename from metadata
        $metadata = $span->metadata ?? [];
        $filename = $metadata['filename'] ?? null;

        if (!$filename) {
            abort(404, 'Image not found');
        }

        // Validate size parameter
        $validSizes = ['original', 'large', 'medium', 'thumbnail'];
        if (!in_array($size, $validSizes)) {
            $size = 'medium';
        }

        // Construct the path
        $path = "photos/{$size}/{$filename}";

        // Check if file exists
        if (!Storage::disk('r2')->exists($path)) {
            abort(404, 'Image not found');
        }

        // Get file contents
        $contents = Storage::disk('r2')->get($path);
        $mimeType = Storage::disk('r2')->mimeType($path);

        // Return the image with appropriate headers
        return Response::make($contents, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600', // Cache for 1 hour
            'Content-Length' => strlen($contents),
        ]);
    }

    /**
     * Get image info for a span
     */
    public function info(Request $request, $spanId)
    {
        $span = Span::findOrFail($spanId);
        
        // Check access permissions
        if (!$span->isPublic() && !auth()->check()) {
            abort(403, 'Access denied');
        }
        
        if (!$span->isPublic() && auth()->id() !== $span->owner_id) {
            abort(403, 'Access denied');
        }

        $metadata = $span->metadata ?? [];
        
        return response()->json([
            'span_id' => $span->id,
            'filename' => $metadata['filename'] ?? null,
            'file_size' => $metadata['file_size'] ?? null,
            'mime_type' => $metadata['mime_type'] ?? null,
            'urls' => [
                'original' => route('images.proxy', ['spanId' => $span->id, 'size' => 'original']),
                'large' => route('images.proxy', ['spanId' => $span->id, 'size' => 'large']),
                'medium' => route('images.proxy', ['spanId' => $span->id, 'size' => 'medium']),
                'thumbnail' => route('images.proxy', ['spanId' => $span->id, 'size' => 'thumbnail']),
            ]
        ]);
    }
}


