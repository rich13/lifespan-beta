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
        // If we're already in a switched session, store the original admin user ID
        if (!$request->session()->has('admin_user_id')) {
            // This is the first switch, store the current user as the admin
            $request->session()->put('admin_user_id', Auth::id());
        }

        // Switch to the requested user
        Auth::loginUsingId($userId);

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
        // Check if we have an admin user stored in the session
        if ($request->session()->has('admin_user_id')) {
            $adminUserId = $request->session()->get('admin_user_id');
            
            // Switch back to the admin user
            Auth::loginUsingId($adminUserId);
            
            // Remove the stored admin user from the session
            $request->session()->forget('admin_user_id');
            
            return redirect()->back()->with('status', 'Switched back to admin user.');
        }
        
        return redirect()->back()->with('error', 'No admin user found in session.');
    }

    /**
     * Get a list of users for the switcher dropdown
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserList()
    {
        // Get all users
        $users = User::orderBy('email')->get();
        
        // Mark the current user
        $currentUserId = auth()->id();
        
        // Get the original admin user ID if we're in a switched session
        $adminUserId = session()->has('admin_user_id') ? session('admin_user_id') : null;
        
        // Prepare the user list
        $userList = [];
        
        foreach ($users as $user) {
            // Add properties to identify special users
            $user->is_current = ($user->id === $currentUserId);
            $user->is_admin_user = ($adminUserId && $user->id === $adminUserId);
            
            // Add to the list
            $userList[] = $user;
        }
        
        // If we're in a switched session, add a special "Switch Back to Admin" option at the top
        if ($adminUserId) {
            $adminUser = User::find($adminUserId);
            if ($adminUser) {
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
        
        return response()->json($userList);
    }
}
