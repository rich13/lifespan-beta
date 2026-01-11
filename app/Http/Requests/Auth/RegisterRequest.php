<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\InvitationCode;
use App\Models\User;
use App\Services\SlackNotificationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;
use App\Models\Span;
use App\Mail\RegistrationApprovalRequest;
use Illuminate\Support\Facades\Mail;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        Log::info('Validating registration request', [
            'email' => $this->email,
            // 'invitation_code' => $this->invitation_code // Commented out - invite codes disabled
        ]);

        $rules = [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            // Invitation code validation - commented out but kept for potential future use
            // 'invitation_code' => ['nullable', 'string', function ($attribute, $value, $fail) {
            //     if ($value && $value !== 'lifespan-beta-5b18a03898a7e8dac3582ef4b58508c4' && !InvitationCode::where('code', $value)->where('used', false)->exists()) {
            //         Log::warning('Invalid invitation code used', [
            //             'code' => $value
            //         ]);
            //         $fail('Invalid invitation code.');
            //     }
            // }],
        ];

        return $rules;
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('Registration validation failed', [
            'errors' => $validator->errors()->toArray()
        ]);

        // Preserve the email in the session and input
        if ($this->email) {
            session()->flash('email', $this->email);
        }
        
        throw ValidationException::withMessages($validator->errors()->toArray());
    }

    /**
     * Handle the registration request.
     */
    public function register()
    {
        Log::info('Starting user registration', [
            'email' => $this->email,
            // 'invitation_code' => $this->invitation_code ?? 'none' // Commented out - invite codes disabled
        ]);

        // Invitation code logic - commented out but kept for potential future use
        // $hasValidInviteCode = false;
        $needsApproval = true;

        // Check if invitation code is provided and valid
        // if ($this->invitation_code) {
        //     if ($this->invitation_code === 'lifespan-beta-5b18a03898a7e8dac3582ef4b58508c4') {
        //         Log::info('Using universal invitation code: lifespan-beta-5b18a03898a7e8dac3582ef4b58508c4');
        //         $hasValidInviteCode = true;
        //         $needsApproval = false;
        //     } else {
        //         $code = InvitationCode::where('code', $this->invitation_code)
        //             ->where('used', false)
        //             ->first();
        //
        //         if ($code) {
        //             Log::info('Marking invitation code as used', [
        //                 'code' => $this->invitation_code,
        //                 'used_by' => $this->email
        //             ]);
        //
        //             $code->update([
        //                 'used' => true,
        //                 'used_at' => now(),
        //                 'used_by' => $this->email,
        //             ]);
        //             $hasValidInviteCode = true;
        //             $needsApproval = false;
        //         } else {
        //             Log::warning('Invalid invitation code during registration', [
        //                 'code' => $this->invitation_code
        //             ]);
        //             throw ValidationException::withMessages([
        //                 'invitation_code' => 'Invalid invitation code.',
        //             ]);
        //         }
        //     }
        // }

        // Create user with approval status
        $userData = [
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ];

        if (!$needsApproval) {
            $userData['approved_at'] = now();
        }

        $user = User::create($userData);

        Log::info('User created successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
            'needs_approval' => $needsApproval
        ]);

        // Create personal span for the user using the User model's method
        $personalSpan = $user->createPersonalSpan([
            'name' => $this->name,
            'birth_year' => $this->birth_year,
            'birth_month' => $this->birth_month,
            'birth_day' => $this->birth_day,
        ]);

        Log::info('Personal span created for user', [
            'user_id' => $user->id,
            'span_id' => $personalSpan->id,
            'name' => $personalSpan->name,
            'birth_date' => [
                'year' => $this->birth_year,
                'month' => $this->birth_month,
                'day' => $this->birth_day
            ]
        ]);

        event(new Registered($user));

        // Send email verification notification
        $user->sendEmailVerificationNotification();
        
        Log::info('Email verification notification sent', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);

        // Send Slack notification for new user registration
        $slackService = app(SlackNotificationService::class);
        $slackService->notifyUserRegistered($user);

        if ($needsApproval) {
            // Send email to admin for approval
            $this->sendApprovalRequestEmail($user);
            
            Log::info('User registration pending approval', [
                'user_id' => $user->id,
                'email' => $user->email
            ]);
        } else {
            // Auto-approve (but don't log in - they still need to verify email)
            Log::info('User auto-approved (invite code used)', [
                'user_id' => $user->id
            ]);
        }

        return $user;
    }

    /**
     * Send approval request email to admin users
     */
    protected function sendApprovalRequestEmail(User $user): void
    {
        try {
            // Get all admin users
            $adminUsers = User::where('is_admin', true)->get();
            
            if ($adminUsers->isEmpty()) {
                Log::warning('No admin users found to send approval request email', [
                    'user_id' => $user->id
                ]);
                return;
            }

            // Send email to each admin
            foreach ($adminUsers as $admin) {
                Mail::to($admin->email)->send(new RegistrationApprovalRequest($user));
            }

            Log::info('Approval request emails sent to admins', [
                'user_id' => $user->id,
                'admin_count' => $adminUsers->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send approval request email', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
 