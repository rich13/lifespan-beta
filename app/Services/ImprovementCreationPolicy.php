<?php

namespace App\Services;

use App\Models\Span;

/**
 * Stops improvement from creating spans without limit.
 * A generation 0 span may be enriched, and during a coordinator run it may
 * cause a limited number of new spans. Those children are generation 1:
 * they can be filled in once, and they cannot create anything further.
 * Outside a coordinator run the breadth cap is not applied, so a dedicated
 * importer the user started can still bring in a whole discography.
 */
class ImprovementCreationPolicy
{
    public const MAX_GENERATION = 1;

    private bool $runActive = false;

    private int $created = 0;

    public function runCap(): int
    {
        return max(0, (int) config('services.improvement.max_new_spans_per_run', 25));
    }

    public function childCap(): int
    {
        return max(0, (int) config('services.improvement.max_child_spans', 3));
    }

    public function beginRun(): void
    {
        $this->runActive = true;
        $this->created = 0;
    }

    public function endRun(): void
    {
        $this->runActive = false;
        $this->created = 0;
    }

    public function createdCount(): int
    {
        return $this->created;
    }

    public function allowsCreation(Span $parent, int $count = 1): bool
    {
        if ((int) $parent->improvement_generation >= self::MAX_GENERATION) {
            return false;
        }

        if (! $this->runActive) {
            return true;
        }

        $count = max(1, $count);
        if (($this->created + $count) > $this->runCap()) {
            return false;
        }

        return ($this->contentChildCount($parent) + $count) <= $this->childCap();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createChild(Span $parent, array $attributes): ?Span
    {
        $isConnection = ($attributes['type_id'] ?? null) === 'connection';
        if ($isConnection) {
            if ((int) $parent->improvement_generation >= self::MAX_GENERATION) {
                return null;
            }
        } elseif (! $this->allowsCreation($parent)) {
            return null;
        }

        $span = Span::create(array_merge($attributes, [
            'improvement_generation' => (int) $parent->improvement_generation + 1,
            'improvement_mode' => Span::IMPROVEMENT_DEFER,
            'improvement_parent_id' => $parent->id,
        ]));

        if (! $isConnection && $this->runActive) {
            $this->created++;
        }

        return $span;
    }

    private function contentChildCount(Span $parent): int
    {
        return Span::query()
            ->where('improvement_parent_id', $parent->id)
            ->where('type_id', '!=', 'connection')
            ->count();
    }
}
