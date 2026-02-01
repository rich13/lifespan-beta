# Lifespan Beta

A project to map time.

## Local Development

**Start:** `docker compose up -d`  
**Stop (keeps project visible in Docker Desktop):** `docker compose stop`  
Avoid `docker compose down` unless you need to remove containers—it can make the project disappear from the Docker UI until you run `up` again.

### Email Testing

The project includes Mailpit for email testing in local development. When you run `docker-compose up`, Mailpit will be available at:

- **Web UI**: http://localhost:8025 (view all sent emails)
- **SMTP**: mailpit:1025 (used by the application)

All emails sent by the application will be captured in Mailpit, so you can test email functionality without sending real emails.

### Queue Workers

Queue workers run by default (`docker compose up`). Jobs such as the blue plaque import run in the background: the POST returns immediately, progress is visible via polling, and imports survive page refreshes and request timeouts.

Manage workers at **Admin → Workers** (`/admin/workers`): view queue health, restart workers, and see active jobs.
