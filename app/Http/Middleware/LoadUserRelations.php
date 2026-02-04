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
            $user = Auth::user();
            $isSwitchedSession = $request->session()->has('admin_user_id');

            try {
                if (!$user->personal_span_id) {
                    return $next($request);
                }

                // Only run ensureCorrectPersonalSpan when span might be wrong (e.g. after admin user switch)
                if ($isSwitchedSession) {
                    $personalSpan = $user->ensureCorrectPersonalSpan();
                    if (!$personalSpan && $user->personal_span_id) {
                        Log::warning('User has personal_span_id but span does not exist', [
                            'user_id' => $user->id,
                            'personal_span_id' => $user->personal_span_id
                        ]);
                        $user->personal_span_id = null;
                        $user->save();
                    } else {
                        $user->load('personalSpan');
                    }
                    Log::debug('User relations loaded in switched session', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'personal_span_id' => $user->personal_span_id,
                        'admin_user_id' => $request->session()->get('admin_user_id')
                    ]);
                } else {
                    // Normal case: just ensure personalSpan is loaded (one query if missing)
                    $user->loadMissing('personalSpan');
                }
            } catch (\Exception $e) {
                Log::error('Error loading user relations: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'personal_span_id' => $user->personal_span_id ?? null,
                    'exception' => $e
                ]);
            }
        }

        return $next($request);
    }
} 