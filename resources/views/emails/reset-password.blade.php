<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2c3e50;">Reset Your Password</h2>
    
    <p>You are receiving this email because we received a password reset request for your account.</p>
    
    <p>Click the button below to reset your password:</p>
    
    <p style="margin: 30px 0;">
        <a href="{{ $url }}" 
           style="display: inline-block; background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Reset Password
        </a>
    </p>
    
    <p style="color: #666; font-size: 0.9em;">
        If you did not request a password reset, no further action is required.
    </p>
    
    <p style="color: #666; font-size: 0.85em; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
        <strong>Having trouble clicking the button?</strong><br>
        Copy and paste this URL into your web browser:<br>
        <a href="{{ $url }}" style="color: #007bff; word-break: break-all;">{{ $url }}</a>
    </p>
    
    <p style="color: #666; font-size: 0.9em; margin-top: 30px;">
        This password reset link will expire in {{ $count ?? 60 }} minutes.
    </p>
    
    <p style="color: #666; font-size: 0.9em; margin-top: 30px;">
        Regards,<br>
        The Lifespan Team
    </p>
</body>
</html>
