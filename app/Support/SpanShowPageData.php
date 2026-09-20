<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Support\Collection;

/**
 * Access-dependent facts for a span show request.
 * Blade still receives the same view variables as before.
 */
final class SpanShowPageData
{
    /**
     * @param  array<string, mixed>|null  $story
     * @param  array<string, mixed>|null  $bluePlaqueCardData
     * @param  Collection<int|string, mixed>  $annotatingNotes
     * @param  Collection<int|string, mixed>  $directorConnectionsByFilmId
     * @param  Collection<int, Connection>  $parentConnections
     * @param  Collection<int, Connection>  $childConnections
     */
    public function __construct(
        public readonly Span $span,
        public readonly SpanShowContext $context,
        public readonly Collection $parentConnections,
        public readonly Collection $childConnections,
        public readonly ?array $story,
        public readonly ?Connection $connectionForSpan,
        public readonly Collection $annotatingNotes,
        public readonly ?array $bluePlaqueCardData,
        public readonly Collection $directorConnectionsByFilmId,
    ) {}

    /**
     * Variables `spans.show` already expects.
     *
     * @return array<string, mixed>
     */
    public function viewData(): array
    {
        return [
            'span' => $this->span,
            'precomputedConnections' => $this->context->connections,
            'parentConnections' => $this->parentConnections,
            'childConnections' => $this->childConnections,
            'familyData' => $this->context->familyData,
            'educationCardData' => $this->context->educationCardData,
            'timelineSeed' => $this->context->timelineSeed,
            'personalTimelineSeed' => $this->context->personalTimelineSeed,
            'desertIslandDiscsSet' => $this->context->desertIslandDiscsCardData['set'] ?? null,
            'desertIslandDiscsTracks' => $this->context->desertIslandDiscsCardData['tracks'] ?? null,
            'story' => $this->story,
            'connectionForSpan' => $this->connectionForSpan,
            'annotatingNotes' => $this->annotatingNotes,
            'bluePlaqueCardData' => $this->bluePlaqueCardData,
            'directorConnectionsByFilmId' => $this->directorConnectionsByFilmId,
        ];
    }
}
