# MailerSend Setup Guide

## SMTP vs API: Which to Use?

### Recommendation: **SMTP Relay** (Simpler)

**Use SMTP if:**
- ✅ You want the simplest setup
- ✅ You're using Laravel's built-in mail system
- ✅ You don't need advanced features like webhooks
- ✅ You want to keep it consistent with other email services

**Use API if:**
- ✅ You need webhooks for delivery tracking
- ✅ You want more detailed analytics
- ✅ You need advanced features like email templates
- ✅ You're building a more complex email system

## Option 1: SMTP Relay (Recommended - Simplest)

### Setup Steps:

1. **Sign up**: https://www.mailersend.com/
2. **Get SMTP credentials**:
   - Go to Settings → SMTP
   - Copy your SMTP credentials
3. **Add to Railway**:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailersend.com
   MAIL_PORT=587
   MAIL_USERNAME=your-smtp-username
   MAIL_PASSWORD=your-smtp-token (Secret 🔒)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME=Lifespan
   ```

**That's it!** Works with your existing Laravel mail setup.

## Option 2: API (More Features)

### Setup Steps:

1. **Install MailerSend package**:
   ```bash
   composer require mailersend/laravel-driver
   ```
   
   **Note**: This package has already been added to `composer.json`. Run `composer install` to install it.

2. **Get API token**:
   - Go to Settings → API Tokens
   - Create a new token

3. **Add to Railway**:
   ```
   MAIL_MAILER=mailersend
   MAILERSEND_API_KEY=your-api-token (Secret 🔒)
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME=Lifespan
   ```

4. **Configuration files** (already updated):
   - `config/mail.php` - MailerSend mailer added
   - `config/services.php` - MailerSend API key configuration added

## Comparison

| Feature | SMTP | API |
|---------|------|-----|
| Setup Complexity | ⭐⭐⭐⭐⭐ Very Easy | ⭐⭐⭐ Moderate |
| Package Required | No | Yes |
| Webhooks | ❌ | ✅ |
| Advanced Analytics | ❌ | ✅ |
| Email Templates | ❌ | ✅ |
| Works with Laravel Mail | ✅ | ✅ (with package) |

## My Recommendation

**Start with SMTP** - it's simpler, works immediately with your existing setup, and you can always switch to API later if you need advanced features.

The SMTP approach requires zero code changes - just add the environment variables and you're done!

## Troubleshooting: DNS Resolution Issues on Railway

If you encounter a DNS resolution error like:
```
getaddrinfo for smtp.mailersend.com failed: Name or service not known
```

This is a known Railway issue where DNS resolution fails. Try these steps in order:

1. **Restart the Railway service**:
   - Go to Railway dashboard → Your service → Settings
   - Click "Redeploy" or restart the service
   - This often fixes DNS resolution issues

2. **Verify environment variables are set correctly**:
   - Check that all SMTP variables are set in Railway's Variables tab
   - Ensure `MAIL_HOST=smtp.mailersend.com` (no typos)
   - Verify credentials are correct

3. **Use MailerSend API instead** (recommended if SMTP continues to fail):
   - The API uses HTTP/HTTPS, which doesn't have DNS resolution issues
   - See "Option 2: API" above for setup instructions
   - This requires installing the MailerSend package but is more reliable on Railway
