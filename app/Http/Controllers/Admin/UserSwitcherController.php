<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class UserSwitcherController extends Controller
{
    /**
     * Switch to another user while maintaining the admin session
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $userId
     * @return \Illuminate\Http\Response
     */
    public function switchToUser(Request $request, $userId)
    {
        \Log::info('UserSwitcher: Switching to user', [
            'target_user_id' => $userId,
            'current_user_id' => auth()->id(),
            'has_admin_session' => $request->session()->has('admin_user_id')
        ]);

        // If we're already in a switched session, store the original admin user ID
        if (!$request->session()->has('admin_user_id')) {
            // This is the first switch, store the current user as the admin
            $request->session()->put('admin_user_id', Auth::id());
            \Log::info('UserSwitcher: Storing admin user ID in session', [
                'admin_user_id' => Auth::id()
            ]);
        }

        // Get the target user
        $targetUser = User::find($userId);
        
        if (!$targetUser) {
            \Log::error('UserSwitcher: Target user not found', [
                'target_user_id' => $userId
            ]);
            return redirect()->back()->with('error', 'User not found.');
        }
        
        // Ensure target user has the correct personal span
        $targetUser->ensureCorrectPersonalSpan();

        // Switch to the requested user
        Auth::login($targetUser);
        \Log::info('UserSwitcher: Successfully switched to user', [
            'new_user_id' => $userId,
            'personal_span_id' => $targetUser->personal_span_id
        ]);

        return redirect()->back()->with('status', 'Switched to user successfully.');
    }

    /**
     * Switch back to the admin user
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function switchBack(Request $request)
    {
        \Log::info('UserSwitcher: Attempting to switch back to admin');

        // Check if we have an admin user stored in the session
        if ($request->session()->has('admin_user_id')) {
            $adminUserId = $request->session()->get('admin_user_id');
            
            \Log::info('UserSwitcher: Found admin user in session', [
                'admin_user_id' => $adminUserId
            ]);
            
            // Get the admin user
            $adminUser = User::find($adminUserId);
            
            if (!$adminUser) {
                \Log::error('UserSwitcher: Admin user not found', [
                    'admin_user_id' => $adminUserId
                ]);
                $request->session()->forget('admin_user_id');
                return redirect()->back()->with('error', 'Admin user not found.');
            }
            
            // Ensure admin user has the correct personal span
            $adminUser->ensureCorrectPersonalSpan();
            
            // Switch back to the admin user
            Auth::login($adminUser);
            
            \Log::info('UserSwitcher: Successfully switched back to admin', [
                'admin_user_id' => $adminUserId,
                'personal_span_id' => $adminUser->personal_span_id
            ]);
            
            // Remove the stored admin user from the session
            $request->session()->forget('admin_user_id');
            
            return redirect()->back()->with('status', 'Switched back to admin user.');
        }
        
        \Log::warning('UserSwitcher: No admin user found in session');
        return redirect()->back()->with('error', 'No admin user found in session.');
    }

    /**
     * Get a list of users for the switcher dropdown
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserList()
    {
        \Log::info('UserSwitcher: Getting user list');
        
        // Get all users except the system user
        $users = User::where('email', '!=', 'system@lifespan.app')
            ->orderBy('email')
            ->get();
        
        \Log::info('UserSwitcher: Found ' . $users->count() . ' users');
        
        // Mark the current user
        $currentUserId = auth()->id();
        \Log::info('UserSwitcher: Current user ID: ' . $currentUserId);
        
        // Get the original admin user ID if we're in a switched session
        $adminUserId = session()->has('admin_user_id') ? session('admin_user_id') : null;
        \Log::info('UserSwitcher: Admin user ID: ' . ($adminUserId ?? 'none'));
        
        // Prepare the user list
        $userList = [];
        
        foreach ($users as $user) {
            // Add properties to identify special users
            $user->is_current = ($user->id === $currentUserId);
            $user->is_admin_user = ($adminUserId && $user->id === $adminUserId);
            
            \Log::info('UserSwitcher: Processing user', [
                'id' => $user->id,
                'email' => $user->email,
                'is_current' => $user->is_current,
                'is_admin_user' => $user->is_admin_user
            ]);
            
            // Add to the list
            $userList[] = $user;
        }
        
        // If we're in a switched session, add a special "Switch Back to Admin" option at the top
        if ($adminUserId) {
            $adminUser = User::find($adminUserId);
            if ($adminUser) {
                \Log::info('UserSwitcher: Adding switch back option for admin', [
                    'admin_id' => $adminUserId,
                    'admin_email' => $adminUser->email
                ]);
                
                // Create a special entry for switching back to admin
                $switchBackOption = [
                    'id' => $adminUserId,
                    'email' => 'Switch back to ' . $adminUser->email,
                    'is_switch_back' => true,
                    'is_admin_user' => true
                ];
                
                // Add it to the beginning of the array
                array_unshift($userList, (object)$switchBackOption);
            }
        }
        
        \Log::info('UserSwitcher: Returning user list with ' . count($userList) . ' entries');
        return response()->json($userList);
    }
}
