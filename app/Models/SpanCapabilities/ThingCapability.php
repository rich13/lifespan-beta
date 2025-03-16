<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;

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

    public function getMetadataSchema(): array
    {
        return [
            'subtype' => [
                'type' => 'text',
                'label' => 'Type of Thing',
                'component' => 'select',
                'options' => ['book', 'album', 'painting', 'sculpture', 'recording', 'other'],
                'required' => true
            ],
            'creator' => [
                'type' => 'span',
                'label' => 'Creator',
                'component' => 'span-input',
                'span_type' => 'person',
                'required' => true
            ]
        ];
    }

    public function validateMetadata(): void
    {
        $metadata = $this->span->metadata ?? [];
        
        // Validate subtype
        if (!isset($metadata['subtype']) || !in_array($metadata['subtype'], ['book', 'album', 'painting', 'sculpture', 'recording', 'other'])) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }

        // Validate creator
        if (!isset($metadata['creator'])) {
            throw new \InvalidArgumentException('Creator is required for things');
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
        if (!in_array($subtype, ['book', 'album', 'painting', 'sculpture', 'recording', 'other'])) {
            throw new \InvalidArgumentException('Invalid thing subtype');
        }

        $metadata = $this->span->metadata ?? [];
        $metadata['subtype'] = $subtype;
        $this->span->metadata = $metadata;
    }
} 