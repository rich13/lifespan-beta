<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Services\Lifespan\UrlDateParser;
use App\Support\ApiEnvelope;
use Illuminate\Http\Request;

class LifespanUrlController extends Controller
{
    public function resolve(Request $request, string $path = ''): \Illuminate\Http\JsonResponse
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), fn ($s) => $s !== ''));
        if (empty($segments)) {
            return ApiEnvelope::error('malformed_path', 'Path cannot be empty.');
        }

        $parser = app(UrlDateParser::class);
        $at = null;
        $asOf = null;

        if (str_starts_with($segments[0], '@')) {
            $at = $parser->parseAnchor(substr($segments[0], 1));
            if ($at === null) {
                return ApiEnvelope::error('malformed_date', 'Invalid valid-time anchor format.', 400);
            }
            array_shift($segments);
        }

        if (!empty($segments) && str_starts_with($segments[count($segments) - 1], '@')) {
            $asOf = $parser->parseAnchor(substr($segments[count($segments) - 1], 1));
            if ($asOf === null) {
                return ApiEnvelope::error('malformed_date', 'Invalid transaction-time anchor format.', 400);
            }
            array_pop($segments);
        }

        if (count($segments) === 1) {
            return $this->resolveSpan($request, $segments[0], $at, $asOf, $path);
        }

        if (count($segments) === 3 || count($segments) === 4) {
            return $this->resolveTriple($request, $segments, $at, $asOf, $path);
        }

        return ApiEnvelope::error('malformed_path', 'Path shape does not match supported grammar.', 400);
    }

    private function resolveSpan(Request $request, string $slug, ?array $at, ?array $asOf, string $path): \Illuminate\Http\JsonResponse
    {
        $span = Span::where('slug', $slug)->first();
        if (!$span) {
            return ApiEnvelope::error('not_found', 'No such span identity exists in graph.', 404);
        }

        if (!$span->isAccessibleBy($request->user())) {
            return ApiEnvelope::error('not_found', 'No such span identity exists in graph.', 404);
        }

        if ($asOf !== null && !$span->isKnownByDateParts($asOf['year'], $asOf['month'], $asOf['day'])) {
            return ApiEnvelope::error('not_found', 'No such span identity exists in graph.', 404);
        }

        $state = $span->getTemporalStateAtDateParts($at['year'] ?? null, $at['month'] ?? null, $at['day'] ?? null);
        $lastAsserted = $span->updated_at?->toISOString();
        if ($asOf !== null && $span->updated_at && $span->updated_at->gt(\Carbon\Carbon::create($asOf['year'], $asOf['month'], $asOf['day'], 23, 59, 59))) {
            $lastAsserted = null;
        }

        return ApiEnvelope::success([
            'url' => '/' . trim($path, '/'),
            'query' => [
                'mode' => ($at || $asOf) ? 'time-traveller' : 'tralfamadorian',
                'at' => $at['iso'] ?? null,
                'as_of' => $asOf['iso'] ?? null,
            ],
            'status' => $state['status'],
            'span' => [
                'slug' => $span->slug,
                'short_id' => $span->short_id,
                'name' => $span->name,
                'type' => $span->type_id,
                'subtype' => $span->subtype,
                'start' => $span->toTemporalDatePayload('start'),
                'end' => $span->toTemporalDatePayload('end'),
                'last_asserted' => $lastAsserted,
                'owner' => $span->access_level === 'public' ? 'public' : 'private',
                'sources' => $span->sources ?? [],
            ],
            'temporal' => [
                'starts' => $state['starts'],
                'ended' => $state['ended'],
            ],
        ]);
    }

    private function resolveTriple(Request $request, array $segments, ?array $at, ?array $asOf, string $path): \Illuminate\Http\JsonResponse
    {
        [$subjectSlug, $predicateSlug, $objectSlug] = array_slice($segments, 0, 3);
        $shortId = $segments[3] ?? null;

        $subject = Span::where('slug', $subjectSlug)->first();
        $object = Span::where('slug', $objectSlug)->first();
        if (!$subject || !$object) {
            return ApiEnvelope::error('not_found', 'No such base triple identity exists in graph.', 404);
        }

        $predicate = str_replace('-', ' ', $predicateSlug);
        $connectionType = ConnectionType::where('forward_predicate', $predicate)
            ->orWhere('inverse_predicate', $predicate)
            ->first();

        if (!$connectionType) {
            return ApiEnvelope::error('not_found', 'No such base triple identity exists in graph.', 404);
        }

        $connections = Connection::query()
            ->where('type_id', $connectionType->type)
            ->where(function ($query) use ($subject, $object) {
                $query->where(function ($subQ) use ($subject, $object) {
                    $subQ->where('parent_id', $subject->id)->where('child_id', $object->id);
                })->orWhere(function ($subQ) use ($subject, $object) {
                    $subQ->where('parent_id', $object->id)->where('child_id', $subject->id);
                });
            })
            ->with('connectionSpan')
            ->get()
            ->filter(function ($connection) use ($request, $asOf, $at) {
                $span = $connection->connectionSpan;
                if (!$span || !$span->isAccessibleBy($request->user())) {
                    return false;
                }
                if ($asOf !== null && !$span->isKnownByDateParts($asOf['year'], $asOf['month'], $asOf['day'])) {
                    return false;
                }
                if ($at !== null) {
                    $state = $span->getTemporalStateAtDateParts($at['year'], $at['month'], $at['day']);
                    return $state['status'] === 'active';
                }

                return true;
            })
            ->values();

        if ($connections->isEmpty()) {
            return ApiEnvelope::error('not_found', 'No such base triple identity exists in graph.', 404);
        }

        if ($shortId !== null) {
            $connections = $connections->filter(fn ($connection) => $connection->connectionSpan?->short_id === $shortId)->values();
            if ($connections->isEmpty()) {
                return ApiEnvelope::error('not_found', 'No matching relationship span for supplied short id.', 404);
            }
        }

        if ($connections->count() > 1 && $shortId === null) {
            return ApiEnvelope::success([
                'url' => '/' . trim($path, '/'),
                'query' => [
                    'mode' => ($at || $asOf) ? 'time-traveller' : 'tralfamadorian',
                    'at' => $at['iso'] ?? null,
                    'as_of' => $asOf['iso'] ?? null,
                ],
                'status' => 'disambiguation',
                'matches' => $connections->map(function ($connection) use ($subjectSlug, $predicateSlug, $objectSlug) {
                    return sprintf(
                        '/%s/%s/%s/%s',
                        $subjectSlug,
                        $predicateSlug,
                        $objectSlug,
                        $connection->connectionSpan?->short_id
                    );
                })->values()->all(),
            ]);
        }

        $connectionSpan = $connections->first()->connectionSpan;
        $state = $connectionSpan->getTemporalStateAtDateParts($at['year'] ?? null, $at['month'] ?? null, $at['day'] ?? null);

        return ApiEnvelope::success([
            'url' => '/' . trim($path, '/'),
            'query' => [
                'mode' => ($at || $asOf) ? 'time-traveller' : 'tralfamadorian',
                'at' => $at['iso'] ?? null,
                'as_of' => $asOf['iso'] ?? null,
            ],
            'status' => $state['status'],
            'span' => [
                'slug' => $connectionSpan->slug,
                'short_id' => $connectionSpan->short_id,
                'name' => $connectionSpan->name,
                'type' => $connectionSpan->type_id,
                'subtype' => $connectionSpan->subtype,
                'start' => $connectionSpan->toTemporalDatePayload('start'),
                'end' => $connectionSpan->toTemporalDatePayload('end'),
                'last_asserted' => $connectionSpan->updated_at?->toISOString(),
                'owner' => $connectionSpan->access_level === 'public' ? 'public' : 'private',
                'sources' => $connectionSpan->sources ?? [],
            ],
            'temporal' => [
                'starts' => $state['starts'],
                'ended' => $state['ended'],
            ],
        ]);
    }
}
