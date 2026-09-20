<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Support\SpanShowPageLoader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExperimentalSpanController extends Controller
{
    /**
     * First-hit lab for span-show cards. Uses the same SpanShowPageLoader as
     * the main page so dump slicing stays aligned; persistent cache is bypassed
     * so timings show first-hit cost, not a warmed Redis.
     */
    public function show(string $spanSlug): Response|RedirectResponse
    {
        $controllerStartedAt = microtime(true);
        $persistentCacheDriver = config('cache.default');
        Cache::purge('experimental_span');
        Cache::setDefaultDriver('experimental_span');

        try {
            DB::enableQueryLog();
            DB::flushQueryLog();

            $spanModel = Span::query()->where('slug', $spanSlug)->first();

            if (! $spanModel && Str::isUuid($spanSlug)) {
                $spanModel = Span::query()->where('id', $spanSlug)->first();
            }

            if (! $spanModel) {
                abort(404);
            }

            if ($redirect = $this->authoriseExperimentalView($spanModel)) {
                return $redirect;
            }

            $page = app(SpanShowPageLoader::class)->load($spanModel, includePageExtras: false);

            $html = view('spans.experimental.show', [
                'span' => $spanModel,
                'displayTitleWithDates' => $spanModel->getDisplayTitleWithDates(),
                'story' => $page->story,
                'spanShowContext' => $page->context,
                'connectionForSpan' => $page->connectionForSpan,
                'bootMs' => '__BOOT_MS__',
                'controllerMs' => '__CONTROLLER_MS__',
                'queryCount' => '__QUERY_COUNT__',
                'queryLabel' => '__QUERY_LABEL__',
                'totalMs' => '__TOTAL_MS__',
                'querySummaryHtml' => '__QUERY_SUMMARY_HTML__',
            ])->render();

            $queryLog = DB::getQueryLog();
            $queryCount = count($queryLog);
            $finishedAt = microtime(true);
            $bootMs = defined('LARAVEL_START')
                ? round(($controllerStartedAt - LARAVEL_START) * 1000, 1)
                : null;
            $controllerMs = round(($finishedAt - $controllerStartedAt) * 1000, 1);
            $totalMs = defined('LARAVEL_START')
                ? round(($finishedAt - LARAVEL_START) * 1000, 1)
                : null;

            $html = str_replace(
                ['__BOOT_MS__', '__CONTROLLER_MS__', '__QUERY_COUNT__', '__QUERY_LABEL__', '__TOTAL_MS__', '__QUERY_SUMMARY_HTML__'],
                [
                    $this->formatTimingMs($bootMs),
                    $this->formatTimingMs($controllerMs),
                    (string) $queryCount,
                    $queryCount === 1 ? 'query' : 'queries',
                    $this->formatTimingMs($totalMs),
                    $this->renderQuerySummaryHtml($queryLog),
                ],
                $html
            );

            return response($html, 200)
                ->header('Server-Timing', implode(', ', array_filter([
                    $bootMs !== null ? 'boot;desc="laravel boot";dur='.$bootMs : null,
                    'controller;desc="experimental span";dur='.$controllerMs,
                    $totalMs !== null ? 'total;desc="request";dur='.$totalMs : null,
                ])));
        } finally {
            Cache::setDefaultDriver($persistentCacheDriver);
            Cache::purge('experimental_span');
        }
    }

    /**
     * Public spans are visible to anyone. Private/shared spans are owner or admin only
     * so this page does not query the permissions table.
     */
    private function authoriseExperimentalView(Span $span): ?RedirectResponse
    {
        if ($span->access_level === 'public') {
            return null;
        }

        $user = Auth::user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($span->owner_id === $user->id || $user->getEffectiveAdminStatus()) {
            return null;
        }

        abort(403);
    }

    private function formatTimingMs(?float $milliseconds): string
    {
        return $milliseconds === null ? '—' : (string) $milliseconds;
    }

    /**
     * Group the query log by SQL shape so repeated lookups are obvious.
     *
     * @param  array<int, array{query: string, bindings?: array, time?: float}>  $queryLog
     */
    private function renderQuerySummaryHtml(array $queryLog): string
    {
        $counts = [];
        foreach ($queryLog as $entry) {
            $sql = $entry['query'] ?? '';
            $counts[$sql] = ($counts[$sql] ?? 0) + 1;
        }
        arsort($counts);

        $repeats = array_filter($counts, fn (int $count): bool => $count > 1);
        $repeatExtra = array_sum($repeats) - count($repeats);

        $lines = [
            '<p class="small text-muted mb-2">'.e(count($counts).' query shapes · '.count($repeats).' repeated · '.$repeatExtra.' extra queries from repeats').'</p>',
        ];

        if ($repeats === []) {
            $lines[] = '<p class="small mb-0">No repeated query shapes.</p>';

            return implode('', $lines);
        }

        $lines[] = '<table class="table table-sm small mb-0"><thead><tr><th>Times</th><th>Query shape</th></tr></thead><tbody>';
        foreach ($repeats as $sql => $count) {
            $lines[] = '<tr><td>'.e((string) $count).'</td><td><code>'.e(Str::limit($sql, 220)).'</code></td></tr>';
        }
        $lines[] = '</tbody></table>';

        return implode('', $lines);
    }
}
