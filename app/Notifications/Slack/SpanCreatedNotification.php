<?php

namespace App\Notifications\Slack;

use App\Models\Span;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SpanCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Span $span;

    /**
     * Create a new notification instance.
     */
    public function __construct(Span $span)
    {
        $this->span = $span;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['slack'];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $span = $this->span;
        $owner = $span->owner;
        
        $message = (new SlackMessage)
            ->success()
            ->content('A new span has been created!')
            ->attachment(function ($attachment) use ($span, $owner) {
                $attachment
                    ->title($span->name, url("/spans/{$span->slug}"))
                    ->fields([
                        'Type' => ucfirst($span->type_id),
                        'Owner' => $owner->name ?? $owner->email,
                        'State' => ucfirst($span->state),
                        'Access Level' => ucfirst($span->access_level),
                    ]);

                // Add date information if available
                if ($span->start_year) {
                    $startDate = $span->formatted_start_date;
                    $endDate = $span->formatted_end_date ?? 'Ongoing';
                    $attachment->field('Period', "{$startDate} - {$endDate}");
                }

                // Add description if available
                if ($span->description) {
                    $description = strlen($span->description) > 100 
                        ? substr($span->description, 0, 100) . '...' 
                        : $span->description;
                    $attachment->field('Description', $description);
                }

                $attachment->footer('Lifespan Beta')
                    ->footerIcon('https://beta.lifespan.dev/favicon.ico')
                    ->timestamp($span->created_at);
            });

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'span_id' => $this->span->id,
            'span_name' => $this->span->name,
            'span_type' => $this->span->type_id,
            'owner_id' => $this->span->owner_id,
        ];
    }
} 