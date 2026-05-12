<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bootstrap Icons suffixes (the part after "bi-") for span types, connections, subtypes, etc.
 * Single source for the x-icon Blade component and client-side scripts.
 */
final class BootstrapIconMap
{
    private const DEFAULT_SPAN = 'box';

    private const DEFAULT_CONNECTION = 'link-45deg';

    private const DEFAULT_SUBTYPE = 'tag';

    private const DEFAULT_STATUS = 'circle';

    private const DEFAULT_ACTION = 'gear';

    /** @var array<string, string> */
    private const SPAN = [
        'person' => 'person-fill',
        'organisation' => 'building',
        'place' => 'geo-alt-fill',
        'event' => 'calendar-event-fill',
        'band' => 'cassette',
        'thing' => 'box',
        'connection' => 'link-45deg',
        'role' => 'person-badge',
        'note' => 'sticky',
        'collection' => 'collection',
        'set' => 'archive',
        'phase' => 'hourglass-split',
        'name' => 'type',
        'geometry' => 'pentagon',
        'animal' => 'bug',
    ];

    /** @var array<string, string> */
    private const CONNECTION = [
        'education' => 'mortarboard-fill',
        'employment' => 'briefcase-fill',
        'work' => 'briefcase-fill',
        'member_of' => 'people-fill',
        'membership' => 'people-fill',
        'residence' => 'house-fill',
        'family' => 'people-fill',
        'friend' => 'person-plus',
        'relationship' => 'person-heart',
        'created' => 'node-plus-fill',
        'contains' => 'box-seam',
        'travel' => 'airplane',
        'participation' => 'calendar-event',
        'ownership' => 'key-fill',
        'has_role' => 'person-badge',
        'at_organisation' => 'building',
        'features' => 'box-arrow-in-down-right',
        'located' => 'geo-alt',
    ];

    /** @var array<string, string> */
    private const SUBTYPE = [
        'track' => 'music-note-beamed',
        'album' => 'disc',
        'film' => 'camera-video',
        'programme' => 'tv',
        'play' => 'theater',
        'book' => 'book',
        'poem' => 'file-text',
        'photo' => 'image',
        'sculpture' => 'gem',
        'painting' => 'palette',
        'performance' => 'mic',
        'video' => 'camera-video',
        'article' => 'file-text',
        'paper' => 'file-earmark-text',
        'product' => 'box',
        'vehicle' => 'car-front',
        'tool' => 'wrench',
        'device' => 'cpu',
        'artifact' => 'archive',
        'plaque' => 'award',
        'series' => 'collection',
        'episode' => 'tv',
        'other' => 'box',
        'broadcaster' => 'broadcast',
        'educational' => 'mortarboard',
        'government' => 'building',
        'military' => 'shield',
        'museum' => 'building',
        'gallery' => 'image',
        'theatre' => 'theater',
        'hospital' => 'heart-pulse',
        'tech company' => 'cpu',
        'law firm' => 'scale',
        'union' => 'people',
        'newspaper' => 'newspaper',
        'web platform' => 'globe',
        'transport' => 'truck',
        'professional' => 'briefcase',
        'creative' => 'palette',
        'musicians' => 'music-note',
        'public_figure' => 'person-badge',
        'private_individual' => 'person',
    ];

    /** @var array<string, string> */
    private const STATUS = [
        'personal' => 'star',
        'owner' => 'person',
        'created' => 'calendar-plus',
        'updated' => 'calendar-check',
        'public' => 'globe',
        'private' => 'lock',
        'shared' => 'people',
        'placeholder' => 'dash-circle',
        'draft' => 'pencil-circle',
        'complete' => 'check-circle-fill',
        'all' => 'check-circle',
    ];

    /** @var array<string, string> */
    private const ACTION = [
        'add' => 'plus',
        'edit' => 'pencil',
        'delete' => 'trash',
        'view' => 'eye',
        'search' => 'search',
        'clear' => 'x-circle',
        'import' => 'box-arrow-in-down',
        'export' => 'box-arrow-up',
        'download' => 'download',
        'upload' => 'upload',
        'save' => 'check',
        'cancel' => 'x',
        'no_subtype' => 'x',
        'back' => 'arrow-left',
        'forward' => 'arrow-right',
        'home' => 'house',
        'profile' => 'person-circle',
        'logout' => 'box-arrow-right',
        'shield' => 'shield',
        'shield-lock' => 'shield-lock',
        'shield-fill-check' => 'shield-fill-check',
    ];

    public static function suffix(string $category, ?string $type): string
    {
        $normalised = self::normaliseType($type);

        return match ($category) {
            'span' => $normalised === null
                ? self::DEFAULT_SPAN
                : (self::SPAN[$normalised] ?? self::DEFAULT_SPAN),
            'connection' => $normalised === null
                ? self::DEFAULT_CONNECTION
                : (self::CONNECTION[$normalised] ?? self::DEFAULT_CONNECTION),
            'subtype' => $normalised === null
                ? self::DEFAULT_SUBTYPE
                : (self::SUBTYPE[$normalised] ?? self::DEFAULT_SUBTYPE),
            'status' => $normalised === null
                ? self::DEFAULT_STATUS
                : (self::STATUS[$normalised] ?? self::DEFAULT_STATUS),
            'action' => $normalised === null
                ? self::DEFAULT_ACTION
                : (self::ACTION[$normalised] ?? self::DEFAULT_ACTION),
            default => 'circle',
        };
    }

    /**
     * Payload for {@see window.LifespanBootstrapIcons} (span / connection / subtype lookups in JS).
     *
     * @return array<string, mixed>
     */
    public static function forClient(): array
    {
        return [
            'span' => self::SPAN,
            'connection' => self::CONNECTION,
            'subtype' => self::SUBTYPE,
            'status' => self::STATUS,
            'action' => self::ACTION,
            'defaults' => [
                'span' => self::DEFAULT_SPAN,
                'connection' => self::DEFAULT_CONNECTION,
                'subtype' => self::DEFAULT_SUBTYPE,
                'status' => self::DEFAULT_STATUS,
                'action' => self::DEFAULT_ACTION,
            ],
        ];
    }

    public static function biClass(string $category, ?string $type): string
    {
        return 'bi-'.self::suffix($category, $type);
    }

    private static function normaliseType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return (string) $type;
    }
}
