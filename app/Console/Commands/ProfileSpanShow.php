<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;

class ProfileSpanShow extends Command
{
    protected $signature = 'span:profile-show
                            {slug : The span slug (e.g. richard-northover)}
                            {--view : Also time full view render (can be slow)}
                            {--queries : Show individual DB queries}
                            {--analyze-queries : With --view, show most repeated query patterns (finds duplicates/N+1)}';

    protected $description = 'Profile the span show page: time each phase (resolve, load relations, Desert Island Discs, connections, optional view render) and report DB query count.';

    public function handle(): int
    {
        $slug = $this->argument('slug');
        $showQueries = $this->option('queries');
        $renderView = $this->option('view');
        $analyzeQueries = $this->option('analyze-queries');

        $this->info("Profiling span show for slug: {$slug}");
        $this->newLine();

        $phases = [];
        $queryCounts = [];
        $viewQueryLog = [];
        $viewQueries = 0;

        // Phase 1: Resolve span (simulates route binding: Span::where('slug', $slug)->with('type')->first())
        DB::flushQueryLog();
        DB::enableQueryLog();
        $t0 = microtime(true);
        $span = Span::where('slug', $slug)->with('type')->first();
        if (! $span) {
            if (Str::isUuid($slug)) {
                $span = Span::where('id', $slug)->with('type')->first();
            }
        }
        $t1 = microtime(true);
        $queryCounts['1_resolve_span'] = count(DB::getQueryLog());
        $phases[] = ['1. Resolve span (route binding)', $t1 - $t0, $queryCounts['1_resolve_span']];

        if (! $span) {
            $this->error('Span not found.');
            return 1;
        }

        // Phase 2: Load type, owner, updater (what Cache::remember closure does)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $span->load(['type', 'owner', 'updater']);
        $t1 = microtime(true);
        $queryCounts['2_load_relations'] = count(DB::getQueryLog());
        $phases[] = ['2. Load type, owner, updater', $t1 - $t0, $queryCounts['2_load_relations']];

        // Phase 3: Desert Island Discs (if person)
        $desertIslandDiscsSet = null;
        $queryCounts['3_desert_island_discs'] = 0;
        if ($span->type_id === 'person') {
            DB::flushQueryLog();
            $t0 = microtime(true);
            $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($span);
            $t1 = microtime(true);
            $queryCounts['3_desert_island_discs'] = count(DB::getQueryLog());
            $phases[] = ['3. getDesertIslandDiscsSet (person)', $t1 - $t0, $queryCounts['3_desert_island_discs']];
        } else {
            $phases[] = ['3. getDesertIslandDiscsSet (skip, not person)', 0, 0];
        }

        // Phase 4: Precompute connections for partial (matches controller getConnectionsForSpanShow)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $parentConnections = $span->connectionsAsSubjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan.type', 'parent.type', 'child.type', 'type'])
            ->get()
            ->sortBy(fn ($c) => $c->getEffectiveSortDate());
        $childConnections = $span->connectionsAsObjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan.type', 'parent.type', 'child.type', 'type'])
            ->get()
            ->sortBy(fn ($c) => $c->getEffectiveSortDate());
        $t1 = microtime(true);
        $phases[] = ['4. getConnectionsForSpanShow', $t1 - $t0, count(DB::getQueryLog())];

        // Phase 5: Optional full view render (pass all precomputed data to match controller)
        if ($renderView) {
            $familyData = null;
            if ($span->type_id === 'person') {
                $familyData = [
                    'ancestors' => $span->ancestors(3),
                    'descendants' => $span->descendants(3),
                    'siblings' => $span->siblings(),
                    'unclesAndAunts' => $span->unclesAndAunts(),
                    'cousins' => $span->cousins(),
                    'nephewsAndNieces' => $span->nephewsAndNieces(),
                    'extraNephewsAndNieces' => $span->extraNephewsAndNieces(),
                    'stepParents' => $span->stepParents(),
                    'inLawsAndOutLaws' => $span->inLawsAndOutLaws(),
                    'extraInLawsAndOutLaws' => $span->extraInLawsAndOutLaws(),
                    'childrenInLawsAndOutLaws' => $span->childrenInLawsAndOutLaws(),
                    'grandchildrenInLawsAndOutLaws' => $span->grandchildrenInLawsAndOutLaws(),
                ];
            }
            $story = null;
            try {
                $story = app(\App\Services\ConfigurableStoryGeneratorService::class)->generateStory($span);
            } catch (\Exception $e) {
                $story = ['paragraphs' => [], 'metadata' => [], 'error' => $e->getMessage()];
            }
            // Provide $errors so flash-messages and other shared view vars don't error in console
            $errors = new ViewErrorBag;

            // Share familyData so components that use View::shared('familyData') get it (matches controller)
            View::share('familyData', $familyData);

            DB::flushQueryLog();
            $t0 = microtime(true);
            $viewError = null;
            try {
                view('spans.show', compact('span', 'desertIslandDiscsSet', 'familyData', 'parentConnections', 'childConnections', 'story', 'errors'))->render();
            } catch (\Throwable $e) {
                $viewError = $e;
            }
            $t1 = microtime(true);
            $viewQueryLog = DB::getQueryLog();
            $viewQueries = count($viewQueryLog);
            if ($viewError) {
                $phases[] = ['5. View render (error)', $t1 - $t0, $viewQueries];
                $this->warn('View render threw: ' . $viewError->getMessage());
                $this->line('  at ' . $viewError->getFile() . ':' . $viewError->getLine());
                if ($this->output->isVerbose()) {
                    $this->line($viewError->getTraceAsString());
                }
            } else {
                $phases[] = ['5. View render', $t1 - $t0, $viewQueries];
            }
        }

        // Report table
        $totalTime = 0;
        $totalQueries = 0;
        $rows = [];
        foreach ($phases as [$label, $secs, $queries]) {
            $totalTime += $secs;
            $totalQueries += $queries;
            $rows[] = [$label, number_format($secs * 1000, 1) . ' ms', $queries];
        }
        $this->table(['Phase', 'Time', 'Queries'], $rows);
        $this->newLine();
        $this->info(sprintf('Total: %s ms, %d queries', number_format($totalTime * 1000, 1), $totalQueries));

        if ($showQueries) {
            DB::flushQueryLog();
            // Re-run resolve + load to capture queries again (or we could have stored them per phase)
            Span::where('slug', $slug)->with('type')->first()?->load(['type', 'owner', 'updater']);
            $this->newLine();
            $this->info('Sample queries (resolve + load):');
            foreach (DB::getQueryLog() as $i => $q) {
                $this->line(sprintf('%d. %s', $i + 1, $q['query']));
                $this->line('   Bindings: ' . json_encode($q['bindings']));
            }
        }

        if ($analyzeQueries && $renderView && ! empty($viewQueryLog)) {
            $this->newLine();
            $this->info('View-phase query analysis (most repeated = likely N+1 or duplicates):');
            $normalized = [];
            foreach ($viewQueryLog as $q) {
                $sql = $q['query'];
                // Normalise whitespace so same query with different spacing groups together
                $sql = preg_replace('/\s+/', ' ', trim($sql));
                $normalized[$sql] = ($normalized[$sql] ?? 0) + 1;
            }
            arsort($normalized);
            $top = array_slice($normalized, 0, 25, true);
            $rows = [];
            foreach ($top as $pattern => $count) {
                $short = strlen($pattern) > 120 ? substr($pattern, 0, 117) . '...' : $pattern;
                $rows[] = [$count, $short];
            }
            $this->table(['Count', 'Query pattern'], $rows);
            $unique = count($normalized);
            $this->newLine();
            $this->line(sprintf('Unique patterns: %d | Total queries: %d | If unique was ~%d you would save ~%d queries.', $unique, count($viewQueryLog), (int) ceil($unique / 2), count($viewQueryLog) - $unique));
        }

        return 0;
    }
}
