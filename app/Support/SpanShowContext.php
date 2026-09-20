<?php

namespace App\Support;

/**
 * Request-scoped facts for a span page. Cards and services read these instead
 * of querying again. Card conversion status lives in SpanShowCardCatalogue.
 */
final class SpanShowContext
{
    /**
     * @param  array<string, mixed>|null  $familyData
     * @param  array<string, mixed>|null  $educationCardData
     * @param  array<string, mixed>|null  $timelineSeed
     * @param  array<string, mixed>|null  $personalTimelineSeed
     * @param  array{set: \App\Models\Span, tracks: \Illuminate\Support\Collection}|null  $desertIslandDiscsCardData
     */
    public function __construct(
        public readonly PrecomputedSpanConnections $connections,
        public readonly ?array $familyData = null,
        public readonly ?array $educationCardData = null,
        public readonly ?array $timelineSeed = null,
        public readonly ?array $personalTimelineSeed = null,
        public readonly ?array $desertIslandDiscsCardData = null,
    ) {}

    /**
     * Named facts this request loaded, and which cards or services use them.
     *
     * @return list<array{fact: string, loaded: bool, shared: bool, used_by: list<string>}>
     */
    public function factInventory(): array
    {
        $familyLoaded = $this->familyData !== null;
        $educationLoaded = $this->educationCardData !== null;
        $timelineLoaded = $this->timelineSeed !== null;
        $personalTimelineLoaded = $this->personalTimelineSeed !== null;
        $didLoaded = $this->desertIslandDiscsCardData !== null;

        $connectionUsers = ['story'];
        if ($educationLoaded) {
            $connectionUsers[] = 'education';
        }
        if ($timelineLoaded) {
            $connectionUsers[] = 'timeline';
        }
        if ($this->connections->hasRoleNamed('Musician') || $this->connections->createdAlbumSpans()->isNotEmpty()) {
            $connectionUsers[] = 'discography';
        }
        if ($this->connections->getParentByTypes(['employment', 'has_role'])->isNotEmpty()) {
            $connectionUsers[] = 'employment';
        }
        if ($this->connections->getParentByType('residence')->isNotEmpty()) {
            $connectionUsers[] = 'places-lived';
        }
        if ($this->connections->featuredPhotoConnections()->isNotEmpty()) {
            $connectionUsers[] = 'gallery';
        }
        if ($this->connections->desertIslandDiscsSet() !== null) {
            $connectionUsers[] = 'desert-island-discs';
        }
        if ($this->connections->getChildByTypes(['employment', 'at_organisation'])->isNotEmpty()) {
            $connectionUsers[] = 'employees';
        }
        if ($this->connections->getChildByType('education')->isNotEmpty()) {
            $connectionUsers[] = 'students';
        }
        if ($this->connections->getChildByTypes(['residence', 'located'])->isNotEmpty()) {
            $connectionUsers[] = 'lived-here';
        }
        if ($this->connections->getChildByType('contains')->contains(
            fn ($connection) => $connection->parent
                && $connection->parent->type_id === 'collection'
        )) {
            $connectionUsers[] = 'collections';
        }
        if ($this->connections->getParentByType('contains')->contains(
            fn ($connection) => $connection->child
                && $connection->child->type_id === 'thing'
                && ($connection->child->metadata['subtype'] ?? null) === 'track'
        )) {
            $connectionUsers[] = 'album-tracks';
        }
        if ($this->connections->getParentByType('contains')->contains(
            fn ($connection) => $connection->child
                && $connection->child->type_id === 'thing'
                && ($connection->child->metadata['subtype'] ?? null) === 'episode'
        )) {
            $connectionUsers[] = 'programme-episodes';
        }
        if ($this->connections->getParentByType('features')->contains(
            fn ($connection) => $connection->child
                && ($connection->child->metadata['subtype'] ?? null) !== 'photo'
        )) {
            $connectionUsers[] = 'plaque-featured';
        }
        if ($this->connections->getChildByType('created')->contains(
            fn ($connection) => $connection->parent && $connection->parent->type_id === 'person'
        ) && $this->connections->getParentByType('features')->contains(
            fn ($connection) => $connection->child && $connection->child->type_id === 'person'
        )) {
            $connectionUsers[] = 'related-films';
        }
        if ($this->connections->getParentByType('during')->contains(
            fn ($connection) => $connection->child && $connection->child->type_id === 'phase'
        )) {
            $connectionUsers[] = 'temporal-relations';
        }

        return [
            [
                'fact' => 'connections',
                'loaded' => true,
                'shared' => true,
                'used_by' => $connectionUsers,
            ],
            [
                'fact' => 'family',
                'loaded' => $familyLoaded,
                'shared' => $familyLoaded,
                'used_by' => $familyLoaded ? ['family', 'story'] : [],
            ],
            [
                'fact' => 'connection_span_during',
                'loaded' => $educationLoaded || $timelineLoaded,
                'shared' => $educationLoaded && $timelineLoaded,
                'used_by' => array_values(array_filter([
                    $educationLoaded ? 'education' : null,
                    $timelineLoaded ? 'timeline' : null,
                ])),
            ],
            [
                'fact' => 'timeline_seed',
                'loaded' => $timelineLoaded,
                'shared' => $timelineLoaded && $personalTimelineLoaded,
                'used_by' => array_values(array_filter([
                    $timelineLoaded ? 'timeline' : null,
                    $personalTimelineLoaded ? 'compare' : null,
                ])),
            ],
            [
                'fact' => 'personal_timeline_seed',
                'loaded' => $personalTimelineLoaded,
                'shared' => $personalTimelineLoaded,
                'used_by' => $personalTimelineLoaded ? ['timeline', 'compare'] : [],
            ],
            [
                'fact' => 'desert_island_discs_tracks',
                'loaded' => $didLoaded,
                'shared' => false,
                'used_by' => $didLoaded ? ['desert-island-discs'] : [],
            ],
        ];
    }
}
