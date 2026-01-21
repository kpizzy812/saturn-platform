# Production Deployment Plan

**Status:** In Progress
**Priority:** P1
**Created:** 2026-01-21
**Updated:** 2026-01-22

---

## Decision: Option 1 - Use Coolify Images

We chose to use Coolify's Docker images for sentinel, helper, and realtime components. This allows quick deployment without maintaining separate Docker infrastructure.

---

## Completed Configuration

### 1. Updated `config/constants.php`

```php
'helper_image' => env('HELPER_IMAGE', 'ghcr.io/coollabsio/coolify-helper'),
'realtime_image' => env('REALTIME_IMAGE', 'ghcr.io/coollabsio/coolify-realtime'),
'cdn_url' => env('CDN_URL', 'https://cdn.coollabs.io/coolify'),
'versions_url' => env('VERSIONS_URL', 'https://cdn.coollabs.io/coolify/versions.json'),
```

### 2. Updated `app/Actions/Server/CleanupDocker.php`

Changed image references from `saturn-helper`/`saturn-realtime` to `coolify-helper`/`coolify-realtime`.

### 3. Updated `app/Jobs/CleanupHelperContainersJob.php`

Now uses `config('constants.saturn.helper_image')` instead of hardcoded path.

---

## Current Coolify Versions (from CDN)

| Component | Version |
|-----------|---------|
| helper | 1.0.12 |
| realtime | 1.0.10 |
| sentinel | 0.0.18 |

---

## Remaining Tasks

- [x] Configure VERSIONS_URL (defaults to Coolify CDN)
- [x] Update image references to coolify-*
- [ ] Test deployment on production server
- [ ] Verify sentinel starts correctly with metrics
- [ ] Verify helper containers work during deployments

---

## Future: Option 2 (If Needed)

If we need to migrate to own Saturn images later:

1. **Sentinel** - Fork from [github.com/coollabsio/sentinel](https://github.com/coollabsio/sentinel) (Go, Apache-2.0)
2. **Helper** - Copy Dockerfile from [coolify/docker/coolify-helper](https://github.com/coollabsio/coolify/tree/v4.x/docker/coolify-helper) (Alpine-based)
3. Set up GitHub Container Registry
4. Create CI/CD with GitHub Actions
5. Host own versions.json endpoint

---

## Related Files

- `config/constants.php` - Image and URL configuration
- `app/Actions/Server/StartSentinel.php` - Deploys sentinel to servers
- `app/Jobs/CheckHelperImageJob.php` - Pulls helper image
- `app/Jobs/CheckAndStartSentinelJob.php` - Manages sentinel lifecycle
- `app/Actions/Server/CleanupDocker.php` - Cleans old images
