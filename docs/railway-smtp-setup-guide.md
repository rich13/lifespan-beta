# Setting Up SMTP Email on Railway

Railway doesn't provide SMTP hosting - you need to use a third-party email service. This guide walks you through the process.

## Step 1: Choose an Email Service

Popular options:
- **Mailgun** (Recommended) - Easy setup, good free tier
- **SendGrid** - Popular, good free tier
- **Postmark** - Great for transactional emails
- **AWS SES** - Very cheap, but more complex setup

## Step 2: Sign Up and Get SMTP Credentials

### Option A: Mailgun (Recommended)

1. **Sign up**: https://www.mailgun.com/
2. **Verify your domain** (or use their sandbox for testing):
   - Go to Sending → Domains
   - Add your domain (e.g., `lifespan.dev`)
   - Add the DNS records they provide to your domain
3. **Get SMTP credentials**:
   - Go to Sending → Domain Settings
   - Click on your domain
   - Scroll to "SMTP credentials"
   - Copy the SMTP host, port, username, and password

**Mailgun SMTP Details:**
- Host: `smtp.mailgun.org`
- Port: `587`
- Username: `postmaster@your-domain.mailgun.org`
- Password: (from Mailgun dashboard)

### Option B: SendGrid

1. **Sign up**: https://sendgrid.com/
2. **Create an API key**:
   - Go to Settings → API Keys
   - Create a new API key with "Mail Send" permissions
3. **Use SMTP**:
   - Host: `smtp.sendgrid.net`
   - Port: `587`
   - Username: `apikey`
   - Password: (your API key from step 2)

### Option C: Postmark

1. **Sign up**: https://postmarkapp.com/
2. **Verify your domain**
3. **Get server token**:
   - Go to Servers → Your Server → API Tokens
   - Copy the Server API Token

## Step 3: Add Variables to Railway

1. **Go to Railway Dashboard**:
   - Navigate to your project
   - Click on your service
   - Go to the **Variables** tab

2. **Add Email Variables**:

   For **Mailgun** or **SendGrid** (SMTP):
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailgun.org          (or smtp.sendgrid.net)
   MAIL_PORT=587
   MAIL_USERNAME=your-username-here    (Mark as Secret 🔒)
   MAIL_PASSWORD=your-password-here    (Mark as Secret 🔒)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@lifespan.dev
   MAIL_FROM_NAME=Lifespan
   ```

   For **Postmark**:
   ```
   MAIL_MAILER=postmark
   POSTMARK_TOKEN=your-token-here       (Mark as Secret 🔒)
   MAIL_FROM_ADDRESS=noreply@lifespan.dev
   MAIL_FROM_NAME=Lifespan
   ```

3. **Mark sensitive values as Secret**:
   - Click the lock icon 🔒 next to `MAIL_USERNAME`, `MAIL_PASSWORD`, and `POSTMARK_TOKEN`
   - This prevents them from being visible in logs

## Step 4: Verify Configuration

1. **Redeploy your service** (Railway will automatically redeploy when you add variables)
2. **Test the email flow**:
   - Register a new user on your production site
   - Check that verification emails are sent
   - Check that admin approval emails are sent

## Quick Start: Mailgun Setup

If you want the fastest setup, here's the Mailgun quick start:

1. **Sign up for Mailgun**: https://www.mailgun.com/
2. **Use their sandbox domain** (for testing - no DNS setup needed):
   - Go to Sending → Domains
   - Use the default sandbox domain (e.g., `sandbox12345.mailgun.org`)
   - Get SMTP credentials from Domain Settings
3. **Add to Railway**:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailgun.org
   MAIL_PORT=587
   MAIL_USERNAME=postmaster@sandbox12345.mailgun.org (Secret)
   MAIL_PASSWORD=your-mailgun-password (Secret)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@sandbox12345.mailgun.org
   MAIL_FROM_NAME=Lifespan
   ```

**Note**: Sandbox domains can only send to verified email addresses. For production, verify your own domain.

## Troubleshooting

### Emails not sending?
- Check Railway logs for SMTP errors
- Verify credentials are correct
- Ensure domain is verified (for Mailgun/SendGrid)
- Check spam folder
- Verify `APP_URL` is set correctly (already done: `https://beta.lifespan.dev`)

### "Connection refused" errors?
- Check `MAIL_HOST` and `MAIL_PORT` are correct
- Verify `MAIL_ENCRYPTION` matches the port (tls for 587, ssl for 465)

### "Authentication failed" errors?
- Double-check `MAIL_USERNAME` and `MAIL_PASSWORD`
- Ensure they're marked as Secret in Railway
- For SendGrid, username must be exactly `apikey`
