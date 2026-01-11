# Lifespan Beta

A project to map time.

## Local Development

### Email Testing

The project includes Mailpit for email testing in local development. When you run `docker-compose up`, Mailpit will be available at:

- **Web UI**: http://localhost:8025 (view all sent emails)
- **SMTP**: mailpit:1025 (used by the application)

All emails sent by the application will be captured in Mailpit, so you can test email functionality without sending real emails.
