<?php

namespace App\Models\Traits;

use App\Models\SpanCapabilities\ThingCapability;

/**
 * Adds thing capabilities to a Span model.
 * This trait should be used by thing spans.
 */
trait HasThingCapabilities
{
    /**
     * Boot the trait
     */
    public static function bootHasThingCapabilities()
    {
        static::saving(function ($span) {
            // Validate thing metadata
            if ($span->type_id === 'thing') {
                $span->validateThingData();
            }
        });
    }

    /**
     * Validate the thing data in the metadata
     */
    protected function validateThingData()
    {
        $metadata = $this->metadata ?? [];
        $allowedSubtypes = ThingCapability::getSubtypeOptions();

        if (!isset($metadata['subtype']) || !in_array($metadata['subtype'], $allowedSubtypes)) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }
    }

    /**
     * Get the subtype of this thing
     */
    public function getThingSubtype(): ?string
    {
        return $this->metadata['subtype'] ?? null;
    }

    /**
     * Set the subtype of this thing
     */
    public function setThingSubtype(string $subtype): self
    {
        $allowedSubtypes = ThingCapability::getSubtypeOptions();

        if (!in_array($subtype, $allowedSubtypes)) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }

        $metadata = $this->metadata ?? [];
        $metadata['subtype'] = $subtype;
        $this->metadata = $metadata;
        return $this;
    }
} 