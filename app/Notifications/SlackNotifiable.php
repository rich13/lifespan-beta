<?php

namespace App\Notifications;

use Illuminate\Notifications\Notifiable;

class SlackNotifiable
{
    use Notifiable;

    /**
     * Route notifications for the Slack channel.
     *
     * @return string
     */
    public function routeNotificationForSlack(): string
    {
        return config('services.slack.webhook_url');
    }
} 