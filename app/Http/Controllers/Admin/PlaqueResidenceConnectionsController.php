<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CreatePlaqueResidenceConnectionsJob;
use App\Models\ImportProgress;
use App\Services\PlaqueResidenceConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlaqueResidenceConnectionsController extends Controller
{
    public function __construct(
        private PlaqueResidenceConnectionService $service
    ) {
    }

    public function index(): View
    {
        return view('admin.tools.plaque-residence-connections', [
            'totalPlaques' => $this->service->countPlaques(),
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

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:25',
            'items.*.plaque_id' => 'required|uuid',
            'items.*.person_id' => 'required|uuid',
            'items.*.place_id' => 'required|uuid',
        ]);

        $results = $this->service->createResidences($validated['items'], $request->user());

        $created = collect($results)->where('status', 'created')->count();
        $skipped = collect($results)->where('status', 'skipped')->count();
        $ineligible = collect($results)->where('status', 'ineligible')->count();
        $errors = collect($results)->where('status', 'error')->count();

        return response()->json([
            'success' => $errors === 0,
            'created' => $created,
            'skipped' => $skipped,
            'ineligible' => $ineligible,
            'errors' => $errors,
            'results' => $results,
        ]);
    }

    public function startBackgroundCreate(Request $request): JsonResponse
    {
        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forPlaqueResidenceConnections($userId);

        if ($existing && $existing->status === 'running') {
            return response()->json([
                'success' => false,
                'message' => 'A background create is already running.',
            ], 409);
        }

        ImportProgress::updateOrCreate(
            [
                'import_type' => CreatePlaqueResidenceConnectionsJob::IMPORT_TYPE,
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

        CreatePlaqueResidenceConnectionsJob::dispatch($userId, 25);

        return response()->json([
            'success' => true,
            'message' => 'Creating missing residence connections in the background. Progress will update as plaques are processed.',
        ]);
    }

    public function cancelBackgroundCreate(Request $request): JsonResponse
    {
        $progress = ImportProgress::forPlaqueResidenceConnections((string) $request->user()->id);
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Background create cancelled. If it was running, it will stop after the current batch.',
        ]);
    }

    public function backgroundStatus(Request $request): JsonResponse
    {
        $progress = ImportProgress::forPlaqueResidenceConnections((string) $request->user()->id);

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
