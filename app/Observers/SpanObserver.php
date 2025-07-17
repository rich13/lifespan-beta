<?php

namespace App\Observers;

use App\Models\Span;
use App\Services\SlackNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SpanObserver
{
    protected SlackNotificationService $slackService;

    public function __construct(SlackNotificationService $slackService)
    {
        $this->slackService = $slackService;
    }

    /**
     * Handle the Span "saving" event.
     */
    public function saving(Span $span): void
    {
        // Check if this is a personal span being created/updated
        if ($span->is_personal_span) {
            // Ensure no other spans for the same owner are marked as personal spans
            $existingPersonalSpans = Span::where('owner_id', $span->owner_id)
                ->where('is_personal_span', true)
                ->where('id', '!=', $span->id)
                ->get();
                
            if ($existingPersonalSpans->count() > 0) {
                // Existing personal spans found, mark them as non-personal
                foreach ($existingPersonalSpans as $existingSpan) {
                    Log::warning('Multiple personal spans detected for user, fixing', [
                        'user_id' => $span->owner_id,
                        'existing_span_id' => $existingSpan->id,
                        'new_span_id' => $span->id
                    ]);
                    
                    $existingSpan->is_personal_span = false;
                    $existingSpan->saveQuietly(); // Save without triggering observers again
                }
            }
            
            // Set subtype to private_individual for personal spans
            if ($span->type_id === 'person') {
                $metadata = $span->metadata ?? [];
                $metadata['subtype'] = 'private_individual';
                $span->metadata = $metadata;
            }
        }
        
        // Handle public figure access level
        if ($span->type_id === 'person') {
            $metadata = $span->metadata ?? [];
            $subtype = $metadata['subtype'] ?? null;
            
            // If this is a public figure, ensure it has public access
            if ($subtype === 'public_figure' && $span->access_level !== 'public') {
                Log::info('Public figure detected, setting access level to public', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'old_access_level' => $span->access_level,
                    'new_access_level' => 'public'
                ]);
                
                $span->access_level = 'public';
            }
        }
    }

    /**
     * Handle the Span "saved" event.
     */
    public function saved(Span $span): void
    {
        // If this is a personal span, update the user's personal_span_id
        if ($span->is_personal_span) {
            DB::table('users')
                ->where('id', $span->owner_id)
                ->update(['personal_span_id' => $span->id]);
        }
        
        // Handle public figure connection access
        if ($span->type_id === 'person') {
            $metadata = $span->metadata ?? [];
            $subtype = $metadata['subtype'] ?? null;
            
            // If this is a public figure, ensure all its connections are public
            if ($subtype === 'public_figure' && $span->access_level === 'public') {
                $this->makePublicFigureConnectionsPublic($span);
            }
        }
        
        // Handle family connection end dates when a person dies
        if ($span->type_id === 'person' && $span->end_year && $span->wasChanged('end_year')) {
            $this->endFamilyConnectionsOnDeath($span);
        }
        
        // Sync family connection dates when birth/death dates change
        if ($span->type_id === 'person' && 
            ($span->wasChanged('start_year') || $span->wasChanged('start_month') || $span->wasChanged('start_day') ||
             $span->wasChanged('end_year') || $span->wasChanged('end_month') || $span->wasChanged('end_day'))) {
            $this->syncFamilyConnectionDates($span);
        }
    }

    /**
     * Handle the Span "created" event.
     */
    public function created(Span $span): void
    {
        // Send Slack notification for span creation
        $this->slackService->notifySpanCreated($span);
    }

    /**
     * Handle the Span "updated" event.
     */
    public function updated(Span $span): void
    {
        // Send Slack notification for span updates
        $changes = $span->getDirty();
        $this->slackService->notifySpanUpdated($span, $changes);
    }
    
    /**
     * Make all connections for a public figure public
     */
    private function makePublicFigureConnectionsPublic(Span $span): void
    {
        // Get all connections where this span is the subject (parent)
        $subjectConnections = \App\Models\Connection::where('parent_id', $span->id)->get();
        
        // Get all connections where this span is the object (child)
        $objectConnections = \App\Models\Connection::where('child_id', $span->id)->get();
        
        $allConnections = $subjectConnections->merge($objectConnections);
        
        foreach ($allConnections as $connection) {
            // Get the connection span (the span that represents this connection)
            if ($connection->connectionSpan) {
                $connectionSpan = $connection->connectionSpan;
                
                // If the connection span is not public, make it public
                if ($connectionSpan->access_level !== 'public') {
                    Log::info('Making public figure connection public', [
                        'public_figure_id' => $span->id,
                        'public_figure_name' => $span->name,
                        'connection_id' => $connection->id,
                        'connection_span_id' => $connectionSpan->id,
                        'old_access_level' => $connectionSpan->access_level,
                        'new_access_level' => 'public'
                    ]);
                    
                    $connectionSpan->access_level = 'public';
                    $connectionSpan->saveQuietly(); // Save without triggering observers
                    
                    // Clear timeline caches for the connection span
                    $connectionSpan->clearAllTimelineCaches();
                }
            }
        }
        
        // Clear timeline caches for the public figure
        $span->clearAllTimelineCaches();
    }
    
    /**
     * End family connections when a person dies
     */
    private function endFamilyConnectionsOnDeath(Span $span): void
    {
        $deathYear = $span->end_year;
        $deathMonth = $span->end_month;
        $deathDay = $span->end_day;
        
        Log::info('Person died, ending family connections', [
            'person_id' => $span->id,
            'person_name' => $span->name,
            'death_year' => $deathYear,
            'death_month' => $deathMonth,
            'death_day' => $deathDay
        ]);
        
        // Get all family connections where this person is involved
        $familyConnections = \App\Models\Connection::where('type_id', 'family')
            ->where(function ($query) use ($span) {
                $query->where('parent_id', $span->id)
                      ->orWhere('child_id', $span->id);
            })
            ->with('connectionSpan')
            ->get();
        
        foreach ($familyConnections as $connection) {
            if ($connection->connectionSpan) {
                $connectionSpan = $connection->connectionSpan;
                
                // Only update if the connection doesn't already have an end date
                // or if the current end date is after the person's death
                if (!$connectionSpan->end_year || $connectionSpan->end_year > $deathYear) {
                    Log::info('Ending family connection on person\'s death', [
                        'connection_id' => $connection->id,
                        'connection_span_id' => $connectionSpan->id,
                        'old_end_year' => $connectionSpan->end_year,
                        'new_end_year' => $deathYear,
                        'person_name' => $span->name
                    ]);
                    
                    $connectionSpan->end_year = $deathYear;
                    $connectionSpan->end_month = $deathMonth;
                    $connectionSpan->end_day = $deathDay;
                    $connectionSpan->saveQuietly(); // Save without triggering observers
                    
                    // Clear timeline caches for the connection span
                    $connectionSpan->clearAllTimelineCaches();
                }
            }
        }
        
        // Also handle relationship connections (spouses, etc.)
        $relationshipConnections = \App\Models\Connection::where('type_id', 'relationship')
            ->where(function ($query) use ($span) {
                $query->where('parent_id', $span->id)
                      ->orWhere('child_id', $span->id);
            })
            ->with('connectionSpan')
            ->get();
        
        foreach ($relationshipConnections as $connection) {
            if ($connection->connectionSpan) {
                $connectionSpan = $connection->connectionSpan;
                
                // Only update if the connection doesn't already have an end date
                // or if the current end date is after the person's death
                if (!$connectionSpan->end_year || $connectionSpan->end_year > $deathYear) {
                    Log::info('Ending relationship connection on person\'s death', [
                        'connection_id' => $connection->id,
                        'connection_span_id' => $connectionSpan->id,
                        'old_end_year' => $connectionSpan->end_year,
                        'new_end_year' => $deathYear,
                        'person_name' => $span->name
                    ]);
                    
                    $connectionSpan->end_year = $deathYear;
                    $connectionSpan->end_month = $deathMonth;
                    $connectionSpan->end_day = $deathDay;
                    $connectionSpan->saveQuietly(); // Save without triggering observers
                    
                    // Clear timeline caches for the connection span
                    $connectionSpan->clearAllTimelineCaches();
                }
            }
        }
        
        // Clear timeline caches for the person who died
        $span->clearAllTimelineCaches();
    }
    
    /**
     * Sync family connection dates when birth/death dates change
     */
    private function syncFamilyConnectionDates(Span $span): void
    {
        Log::info('Syncing family connection dates for person', [
            'person_id' => $span->id,
            'person_name' => $span->name,
            'birth_date' => $span->start_year ? "{$span->start_year}-{$span->start_month}-{$span->start_day}" : 'unknown',
            'death_date' => $span->end_year ? "{$span->end_year}-{$span->end_month}-{$span->end_day}" : 'unknown'
        ]);
        
        // Get all family connections where this person is involved
        $familyConnections = \App\Models\Connection::where('type_id', 'family')
            ->where(function ($query) use ($span) {
                $query->where('parent_id', $span->id)
                      ->orWhere('child_id', $span->id);
            })
            ->with(['subject', 'object', 'connectionSpan'])
            ->get();
        
        foreach ($familyConnections as $connection) {
            $this->updateConnectionDates($connection, $span);
        }
        
        // Clear timeline caches for the person
        $span->clearAllTimelineCaches();
    }
    
    /**
     * Update connection dates based on the updated person
     */
    private function updateConnectionDates(\App\Models\Connection $connection, Span $updatedSpan): void
    {
        $span1 = $connection->subject;
        $span2 = $connection->object;
        
        if (!$span1 || !$span2) {
            return;
        }
        
        // Determine which span is the updated one
        $otherSpan = ($span1->id === $updatedSpan->id) ? $span2 : $span1;
        
        // Get birth and death dates
        $span1Birth = $this->getBirthDate($span1);
        $span1Death = $this->getDeathDate($span1);
        $span2Birth = $this->getBirthDate($span2);
        $span2Death = $this->getDeathDate($span2);
        
        $suggestedStartDate = null;
        $suggestedEndDate = null;
        
        if ($connection->type_id === 'family') {
            // Parent-child relationship logic
            if ($span1Birth && $span2Birth) {
                if ($span1Birth->lt($span2Birth)) {
                    // span1 is likely parent, span2 is likely child
                    $suggestedStartDate = $span2Birth; // child's birth
                    $suggestedEndDate = $span1Death ?: $span2Death; // parent's death, or child's death if parent not dead
                } else {
                    // span2 is likely parent, span1 is likely child
                    $suggestedStartDate = $span1Birth; // child's birth
                    $suggestedEndDate = $span2Death ?: $span1Death; // parent's death, or child's death if parent not dead
                }
            } elseif ($span1Birth) {
                $suggestedStartDate = $span1Birth;
                $suggestedEndDate = $span1Death ?: $span2Death;
            } elseif ($span2Birth) {
                $suggestedStartDate = $span2Birth;
                $suggestedEndDate = $span2Death ?: $span1Death;
            }
        } else {
            // Relationship logic
            if ($span1Birth && $span2Birth) {
                $suggestedStartDate = $span1Birth->gt($span2Birth) ? $span1Birth : $span2Birth;
            } elseif ($span1Birth) {
                $suggestedStartDate = $span1Birth;
            } elseif ($span2Birth) {
                $suggestedStartDate = $span2Birth;
            }
            
            if ($span1Death && $span2Death) {
                $suggestedEndDate = $span1Death->lt($span2Death) ? $span1Death : $span2Death;
            } elseif ($span1Death) {
                $suggestedEndDate = $span1Death;
            } elseif ($span2Death) {
                $suggestedEndDate = $span2Death;
            }
        }
        
        // Update the connection span if we have suggestions
        if ($connection->connectionSpan && ($suggestedStartDate || $suggestedEndDate)) {
            $connectionSpan = $connection->connectionSpan;
            $updated = false;
            
            if ($suggestedStartDate) {
                $currentStart = $this->getBirthDate($connectionSpan);
                if (!$currentStart || $currentStart->format('Y-m-d') !== $suggestedStartDate->format('Y-m-d')) {
                    $connectionSpan->start_year = $suggestedStartDate->year;
                    $connectionSpan->start_month = $suggestedStartDate->month;
                    $connectionSpan->start_day = $suggestedStartDate->day;
                    $updated = true;
                }
            }
            
            if ($suggestedEndDate) {
                $currentEnd = $this->getDeathDate($connectionSpan);
                if (!$currentEnd || $currentEnd->format('Y-m-d') !== $suggestedEndDate->format('Y-m-d')) {
                    $connectionSpan->end_year = $suggestedEndDate->year;
                    $connectionSpan->end_month = $suggestedEndDate->month;
                    $connectionSpan->end_day = $suggestedEndDate->day;
                    $updated = true;
                }
            }
            
            if ($updated) {
                Log::info('Updated family connection dates', [
                    'connection_id' => $connection->id,
                    'connection_type' => $connection->type_id,
                    'span1_name' => $span1->name,
                    'span2_name' => $span2->name,
                    'new_start_date' => $suggestedStartDate ? $suggestedStartDate->format('Y-m-d') : 'unchanged',
                    'new_end_date' => $suggestedEndDate ? $suggestedEndDate->format('Y-m-d') : 'unchanged'
                ]);
                
                $connectionSpan->saveQuietly(); // Save without triggering observers
                $connectionSpan->clearAllTimelineCaches();
            }
        }
    }
    
    /**
     * Get the birth date from a span
     */
    private function getBirthDate(Span $span): ?\Carbon\Carbon
    {
        if ($span->start_year) {
            return \Carbon\Carbon::createFromDate(
                $span->start_year, 
                $span->start_month ?: 1, 
                $span->start_day ?: 1
            );
        }
        return null;
    }
    
    /**
     * Get the death date from a span
     */
    private function getDeathDate(Span $span): ?\Carbon\Carbon
    {
        if ($span->end_year) {
            return \Carbon\Carbon::createFromDate(
                $span->end_year, 
                $span->end_month ?: 12, 
                $span->end_day ?: 31
            );
        }
        return null;
    }
} 