<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Collection;

/**
 * Groups place spans that share the same OSM identity (osm_type + osm_id)
 * so the merge tool can offer them for human review.
 */
class PlaceDuplicateDetectionService
{
    /**
     * Normalise Nominatim/Overpass OSM type letters to full words.
     */
    public static function normaliseOsmType(mixed $osmType): ?string
    {
        $type = strtolower(trim((string) $osmType));
        if ($type === '') {
            return null;
        }

        return match ($type) {
            'n', 'node' => 'node',
            'w', 'way' => 'way',
            'r', 'relation' => 'relation',
            default => $type,
        };
    }

    /**
     * Cast OSM ids to a comparable string (JSON number vs string, quoted values).
     */
    public static function normaliseOsmId(mixed $osmId): ?string
    {
        if ($osmId === null) {
            return null;
        }

        $id = trim((string) $osmId, " \t\n\r\0\x0B\"'");
        if ($id === '') {
            return null;
        }

        return $id;
    }

    /**
     * Stable group key, e.g. "relation:51825".
     */
    public static function identityKey(mixed $osmType, mixed $osmId): ?string
    {
        $type = self::normaliseOsmType($osmType);
        $id = self::normaliseOsmId($osmId);
        if ($type === null || $id === null) {
            return null;
        }

        return $type . ':' . $id;
    }

    public static function identityKeyFromOsmData(?array $osmData): ?string
    {
        if ($osmData === null) {
            return null;
        }

        return self::identityKey($osmData['osm_type'] ?? null, $osmData['osm_id'] ?? null);
    }

    public static function identityKeyFromSpan(Span $span): ?string
    {
        return self::identityKeyFromOsmData($span->getOsmData());
    }

    /**
     * Groups of 2+ place spans that share a normalised OSM identity.
     *
     * @return Collection<int, array{
     *     identity_key: string,
     *     osm_type: string,
     *     osm_id: string,
     *     osm_url: string,
     *     canonical_name: string|null,
     *     spans: Collection<int, Span>,
     *     suggested_target_span_id: string,
     *     suggested_source_span_id: string
     * }>
     */
    public function getSameOsmIdentityGroups(): Collection
    {
        $places = Span::where('type_id', 'place')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereRaw("COALESCE(metadata->'external_refs'->'osm'->>'osm_type', '') <> ''")
                        ->whereRaw("COALESCE(metadata->'external_refs'->'osm'->>'osm_id', '') <> ''");
                })->orWhere(function ($q2) {
                    $q2->whereRaw("COALESCE(metadata->'osm_data'->>'osm_type', '') <> ''")
                        ->whereRaw("COALESCE(metadata->'osm_data'->>'osm_id', '') <> ''");
                });
            })
            ->withCount(['connectionsAsSubject', 'connectionsAsObject'])
            ->with([
                'connectionsAsSubject:id,parent_id,child_id,type_id',
                'connectionsAsSubject.child:id,name,slug',
                'connectionsAsObject:id,parent_id,child_id,type_id',
                'connectionsAsObject.parent:id,name,slug',
            ])
            ->orderBy('name')
            ->orderBy('slug')
            ->get();

        return $places
            ->groupBy(fn (Span $span) => self::identityKeyFromSpan($span))
            ->filter(function (Collection $groupSpans, $key) {
                return $key !== '' && $key !== null && $groupSpans->count() >= 2;
            })
            ->map(function (Collection $groupSpans) {
                $sorted = $groupSpans->sort($this->suggestedKeepComparator())->values();
                $suggestedTarget = $sorted->first();
                $suggestedSource = $sorted->first(fn (Span $span) => $span->id !== $suggestedTarget->id)
                    ?? $sorted->last();

                $osmData = $suggestedTarget->getOsmData() ?? $groupSpans->first()?->getOsmData() ?? [];
                $osmType = self::normaliseOsmType($osmData['osm_type'] ?? null);
                $osmId = self::normaliseOsmId($osmData['osm_id'] ?? null);
                $canonicalName = $this->canonicalNameFromGroup($groupSpans);

                return [
                    'identity_key' => $osmType . ':' . $osmId,
                    'osm_type' => $osmType,
                    'osm_id' => $osmId,
                    'osm_url' => 'https://www.openstreetmap.org/' . $osmType . '/' . $osmId,
                    'canonical_name' => $canonicalName,
                    'spans' => $groupSpans->values(),
                    'suggested_target_span_id' => $suggestedTarget->id,
                    'suggested_source_span_id' => $suggestedSource->id,
                ];
            })
            ->sortBy(fn (array $group) => mb_strtolower($group['canonical_name'] ?? $group['identity_key']))
            ->values();
    }

    /**
     * @param Collection<int, Span> $groupSpans
     */
    private function canonicalNameFromGroup(Collection $groupSpans): ?string
    {
        foreach ($groupSpans as $span) {
            $name = $span->getOsmData()['canonical_name'] ?? null;
            if (is_string($name) && trim($name) !== '') {
                return $name;
            }
        }

        return null;
    }

    /**
     * Keep: most connections, then published over draft, then has a boundary, then oldest.
     *
     * @return callable(Span, Span): int
     */
    private function suggestedKeepComparator(): callable
    {
        return function (Span $a, Span $b): int {
            $connA = ($a->connections_as_subject_count ?? 0) + ($a->connections_as_object_count ?? 0);
            $connB = ($b->connections_as_subject_count ?? 0) + ($b->connections_as_object_count ?? 0);
            if ($connA !== $connB) {
                return $connB <=> $connA;
            }

            $stateCmp = $this->stateRank($b) <=> $this->stateRank($a);
            if ($stateCmp !== 0) {
                return $stateCmp;
            }

            $boundaryA = $a->hasBoundary() ? 1 : 0;
            $boundaryB = $b->hasBoundary() ? 1 : 0;
            if ($boundaryA !== $boundaryB) {
                return $boundaryB <=> $boundaryA;
            }

            $createdA = $a->created_at?->getTimestamp() ?? 0;
            $createdB = $b->created_at?->getTimestamp() ?? 0;

            return $createdA <=> $createdB;
        };
    }

    private function stateRank(Span $span): int
    {
        return match ($span->state) {
            'published' => 3,
            'complete' => 2,
            'draft' => 1,
            default => 0,
        };
    }
}
