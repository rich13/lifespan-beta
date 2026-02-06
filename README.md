# Lifespan Beta

A project to map time.

## Local Development

**Start:** `docker compose up -d`  
**Stop (keeps project visible in Docker Desktop):** `docker compose stop`  
Avoid `docker compose down` unless you need to remove containers—it can make the project disappear from the Docker UI until you run `up` again.

Local Docker uses **Redis** for cache (same as production): the app and queue workers connect to the `redis` service. The public span page cache and other Laravel cache use Redis. No `.env` change needed—compose sets `CACHE_DRIVER=redis` and `REDIS_HOST=redis`.

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

### Production / timeouts (Railway or similar)

**App vs server:** The 60-second "Maximum execution time" error is **PHP’s limit** (app config), not the server size. You can fix it with **app settings** (no need to pay for a bigger Railway plan) unless the bottleneck is CPU/RAM.

- **PHP timeout (app):** The span show route uses `timeout.span-show` middleware, which sets `max_execution_time` from `SPAN_SHOW_MAX_EXECUTION_TIME` (default **120** seconds). Set this in Railway env vars only if you need a different value (e.g. 180).
- **Optional memory:** If span show hits memory limits, set `SPAN_SHOW_MEMORY_LIMIT=512M` in .env / Railway.
- **Proxy/server:** Nginx in `docker/prod/nginx.conf` uses `fastcgi_read_timeout 300`, so the app container allows long requests. If Railway or another proxy in front has a 60s gateway timeout, increase it in that layer (Railway dashboard or support); otherwise the browser will still see a timeout even if PHP runs longer.
- **Bigger server:** Only consider more CPU/RAM if, after the above and the N+1/cache fixes, span show still runs too long; a faster instance can shorten cold-cache response time.
- **Cache (Redis):** With many public spans (~30k+), use Redis for cache: set `CACHE_DRIVER=redis` and add a Redis service (e.g. Railway Redis). File cache does not scale at that volume. The public span full-page cache uses the default cache store.
- **Cache warming:** Bulk warming (deploy-time and post-invalidation rewarm job) is disabled by default to avoid triggering expensive full-page renders. Invalidation still runs so stale cache entries are not served; pages repopulate on next request. To enable rewarm after span/connection updates set `WARM_PUBLIC_SPAN_PAGES_ON_INVALIDATION=true`. Manual: `php artisan cache:warm-public-span-pages` (optionally `--limit=N`). Browsers get a short Cache-Control max-age (default 5 min via `PUBLIC_SPAN_BROWSER_MAX_AGE`) so they revalidate and receive updated server-cached content after invalidation.
- **Verify cache with Redis (production-like):** Run the same cache tests against Redis so the production path is exercised: `./scripts/run-pest-with-redis.sh` (requires Redis and test container). If Redis is not available the Redis test file skips; the main cache tests in `PublicSpanPageCacheTest` use the array driver.
