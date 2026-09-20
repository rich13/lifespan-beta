<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Services\JourneyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserConnectionController extends Controller
{
    public function show(Request $request, Span $span): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'steps' => [],
            ], 401);
        }

        if (! $this->canViewSpan($span, $user)) {
            return response()->json([
                'steps' => [],
            ], 403);
        }

        $personalSpan = $user->personalSpan;
        if (! $personalSpan
            || $personalSpan->id === $span->id
            || $span->type_id === 'place'
        ) {
            return response()->json([
                'steps' => [],
            ]);
        }

        $maxDegrees = $span->type_id === 'person' ? 6 : 4;

        try {
            $journey = app(JourneyService::class)
                ->findPathToSpan($personalSpan, $span, $maxDegrees, false);
        } catch (\Exception $e) {
            return response()->json([
                'steps' => [],
            ]);
        }

        return response()->json([
            'steps' => $this->stepsFromJourney($journey),
        ]);
    }

    private function stepsFromJourney(?array $journey): array
    {
        if (! $journey) {
            return [];
        }

        $path = $journey['path'] ?? [];
        $connections = $journey['connections'] ?? [];
        $steps = [];

        for ($i = 0; $i < count($path) - 1; $i++) {
            $currentSpan = $path[$i];
            $nextSpan = $path[$i + 1];
            $connection = $connections[$i] ?? null;

            if (! $connection) {
                continue;
            }

            $connection->loadMissing(['type', 'connectionSpan']);
            $predicate = $connection->getDatedPredicateFrom($currentSpan);

            $steps[] = [
                'from' => [
                    'name' => $currentSpan->name,
                    'url' => route('spans.show', $currentSpan),
                ],
                'predicate' => $predicate,
                'to' => [
                    'name' => $nextSpan->name,
                    'url' => route('spans.show', $nextSpan),
                ],
            ];
        }

        return $steps;
    }

    private function canViewSpan(Span $span, User $user): bool
    {
        return $user->can('view', $span);
    }
}
