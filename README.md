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

### Local Nominatim (London OSM / geocoding)

A Nominatim instance can run in Docker for London OSM data and experiments (e.g. generating the JSON for `/admin/osmdata` without hitting the public API).

**Data:** Uses the Greater London PBF at `temp/greater-london-260201.osm.pbf`. Ensure that file exists (or change the `nominatim` service in `docker-compose.yml` to use `PBF_URL` and a Geofabrik URL such as `https://download.geofabrik.de/europe/united-kingdom/england/greater-london-latest.osm.pbf`).

**Start:**  
`docker compose up -d nominatim`

**First run:** The container runs a one-off import (often 10–60 minutes for the London extract). Watch logs:

`docker compose logs -f nominatim`

When the import finishes, the API process starts and listens on port 8080 inside the container.

**API:**  
- From the host: **http://localhost:7001**  
- From the app container: **http://nominatim:8080**

Try: `http://localhost:7001/search?q=Camden` or `http://localhost:7001/status` (should return `OK` when ready).

To use this instance in the app for geocoding, set `NOMINATIM_BASE_URL` in `.env` (e.g. `http://nominatim:8080` when the app runs in Docker) and point `OSMGeocodingService` at that base URL.

**Generate the OSM JSON for `/admin/osmdata`:** Once Nominatim is ready, run from the app container (so it can reach `nominatim:8080`):

```bash
docker compose exec app php artisan osm:generate-london-json
```

This queries local Nominatim for London boroughs, major stations, and airports (see `config/osm_london_locations.php`) and writes `storage/app/osm/london-major-locations.json`. Use `--dry-run` to print results without writing, or `--limit=5` to test with a small batch.
