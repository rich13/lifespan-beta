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
} 