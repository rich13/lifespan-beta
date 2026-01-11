# Testing Email Locally

You have two options for testing emails locally:

## Option 1: Use Mailpit (Recommended for Local Development)

**Why use Mailpit:**
- ✅ Already set up in docker-compose
- ✅ No real emails sent
- ✅ View all emails in a web interface
- ✅ Test email links work correctly
- ✅ Fast and free

**How it works:**
1. Mailpit is already running when you do `docker-compose up`
2. Your `.env` should have:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=mailpit
   MAIL_PORT=1025
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@lifespan.dev
   MAIL_FROM_NAME="Lifespan"
   APP_URL=http://localhost:8000
   ```

3. **View emails**: Open http://localhost:8025 in your browser
4. All emails sent by your app will appear there
5. You can click links in the emails - they'll use `http://localhost:8000` (from your `APP_URL`)

**This is the best option for daily development!**

## Option 2: Use MailerSend SMTP Locally

**Why use MailerSend locally:**
- ✅ Test with the actual production email service
- ✅ Verify emails are formatted correctly
- ✅ Test deliverability
- ✅ See how emails look in real email clients

**How to set it up:**

1. **Add MailerSend credentials to your local `.env`**:
   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.mailersend.com
   MAIL_PORT=587
   MAIL_USERNAME=your-mailersend-username
   MAIL_PASSWORD=your-mailersend-token
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME="Lifespan"
   APP_URL=http://localhost:8000
   ```

2. **Important**: Make sure `APP_URL=http://localhost:8000` so email links point to localhost

3. **Test it**:
   - Register a new user
   - Check your real email inbox
   - Click the links - they'll point to `http://localhost:8000`

## Recommended Approach

**For daily development**: Use **Mailpit** (Option 1)
- Fast, no real emails, easy to test

**Before deploying**: Switch to **MailerSend** (Option 2) temporarily
- Verify emails look correct
- Test that links work
- Then switch back to Mailpit for continued development

## Quick Switch Between Mailpit and MailerSend

You can easily switch by changing your `.env`:

**Mailpit (local development):**
```env
MAIL_HOST=mailpit
MAIL_PORT=1025
```

**MailerSend (testing production service):**
```env
MAIL_HOST=smtp.mailersend.com
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-token
```

Just restart your Docker containers after changing:
```bash
docker-compose restart app
```

## Testing Checklist

When testing emails locally, verify:

- [ ] Email verification email is sent to user
- [ ] Approval request email is sent to admins
- [ ] Links in emails use correct URL (`http://localhost:8000` for local, `https://beta.lifespan.dev` for production)
- [ ] Email formatting looks correct
- [ ] All email content is correct
