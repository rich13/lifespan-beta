<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Support\SpanShowPageLoader;
use Illuminate\Console\Command;
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

    protected $description = 'Profile the span show page: time each phase (resolve, cached family tree, SpanShowPageLoader, optional view render) and report DB query count.';

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
        $baselineMemory = memory_get_usage(true);
        $phases[] = ['0. Baseline (start)', 0, 0, $baselineMemory];

        $loader = app(SpanShowPageLoader::class);

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
        $phases[] = ['1. Resolve span (route binding)', $t1 - $t0, $queryCounts['1_resolve_span'], memory_get_usage(true)];

        if (! $span) {
            $this->error('Span not found.');

            return 1;
        }

        // Phase 2: Load type, owner, updater (what the Redis cache closure does)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $span->load(['type', 'owner', 'updater']);
        $t1 = microtime(true);
        $queryCounts['2_load_relations'] = count(DB::getQueryLog());
        $phases[] = ['2. Load type, owner, updater', $t1 - $t0, $queryCounts['2_load_relations'], memory_get_usage(true)];

        // Phase 3: Family-tree walk (cached with the span row; connections stay request-scoped)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $familyData = $loader->familyTree($span);
        $t1 = microtime(true);
        $phases[] = [
            $span->type_id === 'person'
                ? '3. familyTree (cached with span row)'
                : '3. familyTree (skip, not person)',
            $t1 - $t0,
            count(DB::getQueryLog()),
            memory_get_usage(true),
        ];

        // Phase 4: Shared loader — dump, enrich, story, extras
        DB::flushQueryLog();
        $t0 = microtime(true);
        $page = $loader->load($span, $familyData);
        $t1 = microtime(true);
        $phases[] = ['4. SpanShowPageLoader::load', $t1 - $t0, count(DB::getQueryLog()), memory_get_usage(true)];

        $viewData = $page->viewData();

        // Phase 5: Optional full view render (same variables as SpanController::show)
        if ($renderView) {
            $errors = new ViewErrorBag;
            View::share('familyData', $viewData['familyData']);

            DB::flushQueryLog();
            $t0 = microtime(true);
            $viewError = null;
            try {
                view('spans.show', array_merge($viewData, compact('errors')))->render();
            } catch (\Throwable $e) {
                $viewError = $e;
            }
            $t1 = microtime(true);
            $viewQueryLog = DB::getQueryLog();
            $viewQueries = count($viewQueryLog);
            if ($viewError) {
                $phases[] = ['5. View render (error)', $t1 - $t0, $viewQueries, memory_get_usage(true)];
                $this->warn('View render threw: '.$viewError->getMessage());
                $this->line('  at '.$viewError->getFile().':'.$viewError->getLine());
                if ($this->output->isVerbose()) {
                    $this->line($viewError->getTraceAsString());
                }
            } else {
                $phases[] = ['5. View render', $t1 - $t0, $viewQueries, memory_get_usage(true)];
            }
        }

        // Report table
        $totalTime = 0;
        $totalQueries = 0;
        $rows = [];
        foreach ($phases as [$label, $secs, $queries, $memBytes]) {
            $totalTime += $secs;
            $totalQueries += $queries;
            $rows[] = [
                $label,
                number_format($secs * 1000, 1).' ms',
                $queries,
                number_format($memBytes / 1024 / 1024, 1).' MB',
            ];
        }
        $this->table(['Phase', 'Time', 'Queries', 'Memory'], $rows);
        $this->newLine();
        $this->info(sprintf('Total: %s ms, %d queries', number_format($totalTime * 1000, 1), $totalQueries));

        if ($showQueries) {
            DB::flushQueryLog();
            Span::where('slug', $slug)->with('type')->first()?->load(['type', 'owner', 'updater']);
            $this->newLine();
            $this->info('Sample queries (resolve + load):');
            foreach (DB::getQueryLog() as $i => $q) {
                $this->line(sprintf('%d. %s', $i + 1, $q['query']));
                $this->line('   Bindings: '.json_encode($q['bindings']));
            }
        }

        if ($analyzeQueries && $renderView && ! empty($viewQueryLog)) {
            $this->newLine();
            $this->info('View-phase query analysis (most repeated = likely N+1 or duplicates):');
            $normalized = [];
            foreach ($viewQueryLog as $q) {
                $sql = $q['query'];
                $sql = preg_replace('/\s+/', ' ', trim($sql));
                $normalized[$sql] = ($normalized[$sql] ?? 0) + 1;
            }
            arsort($normalized);
            $top = array_slice($normalized, 0, 25, true);
            $rows = [];
            foreach ($top as $pattern => $count) {
                $short = strlen($pattern) > 120 ? substr($pattern, 0, 117).'...' : $pattern;
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
