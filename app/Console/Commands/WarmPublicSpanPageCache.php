<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Warm the public span full-page cache by issuing internal GET requests
 * to each public span show URL. TTL is configurable (default 1 year);
 * invalidation is per-span on update. Run on deploy (see docker/prod/entrypoint.sh)
 * to repopulate after cache:clear, or manually. Use --limit for testing.
 */
class WarmPublicSpanPageCache extends Command
{
    protected $signature = 'cache:warm-public-span-pages
                            {--limit= : Maximum number of spans to warm (default: all)}
                            {--locale= : Locale to use (default: app locale)}';

    protected $description = 'Warm the full-page cache for all public span show pages';

    public function handle(HttpKernel $kernel): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $locale = $this->option('locale');

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        $query = Span::query()
            ->where('access_level', 'public')
            ->whereNotNull('slug')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $spans = $query->get(['id', 'slug']);
        $total = $spans->count();

        if ($total === 0) {
            $this->info('No public spans with slugs found. Nothing to warm.');

            return 0;
        }

        $this->info("Warming public span page cache for {$total} span(s)...");
        $progressBar = $this->output->createProgressBar($total);
        $progressBar->start();

        $warmed = 0;
        $skipped = 0;

        foreach ($spans as $span) {
            try {
                $path = '/spans/' . $span->slug;
                $request = Request::create($path, 'GET');
                $request->headers->set('Accept', 'text/html');

                $kernel->handle($request);

                $warmed++;
            } catch (\Throwable $e) {
                Log::warning('Failed to warm public span page cache', [
                    'span_id' => $span->id,
                    'slug' => $span->slug,
                    'error' => $e->getMessage(),
                ]);
                $skipped++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Done. Warmed: {$warmed}, skipped: {$skipped}.");

        return 0;
    }
}
