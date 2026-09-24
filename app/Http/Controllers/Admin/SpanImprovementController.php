<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImproveSpansJob;
use App\Models\ImportProgress;
use App\Services\ImprovementCreationPolicy;
use App\Services\QueueWorkerControlService;
use App\Services\SpanImprovementCoordinator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpanImprovementController extends Controller
{
    public function __construct(
        private readonly SpanImprovementCoordinator $coordinator,
        private readonly ImprovementCreationPolicy $creation,
    ) {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $progress = ImportProgress::forSpanImprovement((string) auth()->id());

        return view('admin.improvement.index', [
            'progress' => $progress,
            'queueCounts' => $this->coordinator->queueCounts(),
            'aiBudget' => $this->coordinator->aiBudget(),
            'creationLimits' => [
                'per_span' => $this->creation->childCap(),
                'per_run' => $this->creation->runCap(),
            ],
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forSpanImprovement($userId);
        if ($existing && $existing->status === 'running' && ! ($existing->metadata['cancel_requested'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Span improvement is already running.',
            ], 409);
        }

        ImportProgress::updateOrCreate(
            [
                'import_type' => ImproveSpansJob::IMPORT_TYPE,
                'plaque_type' => null,
                'user_id' => $userId,
            ],
            [
                'total_items' => 0,
                'processed_items' => 0,
                'created_items' => 0,
                'skipped_items' => 0,
                'error_count' => 0,
                'status' => 'running',
                'started_at' => now(),
                'completed_at' => null,
                'error_message' => null,
                'metadata' => [
                    'cancel_requested' => false,
                    'activity_log' => [],
                    'ai_token_budget' => $this->coordinator->aiBudget()['limit'],
                    'ai_tokens_used' => 0,
                ],
            ]
        );

        ImproveSpansJob::releaseUniquenessFor($userId);
        ImproveSpansJob::dispatch($userId);

        return response()->json([
            'success' => true,
            'message' => 'Span improvement started. It works through Wikipedia, MusicBrainz, and geocoding a few spans at a time.',
        ]);
    }

    public function stop(Request $request, QueueWorkerControlService $workers): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $progress = ImportProgress::forSpanImprovement($userId);
        if (! $progress || $progress->status !== 'running') {
            return response()->json([
                'success' => true,
                'message' => 'Span improvement is not running.',
            ]);
        }

        $result = $workers->forceStopImport($progress);
        $progress->refresh();
        $progress->mergeProgress([
            'current_item' => null,
            'current_improver' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => $result['message'],
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $progress = ImportProgress::forSpanImprovement((string) $request->user()->id);
        $budget = $this->coordinator->aiBudget();
        $meta = $progress->metadata ?? [];

        return response()->json([
            'running' => $progress && $progress->status === 'running' && ! ($meta['cancel_requested'] ?? false),
            'progress' => $progress?->toJobProgressArray(),
            'ai_budget' => [
                'limit' => $meta['ai_token_budget'] ?? $budget['limit'],
                'used' => $meta['ai_tokens_used'] ?? $budget['used'],
                'enabled' => $budget['enabled'],
            ],
        ]);
    }
}
