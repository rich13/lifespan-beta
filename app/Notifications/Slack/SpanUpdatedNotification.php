<?php

namespace App\Notifications\Slack;

use App\Models\Span;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SpanUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Span $span;
    protected array $changes;

    /**
     * Create a new notification instance.
     */
    public function __construct(Span $span, array $changes = [])
    {
        $this->span = $span;
        $this->changes = $changes;
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
        $updater = $span->updater;
        
        $message = (new SlackMessage)
            ->info()
            ->content('A span has been updated!')
            ->attachment(function ($attachment) use ($span, $updater) {
                $attachment
                    ->title($span->name, url("/spans/{$span->slug}"))
                    ->fields([
                        'Type' => ucfirst($span->type_id),
                        'Updated By' => $updater->name ?? $updater->email,
                        'State' => ucfirst($span->state),
                        'Access Level' => ucfirst($span->access_level),
                    ]);

                // Add date information if available
                if ($span->start_year) {
                    $startDate = $span->formatted_start_date;
                    $endDate = $span->formatted_end_date ?? 'Ongoing';
                    $attachment->field('Period', "{$startDate} - {$endDate}");
                }

                // Show what changed if we have the changes
                if (!empty($this->changes)) {
                    $changeFields = [];
                    foreach ($this->changes as $field => $newValue) {
                        if (in_array($field, ['name', 'description', 'state', 'access_level'])) {
                            $changeFields[ucfirst($field)] = is_string($newValue) && strlen($newValue) > 50 
                                ? substr($newValue, 0, 50) . '...' 
                                : (string) $newValue;
                        }
                    }
                    
                    if (!empty($changeFields)) {
                        $attachment->fields($changeFields);
                    }
                }

                $attachment->footer('Lifespan Beta')
                    ->footerIcon('https://beta.lifespan.dev/favicon.ico')
                    ->timestamp($span->updated_at);
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
            'updater_id' => $this->span->updater_id,
            'changes' => $this->changes,
        ];
    }
} 