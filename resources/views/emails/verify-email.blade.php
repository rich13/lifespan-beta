<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lifespan Email Verification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2c3e50;">Lifespan Email Verification</h2>
    
    <p>Thank you for registering with Lifespan.</p>
      
    <p style="margin: 30px 0;">
        <a href="{{ $url }}" 
           style="display: inline-block; background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Verify your email address to continue
        </a>
    </p>
    
    <p style="color: #666; font-size: 0.9em;">
        If you didn't create an account, you can ignore this email.
    </p>
    
    <p style="color: #666; font-size: 0.85em; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
        <strong>If the button doesn't work, copy and paste this URL into your browser:</strong><br>
        <a href="{{ $url }}" style="color: #007bff; word-break: break-all;">{{ $url }}</a>
    </p>
    
    <p style="color: #666; font-size: 0.9em; margin-top: 30px;">
        This message will self-destruct in {{ $count ?? 60 }} minutes.
    </p>
    
</body>
</html>
