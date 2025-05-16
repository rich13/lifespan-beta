<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\DB;

class FixPersonalSpans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-personal-spans {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix personal span issues by ensuring each user only has one personal span';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting personal span diagnosis and repair...');
        
        $dryRun = $this->option('dry-run');
        if ($dryRun) {
            $this->warn('DRY RUN: No changes will be made');
        }
        
        // Step 1: Find users with multiple personal spans
        $this->info('Checking for users with multiple personal spans...');
        $usersWithMultipleSpans = DB::select("
            SELECT owner_id, COUNT(*) as count 
            FROM spans 
            WHERE is_personal_span = true 
            GROUP BY owner_id 
            HAVING COUNT(*) > 1
        ");
        
        if (count($usersWithMultipleSpans) > 0) {
            $this->warn('Found ' . count($usersWithMultipleSpans) . ' users with multiple personal spans');
            
            foreach ($usersWithMultipleSpans as $userRecord) {
                $user = User::find($userRecord->owner_id);
                
                if (!$user) {
                    $this->error('User with ID ' . $userRecord->owner_id . ' not found but has personal spans');
                    continue;
                }
                
                $this->info('Fixing multiple personal spans for user: ' . $user->email);
                
                // Get all personal spans for this user
                $personalSpans = Span::where('owner_id', $user->owner_id)
                    ->where('is_personal_span', true)
                    ->orderBy('updated_at', 'desc')
                    ->get();
                
                $this->table(
                    ['ID', 'Name', 'Is Personal', 'User Link Status'],
                    $personalSpans->map(function ($span) use ($user) {
                        return [
                            $span->id,
                            $span->name,
                            $span->is_personal_span ? 'Yes' : 'No',
                            $user->personal_span_id === $span->id ? 'Current' : 'Not Linked'
                        ];
                    })
                );
                
                // Keep only the most recently updated one
                $keepSpan = $personalSpans->first();
                $otherSpans = $personalSpans->slice(1);
                
                $this->info('Keeping most recent personal span: ' . $keepSpan->name . ' (' . $keepSpan->id . ')');
                
                if (!$dryRun) {
                    // Mark other spans as non-personal
                    foreach ($otherSpans as $span) {
                        $this->info('Marking span as non-personal: ' . $span->name . ' (' . $span->id . ')');
                        $span->is_personal_span = false;
                        $span->save();
                    }
                    
                    // Ensure user is linked to the correct span
                    if ($user->personal_span_id !== $keepSpan->id) {
                        $this->info('Updating user personal_span_id from ' . ($user->personal_span_id ?? 'null') . ' to ' . $keepSpan->id);
                        $user->personal_span_id = $keepSpan->id;
                        $user->save();
                    }
                }
            }
        } else {
            $this->info('No users with multiple personal spans found.');
        }
        
        // Step 2: Find users with missing personal spans
        $this->info('Checking for users with missing personal spans...');
        $usersWithMissingSpans = User::whereNull('personal_span_id')->get();
        
        if ($usersWithMissingSpans->count() > 0) {
            $this->warn('Found ' . $usersWithMissingSpans->count() . ' users with missing personal spans');
            
            foreach ($usersWithMissingSpans as $user) {
                $this->info('Checking user: ' . $user->email);
                
                // Check if user has a personal span that's not linked
                $personalSpan = Span::where('owner_id', $user->id)
                    ->where('is_personal_span', true)
                    ->first();
                
                if ($personalSpan) {
                    $this->info('Found personal span that is not linked: ' . $personalSpan->name . ' (' . $personalSpan->id . ')');
                    
                    if (!$dryRun) {
                        $user->personal_span_id = $personalSpan->id;
                        $user->save();
                        $this->info('Linked user to personal span');
                    }
                } else {
                    $this->warn('No personal span found for user: ' . $user->email);
                    
                    // Ask if we should create a personal span
                    if ($this->confirm('Create a new personal span for this user?')) {
                        if (!$dryRun) {
                            $span = new Span();
                            $span->name = $user->email;
                            $span->type_id = 'person';
                            $span->start_year = now()->year;
                            $span->owner_id = $user->id;
                            $span->updater_id = $user->id;
                            $span->access_level = 'private';
                            $span->is_personal_span = true;
                            $span->save();
                            
                            $user->personal_span_id = $span->id;
                            $user->save();
                            
                            $this->info('Created and linked new personal span: ' . $span->id);
                        } else {
                            $this->info('Would create personal span (dry run)');
                        }
                    }
                }
            }
        } else {
            $this->info('No users with missing personal spans found.');
        }
        
        // Step 3: Check for users with invalid personal span links
        $this->info('Checking for users with invalid personal span links...');
        $usersWithInvalidLinks = User::whereNotNull('personal_span_id')
            ->get()
            ->filter(function ($user) {
                // Check if personal span exists and belongs to user
                $span = Span::find($user->personal_span_id);
                return !$span || $span->owner_id !== $user->id || !$span->is_personal_span;
            });
        
        if ($usersWithInvalidLinks->count() > 0) {
            $this->warn('Found ' . $usersWithInvalidLinks->count() . ' users with invalid personal span links');
            
            foreach ($usersWithInvalidLinks as $user) {
                $span = Span::find($user->personal_span_id);
                
                if (!$span) {
                    $this->error('User ' . $user->email . ' linked to non-existent span: ' . $user->personal_span_id);
                } else {
                    $this->error('User ' . $user->email . ' linked to invalid span: ' . $span->name . ' (owner: ' . $span->owner_id . ', is_personal: ' . ($span->is_personal_span ? 'true' : 'false') . ')');
                }
                
                // Find a valid personal span
                $correctSpan = Span::where('owner_id', $user->id)
                    ->where('is_personal_span', true)
                    ->first();
                
                if ($correctSpan) {
                    $this->info('Found correct personal span: ' . $correctSpan->name . ' (' . $correctSpan->id . ')');
                    
                    if (!$dryRun) {
                        $user->personal_span_id = $correctSpan->id;
                        $user->save();
                        $this->info('Updated user to use correct personal span');
                    }
                } else {
                    $this->warn('No valid personal span found for user: ' . $user->email);
                    
                    // Ask if we should reset the personal_span_id
                    if ($this->confirm('Reset the personal_span_id to null?')) {
                        if (!$dryRun) {
                            $user->personal_span_id = null;
                            $user->save();
                            $this->info('Reset personal_span_id to null');
                        } else {
                            $this->info('Would reset personal_span_id to null (dry run)');
                        }
                    }
                }
            }
        } else {
            $this->info('No users with invalid personal span links found.');
        }
        
        $this->info('Personal span diagnosis and repair complete.');
        
        return Command::SUCCESS;
    }
}
