<?php

namespace App\Jobs;

use App\Models\Span;
use App\Services\MusicBrainzCoverArtService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchAlbumCoverArtJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $tries = 3;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $spanId
    ) {}

    public function uniqueId(): string
    {
        return $this->spanId;
    }

    public function handle(): void
    {
        $span = Span::find($this->spanId);
        if (!$span || !$span->needsCoverArtFetch()) {
            return;
        }

        try {
            MusicBrainzCoverArtService::getInstance()->fetchAndStoreForSpan($span);
        } catch (\Throwable $e) {
            Log::warning('FetchAlbumCoverArtJob failed', [
                'span_id' => $this->spanId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
