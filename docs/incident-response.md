# Incident Response Checklist

## 🚨 Emergency Response (0-5 minutes)

### High CPU Usage
- [ ] Check `docker stats` for container usage
- [ ] Identify which container is affected
- [ ] Restart the problematic container: `docker restart container-name`
- [ ] If persistent, restart all: `docker-compose -f docker-compose.prod.yml restart`
- [ ] Check logs: `docker logs container-name`

### Database Issues
- [ ] Check database container: `docker logs lifespan-db-prod`
- [ ] Restart database: `docker restart lifespan-db-prod`
- [ ] Test connection: `curl https://your-domain.com/health`
- [ ] Check PostgreSQL logs for errors

### Application Down
- [ ] Check all containers: `docker-compose -f docker-compose.prod.yml ps`
- [ ] Check health endpoint: `curl https://your-domain.com/health`
- [ ] Restart application: `docker restart lifespan-app-prod`
- [ ] Check nginx: `docker restart lifespan-nginx-prod`

### Memory Issues
- [ ] Check memory usage: `docker stats`
- [ ] Clear caches: `docker exec lifespan-app-prod php artisan cache:clear`
- [ ] Restart containers with memory limits
- [ ] Check for memory leaks in logs

## 🔍 Investigation (5-30 minutes)

### Gather Information
- [ ] Check monitoring logs: `tail -f /var/log/lifespan-monitor.log`
- [ ] Review application logs: `docker-compose -f docker-compose.prod.yml logs -f app`
- [ ] Check database logs: `docker logs lifespan-db-prod`
- [ ] Review recent deployments/changes
- [ ] Check external dependencies (OpenAI API, etc.)

### Identify Root Cause
- [ ] Is it a resource issue? (CPU, memory, disk)
- [ ] Is it a network issue? (database connections, API calls)
- [ ] Is it a code issue? (recent deployment, configuration)
- [ ] Is it an external dependency? (OpenAI, third-party services)

### Implement Fix
- [ ] Apply immediate fix (restart, rollback, etc.)
- [ ] Monitor for resolution
- [ ] Document the incident
- [ ] Plan preventive measures

## 📊 Post-Incident (30+ minutes)

### Documentation
- [ ] Record incident timeline
- [ ] Document root cause
- [ ] List actions taken
- [ ] Note lessons learned

### Prevention
- [ ] Update monitoring thresholds if needed
- [ ] Add additional alerting
- [ ] Implement preventive measures
- [ ] Update runbooks

### Communication
- [ ] Notify stakeholders
- [ ] Update status page
- [ ] Send incident report
- [ ] Schedule post-mortem if needed

## 🛠️ Common Commands

```bash
# Quick health check
curl -s https://your-domain.com/health | jq .

# Check all containers
docker-compose -f docker-compose.prod.yml ps

# View resource usage
docker stats --no-stream

# Check specific container logs
docker logs lifespan-app-prod --tail 100

# Restart specific service
docker-compose -f docker-compose.prod.yml restart app

# Check database connection
docker exec lifespan-app-prod php artisan tinker --execute="DB::connection()->getPdo()"

# Clear application caches
docker exec lifespan-app-prod php artisan cache:clear
docker exec lifespan-app-prod php artisan config:clear

# Check monitoring logs
tail -f /var/log/lifespan-monitor.log
```

## 📞 Emergency Contacts

- **Primary Admin**: [Your Contact]
- **Backup Admin**: [Backup Contact]
- **Hosting Provider**: [Provider Support]
- **OpenAI Support**: [If AI issues]

## 🔗 Useful Links

- **Application**: https://your-domain.com
- **Health Check**: https://your-domain.com/health
- **Monitoring Dashboard**: [Your monitoring URL]
- **Documentation**: [Your docs URL] 