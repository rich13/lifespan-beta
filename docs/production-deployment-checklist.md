# Production Deployment Checklist

## Overview

This checklist ensures that your application is properly configured for production deployment with Sentry error monitoring and performance tracking.

## Pre-Deployment Checklist

### ✅ Environment Configuration

- [x] **Sentry DSN configured** in `.env.railway`
- [x] **Environment set to production** (`APP_ENV=production`)
- [x] **Debug mode disabled** (`APP_DEBUG=false`)
- [x] **Appropriate log level** (`LOG_LEVEL=error` for production)
- [x] **Secure session settings** (`SESSION_SECURE_COOKIE=true`)

### ✅ Sentry Configuration

- [x] **Production DSN**: `https://62d59ca36c3741769d1622c8578f4d89@o133107.ingest.us.sentry.io/294067`
- [x] **Environment tag**: `SENTRY_ENVIRONMENT=production`
- [x] **Performance sampling**: `SENTRY_TRACES_SAMPLE_RATE=0.1` (10%)
- [x] **Error sampling**: `SENTRY_SAMPLE_RATE=1.0` (100%)
- [x] **PII enabled**: `SENTRY_SEND_DEFAULT_PII=true`
- [x] **Logs enabled**: `SENTRY_ENABLE_LOGS=true`

### ✅ Security Settings

- [x] **APP_DEBUG=false** in production
- [x] **Secure cookies** enabled
- [x] **CORS origins** configured for production domain
- [x] **Session lifetime** appropriate for production
- [x] **Database credentials** using Railway environment variables

### ✅ Performance Settings

- [x] **Cache driver** configured (`CACHE_DRIVER=file`)
- [x] **Queue connection** configured (`QUEUE_CONNECTION=sync`)
- [x] **Session driver** configured (`SESSION_DRIVER=file`)
- [x] **Sentry performance monitoring** enabled

## Deployment Steps

### 1. Railway Deployment

```bash
# Deploy to Railway
railway up

# Verify deployment
railway status
```

### 2. Environment Verification

After deployment, verify these environment variables are set in Railway:

```env
APP_ENV=production
APP_DEBUG=false
SENTRY_LARAVEL_DSN=https://62d59ca36c3741769d1622c8578f4d89@o133107.ingest.us.sentry.io/294067
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_SAMPLE_RATE=1.0
SENTRY_SEND_DEFAULT_PII=true
SENTRY_ENABLE_LOGS=true
```

### 3. Post-Deployment Testing

#### Health Check
```bash
curl https://lifespan-beta.up.railway.app/health
```

Expected response:
```json
{
  "status": "healthy",
  "environment": "production",
  "database": { "status": "connected" }
}
```

#### Sentry Test
```bash
# Test Sentry from production
curl -X POST https://lifespan-beta.up.railway.app/sentry-test
```

#### Error Simulation
```bash
# Test error handling (should trigger Sentry)
curl https://lifespan-beta.up.railway.app/error?code=500
```

## Monitoring Setup

### 1. Sentry Dashboard

- [ ] **Access Sentry dashboard**: [sentry.io](https://sentry.io)
- [ ] **Verify project**: "Lifespan" project exists
- [ ] **Check environment**: Events tagged with "production"
- [ ] **Monitor performance**: Transaction monitoring active

### 2. Alert Configuration

Set up alerts in Sentry for:

- [ ] **High Error Rate**: >5% error rate
- [ ] **Performance Issues**: Response times >2 seconds
- [ ] **Critical Errors**: 500 status codes
- [ ] **Database Issues**: Connection failures
- [ ] **Memory Issues**: High memory usage

### 3. Performance Monitoring

Monitor these key areas:

- [ ] **Timeline API endpoints**
- [ ] **Database query performance**
- [ ] **Cache hit rates**
- [ ] **External API calls** (OpenAI)
- [ ] **Memory usage patterns**

## Verification Commands

### Local Testing (Before Deployment)

```bash
# Test Sentry configuration
docker-compose exec app php artisan sentry:test

# Test production environment locally
APP_ENV=production docker-compose exec app php artisan sentry:test

# Verify configuration
docker-compose exec app php artisan config:show app
```

### Production Verification

```bash
# Health check
curl https://lifespan-beta.up.railway.app/health

# Debug info (should be limited in production)
curl https://lifespan-beta.up.railway.app/debug

# Test error handling
curl https://lifespan-beta.up.railway.app/error?code=500
```

## Troubleshooting

### Common Issues

#### Sentry Not Working in Production

1. **Check DSN**: Verify DSN is correct in Railway environment
2. **Check Environment**: Ensure `SENTRY_ENVIRONMENT=production`
3. **Check Network**: Verify Railway can reach Sentry servers
4. **Check Logs**: Review Railway logs for Sentry errors

#### Performance Issues

1. **Check Sampling Rate**: Ensure `SENTRY_TRACES_SAMPLE_RATE=0.1`
2. **Monitor Costs**: Track Sentry usage in dashboard
3. **Review Queries**: Check for slow database queries
4. **Cache Performance**: Monitor cache hit rates

#### Security Issues

1. **PII Handling**: Verify `SENTRY_SEND_DEFAULT_PII=true` is appropriate
2. **Environment Separation**: Ensure production data doesn't leak to staging
3. **Access Control**: Review who has access to Sentry dashboard

### Debug Commands

```bash
# Check Railway environment
railway variables

# Check Railway logs
railway logs

# Test database connection
railway run php artisan tinker --execute="echo DB::connection()->getPdo() ? 'Connected' : 'Failed';"

# Test Sentry from Railway
railway run php artisan sentry:test
```

## Cost Management

### Monitoring Usage

- [ ] **Track error count**: Stay within 5,000/month free tier
- [ ] **Monitor transactions**: Adjust sampling rate if needed
- [ ] **Review profiles**: Monitor profile sampling usage
- [ ] **Clean up old data**: Remove resolved issues

### Optimization

- [ ] **Filter non-critical errors**: Ignore expected errors
- [ ] **Adjust sampling rates**: Reduce if approaching limits
- [ ] **Use error grouping**: Avoid duplicate error noise
- [ ] **Monitor performance impact**: Ensure Sentry doesn't slow app

## Success Criteria

### ✅ Deployment Successful

- [ ] Application responds to health check
- [ ] Sentry receives test events from production
- [ ] Error handling works correctly
- [ ] Performance monitoring active
- [ ] No critical errors in logs

### ✅ Monitoring Active

- [ ] Sentry dashboard shows production events
- [ ] Performance data being collected
- [ ] Alerts configured and working
- [ ] Error rates within acceptable limits
- [ ] Response times meeting targets

### ✅ Security Verified

- [ ] No sensitive data in error reports
- [ ] Environment properly isolated
- [ ] Access controls in place
- [ ] Secure configuration active

## Next Steps

After successful deployment:

1. **Monitor Sentry dashboard** for the first 24 hours
2. **Set up additional alerts** based on observed patterns
3. **Configure performance budgets** for timeline API
4. **Review and optimize** based on real usage data
5. **Plan scaling strategy** if approaching free tier limits

## Support Resources

- **Sentry Documentation**: [docs.sentry.io](https://docs.sentry.io)
- **Laravel Sentry Guide**: [docs.sentry.io/platforms/php/guides/laravel](https://docs.sentry.io/platforms/php/guides/laravel)
- **Railway Documentation**: [docs.railway.app](https://docs.railway.app)
- **Project Documentation**: `docs/sentry-production-setup.md` 