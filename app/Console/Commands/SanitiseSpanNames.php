<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class SanitiseSpanNames extends Command
{
    protected $signature = 'spans:sanitise-names {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Replace line breaks and other whitespace in span names with single spaces';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $spans = Span::query()
            ->where(function ($query) {
                $query->where('name', 'like', "%\n%")
                    ->orWhere('name', 'like', "%\r%");
            })
            ->get();

        if ($spans->isEmpty()) {
            $this->info('No span names contain line breaks.');

            return self::SUCCESS;
        }

        $this->info("Found {$spans->count()} span(s) with line breaks in the name.");

        $updatedCount = 0;

        foreach ($spans as $span) {
            $oldName = $span->getAttributes()['name'] ?? $span->name;
            $newName = Span::sanitiseSingleLineText($oldName);

            if ($oldName === $newName) {
                continue;
            }

            $this->line("{$span->id}: {$this->preview($oldName)} -> {$newName}");

            if (! $dryRun) {
                $span->name = $oldName;
                $span->saveQuietly();
            }

            $updatedCount++;
        }

        $this->info($dryRun
            ? "Would sanitise {$updatedCount} span name(s)."
            : "Sanitised {$updatedCount} span name(s).");

        return self::SUCCESS;
    }

    private function preview(string $name): string
    {
        return str_replace(["\r\n", "\r", "\n"], '↵', $name);
    }
}
