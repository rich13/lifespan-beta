<?php

namespace App\Models\Traits;

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
        
        // Validate subtype
        if (!isset($metadata['subtype']) || !in_array($metadata['subtype'], ['book', 'album', 'painting', 'sculpture', 'photo', 'other'])) {
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
        if (!in_array($subtype, ['book', 'album', 'painting', 'sculpture', 'photo', 'other'])) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }

        $metadata = $this->metadata ?? [];
        $metadata['subtype'] = $subtype;
        $this->metadata = $metadata;
        return $this;
    }
} 