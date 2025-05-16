<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class LoadUserRelations
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Load personal span and ensure it's the correct one
            $user = Auth::user();
            
            // Check if we're in a switched session
            $isSwitchedSession = $request->session()->has('admin_user_id');
            
            try {
                // Ensure the correct personal span is loaded
                $user->ensureCorrectPersonalSpan();
                
                // Reload relationship to be sure we have the correct span
                $user->load('personalSpan');
                
                // Log the personal span being used for debugging
                if ($isSwitchedSession) {
                    Log::debug('User relations loaded in switched session', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'personal_span_id' => $user->personal_span_id,
                        'admin_user_id' => $request->session()->get('admin_user_id')
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error loading user relations: ' . $e->getMessage());
            }
        }

        return $next($request);
    }
} 