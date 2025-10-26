<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AdminModeController
 * 
 * Handles toggling admin mode on and off for admin users.
 * This allows admins to temporarily disable their admin status to see
 * what normal users see, whilst still maintaining the ability to toggle back.
 * 
 * The toggle state is stored in the session and is cleared on logout.
 */
class AdminModeController extends Controller
{
    /**
     * Create a new controller instance
     */
    public function __construct()
    {
        // Require authentication to access these endpoints
        $this->middleware('auth');
    }

    /**
     * Get the current admin mode status for the authenticated user
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getStatus(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Non-admins cannot have admin mode toggled
        if (!$user->canToggleAdminMode()) {
            return response()->json([
                'can_toggle' => false,
                'admin_mode_disabled' => false,
                'message' => 'User is not an admin',
            ], 403);
        }

        return response()->json([
            'can_toggle' => true,
            'admin_mode_disabled' => $user->isAdminModeDisabled(),
            'effective_admin_status' => $user->getEffectiveAdminStatus(),
        ]);
    }

    /**
     * Disable admin mode for the authenticated user
     * 
     * This hides the admin status from the current session, allowing the user
     * to see what normal users see, without affecting the database record.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function disable(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Verify the user is actually an admin
        if (!$user->canToggleAdminMode()) {
            Log::warning('Non-admin user attempted to disable admin mode', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Only admin users can toggle admin mode',
            ], 403);
        }

        // Check if already disabled
        if ($user->isAdminModeDisabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin mode is already disabled',
                'admin_mode_disabled' => true,
            ]);
        }

        try {
            $result = $user->disableAdminMode();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to disable admin mode',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin mode disabled. You now see the app as a normal user.',
                'admin_mode_disabled' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Error disabling admin mode', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while disabling admin mode',
            ], 500);
        }
    }

    /**
     * Enable admin mode for the authenticated user
     * 
     * This restores the admin status that was hidden during a toggle.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function enable(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Verify the user is actually an admin
        if (!$user->canToggleAdminMode()) {
            Log::warning('Non-admin user attempted to enable admin mode', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Only admin users can toggle admin mode',
            ], 403);
        }

        // Check if already enabled
        if (!$user->isAdminModeDisabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Admin mode is already enabled',
                'admin_mode_disabled' => false,
            ]);
        }

        try {
            $result = $user->enableAdminMode();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to enable admin mode',
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin mode enabled. You are now viewing as an admin.',
                'admin_mode_disabled' => false,
            ]);
        } catch (\Exception $e) {
            Log::error('Error enabling admin mode', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while enabling admin mode',
            ], 500);
        }
    }

    /**
     * Toggle admin mode on or off
     * 
     * Automatically determines the current state and toggles to the opposite.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function toggle(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Verify the user is actually an admin
        if (!$user->canToggleAdminMode()) {
            Log::warning('Non-admin user attempted to toggle admin mode', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Only admin users can toggle admin mode',
            ], 403);
        }

        try {
            if ($user->isAdminModeDisabled()) {
                // Admin mode is disabled, so enable it
                return $this->enable($request);
            } else {
                // Admin mode is enabled, so disable it
                return $this->disable($request);
            }
        } catch (\Exception $e) {
            Log::error('Error toggling admin mode', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while toggling admin mode',
            ], 500);
        }
    }
}
