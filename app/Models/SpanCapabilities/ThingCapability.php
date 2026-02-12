<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;
use App\Models\SpanType;
use Illuminate\Support\Facades\Cache;

/**
 * Thing capability for spans.
 * Provides functionality for human-made items.
 */
class ThingCapability implements SpanCapability
{
    protected Span $span;

    public function __construct(Span $span)
    {
        $this->span = $span;
    }

    public function getName(): string
    {
        return 'thing';
    }

    public function getSpan(): Span
    {
        return $this->span;
    }

    /**
     * Get allowed thing subtypes from the SpanType (DB) - cached for performance
     */
    public static function getSubtypeOptions(): array
    {
        return Cache::remember('span_type_thing_subtype_options', 3600, function () {
            $thingType = SpanType::find('thing');

            return $thingType?->getSubtypeOptions() ?? [];
        });
    }

    public function getMetadataSchema(): array
    {
        $options = self::getSubtypeOptions();

        return [
            'subtype' => [
                'type' => 'text',
                'label' => 'Type of Thing',
                'component' => 'select',
                'options' => empty($options) ? ['other'] : $options,
                'required' => true
            ]
        ];
    }

    public function validateMetadata(): void
    {
        $metadata = $this->span->metadata ?? [];
        $allowedSubtypes = self::getSubtypeOptions();

        if (!isset($metadata['subtype']) || !in_array($metadata['subtype'], $allowedSubtypes)) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }
    }

    public function isAvailable(): bool
    {
        return $this->span->type_id === 'thing';
    }

    /**
     * Get the subtype of this thing
     */
    public function getSubtype(): ?string
    {
        return $this->span->metadata['subtype'] ?? null;
    }

    /**
     * Set the subtype of this thing
     */
    public function setSubtype(string $subtype): void
    {
        $allowedSubtypes = self::getSubtypeOptions();

        if (!in_array($subtype, $allowedSubtypes)) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }

        $metadata = $this->span->metadata ?? [];
        $metadata['subtype'] = $subtype;
        $this->span->metadata = $metadata;
    }
} 