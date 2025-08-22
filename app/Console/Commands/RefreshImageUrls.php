<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\ImageStorageService;
use Illuminate\Console\Command;

class RefreshImageUrls extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:refresh-urls {--span-id= : Refresh URLs for a specific span ID} {--all : Refresh URLs for all photo spans}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh signed URLs for photo spans stored in R2';

    protected $imageStorageService;

    public function __construct(ImageStorageService $imageStorageService)
    {
        parent::__construct();
        $this->imageStorageService = $imageStorageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $spanId = $this->option('span-id');
        $refreshAll = $this->option('all');

        if ($spanId) {
            $this->refreshSpecificSpan($spanId);
        } elseif ($refreshAll) {
            $this->refreshAllSpans();
        } else {
            $this->error('Please specify either --span-id or --all option');
            return 1;
        }

        return 0;
    }

    protected function refreshSpecificSpan($spanId)
    {
        $span = Span::find($spanId);
        
        if (!$span) {
            $this->error("Span with ID {$spanId} not found");
            return 1;
        }

        if (!isset($span->metadata['subtype']) || $span->metadata['subtype'] !== 'photo') {
            $this->error("Span {$spanId} is not a photo span");
            return 1;
        }

        $this->info("Refreshing URLs for span: {$span->name} (ID: {$span->id})");
        
        try {
            $this->imageStorageService->refreshSpanUrls($span);
            $this->info("Successfully refreshed URLs for span {$span->id}");
        } catch (\Exception $e) {
            $this->error("Failed to refresh URLs for span {$span->id}: " . $e->getMessage());
            return 1;
        }
    }

    protected function refreshAllSpans()
    {
        $this->info("Finding all photo spans...");
        
        $photoSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->upload_source', 'direct_upload')
            ->get();

        $this->info("Found {$photoSpans->count()} photo spans to refresh");

        $bar = $this->output->createProgressBar($photoSpans->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($photoSpans as $span) {
            try {
                $this->imageStorageService->refreshSpanUrls($span);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to refresh span {$span->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("URL refresh completed:");
        $this->info("- Successfully refreshed: {$successCount}");
        $this->info("- Failed: {$errorCount}");
    }
}


