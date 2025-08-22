<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing place hierarchy and creating missing administrative spans
 */
class PlaceHierarchyService
{
    private OSMGeocodingService $osmService;

    public function __construct(OSMGeocodingService $osmService)
    {
        $this->osmService = $osmService;
    }

    /**
     * Process a place import and create missing higher-level spans
     */
    public function processPlaceImport(Span $place, array $osmData): array
    {
        $createdSpans = [];
        $hierarchy = $osmData['hierarchy'] ?? [];

        foreach ($hierarchy as $level) {
            $levelName = $level['name'];
            $adminLevel = $level['admin_level'];

            // Check if a span already exists with this name
            $existingSpan = Span::where('name', $levelName)
                ->where('type_id', 'place')
                ->first();

            if (!$existingSpan) {
                // Create a new span for this administrative level
                $newSpan = $this->createAdministrativeSpan($levelName, $adminLevel, $level);
                if ($newSpan) {
                    $createdSpans[] = $newSpan;
                    Log::info("Created missing administrative span", [
                        'name' => $levelName,
                        'admin_level' => $adminLevel,
                        'span_id' => $newSpan->id
                    ]);
                }
            }
        }

        return $createdSpans;
    }

    /**
     * Create a span for an administrative division
     */
    public function createAdministrativeSpan(string $name, int $adminLevel, array $levelData): ?Span
    {
        try {
            // Get OSM data for this administrative level
            $osmData = $this->osmService->geocode($name);
            
            if (!$osmData) {
                Log::warning("Could not geocode administrative level", [
                    'name' => $name,
                    'admin_level' => $adminLevel
                ]);
                return null;
            }

            // Get or create system user
            $systemUser = \App\Models\User::where('email', 'system@lifespan.app')->first();
            if (!$systemUser) {
                $systemUser = \App\Models\User::create([
                    'email' => 'system@lifespan.app',
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ]);
            }

            // Create the span with proper parameters for timeless place spans
            $span = Span::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $name,
                'type_id' => 'place',
                'state' => 'complete',
                'start_year' => null, // Places are timeless, so no start year required
                'end_year' => null,
                'owner_id' => $systemUser->id,
                'updater_id' => $systemUser->id,
                'access_level' => 'public', // Administrative places should be public
                'metadata' => [
                    'osm_data' => $osmData,
                    'coordinates' => $osmData['coordinates'] ?? null,
                    'administrative_level' => $adminLevel,
                    'auto_created' => true,
                    'timeless' => true // Explicitly mark as timeless
                ]
            ]);

            return $span;

        } catch (\Exception $e) {
            Log::error("Failed to create administrative span", [
                'name' => $name,
                'admin_level' => $adminLevel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get all missing administrative spans for existing places
     */
    public function findMissingAdministrativeSpans(): array
    {
        $places = Span::where('type_id', 'place')
            ->whereRaw("metadata->>'osm_data' IS NOT NULL")
            ->get();

        $allLevels = [];
        $existingNames = Span::where('type_id', 'place')->pluck('name')->toArray();

        foreach ($places as $place) {
            $hierarchy = $place->metadata['osm_data']['hierarchy'] ?? [];
            foreach ($hierarchy as $level) {
                $key = $level['name'] . '_' . $level['admin_level'];
                if (!in_array($level['name'], $existingNames)) {
                    $allLevels[$key] = $level;
                }
            }
        }

        return array_values($allLevels);
    }

    /**
     * Create all missing administrative spans
     */
    public function createAllMissingSpans(): array
    {
        $missingLevels = $this->findMissingAdministrativeSpans();
        $createdSpans = [];

        foreach ($missingLevels as $level) {
            $span = $this->createAdministrativeSpan($level['name'], $level['admin_level'], $level);
            if ($span) {
                $createdSpans[] = $span;
            }
        }

        return $createdSpans;
    }
}
