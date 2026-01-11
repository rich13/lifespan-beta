# Super-Minimal Email Service Alternatives

Here are the simplest email services for sending transactional emails:

## 1. Resend (Recommended - Simplest)

**Why it's minimal:**
- Modern, developer-friendly API
- Simple setup (just an API key)
- Great free tier (3,000 emails/month)
- Built for developers

**Setup:**
1. Sign up: https://resend.com/
2. Get your API key from the dashboard
3. Add to Railway:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.resend.com
   MAIL_PORT=465
   MAIL_USERNAME=resend
   MAIL_PASSWORD=your-api-key-here (Secret 🔒)
   MAIL_ENCRYPTION=ssl
   MAIL_FROM_ADDRESS=onboarding@resend.dev (or verify your domain)
   MAIL_FROM_NAME=Lifespan
   ```

**Note**: You can use `onboarding@resend.dev` for testing, or verify your own domain.

## 2. Brevo (formerly Sendinblue)

**Why it's minimal:**
- Very simple interface
- Generous free tier (300 emails/day)
- Easy SMTP setup
- No credit card required for free tier

**Setup:**
1. Sign up: https://www.brevo.com/
2. Go to SMTP & API → SMTP
3. Create an SMTP key
4. Add to Railway:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=smtp-relay.brevo.com
   MAIL_PORT=587
   MAIL_USERNAME=your-email@example.com
   MAIL_PASSWORD=your-smtp-key (Secret 🔒)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-email@example.com
   MAIL_FROM_NAME=Lifespan
   ```

## 3. SMTP2GO

**Why it's minimal:**
- Pure SMTP service, nothing fancy
- Simple dashboard
- Free tier: 1,000 emails/month
- Very straightforward

**Setup:**
1. Sign up: https://www.smtp2go.com/
2. Go to Settings → SMTP Users
3. Create an SMTP user
4. Add to Railway:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=mail.smtp2go.com
   MAIL_PORT=587
   MAIL_USERNAME=your-smtp-username
   MAIL_PASSWORD=your-smtp-password (Secret 🔒)
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your-email@example.com
   MAIL_FROM_NAME=Lifespan
   ```

## 4. Postmark (Simple but Paid)

**Why it's minimal:**
- Very simple, focused on transactional emails
- Great deliverability
- Simple API
- **Note**: Paid service (but very affordable)

**Setup:**
1. Sign up: https://postmarkapp.com/
2. Get your Server API Token
3. Add to Railway:
   ```
   MAIL_MAILER=postmark
   POSTMARK_TOKEN=your-server-token (Secret 🔒)
   MAIL_FROM_ADDRESS=noreply@yourdomain.com
   MAIL_FROM_NAME=Lifespan
   ```

## Comparison

| Service | Free Tier | Setup Complexity | Best For |
|---------|-----------|------------------|----------|
| **Resend** | 3,000/month | ⭐⭐⭐⭐⭐ Easiest | Modern apps, developers |
| **Brevo** | 300/day | ⭐⭐⭐⭐ Very Easy | Getting started |
| **SMTP2GO** | 1,000/month | ⭐⭐⭐⭐ Very Easy | Simple SMTP needs |
| **Postmark** | Paid | ⭐⭐⭐⭐ Very Easy | Production apps |

## Recommendation

**For super-minimal setup**: **Resend** is probably your best bet:
- Simplest API
- Modern developer experience
- Good free tier
- Can use `onboarding@resend.dev` for testing (no domain verification needed initially)

**For maximum free emails**: **Brevo** (300/day = 9,000/month)

**For pure simplicity**: **SMTP2GO** (just SMTP, nothing else)
