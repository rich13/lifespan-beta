<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        // Get intended URL, but ignore API/status endpoints
        $intendedUrl = $request->session()->pull('url.intended', RouteServiceProvider::HOME.'?verified=1');
        
        // If intended URL is an API endpoint or status endpoint, ignore it
        if ($intendedUrl && (
            str_starts_with($intendedUrl, '/admin-mode/') ||
            str_starts_with($intendedUrl, '/api/') ||
            str_starts_with($intendedUrl, '/wikipedia/')
        )) {
            $intendedUrl = RouteServiceProvider::HOME.'?verified=1';
        }
        
        if ($request->user()->hasVerifiedEmail()) {
            return redirect($intendedUrl);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect($intendedUrl);
    }
}
