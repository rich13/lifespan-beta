<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Span;
use App\Mail\WelcomeEmail;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Verified;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(): View
    {
        $users = User::with('personalSpan')
            ->leftJoin('spans', 'users.personal_span_id', '=', 'spans.id')
            ->orderBy('spans.name')
            ->select('users.*')
            ->paginate(30);
        $invitationCodes = \App\Models\InvitationCode::orderBy('created_at', 'desc')->get();
        $unusedCodes = $invitationCodes->where('used', false)->count();
        $usedCodes = $invitationCodes->where('used', true)->count();
        
        return view('admin.users.index', compact('users', 'unusedCodes', 'usedCodes', 'invitationCodes'));
    }

    public function show(User $user): View
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'is_admin' => 'sometimes|boolean',
            'password' => 'sometimes|nullable|min:8|confirmed',
            'verify_email' => 'sometimes|boolean',
            'unverify_email' => 'sometimes|boolean',
        ]);

        // Handle checkbox: when unchecked, it won't be in the request
        // We need to explicitly set it to false in that case
        if (!$request->has('is_admin')) {
            $validated['is_admin'] = false;
        }

        // Handle password update if provided
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Handle email verification
        if ($request->has('verify_email') && $request->verify_email) {
            // Mark email as verified
            if (!$user->hasVerifiedEmail()) {
                $user->markEmailAsVerified();
                event(new Verified($user));
            }
        } elseif ($request->has('unverify_email') && $request->unverify_email) {
            // Unverify email
            $user->email_verified_at = null;
            $user->save();
        }

        // Remove verification fields from validated array (they're handled above)
        unset($validated['verify_email'], $validated['unverify_email']);

        $user->update($validated);
        return redirect()->route('admin.users.show', $user)
            ->with('status', 'User updated successfully');
    }

    public function approve(User $user)
    {
        if ($user->approved_at) {
            return redirect()->route('admin.users.show', $user)
                ->with('status', 'User is already approved.');
        }

        $user->update([
            'approved_at' => now(),
        ]);

        Log::info('User approved by admin', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'approved_by' => auth()->id(),
        ]);

        // Send welcome email to the newly approved user
        try {
            Mail::to($user->email)->send(new WelcomeEmail($user));
            
            Log::info('Welcome email sent to approved user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email to approved user', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            // Don't fail the approval if email sending fails
        }

        return redirect()->route('admin.users.show', $user)
            ->with('status', 'User approved successfully. Welcome email sent.');
    }

    public function generateInvitationCodes(Request $request)
    {
        $count = 10;
        $codes = [];
        
        DB::enableQueryLog();
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $code = \App\Models\InvitationCode::create([
                    'code' => 'BETA-' . strtoupper(uniqid()),
                    'used' => false,
                ]);
                $codes[] = $code->code;
            } catch (\Exception $e) {
                Log::error('Failed to create invitation code', [
                    'error' => $e->getMessage(),
                    'sql' => DB::getQueryLog(),
                ]);
                throw $e;
            }
        }
        
        return redirect()->route('admin.users.index')
            ->with('status', "Generated $count new invitation codes")
            ->with('new_codes', $codes);
    }

    public function deleteAllInvitationCodes(Request $request)
    {
        $count = \App\Models\InvitationCode::count();
        \App\Models\InvitationCode::truncate();
        
        return redirect()->route('admin.users.index')
            ->with('status', "Deleted all $count invitation codes");
    }

    public function destroy(User $user)
    {
        if ($user->is_admin) {
            return back()->with('error', 'Administrator accounts cannot be deleted.');
        }

        // Use database transaction to ensure consistency
        DB::transaction(function () use ($user) {
            // First, remove the personal span reference
            $user->personal_span_id = null;
            $user->save();

            // Delete all spans owned by the user (using the direct relationship)
            $user->createdSpans()->delete();
            
            // Delete user-span relationships
            DB::table('user_spans')->where('user_id', $user->id)->delete();
            
            // Delete group memberships
            DB::table('group_user')->where('user_id', $user->id)->delete();
            
            // Delete the user
            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('status', 'User and their spans deleted successfully.');
    }

    /**
     * Show the form to create a user from an existing span.
     */
    public function createFromSpan(): View
    {
        // We no longer need to load all spans since we're using livesearch
        $availableSpans = collect(); // Empty collection for backward compatibility

        return view('admin.users.create-from-span', compact('availableSpans'));
    }

    /**
     * Store a new user created from an existing span.
     */
    public function storeFromSpan(Request $request)
    {
        $validated = $request->validate([
            'span_id' => 'required|exists:spans,id',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
        ]);

        // Verify the span is a person span and not already a personal span
        $span = Span::findOrFail($validated['span_id']);
        
        if ($span->type_id !== 'person') {
            return back()->withErrors(['span_id' => 'Only person spans can be converted to personal spans.']);
        }

        if ($span->is_personal_span) {
            return back()->withErrors(['span_id' => 'This span is already a personal span.']);
        }

        // Create the new user
        $user = User::create([
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_admin' => false,
            'approved_at' => now(), // Auto-approve since admin is creating the account
        ]);

        // Since this is created by an admin, automatically verify email
        $user->markEmailAsVerified();
        event(new Verified($user));

        // Convert the span to a personal span
        $span->is_personal_span = true;
        $span->owner_id = $user->id;
        $span->updater_id = $user->id;
        $span->access_level = 'private'; // Personal spans should be private
        $span->save();

        // Link the user to their personal span
        $user->personal_span_id = $span->id;
        $user->save();

        // Create default sets for the user
        $user->createDefaultSets($span);

        return redirect()->route('admin.users.index')
            ->with('status', 'User created successfully from span');
    }
} 