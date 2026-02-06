<?php

namespace App\Jobs;

use App\Models\Span;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Rewarm the public span full-page cache for a given set of span IDs.
 * Only warms spans that are public and have a slug. Used after invalidation
 * so updated spans (and their connected neighbours) get fresh cache entries.
 */
class WarmPublicSpanPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;

    /**
     * @param array<int, string> $spanIds
     */
    public function __construct(
        private readonly array $spanIds
    ) {}

    public function handle(HttpKernel $kernel): void
    {
        if (empty($this->spanIds)) {
            return;
        }

        $spans = Span::query()
            ->whereIn('id', $this->spanIds)
            ->where('access_level', 'public')
            ->whereNotNull('slug')
            ->get(['id', 'slug']);

        foreach ($spans as $span) {
            try {
                $request = Request::create('/spans/' . $span->slug, 'GET');
                $request->headers->set('Accept', 'text/html');
                $kernel->handle($request);
            } catch (\Throwable $e) {
                Log::warning('WarmPublicSpanPagesJob: failed to warm span', [
                    'span_id' => $span->id,
                    'slug' => $span->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
