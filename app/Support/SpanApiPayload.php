<?php

namespace App\Support;

use App\Models\Connection;
use App\Models\Span;

/**
 * Canonical span JSON for GET /spans/{span}.json and the types explorer JSON column.
 *
 * Connection spans may include an extra {@see core} key `connection` (subject / predicate / object).
 * All other span types omit `connection` so the shape stays the same for existing consumers.
 */
class SpanApiPayload
{
    /**
     * Core span fields (the {@see successEnvelope} `data` object).
     *
     * @return array<string, mixed>
     */
    public static function core(Span $span): array
    {
        $data = [
            'id' => $span->id,
            'name' => $span->name,
            'slug' => $span->slug,
            'short_id' => $span->short_id,
            'type_id' => $span->type_id,
            'subtype' => $span->subtype,
            'state' => $span->state,
            'description' => $span->description,
            'notes' => $span->notes,
            'is_personal_span' => $span->is_personal_span,
            'start_year' => $span->start_year,
            'start_month' => $span->start_month,
            'start_day' => $span->start_day,
            'start_precision' => $span->start_precision,
            'end_year' => $span->end_year,
            'end_month' => $span->end_month,
            'end_day' => $span->end_day,
            'end_precision' => $span->end_precision,
            'formatted_start_date' => $span->formatted_start_date,
            'formatted_end_date' => $span->formatted_end_date,
            'metadata' => $span->metadata,
            'sources' => $span->sources,
            'access_level' => $span->access_level,
            'owner_id' => $span->owner_id,
            'updater_id' => $span->updater_id,
            'created_at' => $span->created_at?->toIso8601String(),
            'updated_at' => $span->updated_at?->toIso8601String(),
            'url' => route('spans.show', ['subject' => $span]),
        ];

        if ($span->type_id === 'connection') {
            $triple = self::connectionTripleForSpan($span);
            if ($triple !== null) {
                $data['connection'] = $triple;
            }
        }

        return $data;
    }

    /**
     * Subject / predicate / object for a connection span (from the `connections` row).
     *
     * @return array{id: string, predicate: string, subject: ?array, object: ?array}|null
     */
    private static function connectionTripleForSpan(Span $span): ?array
    {
        $connection = Connection::query()
            ->withOnly(['subject', 'object'])
            ->where('connection_span_id', $span->id)
            ->first();

        if ($connection === null) {
            return null;
        }

        return [
            'id' => $connection->id,
            'predicate' => $connection->type_id,
            'subject' => self::spanEndpointRef($connection->subject),
            'object' => self::spanEndpointRef($connection->object),
        ];
    }

    /**
     * @return array{id: string, name: string, slug: ?string, short_id: ?string, type_id: string, url: string}|null
     */
    private static function spanEndpointRef(?Span $span): ?array
    {
        if ($span === null) {
            return null;
        }

        return [
            'id' => $span->id,
            'name' => $span->name,
            'slug' => $span->slug,
            'short_id' => $span->short_id,
            'type_id' => $span->type_id,
            'url' => route('spans.show', ['subject' => $span]),
        ];
    }

    /**
     * Full body returned by {@see \App\Http\Controllers\SpanController::showJson}.
     *
     * @return array{data: array<string, mixed>, meta: array<mixed>}
     */
    public static function successEnvelope(Span $span): array
    {
        return [
            'data' => self::core($span),
            'meta' => [
                'links' => [
                    'connections' => route('spans.show.connections.json', ['span' => $span]),
                ],
            ],
        ];
    }
}
