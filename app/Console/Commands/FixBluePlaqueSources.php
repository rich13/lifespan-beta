<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixBluePlaqueSources extends Command
{
    protected $signature = 'blue-plaques:fix-sources
        {--type=london_blue : Plaque type: london_blue or london_green}
        {--limit= : Limit number of plaques to fix (for testing)}';

    protected $description = 'Update OpenPlaques source URLs for plaques that still have the generic homepage link. Run when no other imports are in progress to avoid lock contention.';

    public function handle(): int
    {
        $type = $this->option('type');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $dataSource = $type === 'london_green' ? 'openplaques_london_green_2023' : 'openplaques_london_2023';

        $query = Span::where('metadata->data_source', $dataSource)
            ->whereRaw("sources::text LIKE '%openplaques.org/%'")
            ->whereRaw("sources::text NOT LIKE '%openplaques.org/plaques/%'");

        if ($limit) {
            $query->limit($limit);
        }

        $plaques = $query->get(['id', 'metadata', 'sources']);
        $total = $plaques->count();

        if ($total === 0) {
            $this->info('No plaques need source URL updates.');

            return 0;
        }

        $this->info("Updating source URLs for {$total} plaques...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        foreach ($plaques as $span) {
            $plaqueId = $span->metadata['external_id'] ?? null;
            if (empty($plaqueId) || $plaqueId === 'unknown' || !is_numeric($plaqueId)) {
                $bar->advance();
                continue;
            }

            $directUrl = 'https://openplaques.org/plaques/' . (int) $plaqueId;
            $sources = $span->sources ?? [];
            $changed = false;

            foreach ($sources as $index => $source) {
                if (is_array($source) && ($source['url'] ?? null) === 'https://openplaques.org/') {
                    $sources[$index]['url'] = $directUrl;
                    $changed = true;
                }
            }

            if ($changed) {
                DB::table('spans')
                    ->where('id', $span->id)
                    ->update(['sources' => json_encode($sources)]);
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated {$updated} plaque source URLs.");

        return 0;
    }
}
