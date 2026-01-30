# Monorepo Detection v2 - Improvements

**Started:** 2026-01-30
**Status:** In Progress

## Overview

Enhancements to the monorepo auto-detection system for smarter infrastructure provisioning.

---

## Features to Implement

### 1. Docker Compose Detection
Parse `docker-compose.yml` to extract services and create corresponding databases/services.

```yaml
services:
  db:
    image: postgres:15  # → create PostgreSQL
  redis:
    image: redis:7      # → create Redis
  elasticsearch:
    image: elasticsearch:8  # → warn about external service
```

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/DockerComposeAnalyzer.php`

---

### 2. Port Detection from Code
Parse source code to detect ports used by the application.

```javascript
// Node.js
app.listen(process.env.PORT || 3000)
server.listen(8080)

// Python
uvicorn.run(app, port=8000)
app.run(port=5000)

// Go
http.ListenAndServe(":8080", nil)
```

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/PortDetector.php`

---

### 3. Health Check Detection
Find health check endpoints in code and configure them automatically.

```
Routes to detect:
- /health, /healthz, /health-check
- /api/health, /api/v1/health
- /ready, /readiness
- /live, /liveness
```

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/HealthCheckDetector.php`

---

### 4. Dockerfile Parsing Enhancement
Extract more information from Dockerfile:
- ENV variables
- HEALTHCHECK commands
- Build arguments
- Working directory

```dockerfile
ENV NODE_ENV=production
ENV API_URL=http://api:3000
HEALTHCHECK --interval=30s CMD curl -f http://localhost:3000/health
ARG BUILD_VERSION
WORKDIR /app
```

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/DockerfileAnalyzer.php`

---

### 5. CI/CD Configuration Detection
Parse CI/CD files to extract build/test commands.

```yaml
# .github/workflows/*.yml
jobs:
  build:
    steps:
      - run: npm ci
      - run: npm run build
      - run: npm test

# .gitlab-ci.yml
build:
  script:
    - npm ci
    - npm run build
```

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/CIConfigDetector.php`

---

### 6. Preview Deployments for Monorepo
When creating preview deployments for PRs, create previews for all apps in the monorepo group.

**Files:**
- Update `app/Jobs/GithubPreviewDeploymentJob.php`
- Update `app/Services/RepositoryAnalyzer/InfrastructureProvisioner.php`

---

### 7. App Dependencies Detection
Detect dependencies between apps in monorepo (e.g., web depends on api).

```json
// apps/web/package.json
{
  "dependencies": {
    "@monorepo/api-client": "workspace:*"
  }
}
```

**Features:**
- Deploy order (api before web)
- Auto-inject internal URLs (API_URL for web pointing to api)

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/AppDependencyDetector.php`
- Update `InfrastructureProvisioner.php`

---

### 8. Shared Packages Detection
Detect shared packages used across multiple apps.

```
packages/
  shared/        # used by web and api
  ui-components/ # used by web only
  utils/         # used by api only
```

**Features:**
- Show warning about shared dependencies
- Suggest build order

**Files:**
- `app/Services/RepositoryAnalyzer/Detectors/SharedPackageDetector.php`

---

## Implementation Order

1. **Phase 1: Core Detectors** (High Priority) ✅
   - [x] DockerComposeAnalyzer
   - [x] PortDetector
   - [x] HealthCheckDetector
   - [x] DockerfileAnalyzer enhancement (ENV, EXPOSE, ARG, WORKDIR, HEALTHCHECK, ENTRYPOINT, CMD, LABEL parsing)

2. **Phase 2: CI/CD & Dependencies** (Medium Priority) ✅
   - [x] CIConfigDetector
   - [x] AppDependencyDetector
   - [ ] SharedPackageDetector (integrated into AppDependencyDetector)

3. **Phase 3: Preview Deployments** (Medium Priority) ✅
   - [x] Monorepo preview deployment support

4. **Phase 4: Integration & Testing** ✅
   - [x] Update RepositoryAnalyzer to use new detectors
   - [ ] Update frontend to show new information
   - [x] Add unit tests (93 tests passing)

---

## DTOs to Add

```php
// DockerComposeService
readonly class DockerComposeService {
    public function __construct(
        public string $name,
        public string $image,
        public array $ports,
        public array $environment,
        public ?string $healthcheck,
    ) {}
}

// DetectedHealthCheck
readonly class DetectedHealthCheck {
    public function __construct(
        public string $path,
        public string $method = 'GET',
        public int $interval = 30,
        public int $timeout = 5,
    ) {}
}

// AppDependency
readonly class AppDependency {
    public function __construct(
        public string $appName,
        public array $dependsOn,  // app names this app depends on
        public array $internalUrls,  // URLs to inject
    ) {}
}

// CIConfig
readonly class CIConfig {
    public function __construct(
        public ?string $installCommand,
        public ?string $buildCommand,
        public ?string $testCommand,
        public ?string $startCommand,
    ) {}
}
```
