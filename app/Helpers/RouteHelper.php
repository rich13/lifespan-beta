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

    /**
     * Build the photos index URL for the given filters (maintainable single place for photo list URLs).
     * Use this instead of calling route('photos.of', ...) / route('photos.in', ...) directly so
     * all URL patterns stay consistent (e.g. /photos/of/:slug, /photos/in/:slug, /photos/of/:slug/in/:slug).
     *
     * @param  Span|null  $of  Span that photos feature (connection type "features")
     * @param  Span|null  $in  Span that photos are located in (connection type "located")
     * @param  array  $query  Extra query params (e.g. search, state, from_date, to_date)
     */
    public static function photosIndexUrl(?Span $of = null, ?Span $in = null, array $query = []): string
    {
        if ($of && $in) {
            return route('photos.of.in', ['slug' => $of->slug, 'locationSlug' => $in->slug]) . self::queryString($query);
        }
        if ($of) {
            return route('photos.of', ['slug' => $of->slug]) . self::queryString($query);
        }
        if ($in) {
            return route('photos.in', ['slug' => $in->slug]) . self::queryString($query);
        }
        return route('photos.index', $query);
    }

    private static function queryString(array $query): string
    {
        if ($query === []) {
            return '';
        }
        return '?' . http_build_query($query);
    }
}
