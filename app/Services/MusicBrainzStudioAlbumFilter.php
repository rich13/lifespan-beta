<?php

namespace App\Services;

/**
 * Keep official studio albums and drop live, compilation, and bootleg release groups.
 */
class MusicBrainzStudioAlbumFilter
{
    private const EXCLUDED_SECONDARY_TYPES = [
        'compilation',
        'live',
        'soundtrack',
        'remix',
        'mixtape',
        'dj-mix',
        'karaoke',
        'spokenword',
        'audiobook',
        'broadcast',
        'demo',
        'interview',
        'bootleg',
    ];

    /**
     * @param  list<array<string, mixed>>  $albums
     * @return list<array<string, mixed>>
     */
    public function filter(array $albums): array
    {
        return array_values(array_filter($albums, fn (array $album) => $this->isStudioAlbum($album)));
    }

    public function isStudioAlbum(array $album): bool
    {
        $primary = strtolower((string) ($album['primary-type'] ?? $album['type'] ?? ''));
        if ($primary !== 'album') {
            return false;
        }

        $secondary = array_map(
            fn ($type) => strtolower((string) $type),
            $album['secondary-types'] ?? []
        );
        if (array_intersect($secondary, self::EXCLUDED_SECONDARY_TYPES) !== []) {
            return false;
        }

        $title = (string) ($album['title'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}:/', $title)) {
            return false;
        }

        $haystack = strtolower($title . ' ' . (string) ($album['disambiguation'] ?? ''));
        foreach (['bootleg', 'unofficial'] as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return false;
            }
        }

        return true;
    }
}
