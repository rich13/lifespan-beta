<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\FixPrivateIndividualConnectionsJob;
use App\Models\ImportProgress;
use App\Services\PrivateIndividualConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrivateIndividualConnectionsController extends Controller
{
    public function __construct(
        private PrivateIndividualConnectionService $service
    ) {
    }

    public function index(): View
    {
        return view('admin.tools.fix-private-individual-connections', [
            'stats' => $this->service->stats(),
        ]);
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'stats' => $this->service->stats(),
        ]);
    }

    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ]);

        $result = $this->service->scanBatch(
            (int) ($validated['limit'] ?? 25),
            (int) ($validated['offset'] ?? 0)
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    public function startBackgroundFix(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'individual_ids' => 'nullable|array',
            'individual_ids.*' => 'uuid',
        ]);

        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forPrivateIndividualConnections($userId);

        if ($existing && $existing->status === 'running') {
            return response()->json([
                'success' => false,
                'message' => 'A background fix is already running.',
            ], 409);
        }

        ImportProgress::updateOrCreate(
            [
                'import_type' => FixPrivateIndividualConnectionsJob::IMPORT_TYPE,
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
                'metadata' => [],
            ]
        );

        $individualIds = $validated['individual_ids'] ?? null;

        FixPrivateIndividualConnectionsJob::dispatch($userId, 25, $individualIds);

        $scope = $individualIds
            ? count($individualIds).' selected private individual(s)'
            : 'all private individuals that still expose public connection spans';

        return response()->json([
            'success' => true,
            'message' => 'Fixing '.$scope.' in the background. Progress will update as people are processed.',
        ]);
    }

    public function cancelBackgroundFix(Request $request): JsonResponse
    {
        $progress = ImportProgress::forPrivateIndividualConnections((string) $request->user()->id);
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Background fix cancelled. If it was running, it will stop after the current batch.',
        ]);
    }

    public function backgroundStatus(Request $request): JsonResponse
    {
        $progress = ImportProgress::forPrivateIndividualConnections((string) $request->user()->id);

        if (! $progress || ! in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'], true)) {
            return response()->json([
                'success' => true,
                'background_job' => false,
                'is_running' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'background_job' => true,
            'job_status' => $progress->status,
            'is_running' => $progress->status === 'running',
            'job_progress' => $progress->toJobProgressArray(),
        ]);
    }
}
