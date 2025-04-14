#!/bin/bash

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Check if environment variables are set
if [ -z "$PGHOST" ] || [ -z "$PGPORT" ] || [ -z "$PGDATABASE" ] || [ -z "$PGUSER" ] || [ -z "$PGPASSWORD" ]; then
    log "ERROR: Missing PostgreSQL environment variables"
    exit 1
fi

# Remove any quotes from the values
PGHOST=$(echo $PGHOST | tr -d '"')
PGPORT=$(echo $PGPORT | tr -d '"')
PGDATABASE=$(echo $PGDATABASE | tr -d '"')
PGUSER=$(echo $PGUSER | tr -d '"')
PGPASSWORD=$(echo $PGPASSWORD | tr -d '"')

# Log the connection details
log "Attempting to connect to PostgreSQL database:"
log "Host: $PGHOST"
log "Port: $PGPORT"
log "Database: $PGDATABASE"
log "Username: $PGUSER"

# Try to connect to the database
log "Testing connection..."
if PGPASSWORD=$PGPASSWORD psql -h $PGHOST -p $PGPORT -U $PGUSER -d $PGDATABASE -c '\q'; then
    log "SUCCESS: Connected to database successfully"
    exit 0
else
    log "ERROR: Failed to connect to database"
    exit 1
fi 