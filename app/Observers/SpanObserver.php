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
} 