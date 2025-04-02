<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Auth\Events\Registered;

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
        return [
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'invitation_code' => ['required', 'string', 'exists:invitation_codes,code,used,0'],
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Preserve the email in the session
        session()->flash('email', $this->email);
        
        throw ValidationException::withMessages($validator->errors()->toArray());
    }

    /**
     * Handle the registration request.
     */
    public function register()
    {
        $code = InvitationCode::where('code', $this->invitation_code)
            ->where('used', false)
            ->first();

        if (!$code) {
            throw ValidationException::withMessages([
                'invitation_code' => 'Invalid invitation code.',
            ]);
        }

        $user = User::create([
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        $code->update([
            'used' => true,
            'used_at' => now(),
            'used_by' => $this->email,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return $user;
    }
}
 