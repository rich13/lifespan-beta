<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\PlaceLocationService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * List place relations (Contains / Inside / Near) for a place in the CLI.
 * Outputs exactly what the place relations card shows on the place show page.
 *
 * Examples:
 *   php artisan places:relations London
 *   php artisan places:relations "Greater London"
 *   php artisan places:relations abc123-def456-...   (UUID)
 *   php artisan places:relations london              (slug)
 *   php artisan places:relations London --clear-cache
 */
class ListPlaceRelations extends Command
{
    protected $signature = 'places:relations
                            {place : Place ID (UUID), slug, or name (e.g. London, "Greater London")}
                            {--clear-cache : Clear this place\'s relation caches before listing}
                            {--limit=20 : Max items per section (Contains, Inside, Near)}';

    protected $description = 'List place relations (Contains / Inside / Near) as shown on the place relations card';

    public function handle(PlaceLocationService $locationService): int
    {
        $placeInput = $this->argument('place');
        $clearCache = $this->option('clear-cache');
        $limit = (int) $this->option('limit');
        if ($limit < 1) {
            $limit = 20;
        }

        $place = $this->resolvePlace($placeInput);
        if (! $place) {
            $this->error("Place not found: {$placeInput}");
            $this->line('Try by UUID, slug, or exact name (e.g. "London", "Greater London").');
            return 1;
        }

        if ($clearCache) {
            $locationService->clearPlaceCaches($place);
            $this->info('Cleared place relation caches for this place.');
        }

        $summary = $locationService->getPlaceRelationSummary($place, $limit, $limit, $limit);
        if (! $summary) {
            $this->warn('No place relation summary (missing coordinates or boundary centroid).');
            $this->line('Place: ' . $place->name . ' (id: ' . $place->id . ')');
            $coords = $place->getCoordinates();
            $this->line('Coordinates: ' . ($coords ? json_encode($coords) : 'none'));
            $this->newLine();
            $this->line('Re-geocode this place in the UI to add location data, then run this command again.');
            return 0;
        }

        $levelLabel = $place->getPlaceRelationLevelLabel();
        $currentOrder = $levelLabel['order'] ?? null;
        $currentLabel = $levelLabel['label'] ?? '—';

        $this->newLine();
        $this->line('<info>Place:</info> ' . $place->name . ' (id: ' . $place->id . ')');
        $this->line('<info>Admin level:</info> ' . ($currentOrder !== null ? "{$currentOrder} ({$currentLabel})" : '—'));
        $this->line('<info>Has boundary:</info> ' . ($place->hasBoundary() ? 'yes' : 'no'));
        $this->newLine();
        $this->line('<info>——— Place relations card ———</info>');
        $this->newLine();

        $containsByLevel = $summary['contains_sample_by_level'] ?? [];
        $containedByByLevel = $summary['contained_by_by_level'] ?? [];
        $nearByLevel = $summary['near_by_level'] ?? [];

        $this->line('<comment>Contains</comment> ' . ($summary['contains_count'] !== null ? ($summary['contains_count'] === 1 ? '1 place' : $summary['contains_count'] . ' places') : ''));
        $this->outputCardSection($containsByLevel, $summary['contains_sample'] ?? []);
        $this->newLine();

        $this->line('<comment>Inside</comment>');
        $this->outputCardSection($containedByByLevel, $summary['contained_by'] ?? []);
        $this->newLine();

        $this->line('<comment>Near</comment>');
        $this->outputCardSection($nearByLevel, $summary['near'] ?? []);

        return 0;
    }

    private function resolvePlace(string $input): ?Span
    {
        $input = trim($input);
        if ($input === '') {
            return null;
        }

        if (Str::isUuid($input)) {
            $span = Span::where('type_id', 'place')->where('id', $input)->first();
            if ($span) {
                return $span;
            }
        }

        $span = Span::where('type_id', 'place')->where('slug', $input)->first();
        if ($span) {
            return $span;
        }

        return Span::where('type_id', 'place')->where('name', $input)->first();
    }

    private function outputCardSection(array $byLevel, array $fallbackSpans): void
    {
        if (! empty($byLevel)) {
            foreach ($byLevel as $group) {
                $label = $group['label'] ?? 'Other';
                $spans = $group['spans'] ?? [];
                $this->line('  ' . $label . ': ' . $this->formatSpans($spans));
            }
        } elseif (! empty($fallbackSpans)) {
            $this->line('  ' . $this->formatSpans($fallbackSpans));
        } else {
            $this->line('  (none)');
        }
    }

    private function formatSpans(array $spans): string
    {
        $names = array_map(fn ($s) => $s->name, $spans);
        return implode(', ', $names);
    }
}
