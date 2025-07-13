<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\User;
use Illuminate\Console\Command;

class FixPersonalSpanSubtypes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spans:fix-personal-subtypes {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix personal spans to have private_individual subtype';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        // Find all personal spans that are people but don't have the subtype set
        $personalSpans = Span::where('type_id', 'person')
            ->where('is_personal_span', true)
            ->where(function($query) {
                $query->whereRaw("metadata->>'subtype' IS NULL")
                      ->orWhereRaw("metadata->>'subtype' = ''");
            })
            ->get();

        if ($personalSpans->isEmpty()) {
            $this->info('All personal spans already have the correct subtype.');
            return 0;
        }

        $this->info("Found {$personalSpans->count()} personal spans that need subtype fixing:");

        foreach ($personalSpans as $span) {
            $user = User::find($span->owner_id);
            $userName = $user ? $user->name : 'Unknown User';
            
            $this->line("- {$span->name} (owned by {$userName})");
            
            if (!$dryRun) {
                $metadata = $span->metadata ?? [];
                $metadata['subtype'] = 'private_individual';
                $span->metadata = $metadata;
                $span->save();
                
                $this->info("  ✓ Fixed subtype to private_individual");
            }
        }

        if ($dryRun) {
            $this->info("\nThis was a dry run. Run without --dry-run to apply changes.");
        } else {
            $this->info("\nSuccessfully fixed {$personalSpans->count()} personal spans.");
        }

        return 0;
    }
} 