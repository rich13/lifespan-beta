# Railway Email Configuration

This guide lists the environment variables you need to configure in Railway for email functionality.

## Required Environment Variables

Add these in Railway's dashboard under your service's **Variables** tab:

### Email Configuration (Required)

| Variable | Value | Notes |
|----------|-------|-------|
| `MAIL_MAILER` | `smtp` | Use `smtp` for most services, or `postmark` if using Postmark |
| `MAIL_HOST` | Your SMTP host | e.g., `smtp.mailgun.org`, `smtp.sendgrid.net` |
| `MAIL_PORT` | `587` | Standard SMTP port (or `465` for SSL) |
| `MAIL_USERNAME` | Your SMTP username | **Mark as Secret** |
| `MAIL_PASSWORD` | Your SMTP password | **Mark as Secret** |
| `MAIL_ENCRYPTION` | `tls` | Use `tls` for port 587, `ssl` for port 465 |
| `MAIL_FROM_ADDRESS` | `noreply@lifespan.dev` | The "from" email address |
| `MAIL_FROM_NAME` | `Lifespan` | The "from" name |

### Already Configured

These are already set in `railway.toml`:
- `APP_URL` = `https://beta.lifespan.dev` ✅

## Setting Up in Railway

1. Go to your Railway project dashboard
2. Select your service
3. Click on the **Variables** tab
4. Click **+ New Variable** for each variable above
5. **Important**: Mark `MAIL_USERNAME` and `MAIL_PASSWORD` as **Secret** (click the lock icon)

## Example Configurations

### Mailgun
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=postmaster@your-domain.mailgun.org (Secret)
MAIL_PASSWORD=your-mailgun-smtp-password (Secret)
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@lifespan.dev
MAIL_FROM_NAME=Lifespan
```

### SendGrid
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey (Secret)
MAIL_PASSWORD=your-sendgrid-api-key (Secret)
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@lifespan.dev
MAIL_FROM_NAME=Lifespan
```

### Postmark
```
MAIL_MAILER=postmark
POSTMARK_TOKEN=your-postmark-token (Secret)
MAIL_FROM_ADDRESS=noreply@lifespan.dev
MAIL_FROM_NAME=Lifespan
```

## Testing After Deployment

1. Register a new user on the production site
2. Check that:
   - User receives email verification email
   - Admin receives approval request email
3. Verify email links point to `https://beta.lifespan.dev` (not localhost)

## Troubleshooting

If emails aren't sending:
- Check Railway logs for email errors
- Verify SMTP credentials are correct
- Ensure domain/email is verified with your email service
- Check that `APP_URL` is set correctly (already done in railway.toml)
