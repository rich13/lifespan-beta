# Email Configuration Guide

This guide explains how to set up email sending for both local development and production.

## Local Development (Mailpit)

Mailpit is included in the docker-compose setup and automatically captures all emails sent by the application. It's perfect for local development testing.

### Setup:

1. **Mailpit is already configured** in `docker-compose.yml` and will start automatically when you run `docker-compose up`

2. **Your `.env` file should have:**
```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@lifespan.dev
MAIL_FROM_NAME="Lifespan"

# Make sure APP_URL is set to your local URL
APP_URL=http://localhost:8000
```

3. **Access Mailpit Web UI:**
   - Open http://localhost:8025 in your browser
   - All emails sent by the application will appear here
   - You can view the full email content, HTML rendering, and click links

4. **Test it:**
   - Register a new user without an invite code
   - Check Mailpit at http://localhost:8025 - you'll see the email there
   - Click the approval link - it will use your localhost URL (`http://localhost:8000`)

## Production

For production, you can use any SMTP service. Here are popular options:

### Option 1: Mailgun (Recommended)

1. Sign up at https://www.mailgun.com/
2. Verify your domain
3. Get your SMTP credentials

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your_mailgun_username
MAIL_PASSWORD=your_mailgun_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Lifespan"
APP_URL=https://beta.lifespan.dev
```

### Option 2: SendGrid

1. Sign up at https://sendgrid.com/
2. Create an API key
3. Use SMTP credentials

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your_sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Lifespan"
APP_URL=https://beta.lifespan.dev
```

### Option 3: AWS SES

1. Set up AWS SES
2. Verify your domain/email
3. Get SMTP credentials

```env
MAIL_MAILER=smtp
MAIL_HOST=email-smtp.us-east-1.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=your_ses_smtp_username
MAIL_PASSWORD=your_ses_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Lifespan"
APP_URL=https://beta.lifespan.dev
```

### Option 4: Postmark

1. Sign up at https://postmarkapp.com/
2. Verify your domain
3. Use Postmark's Laravel integration

```env
MAIL_MAILER=postmark
POSTMARK_TOKEN=your_postmark_token
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Lifespan"
APP_URL=https://beta.lifespan.dev
```

## Environment-Aware URLs

The email templates automatically use the correct URLs based on your `APP_URL` environment variable:

- **Local**: `http://localhost:8000` → Links will point to localhost
- **Production**: `https://beta.lifespan.dev` → Links will point to production

The `url()` helper in Laravel automatically uses `APP_URL`, so all email links will work correctly in both environments.

## Testing

After configuration, test the email flow:

1. Register a new user without an invite code
2. Check your email inbox (Mailtrap for local, real inbox for production)
3. Click the approval link - it should take you to the correct environment
4. Approve the user
5. Verify the user can now log in

## Troubleshooting

### Emails not sending locally:
- Check Mailtrap credentials are correct
- Verify `APP_URL` is set correctly
- Check Laravel logs: `storage/logs/laravel.log`

### Emails not sending in production:
- Verify SMTP credentials
- Check domain/email is verified (for Mailgun/SendGrid)
- Check spam folder
- Review Laravel logs
- Verify `APP_URL` matches your production domain
