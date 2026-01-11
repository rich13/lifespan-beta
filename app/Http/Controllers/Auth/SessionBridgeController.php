<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SessionBridgeController extends Controller
{
    /**
     * Restore session using a bridge token
     * This endpoint is called when a user has been logged out due to a redeploy
     * but still has a valid bridge token in localStorage
     */
    public function restoreSession(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $token = $request->input('token');
            
            // Find the personal access token and get the associated user
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            
            if (!$personalAccessToken || $personalAccessToken->name !== 'session-bridge') {
                Log::warning('Invalid or expired session bridge token attempted', [
                    'token_type' => $personalAccessToken?->name ?? 'null'
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired session token'
                ], 401);
            }
            
            $user = $personalAccessToken->tokenable;
            
            // Log the session restoration
            Log::info('Session bridge token used to restore session', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
            
            // Authenticate the user (this creates a session)
            Auth::login($user, remember: false);
            
            // Regenerate the session
            $request->session()->regenerate();
            
            // Generate a fresh bridge token for future use
            $user->tokens()->where('name', 'session-bridge')->delete();
            $newToken = $user->createToken('session-bridge');
            $request->session()->put('bridge_token', $newToken->plainTextToken);
            
            // Ensure default sets exist (only if user has personal span)
            if ($user->personal_span_id) {
                $user->ensureDefaultSetsExist();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Session restored successfully',
                'new_token' => $newToken->plainTextToken
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error restoring session with bridge token', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error restoring session'
            ], 500);
        }
    }

    /**
     * Refresh the bridge token without requiring re-authentication
     * This can be called periodically or when the app loads
     */
    public function refreshBridgeToken(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated'
            ], 401);
        }

        try {
            $user = Auth::user();
            
            // Delete old bridge tokens and create a new one
            $user->tokens()->where('name', 'session-bridge')->delete();
            $token = $user->createToken('session-bridge');
            
            // Store in session as well
            $request->session()->put('bridge_token', $token->plainTextToken);
            
            Log::info('Bridge token refreshed', [
                'user_id' => $user->id
            ]);
            
            return response()->json([
                'success' => true,
                'token' => $token->plainTextToken
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error refreshing bridge token', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error refreshing token'
            ], 500);
        }
    }

    /**
     * Check if the current session is still valid
     * Used by the frontend to detect session loss
     */
    public function checkSession(Request $request)
    {
        return response()->json([
            'authenticated' => Auth::check(),
            'user_id' => Auth::id(),
            'has_token' => (bool) $request->session()->get('bridge_token')
        ]);
    }
}
