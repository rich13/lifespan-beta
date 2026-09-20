<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\User;
use App\Services\WikipediaSpanMatcherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LinkedDescriptionController extends Controller
{
    public function show(Request $request, Span $span): JsonResponse
    {
        $user = $request->user();
        if (! $this->canViewSpan($span, $user)) {
            return response()->json([
                'html' => null,
            ], 403);
        }

        if (! $span->description) {
            return response()->json([
                'html' => null,
            ]);
        }

        $viewerKey = $user?->id ?? 'guest';
        $cacheKey = sprintf(
            'linked_description:v1:%s:%s:%s',
            $span->id,
            $viewerKey,
            sha1($span->description)
        );

        $html = Cache::remember($cacheKey, 900, function () use ($span) {
            return app(WikipediaSpanMatcherService::class)
                ->linkedHtmlFromMarkdown($span->description);
        });

        return response()->json([
            'html' => $html,
        ]);
    }

    private function canViewSpan(Span $span, ?User $user): bool
    {
        if (! $user) {
            return $span->access_level === 'public';
        }

        return $user->can('view', $span);
    }
}
