<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\WikidataPlaceHierarchyFetcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sync administrative hierarchy from Wikidata into place span metadata.
 * Runs only in the background (CLI) — never call Wikidata during web requests.
 * Uses short timeouts and rate-limiting to avoid hanging.
 */
class SyncWikidataPlaceHierarchy extends Command
{
    protected $signature = 'places:sync-wikidata-hierarchy
                            {--dry-run : Do not save changes}
                            {--limit= : Max number of places to process}
                            {--refresh : Re-fetch even when hierarchy already stored}';

    protected $description = 'Fetch P131/P150 from Wikidata for places with wikidata_id and store in metadata (background only)';

    public function handle(WikidataPlaceHierarchyFetcher $fetcher): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit');
        $refresh = (bool) $this->option('refresh');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be saved.');
        }

        $query = Span::where('type_id', 'place')
            ->where(function ($q) {
                $q->whereRaw("(metadata->'external_refs'->'osm'->>'wikidata_id') IS NOT NULL AND (metadata->'external_refs'->'osm'->>'wikidata_id') != ''")
                    ->orWhereRaw("(metadata->'osm_data'->>'wikidata_id') IS NOT NULL AND (metadata->'osm_data'->>'wikidata_id') != ''")
                    ->orWhereRaw("(metadata->'external_refs'->'wikidata'->>'id') IS NOT NULL AND (metadata->'external_refs'->'wikidata'->>'id') != ''");
            });

        // Exclude places that already have hierarchy unless --refresh
        if (!$refresh) {
            $query->whereRaw("(metadata->'wikidata_hierarchy') IS NULL");
        }

        if ($limit !== null) {
            $query->limit((int) $limit);
        }

        $places = $query->get();
        $total = $places->count();

        if ($total === 0) {
            $this->info('No places with wikidata_id found to sync.');
            return 0;
        }

        $this->info("Syncing Wikidata hierarchy for up to {$total} place(s). One request per second to avoid rate limits.");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $ok = 0;
        $fail = 0;
        $skipped = 0;

        foreach ($places as $span) {
            $wikidataId = $this->getWikidataIdFromSpan($span);
            if ($wikidataId === null || $wikidataId === '') {
                $skipped++;
                $bar->advance();
                continue;
            }

            $hierarchy = $fetcher->fetchHierarchy($wikidataId);
            if ($hierarchy === null) {
                $fail++;
                Log::debug('places:sync-wikidata-hierarchy: no hierarchy for ' . $wikidataId, ['span_id' => $span->id]);
            } else {
                if (!$dryRun) {
                    $metadata = $span->metadata ?? [];
                    $metadata['wikidata_hierarchy'] = $hierarchy;
                    $span->metadata = $metadata;
                    $span->save();
                }
                $ok++;
            }

            $bar->advance();
            // Rate-limit: one request per second (fetcher may do several requests per place)
            sleep(1);
        }

        $bar->finish();
        $this->newLine();
        $this->info("Done: {$ok} synced, {$fail} failed, {$skipped} skipped.");

        return 0;
    }

    private function getWikidataIdFromSpan(Span $span): ?string
    {
        $metadata = $span->metadata ?? [];
        $id = $metadata['external_refs']['wikidata']['id'] ?? null;
        if ($id !== null && $id !== '') {
            return $id;
        }
        $id = $metadata['external_refs']['osm']['wikidata_id'] ?? null;
        if ($id !== null && $id !== '') {
            return $id;
        }
        $id = $metadata['osm_data']['wikidata_id'] ?? null;
        return $id !== null && $id !== '' ? $id : null;
    }
}
