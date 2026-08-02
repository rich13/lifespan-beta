<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Services\PlaqueVirtualPlaqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExplorePlaqueVirtualPlaqueController extends Controller
{
    public function __construct(
        private readonly PlaqueVirtualPlaqueService $virtualPlaqueService,
    ) {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * GET /explore/plaques/{span}/virtual-plaque
     */
    public function status(Span $span): JsonResponse
    {
        if (!$span->isAccessibleBy(Auth::user())) {
            abort(404);
        }

        return response()->json($this->virtualPlaqueService->statusForPlaque($span));
    }

    /**
     * POST /explore/plaques/{span}/virtual-plaque
     */
    public function store(Request $request, Span $span): JsonResponse
    {
        if (!$span->isAccessibleBy(Auth::user())) {
            abort(404);
        }

        if (!$this->virtualPlaqueService->isLondonPlaqueSpan($span)) {
            return response()->json([
                'success' => false,
                'message' => 'This span is not a London blue plaque.',
            ], 400);
        }

        $validated = $request->validate([
            'person_id' => ['required', 'uuid', 'exists:spans,id'],
            'place_id' => ['required', 'uuid', 'exists:spans,id'],
            'connection_type' => ['required', 'string'],
            'start_year' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'end_year' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ]);

        $people = $this->virtualPlaqueService->featuredPeople($span);
        $places = $this->virtualPlaqueService->plaquePlaces($span);

        if (!$people->contains('id', $validated['person_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Person is not featured on this plaque.',
            ], 422);
        }

        if (!$places->contains('id', $validated['place_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Place is not the location of this plaque.',
            ], 422);
        }

        $person = Span::findOrFail($validated['person_id']);
        $place = Span::findOrFail($validated['place_id']);

        try {
            $connection = $this->virtualPlaqueService->createPersonPlaceConnection(
                $person,
                $place,
                $validated['connection_type'],
                Auth::user(),
                $validated['start_year'] ?? null,
                $validated['end_year'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $virtualUrl = $this->virtualPlaqueService->virtualPlaqueUrl($connection);

        return response()->json([
            'success' => true,
            'message' => 'Connection created.',
            'virtual_plaque_url' => $virtualUrl,
            'status' => $this->virtualPlaqueService->statusForPlaque($span),
        ]);
    }
}
