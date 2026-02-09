<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use App\Support\PrecomputedSpanConnections;
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
        $baselineMemory = memory_get_usage(true);
        $phases[] = ['0. Baseline (start)', 0, 0, $baselineMemory];

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

        // Phase 2: Load type, owner, updater (what Cache::remember closure does)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $span->load(['type', 'owner', 'updater']);
        $t1 = microtime(true);
        $queryCounts['2_load_relations'] = count(DB::getQueryLog());
        $phases[] = ['2. Load type, owner, updater', $t1 - $t0, $queryCounts['2_load_relations'], memory_get_usage(true)];

        // Phase 3: Desert Island Discs (if person)
        $desertIslandDiscsSet = null;
        $queryCounts['3_desert_island_discs'] = 0;
        if ($span->type_id === 'person') {
            DB::flushQueryLog();
            $t0 = microtime(true);
            $desertIslandDiscsSet = Span::getDesertIslandDiscsSet($span);
            $t1 = microtime(true);
            $queryCounts['3_desert_island_discs'] = count(DB::getQueryLog());
            $phases[] = ['3. getDesertIslandDiscsSet (person)', $t1 - $t0, $queryCounts['3_desert_island_discs'], memory_get_usage(true)];
        } else {
            $phases[] = ['3. getDesertIslandDiscsSet (skip, not person)', 0, 0, memory_get_usage(true)];
        }

        // Phase 4: Precompute connections for partial (matches controller getConnectionsForSpanShow eager load)
        DB::flushQueryLog();
        $t0 = microtime(true);
        $parentConnections = $span->connectionsAsSubjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with([
                'connectionSpan.type',
                'connectionSpan.connectionsAsSubject.child.type',
                'connectionSpan.connectionsAsSubject.type',
                'connectionSpan.connectionsAsSubject.connectionSpan',
                'parent.type',
                'child.type',
                'type',
            ])
            ->get()
            ->sortBy(fn ($c) => $c->getEffectiveSortDate());
        $childConnections = $span->connectionsAsObjectWithAccess()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with([
                'connectionSpan.type',
                'connectionSpan.connectionsAsSubject.child.type',
                'connectionSpan.connectionsAsSubject.type',
                'connectionSpan.connectionsAsSubject.connectionSpan',
                'parent.type',
                'child.type',
                'type',
            ])
            ->get()
            ->sortBy(fn ($c) => $c->getEffectiveSortDate());
        $t1 = microtime(true);
        $phases[] = ['4. getConnectionsForSpanShow', $t1 - $t0, count(DB::getQueryLog()), memory_get_usage(true)];

        // Phase 4b: getFamilyDataForSpan (person only) – matches controller cache closure
        $familyData = null;
        $familyQueries = 0;
        if ($span->type_id === 'person') {
            DB::flushQueryLog();
            $t0 = microtime(true);
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
            $t1 = microtime(true);
            $familyQueries = count(DB::getQueryLog());
            $phases[] = ['4b. getFamilyDataForSpan', $t1 - $t0, $familyQueries, memory_get_usage(true)];
        } else {
            $phases[] = ['4b. getFamilyDataForSpan (skip, not person)', 0, 0, memory_get_usage(true)];
        }

        // Phase 4c: generateStory with precomputed connections – matches controller (story uses same connections, fewer queries)
        $precomputedConnections = new PrecomputedSpanConnections($parentConnections, $childConnections);
        $story = null;
        DB::flushQueryLog();
        $t0 = microtime(true);
        try {
            $story = app(\App\Services\ConfigurableStoryGeneratorService::class)->generateStory($span, $precomputedConnections);
        } catch (\Exception $e) {
            $story = ['paragraphs' => [], 'metadata' => [], 'error' => $e->getMessage()];
        }
        $t1 = microtime(true);
        $phases[] = ['4c. generateStory (with precomputed)', $t1 - $t0, count(DB::getQueryLog()), memory_get_usage(true)];

        // Phase 4d: Prepare view data (educationCardData, connectionForSpan, annotatingNotes, bluePlaqueCardData, directorConnectionsByFilmId, enrich familyData) – matches controller
        DB::flushQueryLog();
        $t0 = microtime(true);
        $educationCardData = null;
        if ($span->type_id === 'person') {
            $educationConnections = $precomputedConnections->getParentByType('education')
                ->sortBy(function ($conn) {
                    $parts = $conn->getEffectiveSortDate();
                    $y = $parts[0] ?? PHP_INT_MAX;
                    $m = $parts[1] ?? PHP_INT_MAX;
                    $d = $parts[2] ?? PHP_INT_MAX;
                    return sprintf('%08d-%02d-%02d', $y, $m, $d);
                })->values();
            $connectionSpanIds = $educationConnections->map(fn ($c) => $c->connectionSpan?->id)->filter()->unique()->values()->all();
            $duringBySubject = collect();
            $duringByObject = collect();
            if (! empty($connectionSpanIds)) {
                $allDuring = Connection::where(fn ($q) => $q->whereIn('parent_id', $connectionSpanIds)->orWhereIn('child_id', $connectionSpanIds))
                    ->whereHas('type', fn ($q) => $q->where('type', 'during'))
                    ->with(['child', 'parent'])
                    ->get();
                $duringBySubject = $allDuring->groupBy('parent_id');
                $duringByObject = $allDuring->groupBy('child_id');
            }
            $educationCardData = [
                'connections' => $educationConnections,
                'duringBySubject' => $duringBySubject,
                'duringByObject' => $duringByObject,
            ];
        }
        $connectionForSpan = null;
        if ($span->type_id === 'connection') {
            $connectionForSpan = Connection::where('connection_span_id', $span->id)
                ->with(['parent.type', 'child.type', 'type', 'connectionSpan.type'])
                ->first();
        }
        $user = \Illuminate\Support\Facades\Auth::user();
        $annotatingNotes = Connection::where('type_id', 'annotates')
            ->where('child_id', $span->id)
            ->with(['parent' => function ($q) {
                $q->where('type_id', 'note')->with(['owner.personalSpan']);
            }])
            ->get()
            ->pluck('parent')
            ->filter()
            ->filter(function ($note) use ($user) {
                if (! $note) return false;
                if (! $user) return $note->access_level === 'public';
                if ($note->owner_id === $user->id) return true;
                return $note->isAccessibleBy($user);
            })
            ->unique('id')
            ->values();
        $bluePlaqueCardData = null;
        if ($span->type_id === 'person') {
            $plaqueConns = Connection::where('type_id', 'features')
                ->where('child_id', $span->id)
                ->whereHas('parent', fn ($q) => $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'plaque'))
                ->with(['parent'])
                ->get();
            if ($plaqueConns->isNotEmpty()) {
                $plaque = $plaqueConns->first()->parent;
                $photoConns = Connection::where('type_id', 'features')->where('child_id', $plaque->id)
                    ->whereHas('parent', fn ($q) => $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo'))
                    ->with(['parent'])->get();
                $plaquePhoto = $photoConns->isNotEmpty() ? $photoConns->first()->parent : null;
                $photoUrl = $plaquePhoto
                    ? ($plaquePhoto->metadata['thumbnail_url'] ?? $plaquePhoto->metadata['medium_url'] ?? $plaquePhoto->metadata['large_url'] ?? $plaquePhoto->metadata['original_url'] ?? (isset($plaquePhoto->metadata['filename']) ? route('images.proxy', ['spanId' => $plaquePhoto->id, 'size' => 'thumbnail']) : null))
                    : ($plaque->metadata['main_photo'] ?? $plaque->metadata['thumbnail_url'] ?? null);
                $locConn = $plaque->connectionsAsSubject()->where('type_id', 'located')->with(['child'])->first();
                $bluePlaqueCardData = [
                    'plaque' => $plaque,
                    'photoUrl' => $photoUrl,
                    'locationName' => $locConn && $locConn->child ? $locConn->child->name : null,
                    'plaqueMetadata' => $plaque->metadata ?? [],
                    'plaqueColour' => ($plaque->metadata['colour'] ?? 'blue'),
                    'erectedYear' => $plaque->metadata['erected'] ?? $plaque->start_year,
                ];
            }
        }
        $directorConnectionsByFilmId = collect();
        if ($span->type_id === 'person') {
            $filmConns = $precomputedConnections->getChildByType('features')
                ->filter(fn ($c) => ($p = $c->parent) && $p->type_id === 'thing' && isset($p->metadata['subtype']) && $p->metadata['subtype'] === 'film');
            $filmIds = $filmConns->pluck('parent_id')->unique()->filter()->values()->all();
            if (! empty($filmIds)) {
                $directorConnectionsByFilmId = Connection::where('type_id', 'created')
                    ->whereIn('child_id', $filmIds)
                    ->with('parent')
                    ->get()
                    ->groupBy('child_id')
                    ->map(fn ($conns) => $conns->first());
            }
        }
        if ($familyData !== null) {
            $descendants = $familyData['descendants'] ?? collect();
            $childrenForGrouped = $descendants->filter(fn ($item) => $item['generation'] === 1)->pluck('span');
            $childIdsForGrouped = $childrenForGrouped->pluck('id')->all();
            $otherParentConnectionsPrecomputed = ! empty($childIdsForGrouped)
                ? Connection::where('type_id', 'family')->whereIn('child_id', $childIdsForGrouped)->where('parent_id', '!=', $span->id)->with('parent')->get()
                : collect();
            $otherParentSpans = $otherParentConnectionsPrecomputed->pluck('parent')->unique('id')->filter();
            $allSpans = ($familyData['ancestors'] ?? collect())->pluck('span')
                ->merge($descendants->pluck('span'))
                ->merge($familyData['siblings'] ?? collect())->merge($familyData['unclesAndAunts'] ?? collect())
                ->merge($familyData['cousins'] ?? collect())->merge($familyData['nephewsAndNieces'] ?? collect())
                ->merge($familyData['extraNephewsAndNieces'] ?? collect())->merge($familyData['stepParents'] ?? collect())
                ->merge($familyData['inLawsAndOutLaws'] ?? collect())->merge($familyData['extraInLawsAndOutLaws'] ?? collect())
                ->merge($familyData['childrenInLawsAndOutLaws'] ?? collect())->merge($familyData['grandchildrenInLawsAndOutLaws'] ?? collect())
                ->merge($otherParentSpans)
                ->filter(fn ($s) => $s && $s->type_id === 'person')->unique('id');
            $personIds = $allSpans->pluck('id')->filter()->unique()->values()->all();
            $photoConnections = collect();
            $parentConnectionsForMap = collect();
            $parentsMap = collect();
            if (! empty($personIds)) {
                $photoConnections = Connection::where('type_id', 'features')
                    ->whereIn('child_id', $personIds)
                    ->whereHas('parent', fn ($q) => $q->where('type_id', 'thing')->whereJsonContains('metadata->subtype', 'photo'))
                    ->with(['parent'])->get()->groupBy('child_id')->map(fn ($c) => $c->first());
                $parentConnectionsForMap = Connection::where('type_id', 'family')
                    ->whereIn('child_id', $personIds)
                    ->whereHas('parent', fn ($q) => $q->where('type_id', 'person'))
                    ->with(['parent'])->get()->groupBy('child_id');
                foreach ($personIds as $pid) {
                    $conns = $parentConnectionsForMap->get($pid);
                    if ($conns && $conns->isNotEmpty()) {
                        $parentSpans = $conns->map(fn ($c) => $c->parent)->filter()->values();
                        if ($parentSpans->isNotEmpty()) {
                            $parentsMap->put($pid, $parentSpans);
                        }
                    }
                }
            }
            $familyData['otherParentConnectionsPrecomputed'] = $otherParentConnectionsPrecomputed;
            $familyData['photoConnections'] = $photoConnections;
            $familyData['parentConnectionsForMap'] = $parentConnectionsForMap;
            $familyData['parentsMap'] = $parentsMap;
        }
        $t1 = microtime(true);
        $phases[] = ['4d. Prepare view data (precomputed + education during + connectionForSpan + annotatingNotes + bluePlaque + directors + family batches)', $t1 - $t0, count(DB::getQueryLog()), memory_get_usage(true)];

        // Phase 5: Optional full view render (pass all precomputed data to match controller)
        if ($renderView) {
            // Provide $errors so flash-messages and other shared view vars don't error in console
            $errors = new ViewErrorBag;

            // Share familyData so components that use View::shared('familyData') get it (matches controller)
            View::share('familyData', $familyData);

            DB::flushQueryLog();
            $t0 = microtime(true);
            $viewError = null;
            try {
                view('spans.show', compact('span', 'desertIslandDiscsSet', 'familyData', 'parentConnections', 'childConnections', 'story', 'errors', 'precomputedConnections', 'educationCardData', 'connectionForSpan', 'annotatingNotes', 'bluePlaqueCardData', 'directorConnectionsByFilmId'))->render();
            } catch (\Throwable $e) {
                $viewError = $e;
            }
            $t1 = microtime(true);
            $viewQueryLog = DB::getQueryLog();
            $viewQueries = count($viewQueryLog);
            if ($viewError) {
                $phases[] = ['5. View render (error)', $t1 - $t0, $viewQueries, memory_get_usage(true)];
                $this->warn('View render threw: ' . $viewError->getMessage());
                $this->line('  at ' . $viewError->getFile() . ':' . $viewError->getLine());
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
                number_format($secs * 1000, 1) . ' ms',
                $queries,
                number_format($memBytes / 1024 / 1024, 1) . ' MB',
            ];
        }
        $this->table(['Phase', 'Time', 'Queries', 'Memory'], $rows);
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
