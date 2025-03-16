<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Collection;

/**
 * Band capability for spans.
 * Provides functionality for musical groups.
 */
class BandCapability implements SpanCapability
{
    protected Span $span;

    public function __construct(Span $span)
    {
        $this->span = $span;
    }

    public function getName(): string
    {
        return 'band';
    }

    public function getSpan(): Span
    {
        return $this->span;
    }

    public function getMetadataSchema(): array
    {
        return [
            'genres' => [
                'type' => 'array',
                'label' => 'Genres',
                'component' => 'tag-input',
                'help' => 'Musical genres associated with this band',
                'required' => false
            ],
            'formation_location' => [
                'type' => 'span',
                'label' => 'Formation Location',
                'component' => 'span-input',
                'span_type' => 'place',
                'required' => false
            ]
        ];
    }

    public function validateMetadata(): void
    {
        $metadata = $this->span->metadata ?? [];
        
        // Validate genres if present
        if (isset($metadata['genres']) && !is_array($metadata['genres'])) {
            throw new \InvalidArgumentException('Genres must be an array');
        }

        // Validate formation location if present
        if (isset($metadata['formation_location']) && !is_string($metadata['formation_location'])) {
            throw new \InvalidArgumentException('Formation location must be a span ID');
        }
    }

    public function isAvailable(): bool
    {
        return $this->span->type_id === 'band';
    }

    /**
     * Get current members of the band
     */
    public function getCurrentMembers(): Collection
    {
        return $this->span->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
            ->where('connections.type_id', 'membership')
            ->whereNull('spans.end_year')
            ->withPivot('connection_span_id')
            ->get();
    }

    /**
     * Get all members (past and present) of the band
     */
    public function getAllMembers(): Collection
    {
        return $this->span->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
            ->where('connections.type_id', 'membership')
            ->withPivot('connection_span_id')
            ->get();
    }

    /**
     * Get the band's discography (things created by the band)
     */
    public function getDiscography(): Collection
    {
        return $this->span->belongsToMany(Span::class, 'connections', 'parent_id', 'child_id')
            ->where('connections.type_id', 'created')
            ->where('spans.type_id', 'thing')
            ->whereIn('spans.metadata->subtype', ['album', 'single'])
            ->withPivot('connection_span_id')
            ->orderBy('spans.start_year')
            ->orderBy('spans.start_month')
            ->orderBy('spans.start_day')
            ->get();
    }

    /**
     * Get the band's status (active/disbanded)
     */
    public function getStatus(): string
    {
        if ($this->span->end_year) {
            return 'disbanded';
        }
        return 'active';
    }

    /**
     * Add a member to the band
     */
    public function addMember(Span $member, array $metadata = []): void
    {
        if ($member->type_id !== 'person') {
            throw new \InvalidArgumentException('Member must be a person span');
        }

        // Create the connection span
        $connectionSpan = Span::create([
            'name' => "{$member->name} - {$this->span->name} Membership",
            'type_id' => 'connection',
            'owner_id' => $this->span->owner_id,
            'updater_id' => $this->span->updater_id,
            'start_year' => $member->start_year,
            'start_month' => $member->start_month,
            'start_day' => $member->start_day,
            'access_level' => $this->span->access_level
        ]);

        // Create the connection
        Connection::create([
            'type_id' => 'membership',
            'parent_id' => $this->span->id,
            'child_id' => $member->id,
            'connection_span_id' => $connectionSpan->id,
            'metadata' => array_merge([
                'role' => $metadata['role'] ?? null
            ], $metadata)
        ]);
    }

    /**
     * Remove a member from the band
     */
    public function removeMember(Span $member): void
    {
        $this->span->belongsToMany(Span::class, 'connections', 'child_id', 'parent_id')
            ->where('connections.type_id', 'membership')
            ->where('spans.id', $member->id)
            ->whereNull('spans.end_year')
            ->update([
                'spans.end_year' => now()->year,
                'spans.end_month' => now()->month,
                'spans.end_day' => now()->day,
                'spans.end_precision' => 'day'
            ]);
    }
} 