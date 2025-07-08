<?php

namespace App\Notifications\Slack;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class SystemEventNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected string $event;
    protected array $data;
    protected string $level;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $event, array $data = [], string $level = 'info')
    {
        $this->event = $event;
        $this->data = $data;
        $this->level = $level;
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
        $message = (new SlackMessage)
            ->{$this->level}()
            ->content("System Event: {$this->event}")
            ->attachment(function ($attachment) {
                $attachment->fields($this->formatFields());
                
                $attachment->footer('Lifespan Beta')
                    ->footerIcon('https://beta.lifespan.dev/favicon.ico')
                    ->timestamp(now());
            });

        return $message;
    }

    /**
     * Format the data fields for Slack
     */
    protected function formatFields(): array
    {
        $fields = [];
        
        foreach ($this->data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            }
            
            if (is_string($value) && strlen($value) > 100) {
                $value = substr($value, 0, 100) . '...';
            }
            
            $fields[ucfirst($key)] = (string) $value;
        }
        
        return $fields;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'level' => $this->level,
        ];
    }
} 