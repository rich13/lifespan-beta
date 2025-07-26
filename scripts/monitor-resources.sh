#!/bin/bash

# Monitor Docker resource usage and alert when it gets too high
# Usage: ./scripts/monitor-resources.sh

# Thresholds (percentage)
CPU_THRESHOLD=80
MEMORY_THRESHOLD=80

echo "Monitoring Docker resource usage..."
echo "CPU threshold: ${CPU_THRESHOLD}%"
echo "Memory threshold: ${MEMORY_THRESHOLD}%"
echo "Press Ctrl+C to stop"
echo ""

while true; do
    # Get current stats
    STATS=$(docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}\t{{.MemPerc}}")
    
    # Check each container
    echo "$STATS" | tail -n +2 | while read -r container cpu mem; do
        # Extract percentage numbers
        CPU_NUM=$(echo "$cpu" | sed 's/%//')
        MEM_NUM=$(echo "$mem" | sed 's/%//')
        
        # Check CPU threshold
        if (( $(echo "$CPU_NUM > $CPU_THRESHOLD" | bc -l) )); then
            echo "⚠️  WARNING: $container CPU usage is ${CPU_NUM}% (threshold: ${CPU_THRESHOLD}%)"
        fi
        
        # Check memory threshold
        if (( $(echo "$MEM_NUM > $MEMORY_THRESHOLD" | bc -l) )); then
            echo "⚠️  WARNING: $container memory usage is ${MEM_NUM}% (threshold: ${MEMORY_THRESHOLD}%)"
        fi
    done
    
    echo "--- $(date) ---"
    sleep 30
done 