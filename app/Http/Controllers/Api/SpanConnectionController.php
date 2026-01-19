<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpanConnectionController extends Controller
{
    public function show(Request $request, Span $span, Span $other): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewSpan($span, $user) || !$this->canViewSpan($other, $user)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden'
            ], 403);
        }

        $connection = Connection::where(function ($query) use ($span, $other) {
            $query->where('parent_id', $span->id)
                ->where('child_id', $other->id);
        })->orWhere(function ($query) use ($span, $other) {
            $query->where('parent_id', $other->id)
                ->where('child_id', $span->id);
        })
            ->whereNotNull('connection_span_id')
            ->with(['connectionSpan'])
            ->orderByDesc('updated_at')
            ->first();

        if (!$connection || !$connection->connectionSpan) {
            return response()->json([
                'success' => true,
                'connection_span' => null
            ]);
        }

        $connectionSpan = $connection->connectionSpan;
        if (!$this->canViewSpan($connectionSpan, $user)) {
            return response()->json([
                'success' => true,
                'connection_span' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'target_span' => [
                'id' => $other->id,
                'slug' => $other->slug,
                'name' => $other->getDisplayTitle(),
                'url' => route('spans.show', $other),
            ],
            'connection_span' => [
                'id' => $connectionSpan->id,
                'slug' => $connectionSpan->slug,
                'name' => $connectionSpan->getDisplayTitle(),
                'url' => route('spans.show', $connectionSpan),
            ]
        ]);
    }

    private function canViewSpan(Span $span, ?User $user): bool
    {
        if (!$user) {
            return $span->access_level === 'public';
        }

        return $user->can('view', $span);
    }
}
