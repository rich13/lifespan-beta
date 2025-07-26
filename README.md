# Lifespan Beta

A project to map time.

## Development

### Prerequisites
- Docker and Docker Compose
- Node.js (for local development)

### Quick Start
1. Clone the repository
2. Run `docker-compose up -d`
3. Access the application at `http://localhost:8000`

### Resource Management

#### Monitoring
To monitor Docker resource usage:
```bash
./scripts/monitor-resources.sh
```

#### Cleanup
If you experience high CPU usage or stuck processes:
```bash
./scripts/cleanup-processes.sh
```

#### Resource Limits
The application has been configured with resource limits:
- **App Container**: 2 CPU cores, 2GB memory
- **Database Container**: 1 CPU core, 1GB memory

### Troubleshooting

#### High CPU Usage
If you see high CPU usage (>80%):
1. Run the cleanup script: `./scripts/cleanup-processes.sh`
2. Check for stuck processes: `docker stats`
3. Restart containers if needed: `docker-compose restart`

#### Database Connection Issues
If you see database connection errors:
1. Check if the database container is healthy: `docker-compose ps`
2. Restart the database: `docker-compose restart db`
3. Check logs: `docker-compose logs db`

### AI Features
The application includes AI-powered YAML generation for spans. Make sure you have:
- OpenAI API key configured
- Sufficient system resources (AI requests can be CPU-intensive)
