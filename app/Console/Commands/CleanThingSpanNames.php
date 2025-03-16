<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class CleanThingSpanNames extends Command
{
    protected $signature = 'spans:clean-thing-names';
    protected $description = 'Clean thing span names by removing date patterns and trailing spaces';

    public function handle()
    {
        $this->info('Starting to clean thing span names...');

        $thingSpans = Span::where('type_id', 'thing')->get();
        $count = 0;

        foreach ($thingSpans as $span) {
            $oldName = $span->name;
            $newName = $this->cleanName($oldName);

            if ($oldName !== $newName) {
                $span->name = $newName;
                $span->save();
                $this->line("Cleaned: {$oldName} -> {$newName}");
                $count++;
            }
        }

        $this->info("Completed! Cleaned {$count} thing span names.");
    }

    protected function cleanName(string $name): string
    {
        // Remove date patterns (YYYY-MM-DD, YYYY-MM, or YYYY)
        $name = preg_replace('/\s+\d{4}(-\d{2}(-\d{2})?)?$/', '', $name);
        
        // Remove trailing spaces
        return trim($name);
    }
} 