<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            // Get intended URL, but ignore API/status endpoints
            $intendedUrl = $request->session()->pull('url.intended', RouteServiceProvider::HOME);
            
            // If intended URL is an API endpoint or status endpoint, ignore it
            if ($intendedUrl && (
                str_starts_with($intendedUrl, '/admin-mode/') ||
                str_starts_with($intendedUrl, '/api/') ||
                str_starts_with($intendedUrl, '/wikipedia/')
            )) {
                $intendedUrl = RouteServiceProvider::HOME;
            }
            
            return redirect($intendedUrl);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
