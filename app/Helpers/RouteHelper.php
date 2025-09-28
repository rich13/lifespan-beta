<?php

namespace App\Helpers;

use App\Models\Span;

class RouteHelper
{
    /**
     * Get the appropriate route for a span based on its type and subtype
     */
    public static function getSpanRoute(Span $span, string $action = 'show', array $parameters = []): string
    {
        // Check if this is a photo span
        if ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo') {
            return route("photos.{$action}", array_merge([$span], $parameters));
        }

        // Default to spans route for all other types
        return route("spans.{$action}", array_merge([$span], $parameters));
    }

    /**
     * Get the appropriate route name for a span based on its type and subtype
     */
    public static function getSpanRouteName(Span $span, string $action = 'show'): string
    {
        // Check if this is a photo span
        if ($span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo') {
            return "photos.{$action}";
        }

        // Default to spans route for all other types
        return "spans.{$action}";
    }

    /**
     * Check if a span should use photo routes
     */
    public static function isPhotoSpan(Span $span): bool
    {
        return $span->type_id === 'thing' && ($span->metadata['subtype'] ?? null) === 'photo';
    }
}
