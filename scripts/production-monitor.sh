#!/bin/bash

# Production monitoring script for Docker resource usage
# Can be run as a cron job or service
# Usage: ./scripts/production-monitor.sh

# Configuration
ALERT_EMAIL="admin@lifespan.dev"
LOG_FILE="/var/log/lifespan-monitor.log"
CPU_THRESHOLD=80
MEMORY_THRESHOLD=80
DISK_THRESHOLD=90

# Timestamp
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Function to log messages
log_message() {
    echo "[$TIMESTAMP] $1" | tee -a "$LOG_FILE"
}

# Function to send alert
send_alert() {
    local subject="$1"
    local message="$2"
    
    # Log the alert
    log_message "ALERT: $subject - $message"
    
    # Send email (requires mail command configured)
    if command -v mail &> /dev/null; then
        echo "$message" | mail -s "Lifespan Alert: $subject" "$ALERT_EMAIL"
    fi
    
    # Could also send to Slack, PagerDuty, etc.
    # curl -X POST -H 'Content-type: application/json' --data "{\"text\":\"$message\"}" $SLACK_WEBHOOK_URL
}

# Check if Docker is running
if ! docker info &> /dev/null; then
    send_alert "Docker Down" "Docker daemon is not running"
    exit 1
fi

# Get container stats
STATS=$(docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemPerc}}\t{{.NetIO}}\t{{.BlockIO}}")

# Check each container
echo "$STATS" | tail -n +2 | while read -r container cpu mem netio blockio; do
    # Extract percentage numbers
    CPU_NUM=$(echo "$cpu" | sed 's/%//')
    MEM_NUM=$(echo "$mem" | sed 's/%//')
    
    # Check CPU threshold
    if (( $(echo "$CPU_NUM > $CPU_THRESHOLD" | bc -l) )); then
        send_alert "High CPU Usage" "Container $container CPU usage is ${CPU_NUM}% (threshold: ${CPU_THRESHOLD}%)"
    fi
    
    # Check memory threshold
    if (( $(echo "$MEM_NUM > $MEMORY_THRESHOLD" | bc -l) )); then
        send_alert "High Memory Usage" "Container $container memory usage is ${MEM_NUM}% (threshold: ${MEMORY_THRESHOLD}%)"
    fi
done

# Check disk usage
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
if [ "$DISK_USAGE" -gt "$DISK_THRESHOLD" ]; then
    send_alert "High Disk Usage" "Disk usage is ${DISK_USAGE}% (threshold: ${DISK_THRESHOLD}%)"
fi

# Check container health
UNHEALTHY=$(docker ps --format "table {{.Names}}\t{{.Status}}" | grep -i unhealthy || true)
if [ ! -z "$UNHEALTHY" ]; then
    send_alert "Unhealthy Containers" "Found unhealthy containers: $UNHEALTHY"
fi

# Check for zombie processes
ZOMBIES=$(ps aux | grep -w Z | wc -l)
if [ "$ZOMBIES" -gt 5 ]; then
    send_alert "Zombie Processes" "Found $ZOMBIES zombie processes"
fi

log_message "Monitoring check completed" 