<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SlackNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(Request $request): View
    {
        // Pre-fill email if provided in query string (from password screen)
        $email = $request->query('email') ?? session('email') ?? old('email');
        
        return view('auth.forgot-password', [
            'email' => $email
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $requestedEmail = $request->input('email');
        
        // Explicitly find the user by the requested email to ensure we're sending to the right person
        $user = User::where('email', $requestedEmail)->first();
        
        if ($user) {
            // Log for debugging
            Log::info('Password reset requested', [
                'requested_email' => $requestedEmail,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'authenticated_user_id' => auth()->id(),
                'authenticated_user_email' => auth()->user()?->email,
            ]);
            
            // Use Password::sendResetLink which will find the user and send the notification
            // This ensures the notification goes to the user found by email, not the authenticated user
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } else {
            // User not found - still use Password::sendResetLink for consistent behavior
            // (it will return the appropriate error message)
            $status = Password::sendResetLink(
                $request->only('email')
            );
            
            Log::info('Password reset requested for non-existent email', [
                'requested_email' => $requestedEmail,
                'authenticated_user_id' => auth()->id(),
            ]);
        }

        if ($status == Password::RESET_LINK_SENT) {
            // Send Slack notification if user was found
            if ($user) {
                try {
                    $slackService = app(SlackNotificationService::class);
                    $slackService->notifyPasswordResetRequested($user, $request->ip());
                } catch (\Exception $e) {
                    Log::error('Failed to send Slack notification for password reset request', [
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Redirect to forgot-password page with success message so user can see it
            return redirect()->route('password.request')
                ->with('status', 'Your password reset link is on its way...');
        }

        return back()->withInput($request->only('email'))
                    ->withErrors(['email' => __($status)]);
    }
}
