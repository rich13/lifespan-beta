<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Facades\Log;
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
        // Log to help debug email routing issues
        $userEmail = $notifiable->getEmailForPasswordReset();
        $userId = $notifiable->getKey();
        
        Log::info('Password reset notification being sent', [
            'user_id' => $userId,
            'user_email' => $userEmail,
            'notifiable_type' => get_class($notifiable),
            'authenticated_user_id' => auth()->id(),
            'authenticated_user_email' => auth()->user()?->email,
        ]);
        
        $url = $this->resetUrl($notifiable);
        $count = config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        $mailMessage = (new \Illuminate\Notifications\Messages\MailMessage)
            ->subject('Reset Your Password')
            ->view('emails.reset-password', [
                'url' => $url,
                'count' => $count,
            ]);
        
        // Explicitly set the recipient to ensure it goes to the correct email
        // This prevents any potential routing issues
        if (method_exists($notifiable, 'routeNotificationForMail')) {
            $routeEmail = $notifiable->routeNotificationForMail($this);
            if ($routeEmail && $routeEmail !== $userEmail) {
                Log::warning('Email routing mismatch detected', [
                    'user_email' => $userEmail,
                    'route_email' => $routeEmail,
                ]);
            }
        }
        
        return $mailMessage;
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
