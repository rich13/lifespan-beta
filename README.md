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

Queue workers run by default (`docker compose up`). Jobs such as the blue plaque import, Desert Island Discs bulk import, and MusicBrainz discography import run in the background: the POST returns immediately, progress is visible via polling, and imports survive page refreshes and request timeouts.

Manage workers at **Admin → Workers** (`/admin/workers`): view queue health, restart workers, force-stop an individual worker (so Desert Island Discs and MusicBrainz can be halted independently), and see active jobs.

### Geocoding (Nominatim)

The app uses the public OpenStreetMap Nominatim API (`https://nominatim.openstreetmap.org`) in local Docker and in production, so geocoding results can be reproduced after deploy. Do not set `NOMINATIM_BASE_URL` to a local instance if you want that parity.

Public Nominatim allows about **1 request per second**. Interactive search is fine; bulk jobs and `osm:generate-london-json` wait between requests. Always send a real User-Agent (the app already does).

Point geocoding comes from Nominatim search. Polygons for boroughs and regions come from Nominatim `polygon_geojson` (simplified) and, when needed, Overpass.

**Generate the OSM JSON for `/admin/osmdata`:**

```bash
docker compose exec app php artisan osm:generate-london-json
```

This queries public Nominatim for London boroughs, major stations, and airports (see `config/osm_london_locations.php`) and writes `storage/app/osm/london-major-locations.json`. Use `--dry-run` to print results without writing, or `--limit=5` to test with a small batch.

### Production / timeouts (Railway or similar)

**App vs server:** The 60-second "Maximum execution time" error is **PHP’s limit** (app config), not the server size. You can fix it with **app settings** (no need to pay for a bigger Railway plan) unless the bottleneck is CPU/RAM.

- **PHP timeout (app):** The span show route uses `timeout.span-show` middleware, which sets `max_execution_time` from `SPAN_SHOW_MAX_EXECUTION_TIME` (default **120** seconds). Set this in Railway env vars only if you need a different value (e.g. 180).
- **Optional memory:** If span show hits memory limits, set `SPAN_SHOW_MEMORY_LIMIT=512M` in .env / Railway.
- **Proxy/server:** Nginx in `docker/prod/nginx.conf` uses `fastcgi_read_timeout 300`, so the app container allows long requests. If Railway or another proxy in front has a 60s gateway timeout, increase it in that layer (Railway dashboard or support); otherwise the browser will still see a timeout even if PHP runs longer.
- **Bigger server:** Only consider more CPU/RAM if, after the above and the N+1/cache fixes, span show still runs too long; a faster instance can shorten cold-cache response time.
- **Cache (Redis):** With many public spans (~30k+), use Redis for cache: set `CACHE_DRIVER=redis` and add a Redis service (e.g. Railway Redis). File cache does not scale at that volume. The public span full-page cache uses the default cache store.
- **Cache warming:** Bulk warming (deploy-time and post-invalidation rewarm job) is disabled by default to avoid triggering expensive full-page renders. Invalidation still runs so stale cache entries are not served; pages repopulate on next request. To enable rewarm after span/connection updates set `WARM_PUBLIC_SPAN_PAGES_ON_INVALIDATION=true`. Manual: `php artisan cache:warm-public-span-pages` (optionally `--limit=N`). Browsers get a short Cache-Control max-age (default 5 min via `PUBLIC_SPAN_BROWSER_MAX_AGE`) so they revalidate and receive updated server-cached content after invalidation.
- **Verify cache with Redis (production-like):** Run the same cache tests against Redis so the production path is exercised: `./scripts/run-pest-with-redis.sh` (requires Redis and test container). If Redis is not available the Redis test file skips; the main cache tests in `PublicSpanPageCacheTest` use the array driver.

**Production cache checklist (deploy with confidence):**

- **Using file cache (current Railway default):** `.env.railway` has `CACHE_DRIVER=file`. No Redis required. Public span page caching works out of the box (storage is under `storage/framework/cache/data`). Deploy as-is and it just works.
- **If you switch to Redis:** (1) Add a Redis service (e.g. Railway’s Redis plugin). (2) Set `CACHE_DRIVER=redis`. (3) Set connection details: either `REDIS_URL` (if your host provides it) or `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` if required. (4) Redis is only used when a request touches the cache (no boot-time connection), so if Redis is misconfigured you’ll see connection errors on first cached request, not at deploy. (5) If the PHP image has no phpredis extension, set `REDIS_CLIENT=predis` (predis is in composer.json).
