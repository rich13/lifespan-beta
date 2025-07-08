# Sentry Production Error Monitoring Setup

## Overview

Sentry is a powerful error monitoring and performance tracking platform that provides real-time insights into application errors and performance issues. This document outlines how to configure Sentry for the Lifespan application.

## Why Sentry Over Flare?

### Advantages for Lifespan
- **Better Free Tier**: 5,000 errors/month (vs Flare's 2,000)
- **Performance Monitoring**: Built-in transaction monitoring perfect for timeline API
- **More Established**: Larger community and better documentation
- **Cost-Effective**: Better value as you scale
- **Production-Ready**: More mature platform with better reliability

### Perfect for Your Use Case
- Complex timeline performance optimizations
- Database-heavy operations with temporal constraints
- AI YAML generation features
- User authentication and authorization
- Import/export functionality

## Current Setup Status

✅ **Sentry is installed and configured**
- Package: `sentry/sentry-laravel`
- Configuration: `config/sentry.php`
- Environment variables: Added to `.env`
- Test events: Successfully sent

## Configuration

### Environment Variables

Your `.env` file now includes:

```env
SENTRY_LARAVEL_DSN=https://62d59ca36c3741769d1622c8578f4d89@o133107.ingest.us.sentry.io/294067
SENTRY_SEND_DEFAULT_PII=true
SENTRY_TRACES_SAMPLE_RATE=1.0
```

### Configuration Options

- **SENTRY_LARAVEL_DSN**: Your Sentry project's DSN (Data Source Name)
- **SENTRY_SEND_DEFAULT_PII**: Send user data (IP, user agent, etc.)
- **SENTRY_TRACES_SAMPLE_RATE**: Performance monitoring sample rate (1.0 = 100%)

## Features Enabled

### 1. Error Monitoring
- Automatic exception capture
- Stack traces with context
- User information and session data
- Request details and headers

### 2. Performance Monitoring
- Transaction tracking
- Database query monitoring
- Slow query detection
- Timeline API performance insights

### 3. User Context
- User ID and email (when authenticated)
- Session information
- Request metadata

## Integration with Existing Code

### RequestResponseLogger Middleware

Your existing `RequestResponseLogger` middleware already has Sentry integration:

```php
// For 500 errors, include Sentry event ID
if ($status >= 500 && app()->bound('sentry')) {
    $data['sentry_id'] = app('sentry')->getLastEventId();
}
```

This provides correlation between your application logs and Sentry events.

## Performance Monitoring for Timeline API

### Custom Performance Tracking

Add performance monitoring to your timeline endpoints:

```php
// In your timeline controllers
use Sentry\SentrySdk;

public function timeline(Span $span)
{
    $transaction = SentrySdk::getCurrentHub()->startTransaction(
        new TransactionContext('timeline.load', 'timeline')
    );
    
    try {
        // Your timeline logic here
        $connections = $this->getTimelineConnections($span);
        
        $transaction->setData('span_id', $span->id);
        $transaction->setData('connections_count', count($connections));
        
        return response()->json($connections);
    } finally {
        $transaction->finish();
    }
}
```

### Database Query Monitoring

Sentry automatically captures slow database queries. You can also add custom spans:

```php
use Sentry\SentrySdk;

$span = SentrySdk::getCurrentHub()->startSpan(
    new SpanContext('db.query', 'database')
);

try {
    $results = DB::table('connections')->where('parent_id', $spanId)->get();
    $span->setData('query_type', 'connections_by_span');
    $span->setData('results_count', count($results));
} finally {
    $span->finish();
}
```

## Security and Privacy

### Data Protection
- **PII Handling**: `SENTRY_SEND_DEFAULT_PII=true` sends user data
- **IP Addresses**: Automatically captured (can be anonymized)
- **Sensitive Data**: Avoid logging passwords, API keys, or personal information

### Environment Separation
- Use different Sentry projects for staging and production
- Configure different DSNs per environment
- Set appropriate sample rates per environment

## Monitoring Strategy

### Critical Areas to Monitor

1. **Timeline API Endpoints**
   - Performance bottlenecks
   - Database query issues
   - Cache problems
   - Memory usage

2. **Import/Export Operations**
   - File processing errors
   - Memory issues
   - Validation failures
   - Processing time

3. **Authentication & Authorization**
   - Login failures
   - Permission errors
   - Session issues
   - Rate limiting

4. **AI YAML Generation**
   - OpenAI API errors
   - Processing failures
   - Memory consumption
   - Response times

### Alert Configuration

Set up alerts in Sentry for:
- **High Error Rates**: >5% error rate
- **Performance Issues**: Response times >2 seconds
- **Critical Errors**: 500 status codes
- **Database Issues**: Connection failures
- **Memory Issues**: High memory usage

## Cost Considerations

### Free Tier (Hobby Plan)
- **5,000 errors/month**
- **30-day retention**
- **Performance monitoring**
- **Basic features**

### Paid Plans
- **Team**: $26/month for 50,000 errors
- **Business**: $80/month for 100,000 errors
- **Enterprise**: Custom pricing

### Cost Optimization
- Filter out non-critical errors
- Use error grouping effectively
- Monitor usage to stay within limits
- Adjust sample rates based on traffic

## Best Practices

### Error Reporting
1. **Don't Over-Report**: Focus on actionable errors
2. **Add Context**: Include relevant application state
3. **Group Similar Errors**: Avoid noise from repeated issues
4. **Monitor Performance**: Track error reporting overhead

### Performance Monitoring
1. **Key Transactions**: Monitor critical user journeys
2. **Database Queries**: Track slow queries and N+1 problems
3. **External APIs**: Monitor OpenAI and other external services
4. **Memory Usage**: Track memory consumption patterns

### Maintenance
1. **Regular Review**: Check error reports weekly
2. **Clean Up**: Remove resolved issues
3. **Update Context**: Keep custom context relevant
4. **Monitor Costs**: Track usage and optimize

## Integration with Existing Tools

### With Telescope
- **Telescope**: Local development debugging
- **Sentry**: Production error monitoring
- **Complementary**: Both serve different purposes

### With Debugbar
- **Debugbar**: Real-time local debugging
- **Sentry**: Production monitoring and alerting
- **No Conflict**: Different environments

## Troubleshooting

### Common Issues
- **DSN Issues**: Verify DSN is correct and active
- **No Reports**: Check network connectivity and firewall
- **Performance Impact**: Monitor error reporting overhead
- **Data Privacy**: Review what data is being sent

### Testing
- Test events are automatically sent during setup
- Use Sentry's test event feature to verify configuration
- Monitor the Sentry dashboard for incoming events

### Support
- Sentry documentation: [docs.sentry.io](https://docs.sentry.io)
- Laravel integration: [docs.sentry.io/platforms/php/guides/laravel](https://docs.sentry.io/platforms/php/guides/laravel)
- Community support available

## Commands

### Sentry Commands

```bash
# Publish Sentry configuration
php artisan sentry:publish

# Test Sentry configuration
php artisan sentry:test

# Clear Sentry cache
php artisan sentry:clear
```

## Dashboard Access

- **URL**: [sentry.io](https://sentry.io)
- **Project**: Lifespan (automatically created)
- **Features**: Error tracking, performance monitoring, releases, alerts

## Conclusion

Sentry provides excellent value for production error monitoring and performance tracking. The setup is complete and ready for production use. The combination of error monitoring and performance tracking makes it particularly valuable for your timeline-heavy application.

### Next Steps
1. Monitor the Sentry dashboard for incoming events
2. Set up alerts for critical issues
3. Configure performance monitoring for timeline endpoints
4. Review and optimize based on usage patterns 