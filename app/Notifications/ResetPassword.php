<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\URL;

class ResetPassword extends ResetPasswordNotification
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $url = $this->resetUrl($notifiable);
        $count = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Reset Your Password')
            ->view('emails.reset-password', [
                'url' => $url,
                'count' => $count,
            ]);
    }

    /**
     * Get the reset URL for the given notifiable.
     *
     * @param  mixed  $notifiable
     * @return string
     */
    protected function resetUrl($notifiable)
    {
        return URL::route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], true);
    }
}
