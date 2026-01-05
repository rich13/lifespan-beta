## Lifespan Core Spec (bootstrap for a stripped-down fork)

This spec captures the minimum viable setup to spin up a new Lifespan-style project that reuses the same Laravel stack, database schema, and UI patterns while omitting non-essential features.

### 1) Platform & tooling
- Framework: Laravel (app currently runs in Docker; reuse docker-compose with Postgres).
- DB: PostgreSQL with UUID primary keys; Sanctum for auth; Pest for tests; Vite for asset build.
- Conventions: GB spellings, jQuery on the frontend, Blade component-driven UI, no inline JS/CSS.
- Testing: use `./scripts/run-pest.sh` wrapper (inside Docker).

### 2) Core domain model (must-keep tables)
- `spans`: single table for all entities; key fields: `id` (uuid), `name`, `type_id` (FK to span_types), `start_year`, `end_year`, `metadata` (jsonb), `access_level`.
- `span_types`: seed minimal types you want (e.g., `person`, `organisation`, `place`, `event`, `thing`, `band`, `set`, `role`, `connection`). Columns: `type_id` (pk string), `name`, `description`, `metadata`.
- `connections`: typed edges between spans. Columns: `id` (uuid), `parent_id` (uuid span), `child_id` (uuid span), `type` (FK to connection_types.type), `connection_span_id` (uuid span storing temporal data for the relationship).
- `connection_types`: defines allowable relationships. Columns: `type` (pk string), `name`, `forward_predicate`, `inverse_predicate`, `allowed_span_types` (json constraints), `constraint_type`, `description`.
- `span_hierarchy`: optional tree for grouping (parent/root uuids).
- `users`, `personal_spans`, `user_spans`: auth and per-span ACL. `user_spans` stores `access_level` overrides.
- `versions` (or equivalent history tables if present): change tracking for spans/connections.
- Materialized view `span_connections` (JSON aggregation of connections per span) for read performance.

### 3) Temporal + ontology rules
- Every relationship can have its own temporal span via `connection_span_id` referencing a `connection`-type span.
- Start/end year live on both spans and connection spans; use these for time-aware displays and constraints.
- `allowed_span_types` on `connection_types` should encode which span types can be parent/child.
- Constraint patterns (from docs): single constraint (one active), non-overlapping (e.g., residences), no constraint (e.g., friends).

### 4) Access control model
- Access levels on spans and user-span links; admins bypass checks.
- Flow: admin → full access; owner → full; else public? → read; else explicit permission? → read/write; else deny (403/404). Mirror existing policy helpers.

### 5) Minimal seed data to carry over
- `span_types` records for the subset you need.
- `connection_types` records for your supported relationships (family, relationship, friend, employment, education, membership, residence, travel, ownership, participation, has_role, has_set, created, located, contains, at_organisation, during). Trim to your chosen subset.
- A small set of spans + connections to validate UI (one person, one organisation, one place, one connection with a connection span).

### 6) Application setup steps
1. Copy `.env.example` → `.env` and configure Postgres credentials; keep Redis optional. Set `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`.
2. Install PHP deps (`composer install`) and JS deps (`npm install`) inside Docker.
3. Run migrations (and materialized view creation) in Docker: `./scripts/run-tests.sh` already sets up test DB; create a small bootstrap script to migrate + seed span/connection types.
4. Build frontend: `npm run build` (or `npm run dev -- --watch` in container).
5. Ensure Vite includes `resources/js/app.js`, `resources/js/session-bridge.js` if you keep session bridge.

### 7) Authentication & session bridge (optional keep)
- Sanctum-based auth; email-first or password login controllers issue a session-bridge token on login.
- API endpoints: POST `/api/session-bridge/restore`, `/api/session-bridge/check`, `/api/session-bridge/refresh`.
- Frontend helper `resources/js/session-bridge.js` + Blade layout hook in `resources/views/layouts/app.blade.php`; logout forms call `SessionBridge.logout()`.
- If you want a leaner fork, you can disable by removing the Vite import and routes.

### 8) Frontend/UI skeleton
- Blade layout in `resources/views/layouts/app.blade.php`; components under `resources/views/components`.
- Display pattern: cards showing span summary (name, type, lifespan years), connection chips, metadata badges.
- Use jQuery helpers; avoid inline JS/CSS; rely on shared Blade components for buttons/links.
- Routes: core pages in `routes/web.php`; API endpoints in `routes/api.php` (RESTful controllers for spans/connections).

### 9) Core use-cases to preserve
- Create/edit spans (with type selection, years, metadata JSON).
- Create typed connections between spans with temporal data (via connection spans).
- Visualise a span detail page with related spans grouped by connection type and direction.
- Hierarchical grouping via `span_hierarchy` (optional in UI).
- Basic search/listing by name/type/year.

### 10) Nice-to-have but droppable for a stripped fork
- Advanced importers (CSV/YAML), Wikidata/Wikimedia integrations.
- Session bridge (if not needed).
- Heavy story templates, micro stories, photo timeline features.
- Debug/telescope tooling; production monitoring scripts.

### 11) Project structure to mirror
- `app/Models`: Span, Connection, ConnectionType, SpanType, User, UserSpan, SpanHierarchy, Version (or History).
- `app/Http/Controllers`: CRUD for spans/connections; auth controllers; API controllers for session bridge if kept.
- `database/migrations`: carry over all core tables above; prune migrations for excluded features.
- `resources/views`: keep layout + shared components; keep span display cards; remove feature-specific blades if not needed.
- `resources/js`: keep entrypoint and minimal helpers (jQuery, session-bridge optional).
- `tests`: retain span/connection model tests and policy tests; prune feature-specific suites.

### 12) Deployment notes
- Dockerised stack targeting Railway; nginx + PHP-FPM in containers; Postgres service.
- Ensure `APP_KEY`, `APP_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS` set per environment.
- Use `logrotate` and `supervisord` configs already present as templates.

### 13) Migration checklist for the new project
- [ ] Copy base Laravel app and docker-compose scaffold.
- [ ] Migrate core migrations (spans, span_types, connections, connection_types, span_hierarchy, user/auth tables, materialized view).
- [ ] Seed minimal types and one example span/connection.
- [ ] Wire Blade layout + essential components; include jQuery and Vite pipeline.
- [ ] Expose CRUD routes/controllers for spans/connections; secure with Sanctum + policies.
- [ ] Add search/list UI for spans.
- [ ] Decide whether to include session bridge; if yes, copy controller, routes, JS, layout hook.
- [ ] Run `./scripts/run-pest.sh` to confirm green baseline.



