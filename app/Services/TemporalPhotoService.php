<?php

namespace App\Services;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\Temporal\TemporalRange;
use App\Services\Temporal\TemporalService;
use Illuminate\Support\Collection;

class TemporalPhotoService
{
    public function __construct(
        private readonly TemporalService $temporalService
    ) {}

    /**
     * Get the subject span when the given span is a connection span used as connection_span_id.
     * Returns null if the span is not a connection span or no connection uses it.
     */
    public function getSubjectForConnectionSpan(Span $span): ?Span
    {
        if ($span->type_id !== 'connection') {
            return null;
        }

        $connection = Connection::where('connection_span_id', $span->id)
            ->with('subject')
            ->first();

        return $connection?->subject;
    }

    /**
     * Get photo connections (features) where the photo features the subject and the photo's
     * start date overlaps the connection span's date range. Applies access control.
     *
     * @return Collection<int, Connection> Collection of features Connection models
     */
    public function getTemporallyRelatedPhotos(Span $connectionSpan, Span $subject, ?User $user = null): Collection
    {
        $user = $user ?? auth()->user();

        if (!$connectionSpan->start_year) {
            return collect();
        }

        try {
            $currentRange = TemporalRange::fromSpan($connectionSpan);
        } catch (\InvalidArgumentException) {
            return collect();
        }

        // Get features connections where the subject is featured (child), with access control
        $connections = $subject->connectionsAsObjectWithAccess($user)
            ->where('type_id', 'features')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                    ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent', 'child', 'connectionSpan', 'type'])
            ->get();

        return $connections->filter(function (Connection $conn) use ($currentRange) {
            $photoSpan = $conn->parent;
            if (!$photoSpan || !$photoSpan->start_year) {
                return false;
            }

            try {
                $photoRange = TemporalRange::fromSpan($photoSpan);
                return $this->temporalService->overlaps($currentRange, $photoRange);
            } catch (\InvalidArgumentException) {
                return false;
            }
        })->values();
    }
}
