<?php

namespace App\Services\Lifespan;

use App\Models\Connection;
use App\Models\ConnectionEpistemicRevision;
use App\Models\Span;
use App\Models\SpanEpistemicRevision;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class EpistemicRevisionRecorder
{
    public function recordSpanIfChanged(Span $span): void
    {
        if (! $this->shouldRecordModelChange($span)) {
            return;
        }

        $effective = $this->calendarPartsFromInstant($span->wasRecentlyCreated ? $span->created_at : now());

        SpanEpistemicRevision::create([
            'span_id' => $span->id,
            'effective_year' => $effective['year'],
            'effective_month' => $effective['month'],
            'effective_day' => $effective['day'],
            'payload' => $this->spanPayload($span),
        ]);
    }

    public function recordConnectionIfChanged(Connection $connection): void
    {
        if (! $this->shouldRecordModelChange($connection)) {
            return;
        }

        $effective = $this->calendarPartsFromInstant($connection->wasRecentlyCreated ? $connection->created_at : now());

        ConnectionEpistemicRevision::create([
            'connection_id' => $connection->id,
            'effective_year' => $effective['year'],
            'effective_month' => $effective['month'],
            'effective_day' => $effective['day'],
            'payload' => $this->connectionPayload($connection),
        ]);
    }

    /**
     * @param  array{year: int, month: int, day: int}  $parts
     */
    public function recordSpanWithEffectiveCalendar(Span $span, array $parts): void
    {
        SpanEpistemicRevision::create([
            'span_id' => $span->id,
            'effective_year' => $parts['year'],
            'effective_month' => $parts['month'],
            'effective_day' => $parts['day'],
            'payload' => $this->spanPayload($span),
        ]);
    }

    /**
     * @param  array{year: int, month: int, day: int}  $parts
     */
    public function recordConnectionWithEffectiveCalendar(Connection $connection, array $parts): void
    {
        ConnectionEpistemicRevision::create([
            'connection_id' => $connection->id,
            'effective_year' => $parts['year'],
            'effective_month' => $parts['month'],
            'effective_day' => $parts['day'],
            'payload' => $this->connectionPayload($connection),
        ]);
    }

    private function shouldRecordModelChange(Model $model): bool
    {
        if ($model->wasRecentlyCreated) {
            return true;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);

        return $changes !== [];
    }

    /**
     * @return array{name: string, slug: string, type_id: string, ...}
     */
    public function spanPayload(Span $span): array
    {
        return [
            'name' => $span->name,
            'slug' => $span->slug,
            'type_id' => $span->type_id,
            'is_personal_span' => (bool) $span->is_personal_span,
            'parent_id' => $span->parent_id,
            'root_id' => $span->root_id,
            'start_year' => $span->start_year,
            'start_month' => $span->start_month,
            'start_day' => $span->start_day,
            'end_year' => $span->end_year,
            'end_month' => $span->end_month,
            'end_day' => $span->end_day,
            'start_precision' => $span->start_precision,
            'end_precision' => $span->end_precision,
            'state' => $span->state,
            'description' => $span->description,
            'notes' => $span->notes,
            'metadata' => $span->metadata ?? [],
            'sources' => $span->sources ?? [],
            'permissions' => (int) ($span->permissions ?? 0),
            'permission_mode' => $span->permission_mode ?? 'inherit',
            'access_level' => $span->access_level,
            'filter_type' => $span->filter_type,
            'filter_criteria' => $span->filter_criteria,
            'is_predefined' => (bool) $span->is_predefined,
        ];
    }

    /**
     * @return array{parent_id: string, child_id: string, type_id: string, connection_span_id: string, metadata: array}
     */
    public function connectionPayload(Connection $connection): array
    {
        return [
            'parent_id' => $connection->parent_id,
            'child_id' => $connection->child_id,
            'type_id' => $connection->type_id,
            'connection_span_id' => $connection->connection_span_id,
            'metadata' => $connection->metadata ?? [],
        ];
    }

    /**
     * @return array{year: int, month: int, day: int}
     */
    private function calendarPartsFromInstant(?CarbonInterface $instant): array
    {
        $i = $instant ?? now();

        return [
            'year' => (int) $i->year,
            'month' => (int) $i->month,
            'day' => (int) $i->day,
        ];
    }
}
