# Sentry Environment Configuration

## Overview

Sentry is designed to work across all environments, but it's most valuable in **production**. This document explains how to configure Sentry for different environments and when to use it.

## Environment-Specific Configuration

### **Production (Primary Use Case)**

Sentry is **most valuable in production** because:
- Real users encounter real errors
- Performance issues affect actual users
- You need immediate alerts for critical problems
- You can't use local debugging tools

**Configuration for Production:**
```env
APP_ENV=production
SENTRY_LARAVEL_DSN=https://your-production-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=production
SENTRY_SEND_DEFAULT_PII=true
SENTRY_TRACES_SAMPLE_RATE=0.1  # 10% sampling to manage costs
SENTRY_SAMPLE_RATE=1.0         # 100% error sampling
```

### **Staging/Testing**

Use Sentry in staging to:
- Test error reporting before production
- Catch issues before they reach users
- Validate performance monitoring

**Configuration for Staging:**
```env
APP_ENV=staging
SENTRY_LARAVEL_DSN=https://your-staging-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=staging
SENTRY_SEND_DEFAULT_PII=false  # Less sensitive data in staging
SENTRY_TRACES_SAMPLE_RATE=0.5  # 50% sampling
SENTRY_SAMPLE_RATE=1.0         # 100% error sampling
```

### **Local Development**

Sentry can be used locally for:
- Testing error reporting configuration
- Validating custom context and breadcrumbs
- Development team debugging

**Configuration for Local:**
```env
APP_ENV=local
SENTRY_LARAVEL_DSN=https://your-local-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=local
SENTRY_SEND_DEFAULT_PII=false
SENTRY_TRACES_SAMPLE_RATE=1.0  # 100% sampling for development
SENTRY_SAMPLE_RATE=1.0         # 100% error sampling
```

## Current Setup Analysis

### **Your Current Configuration**

Looking at your `.env` file:
```env
APP_ENV=local
SENTRY_LARAVEL_DSN=https://62d59ca36c3741769d1622c8578f4d89@o133107.ingest.us.sentry.io/294067
SENTRY_SEND_DEFAULT_PII=true
SENTRY_TRACES_SAMPLE_RATE=1.0
```

**What this means:**
- ✅ Sentry is active in your local environment
- ✅ Full performance monitoring (100% sampling)
- ✅ User data is being sent (PII enabled)
- ⚠️ This is using your production DSN in local environment

## Recommended Environment Setup

### **Option 1: Separate Projects (Recommended)**

Create different Sentry projects for each environment:

1. **Production Project**: `lifespan-production`
2. **Staging Project**: `lifespan-staging` 
3. **Local Project**: `lifespan-local` (optional)

### **Option 2: Single Project with Environment Tags**

Use one Sentry project but tag events by environment:

```env
# Production
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1

# Staging  
SENTRY_ENVIRONMENT=staging
SENTRY_TRACES_SAMPLE_RATE=0.5

# Local
SENTRY_ENVIRONMENT=local
SENTRY_TRACES_SAMPLE_RATE=1.0
```

## Environment-Specific Settings

### **Production Settings**

```env
# High volume, cost-conscious
SENTRY_TRACES_SAMPLE_RATE=0.1    # 10% performance monitoring
SENTRY_SAMPLE_RATE=1.0           # 100% error monitoring
SENTRY_SEND_DEFAULT_PII=true     # Full user context
SENTRY_ENABLE_LOGS=true          # Capture Laravel logs
```

### **Staging Settings**

```env
# Medium volume, testing focus
SENTRY_TRACES_SAMPLE_RATE=0.5    # 50% performance monitoring
SENTRY_SAMPLE_RATE=1.0           # 100% error monitoring
SENTRY_SEND_DEFAULT_PII=false    # Limited user data
SENTRY_ENABLE_LOGS=true          # Capture Laravel logs
```

### **Local Settings**

```env
# Low volume, development focus
SENTRY_TRACES_SAMPLE_RATE=1.0    # 100% performance monitoring
SENTRY_SAMPLE_RATE=1.0           # 100% error monitoring
SENTRY_SEND_DEFAULT_PII=false    # No user data
SENTRY_ENABLE_LOGS=false         # Don't capture logs locally
```

## When to Use Sentry

### **✅ Use Sentry in Production**

**Always use Sentry in production** for:
- Real-time error monitoring
- Performance tracking
- User impact analysis
- Critical issue alerts
- Database query monitoring
- External API error tracking

### **✅ Use Sentry in Staging**

**Recommended for staging** to:
- Test error reporting configuration
- Validate performance monitoring
- Catch issues before production
- Test alert configurations

### **❓ Use Sentry in Local (Optional)**

**Optional for local development**:
- Testing Sentry configuration
- Validating custom context
- Team debugging sessions
- **Alternative**: Use Telescope + Debugbar for local debugging

## Cost Management by Environment

### **Production Cost Optimization**

```env
# Conservative sampling to manage costs
SENTRY_TRACES_SAMPLE_RATE=0.1    # 10% of transactions
SENTRY_PROFILES_SAMPLE_RATE=0.01 # 1% of profiles
```

### **Staging Cost Optimization**

```env
# Moderate sampling for testing
SENTRY_TRACES_SAMPLE_RATE=0.5    # 50% of transactions
SENTRY_PROFILES_SAMPLE_RATE=0.1  # 10% of profiles
```

### **Local Cost Optimization**

```env
# Full sampling for development
SENTRY_TRACES_SAMPLE_RATE=1.0    # 100% of transactions
SENTRY_PROFILES_SAMPLE_RATE=1.0  # 100% of profiles
```

## Integration with Existing Tools

### **Local Development Stack**

```
Telescope + Debugbar (Primary)
    ↓
Sentry (Optional, for testing)
```

### **Production Stack**

```
Sentry (Primary)
    ↓
Application Logs (Backup)
```

### **Staging Stack**

```
Sentry (Primary)
    ↓
Application Logs (Backup)
```

## Recommended Setup for Your Project

### **Immediate Actions**

1. **Keep current setup for testing**: Your local setup is fine for testing Sentry
2. **Create production DSN**: Set up a separate production project in Sentry
3. **Configure environment variables**: Use different settings per environment

### **Environment Configuration**

**Production (.env.production):**
```env
APP_ENV=production
SENTRY_LARAVEL_DSN=https://your-production-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1
SENTRY_SEND_DEFAULT_PII=true
```

**Staging (.env.staging):**
```env
APP_ENV=staging
SENTRY_LARAVEL_DSN=https://your-staging-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=staging
SENTRY_TRACES_SAMPLE_RATE=0.5
SENTRY_SEND_DEFAULT_PII=false
```

**Local (.env):**
```env
APP_ENV=local
SENTRY_LARAVEL_DSN=https://your-local-dsn@sentry.io/project-id
SENTRY_ENVIRONMENT=local
SENTRY_TRACES_SAMPLE_RATE=1.0
SENTRY_SEND_DEFAULT_PII=false
```

## Best Practices

### **Environment Separation**

1. **Different DSNs**: Use separate Sentry projects per environment
2. **Environment Tags**: Always set `SENTRY_ENVIRONMENT`
3. **Sample Rates**: Adjust based on traffic volume
4. **PII Handling**: Be more conservative in non-production

### **Cost Management**

1. **Monitor Usage**: Track error and transaction counts
2. **Adjust Sampling**: Reduce sampling rates if approaching limits
3. **Filter Events**: Ignore non-critical errors
4. **Regular Review**: Clean up old events and projects

### **Security**

1. **DSN Security**: Keep DSNs secure and environment-specific
2. **PII Control**: Limit sensitive data in non-production
3. **Access Control**: Manage team access per environment
4. **Data Retention**: Configure appropriate retention periods

## Conclusion

**Sentry is primarily for production**, but can be valuable in staging and optionally in local development. The key is to:

1. **Use different configurations per environment**
2. **Manage costs with appropriate sampling rates**
3. **Focus on production monitoring**
4. **Use local tools (Telescope/Debugbar) for development**

Your current setup is good for testing, but you should create environment-specific configurations for production deployment. 