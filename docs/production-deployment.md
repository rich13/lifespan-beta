# Production Deployment Guide

## Overview

This guide covers deploying Lifespan Beta to production with proper monitoring, alerting, and incident response procedures.

## Prerequisites

- Docker and Docker Compose installed on production server
- SSL certificates for HTTPS
- Email service configured for alerts
- Monitoring service (optional: DataDog, New Relic, etc.)

## Environment Variables

Create a `.env.production` file:

```bash
# Database
DB_DATABASE=lifespan_beta_prod
DB_USERNAME=lifespan_user
DB_PASSWORD=secure_password_here

# Application
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your_app_key_here

# Monitoring
ALERT_EMAIL=admin@lifespan.dev
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/your/webhook/url

# OpenAI (for AI features)
OPENAI_API_KEY=your_openai_api_key

# Redis (for caching and queues)
REDIS_HOST=redis
REDIS_PORT=6379
```

## Deployment

### 1. Initial Setup

```bash
# Clone the repository
git clone https://github.com/your-org/lifespan-beta.git
cd lifespan-beta

# Copy production environment
cp .env.production .env

# Start production services
docker-compose -f docker-compose.prod.yml up -d

# Run migrations
docker-compose -f docker-compose.prod.yml exec app php artisan migrate --force

# Set up storage permissions
docker-compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage
```

### 2. SSL Configuration

Configure SSL certificates in `docker/nginx/ssl/` and update `docker/nginx/prod.conf`.

### 3. Monitoring Setup

The production deployment includes automatic monitoring:

- **Health checks** every 30 seconds
- **Resource monitoring** every 5 minutes
- **Email alerts** for issues
- **Log aggregation** to `/var/log/lifespan-monitor.log`

## Monitoring & Alerting

### Automated Monitoring

The `monitor` service automatically checks:

- ✅ Container health status
- ✅ CPU usage (>80% threshold)
- ✅ Memory usage (>80% threshold)
- ✅ Disk usage (>90% threshold)
- ✅ Database connectivity
- ✅ Zombie processes

### Manual Monitoring Commands

```bash
# Check container status
docker-compose -f docker-compose.prod.yml ps

# View resource usage
docker stats

# Check logs
docker-compose -f docker-compose.prod.yml logs -f app

# Test health endpoint
curl https://your-domain.com/health
```

### Alert Channels

1. **Email Alerts**: Sent to `ALERT_EMAIL`
2. **Slack Integration**: Configure `SLACK_WEBHOOK_URL`
3. **Log Files**: `/var/log/lifespan-monitor.log`

## Incident Response

### High CPU Usage (>80%)

**Symptoms:**
- Slow response times
- Timeout errors
- High CPU alerts

**Immediate Actions:**
1. Check which container is affected: `docker stats`
2. View container logs: `docker logs lifespan-app-prod`
3. Restart the affected container: `docker restart lifespan-app-prod`
4. If persistent, restart all containers: `docker-compose -f docker-compose.prod.yml restart`

**Investigation:**
1. Check for stuck processes: `docker exec lifespan-app-prod ps aux`
2. Review recent logs for errors
3. Check database connection pool
4. Verify AI service is responding

### Database Connection Issues

**Symptoms:**
- 500 errors
- Database timeout errors
- Health check failures

**Immediate Actions:**
1. Check database container: `docker logs lifespan-db-prod`
2. Restart database: `docker restart lifespan-db-prod`
3. Verify connectivity: `docker exec lifespan-app-prod php artisan tinker --execute="DB::connection()->getPdo()"`

**Investigation:**
1. Check PostgreSQL logs
2. Verify connection pool settings
3. Check for long-running queries
4. Review database configuration

### Memory Issues

**Symptoms:**
- Out of memory errors
- Container restarts
- High memory alerts

**Immediate Actions:**
1. Check memory usage: `docker stats`
2. Restart containers with memory limits
3. Clear application caches: `docker exec lifespan-app-prod php artisan cache:clear`

**Investigation:**
1. Review memory-intensive operations
2. Check for memory leaks in code
3. Optimize database queries
4. Consider increasing memory limits

### AI Service Issues

**Symptoms:**
- AI generation failures
- Timeout errors
- Empty YAML responses

**Immediate Actions:**
1. Check OpenAI API status
2. Verify API key configuration
3. Check AI service logs
4. Restart application container

**Investigation:**
1. Review AI request logs
2. Check API rate limits
3. Verify prompt formatting
4. Test with simple requests

## Maintenance Procedures

### Regular Maintenance

**Daily:**
- Review monitoring logs
- Check resource usage trends
- Verify backup completion

**Weekly:**
- Update dependencies
- Review error logs
- Check SSL certificate expiration
- Monitor disk usage

**Monthly:**
- Security updates
- Performance review
- Capacity planning
- Backup restoration test

### Backup Procedures

```bash
# Database backup
docker exec lifespan-db-prod pg_dump -U lifespan_user lifespan_beta_prod > backup_$(date +%Y%m%d_%H%M%S).sql

# Application backup
tar -czf app_backup_$(date +%Y%m%d_%H%M%S).tar.gz storage/ public/

# Full system backup
docker-compose -f docker-compose.prod.yml down
tar -czf full_backup_$(date +%Y%m%d_%H%M%S).tar.gz .
docker-compose -f docker-compose.prod.yml up -d
```

### Scaling Considerations

**Vertical Scaling:**
- Increase CPU/memory limits in `docker-compose.prod.yml`
- Add more database connections
- Optimize application code

**Horizontal Scaling:**
- Use load balancer for multiple app instances
- Implement database read replicas
- Use Redis cluster for caching

## Troubleshooting

### Common Issues

1. **Container won't start**: Check logs and resource limits
2. **Database connection refused**: Verify PostgreSQL is running and accessible
3. **SSL certificate errors**: Check certificate paths and permissions
4. **Memory exhaustion**: Increase limits or optimize code
5. **AI generation failures**: Check OpenAI API status and configuration

### Debug Commands

```bash
# Check all container statuses
docker-compose -f docker-compose.prod.yml ps

# View real-time logs
docker-compose -f docker-compose.prod.yml logs -f

# Check resource usage
docker stats --no-stream

# Test database connection
docker exec lifespan-app-prod php artisan tinker --execute="DB::connection()->getPdo()"

# Check application health
curl -v https://your-domain.com/health

# View monitoring logs
tail -f /var/log/lifespan-monitor.log
```

## Security Considerations

1. **Environment Variables**: Never commit `.env.production` to version control
2. **SSL/TLS**: Always use HTTPS in production
3. **Database Security**: Use strong passwords and limit network access
4. **Container Security**: Keep base images updated
5. **API Keys**: Rotate OpenAI API keys regularly
6. **Log Security**: Don't log sensitive information

## Performance Optimization

1. **Caching**: Use Redis for sessions and cache
2. **Database**: Optimize queries and use indexes
3. **Assets**: Use CDN for static assets
4. **Queue**: Move heavy operations to background jobs
5. **Monitoring**: Set up performance monitoring 