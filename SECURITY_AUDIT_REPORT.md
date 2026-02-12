# Security Audit Report – Lifespan Beta

**Date:** 12 February 2026  
**Scope:** Vulnerability assessment of the Lifespan application

---

## Executive Summary

The application has several security concerns that should be addressed. The most urgent are: **exposed debug route in production**, **dependency vulnerabilities** (including symfony/http-foundation and league/commonmark), and **auth routes excluded from CSRF protection**. Earlier reconnaissance (attempts to fetch `/update.sql`, `.env`, etc.) returned 404, confirming that sensitive files are not exposed.

---

## Critical & High Priority

### 1. Debug Route Exposed in Production

**Location:** `routes/web.php` lines 76–107  
**Risk:** Information disclosure

The `/debug` route is publicly accessible and returns:

- Database connection status
- Spans count
- Full list of database table names
- PHP version
- Laravel version
- `APP_DEBUG` value

**Recommendation:** Restrict to non-production or remove entirely:

```php
Route::get('/debug', function() {
    if (app()->environment('production')) {
        abort(404);
    }
    // ... existing logic
});
```

### 2. Symfony HTTP Foundation – Authorization Bypass (CVE-2025-64500)

**Severity:** High  
**CVE:** CVE-2025-64500  

Incorrect parsing of `PATH_INFO` can lead to limited authorization bypass.

**Recommendation:** Update `symfony/http-foundation` to a patched version (e.g. 7.3.7+ or the version advised in the advisory).

### 3. League CommonMark – XSS (CVE-2025-46734)

**Severity:** Medium  
**CVE:** CVE-2025-46734  

XSS in the Attributes extension.

**Recommendation:** Upgrade `league/commonmark` to 2.7.0 or newer.

---

## Medium Priority

### 4. Auth Routes Excluded from CSRF Protection

**Location:** `app/Http/Middleware/VerifyCsrfToken.php` lines 34–41

In production (Railway), auth routes are excluded from CSRF verification:

- `auth/*`
- `login`
- `logout`
- `register`
- `password/*`

**Risk:** An attacker could craft requests that perform login, logout, registration, or password operations without a valid CSRF token, if the auth flow relies on these routes.

**Recommendation:** Re-enable CSRF for auth routes and resolve any session/CSRF issues (e.g. SameSite cookies, correct token handling) instead of disabling protection.

### 5. Potential XSS in WikipediaSpanMatcherService

**Location:** `app/Services/WikipediaSpanMatcherService.php` – `highlightMatches()`

User-controlled span names and text content are inserted into HTML without escaping:

- `$span['name']` in `title` attribute
- `$entity` in link text

**Risk:** Stored XSS if an attacker can create spans with names like `" onmouseover="alert(1)` or similar payloads.

**Recommendation:** Escape all user-controlled values before output:

```php
$replacement = '<a href="' . e($link) . '" class="' . e($classes) . '" title="' . e($span['name']) . '">' . e($entity) . '</a>';
```

### 6. Unescaped Blade Output (`{!! !!}`)

**Locations:** Multiple Blade templates

Several views use `{!! !!}` for HTML output. If the data comes from user input (e.g. descriptions, notes), this can lead to XSS.

**Examples:**

- `resources/views/components/spans/cards/description-card.blade.php` – `{!! $renderedDescription !!}`
- `resources/views/components/spans/partials/description.blade.php` – `{!! $linkedDescription !!}`
- `resources/views/research/show.blade.php` – `{!! $article['html'] !!}`

**Recommendation:** Ensure all data passed to those views is sanitised before rendering. Where HTML is intentional, use a whitelist-based sanitizer (e.g. HTML Purifier) or a safe subset of elements.

### 7. Sentry Test Route – No Protection

**Location:** `routes/web.php` – POST `/sentry-test`

The route is excluded from CSRF and has no rate limiting. It can be used to send arbitrary events to Sentry.

**Recommendation:** Limit to non-production or protect with authentication and rate limiting.

---

## Lower Priority

### 8. Additional Dependency Vulnerabilities

| Package         | Severity | CVE           | Issue                                                   |
|----------------|----------|---------------|---------------------------------------------------------|
| aws/aws-sdk-php | Medium  | CVE-2025-14761 | Key commitment issues in S3 encryption clients          |
| phpunit/phpunit | High   | CVE-2026-24765 | Unsafe deserialization (dev dependency)                 |
| psy/psysh       | Medium  | CVE-2026-25129 | Local privilege escalation (dev dependency)             |
| symfony/process | Medium  | CVE-2026-24739 | Argument escaping on Windows (MSYS2/Git Bash)           |

**Recommendation:** Run `composer update` and update to patched versions where feasible.

### 9. CORS Configuration

**Location:** `config/cors.php`

`allowed_origins` is `['*']`, which allows any origin to call the API. With `supports_credentials` disabled, cookies are not sent on cross-origin requests, reducing risk.

**Recommendation:** For production, consider restricting `allowed_origins` to known frontend domains.

### 10. Health Check Information Disclosure

**Location:** `routes/web.php` – GET `/health`

Returns `memory_usage` and `memory_peak`. This is useful for monitoring but exposes some internal metrics.

**Recommendation:** Consider omitting or restricting these fields in production, or limiting access to monitoring systems.

---

## Positive Findings

- **CSRF protection** is enabled for most web routes (except auth in production).
- **Sensitive files** (`.env`, `.sql`, config files) are not exposed; probing requests correctly return 404.
- **Security headers** are set in nginx (X-Frame-Options, X-XSS-Protection, X-Content-Type-Options, HSTS).
- **SQL injection**: Raw queries use parameterised bindings.
- **Connection creation** (`/api/connections/create`) is protected by auth middleware.
- **Span access control** is enforced via `SpanAccessMiddleware` and `isAccessibleBy()` checks.
- **`.env.example`** is in the project root, not in `public/`, so it is not directly served.

---

## Recommended Actions (Priority Order)

1. **Immediate:** Disable or restrict `/debug` in production.
2. **Immediate:** Update `symfony/http-foundation` and `league/commonmark` to patched versions.
3. **Short term:** Re-enable CSRF protection for auth routes and fix any resulting issues.
4. **Short term:** Escape user input in `WikipediaSpanMatcherService::highlightMatches()`.
5. **Short term:** Audit and sanitise all `{!! !!}` outputs that include user data.
6. **Medium term:** Update remaining dependencies with known vulnerabilities.
7. **Medium term:** Restrict CORS origins and protect the Sentry test route.
