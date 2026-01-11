<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New User Registration Requires Approval</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2c3e50;">New User Registration Requires Approval</h2>
    
    <p>A new user has registered and is awaiting approval:</p>
    
    <div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;">
        <p><strong>Name:</strong> {{ $user->personalSpan?->name ?? 'Not set' }}</p>
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Registered:</strong> {{ $user->created_at->format('F j, Y \a\t g:i A') }}</p>
    </div>
    
    <p>To approve this user, please visit the admin panel:</p>
    
    <p style="margin: 20px 0;">
        <a href="{{ url(route('admin.users.show', $user)) }}" 
           style="display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            Review and Approve User
        </a>
    </p>
    
    <p style="color: #666; font-size: 0.9em; margin-top: 30px;">
        Regards,<br>
        The Lifespan Team
    </p>
    
    <p style="color: #999; font-size: 0.85em; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
        This is an automated notification from the Lifespan registration system.
    </p>
</body>
</html>
