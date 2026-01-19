<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Lifespan</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2c3e50;">Welcome to Lifespan</h2>
    
    <p>Your account has been approved and you're ready to get started.</p>
      
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p style="margin: 0;"><strong>Your account details:</strong></p>
        <p style="margin: 5px 0;"><strong>Email:</strong> {{ $user->email }}</p>
        <p style="margin: 5px 0;"><strong>Name:</strong> {{ $user->personalSpan?->name ?? 'Not set' }}</p>
        <p style="margin: 5px 0;"><strong>Approved:</strong> {{ $user->approved_at->format('F j, Y \a\t g:i A') }}</p>
    </div>
    
    <p style="margin: 30px 0;">
        <a href="{{ route('login') }}" 
           style="display: inline-block; background-color: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold;">
            Sign In to Lifespan
        </a>
    </p>
    
    <p style="color: #666; font-size: 0.9em; margin-top: 30px;">
        Regards,<br>
        The Lifespan Team
    </p>
</body>
</html>
