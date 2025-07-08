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
            'invitation_code' => $this->invitation_code
        ]);

        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'name' => ['required', 'string', 'max:255'],
            'birth_year' => ['required', 'integer', 'min:1900', 'max:' . date('Y')],
            'birth_month' => ['required', 'integer', 'min:1', 'max:12'],
            'birth_day' => ['required', 'integer', 'min:1', 'max:31'],
            'invitation_code' => ['required', 'string', function ($attribute, $value, $fail) {
                if ($value !== 'lifespan' && !InvitationCode::where('code', $value)->where('used', false)->exists()) {
                    Log::warning('Invalid invitation code used', [
                        'code' => $value
                    ]);
                    $fail('Invalid invitation code.');
                }
            }],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        Log::warning('Registration validation failed', [
            'errors' => $validator->errors()->toArray()
        ]);

        // Preserve the email in the session
        session()->flash('email', $this->email);
        
        throw ValidationException::withMessages($validator->errors()->toArray());
    }

    /**
     * Handle the registration request.
     */
    public function register()
    {
        Log::info('Starting user registration', [
            'email' => $this->email,
            'invitation_code' => $this->invitation_code
        ]);

        if ($this->invitation_code !== 'lifespan') {
            $code = InvitationCode::where('code', $this->invitation_code)
                ->where('used', false)
                ->first();

            if (!$code) {
                Log::warning('Invalid invitation code during registration', [
                    'code' => $this->invitation_code
                ]);
                throw ValidationException::withMessages([
                    'invitation_code' => 'Invalid invitation code.',
                ]);
            }

            Log::info('Marking invitation code as used', [
                'code' => $this->invitation_code,
                'used_by' => $this->email
            ]);

            $code->update([
                'used' => true,
                'used_at' => now(),
                'used_by' => $this->email,
            ]);
        } else {
            Log::info('Using universal invitation code: lifespan');
        }

        $user = User::create([
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        Log::info('User created successfully', [
            'user_id' => $user->id,
            'email' => $user->email
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

        // Send Slack notification for new user registration
        $slackService = app(SlackNotificationService::class);
        $slackService->notifyUserRegistered($user);

        Auth::login($user);

        Log::info('User logged in after registration', [
            'user_id' => $user->id
        ]);

        return $user;
    }
}
 