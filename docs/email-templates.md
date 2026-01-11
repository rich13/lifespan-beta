# Email Templates

This document describes all email templates used by the Lifespan application.

## Custom Email Templates

All email templates are located in `resources/views/emails/` and use consistent styling.

### 1. Email Verification (`verify-email.blade.php`)

**Sent when:** A new user registers and needs to verify their email address  
**Notification class:** `App\Notifications\VerifyEmail`  
**Template:** `resources/views/emails/verify-email.blade.php`  
**Variables:**
- `$url` - The verification URL (signed, expires in 60 minutes)
- `$count` - Expiration time in minutes

**Features:**
- Large, prominent "Verify Email Address" button
- Fallback plain text URL for users who can't click the button
- Clear expiration notice
- Consistent branding with other emails

### 2. Password Reset (`reset-password.blade.php`)

**Sent when:** A user requests a password reset  
**Notification class:** `App\Notifications\ResetPassword`  
**Template:** `resources/views/emails/reset-password.blade.php`  
**Variables:**
- `$url` - The password reset URL (expires in 60 minutes)
- `$count` - Expiration time in minutes

**Features:**
- Large, prominent "Reset Password" button
- Fallback plain text URL for users who can't click the button
- Clear expiration notice
- Security notice if user didn't request reset
- Consistent branding with other emails

### 3. Registration Approval Request (`registration-approval-request.blade.php`)

**Sent when:** A new user registers and needs admin approval  
**Mailable class:** `App\Mail\RegistrationApprovalRequest`  
**Template:** `resources/views/emails/registration-approval-request.blade.php`  
**Variables:**
- `$user` - The user model that registered

**Features:**
- User details (name, email, registration date)
- Direct link to admin user approval page
- Consistent branding with other emails

### 4. Welcome Email (`welcome.blade.php`)

**Sent when:** An admin approves a user's registration  
**Mailable class:** `App\Mail\WelcomeEmail`  
**Template:** `resources/views/emails/welcome.blade.php`  
**Variables:**
- `$user` - The user model that was approved

**Features:**
- Welcome message congratulating the user
- Account details (email, name, approval date)
- Direct link to sign in page
- Next steps checklist (verify email, sign in, start building timeline)
- Help section
- Consistent branding with other emails

**Note:** This email is sent automatically when an admin clicks "Approve User" in the admin panel. It invites the user to sign in now that their account is approved.

## Custom Notification Classes

### `App\Notifications\VerifyEmail`

Extends Laravel's `Illuminate\Auth\Notifications\VerifyEmail` to use our custom email template.

**Override methods:**
- `toMail()` - Builds the mail message using our custom view
- `verificationUrl()` - Creates the signed verification URL

### `App\Notifications\ResetPassword`

Extends Laravel's `Illuminate\Auth\Notifications\ResetPassword` to use our custom email template.

**Override methods:**
- `toMail()` - Builds the mail message using our custom view
- `resetUrl()` - Creates the password reset URL

## User Model Integration

The `User` model (`app/Models/User.php`) overrides two methods to use our custom notifications:

```php
public function sendEmailVerificationNotification()
{
    $this->notify(new VerifyEmail);
}

public function sendPasswordResetNotification($token)
{
    $this->notify(new ResetPassword($token));
}
```

## Email Styling

All email templates use a consistent design:

- **Font:** Arial, sans-serif
- **Max width:** 600px
- **Primary color:** #2c3e50 (headings)
- **Button color:** #007bff (Bootstrap primary blue)
- **Text color:** #333 (body), #666 (secondary), #999 (tertiary)
- **Spacing:** Consistent padding and margins
- **Layout:** Centered, responsive-friendly

## Testing Email Templates

### Local Development (Mailpit)

1. Start the application: `docker-compose up`
2. Access Mailpit UI: http://localhost:8025
3. Trigger emails:
   - Register a new user (verification email + approval request email to admins)
   - Request password reset (password reset email)
   - Approve a user in admin panel (welcome email to the approved user)
4. View emails in Mailpit to see how they render

### Production

Emails will be sent via the configured SMTP service (MailerSend) and will use the same templates.

## Customization

To customize email templates:

1. Edit the Blade template files in `resources/views/emails/`
2. The templates use standard Blade syntax with inline CSS for email client compatibility
3. Test changes locally using Mailpit before deploying
4. All templates respect the `APP_URL` environment variable for link generation

## Environment Variables

The following environment variables affect email behavior:

- `APP_URL` - Base URL for email links (automatically used by `url()` helper)
- `MAIL_FROM_ADDRESS` - "From" email address
- `MAIL_FROM_NAME` - "From" name
- `MAIL_MAILER` - Mail driver (smtp, mailgun, etc.)
- `MAIL_HOST`, `MAIL_PORT`, etc. - SMTP configuration

All email links automatically use the correct `APP_URL` based on the environment (localhost for local, production URL for production).
