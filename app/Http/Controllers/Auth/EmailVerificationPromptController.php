<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse|View
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
        
        return view('auth.verify-email');
    }
}
