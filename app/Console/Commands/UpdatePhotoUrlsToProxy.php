<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class UpdatePhotoUrlsToProxy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:update-to-proxy {--span-id= : Update URLs for a specific span ID} {--all : Update URLs for all photo spans}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update photo spans to use proxy URLs instead of direct R2 URLs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $spanId = $this->option('span-id');
        $updateAll = $this->option('all');

        if ($spanId) {
            $this->updateSpecificSpan($spanId);
        } elseif ($updateAll) {
            $this->updateAllSpans();
        } else {
            $this->error('Please specify either --span-id or --all option');
            return 1;
        }

        return 0;
    }

    protected function updateSpecificSpan($spanId)
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

        $this->info("Updating URLs for span: {$span->name} (ID: {$span->id})");
        
        try {
            $this->updateSpanUrls($span);
            $this->info("Successfully updated URLs for span {$span->id}");
        } catch (\Exception $e) {
            $this->error("Failed to update URLs for span {$span->id}: " . $e->getMessage());
            return 1;
        }
    }

    protected function updateAllSpans()
    {
        $this->info("Finding all photo spans...");
        
        $photoSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->upload_source', 'direct_upload')
            ->get();

        $this->info("Found {$photoSpans->count()} photo spans to update");

        $bar = $this->output->createProgressBar($photoSpans->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($photoSpans as $span) {
            try {
                $this->updateSpanUrls($span);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to update span {$span->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("URL update completed:");
        $this->info("- Successfully updated: {$successCount}");
        $this->info("- Failed: {$errorCount}");
    }

    protected function updateSpanUrls(Span $span)
    {
        $metadata = $span->metadata ?? [];
        
        // Update URLs to use proxy routes
        $metadata['original_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'original']);
        $metadata['large_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'large']);
        $metadata['medium_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'medium']);
        $metadata['thumbnail_url'] = route('images.proxy', ['spanId' => $span->id, 'size' => 'thumbnail']);
        
        $span->metadata = $metadata;
        $span->save();
    }
}



