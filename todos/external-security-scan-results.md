# Saturn.ac — External Security Scan Results

**Date:** 2026-02-13
**Target:** https://saturn.ac (currently running saturn-dev, NOT production)
**Tools:** curl, nmap, nikto (background), nuclei (background)

---

## PRE-PRODUCTION CHECKLIST (fix before going live)

### [ ] 1. Disable Laravel Ignition in Production
```
GET https://saturn.ac/_ignition/health-check → 200
Response: {"can_execute_commands":true}
```
- Related to **CVE-2021-3129** (Remote Code Execution)
- OK for dev, **MUST disable before production**
- **Fix:** `APP_ENV=production` + `APP_DEBUG=false` in `.env`

### [ ] 2. Disable Laravel Debug Bar in Production
```
GET https://saturn.ac/_debugbar/open → 200
Header: phpdebugbar-id visible in ALL responses
```
- Debug Bar leaks: SQL queries, env vars, routes, sessions
- OK for dev, **MUST disable before production**
- **Fix:** `DEBUGBAR_ENABLED=false` in `.env`

---

## HIGH

### [ ] 3. Zero Security Headers
No security headers present at all:
```
❌ X-Frame-Options           → Clickjacking attacks possible
❌ X-Content-Type-Options    → MIME type sniffing
❌ Strict-Transport-Security → No HSTS (browser can downgrade to HTTP)
❌ Content-Security-Policy   → XSS attacks easier
❌ Referrer-Policy           → Referrer leakage
❌ Permissions-Policy        → Browser feature access unrestricted
```
Only header: `server: nginx`

**Fix:** Add to Nginx config:
```nginx
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
```

### [ ] 4. Server Version Disclosure
```
server: nginx
```
**Fix:** `server_tokens off;` in nginx.conf

---

## MEDIUM

### [ ] 5. CORS allows all origins
```
access-control-allow-origin: *
```
Any website can make API requests.
**Fix:** `config/cors.php` → restrict `allowed_origins`

---

## OK (No Issues Found)

| Check | Result | Status |
|-------|--------|--------|
| SSL/TLS | TLSv1.3 / AEAD-AES128-GCM-SHA256 | ✅ |
| Certificate | CN=saturn.ac, expires May 2026 | ✅ |
| Session cookie | secure; httponly; samesite=lax | ✅ |
| XSRF token | secure; samesite=lax | ✅ |
| .env file | 302 redirect (not accessible) | ✅ |
| .git directory | 302 redirect (not accessible) | ✅ |
| composer.json | 302 redirect (not accessible) | ✅ |
| Horizon dashboard | 403 forbidden | ✅ |
| Telescope | 302 redirect (auth required) | ✅ |
| robots.txt | Disallow: / (correct for internal) | ✅ |
| API without auth | Returns "Not found" (no data leak) | ✅ |
| Health endpoint | Returns "OK" (minimal info) | ✅ |

---

## IMMEDIATE FIX (5 minutes on server)

```bash
ssh saturn

# Edit .env
nano /data/saturn/source/.env

# Set these:
APP_ENV=production
APP_DEBUG=false
DEBUGBAR_ENABLED=false

# Restart
cd /data/saturn/source && docker compose up -d

# Verify:
curl -sI https://saturn.ac/login | grep phpdebugbar  # Should return nothing
curl -s https://saturn.ac/_ignition/health-check      # Should return 404
```

Then add security headers to Nginx config.

---

## Scan Tool Results

### Nuclei (completed)
- **6181 templates** scanned (6179 signed + 2 unsigned)
- **0 vulnerabilities found** ✅
- Scan time: 7 minutes

### Nikto (completed)
- **7966 requests** sent, 13 items reported
- Scan time: 82 minutes
- New finding: `/test.php` exists — check if it's a leftover test file and remove
- XSRF-TOKEN without httponly flag — **by design** (Laravel JS needs to read CSRF token)
- All other findings overlap with manual curl checks above

### Nmap
- DNS resolution failed (local VPN/network issue) — not a server problem
