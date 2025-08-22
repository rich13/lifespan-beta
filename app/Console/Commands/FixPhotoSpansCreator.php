<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class FixPhotoSpansCreator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:fix-creator {--span-id= : Fix a specific span ID} {--all : Fix all photo spans}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix photo spans that are missing the creator field';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $spanId = $this->option('span-id');
        $fixAll = $this->option('all');

        if ($spanId) {
            $this->fixSpecificSpan($spanId);
        } elseif ($fixAll) {
            $this->fixAllSpans();
        } else {
            $this->error('Please specify either --span-id or --all option');
            return 1;
        }

        return 0;
    }

    protected function fixSpecificSpan($spanId)
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

        $this->info("Fixing creator for span: {$span->name} (ID: {$span->id})");
        
        try {
            $this->fixSpanCreator($span);
            $this->info("Successfully fixed creator for span {$span->id}");
        } catch (\Exception $e) {
            $this->error("Failed to fix creator for span {$span->id}: " . $e->getMessage());
            return 1;
        }
    }

    protected function fixAllSpans()
    {
        $this->info("Finding all photo spans without creator...");
        
        $photoSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->where(function ($query) {
                $query->whereJsonLength('metadata->creator', 0)
                      ->orWhereNull('metadata->creator');
            })
            ->get();

        $this->info("Found {$photoSpans->count()} photo spans without creator");

        if ($photoSpans->count() === 0) {
            $this->info("No photo spans need fixing!");
            return 0;
        }

        $bar = $this->output->createProgressBar($photoSpans->count());
        $bar->start();

        $successCount = 0;
        $errorCount = 0;

        foreach ($photoSpans as $span) {
            try {
                $this->fixSpanCreator($span);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->newLine();
                $this->error("Failed to fix span {$span->id}: " . $e->getMessage());
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        
        $this->info("Creator fix completed:");
        $this->info("- Successfully fixed: {$successCount}");
        $this->info("- Failed: {$errorCount}");
    }

    protected function fixSpanCreator(Span $span)
    {
        $metadata = $span->metadata ?? [];
        
        // Check if creator is already set
        if (isset($metadata['creator']) && !empty($metadata['creator'])) {
            return; // Already has creator
        }
        
        // Get the owner's personal span
        $owner = $span->owner;
        if (!$owner || !$owner->personalSpan) {
            throw new \Exception("Owner {$span->owner_id} has no personal span");
        }
        
        // Set creator to the owner's personal span ID
        $metadata['creator'] = $owner->personalSpan->id;
        
        $span->metadata = $metadata;
        $span->save();
        
        $this->line("Set creator to {$owner->personalSpan->name} ({$owner->personalSpan->id}) for span {$span->id}");
    }
}
