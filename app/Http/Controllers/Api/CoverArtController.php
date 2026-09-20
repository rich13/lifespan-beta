<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\FetchAlbumCoverArtJob;
use App\Models\Span;
use App\Models\User;
use App\Services\MusicBrainzCoverArtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CoverArtController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $spanIds = $request->input('span_ids', []);
        if (! is_array($spanIds)) {
            $spanIds = [$spanIds];
        }

        $spanIds = collect($spanIds)
            ->filter(fn ($id) => is_string($id) && Str::isUuid($id))
            ->unique()
            ->take(50)
            ->values();

        if ($spanIds->isEmpty()) {
            return response()->json([
                'covers' => (object) [],
                'pending' => [],
            ]);
        }

        $user = $request->user();
        $service = MusicBrainzCoverArtService::getInstance();
        $covers = [];
        $pending = [];

        $spans = Span::query()
            ->whereIn('id', $spanIds->all())
            ->get()
            ->keyBy('id');

        foreach ($spanIds as $spanId) {
            $span = $spans->get($spanId);
            if (! $span || ! $this->canViewSpan($span, $user)) {
                continue;
            }

            $stored = $span->storedCoverArtUrl('small');
            if ($stored) {
                $covers[$spanId] = [
                    'small' => $stored,
                    'medium' => $span->storedCoverArtUrl('medium'),
                    'large' => $span->storedCoverArtUrl('large'),
                ];
                continue;
            }

            if ($span->getMeta('cover_art.missing') === true || ! $span->needsCoverArtFetch()) {
                $covers[$spanId] = null;
                continue;
            }

            $cached = $service->getCachedCoverArtResult($span->music_brainz_id);
            if ($cached['hit']) {
                $urls = $service->frontCoverUrlsFromData($cached['data']);
                if ($urls) {
                    $service->persistCoverArtOnSpan($span, $urls);
                    $covers[$spanId] = $urls;
                } elseif ($service->isConfirmedMissing($span->music_brainz_id)) {
                    $service->persistCoverArtOnSpan($span, null);
                    $covers[$spanId] = null;
                } else {
                    FetchAlbumCoverArtJob::dispatch($span->id);
                    $pending[] = $spanId;
                }
                continue;
            }

            FetchAlbumCoverArtJob::dispatch($span->id);
            $pending[] = $spanId;
        }

        return response()->json([
            'covers' => $covers,
            'pending' => $pending,
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
