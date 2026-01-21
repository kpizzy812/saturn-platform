# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Saturn Platform is a self-hosted PaaS (Platform as a Service) - an open-source alternative to Heroku/Netlify/Vercel. It manages servers, applications, and databases via SSH connections, supporting Docker-based deployments.

**Tech Stack:**
- Backend: Laravel 12, PHP 8.4, PostgreSQL 15, Redis 7
- Frontend: React 18 + TypeScript + Inertia.js (new), Livewire 3 + Alpine.js (legacy)
- Infrastructure: Docker, Traefik/Caddy proxy, Soketi WebSocket server

## Essential Commands

### Development Environment (Docker-based)
```bash
# Start all services
make dev                    # or: docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

# Shell access
make shell                  # Laravel container shell
make db-shell               # PostgreSQL shell
make redis-shell            # Redis shell

# Laravel commands (run inside Docker)
make install                # composer install
make migrate                # Run migrations
make fresh                  # Fresh migrate + seed
```

### Testing

**CRITICAL: Feature tests MUST run inside Docker container**

```bash
# Unit tests (no database, can run outside Docker)
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Unit/SomeSpecificTest.php

# Feature tests (require database, MUST run in Docker)
docker exec saturn php artisan test
docker exec saturn php artisan test --filter=SomeTest

# Frontend tests
npm run test
npm run test:ui             # With UI
```

### Code Quality
```bash
./vendor/bin/pint           # PHP code formatting (always run before commit)
./vendor/bin/phpstan analyse # Static analysis
./vendor/bin/rector process --dry-run  # Check refactoring suggestions
```

### Frontend Build
```bash
npm run dev                 # Development with HMR
npm run build               # Production build
```

## Architecture Overview

### Backend Structure (`app/`)
- **Actions/** - Business logic using Action pattern (deploy, backup, server management)
- **Jobs/** - 49+ background jobs for async operations (deployments, backups, monitoring)
- **Models/** - 60+ Eloquent models including polymorphic databases (8 types: PostgreSQL, MySQL, MongoDB, Redis, etc.)
- **Events/** - WebSocket events for real-time updates
- **Policies/** - Team-based authorization (multi-tenant)
- **Http/Controllers/Api/** - REST API v1 (89+ endpoints)

### Frontend Structure (`resources/js/`)
- **pages/** - React pages (137 pages, Inertia.js-based)
- **components/ui/** - Base UI components (Button, Input, Modal, etc.)
- **hooks/** - Custom React hooks for API operations (useApplications, useDeployments, etc.)
- **types/** - TypeScript definitions

### Key Models Hierarchy
```
Team → Project → Environment → Application/Service/Database
                              ↓
                           Server (deployment target)
```

### Deployment Flow
1. User triggers deploy → Creates `ApplicationDeploymentQueue` entry
2. `ApplicationDeploymentJob` runs via Laravel Queue
3. SSH to target server → Git clone/pull → Docker build → Container update
4. WebSocket events broadcast status updates to frontend

## Testing Guidelines

### Unit vs Feature Tests
- **Unit tests** (`tests/Unit/`): No database, use Mockery for models, run anywhere
- **Feature tests** (`tests/Feature/`): May use database, MUST run inside Docker

```php
// Unit test - use mocking
$server = Mockery::mock('App\Models\Server');
$server->shouldReceive('proxyType')->andReturn('traefik');

// Feature test - can use factories (Docker only)
$server = Server::factory()->create(['ip' => '1.2.3.4']);
```

### Test Execution
Never run Feature tests outside Docker - they will fail with database connection errors.

## Key Patterns

### Team-Based Query Scoping
```php
// Use cached methods for team-scoped queries
$applications = Application::ownedByCurrentTeamCached();
$servers = Server::ownedByCurrentTeamCached();
```

### Container Status Aggregation
Status logic is centralized in `App\Services\ContainerStatusAggregator`. Multiple status update paths exist (SSH-based scheduled, Sentinel real-time, multi-server aggregation) - they all use this service for consistency.

### Form Authorization (Livewire/Blade)
```blade
<x-forms.input canGate="update" :canResource="$resource" id="name" label="Name" />
```

### API Token Abilities
- `read` - Read data
- `write` - Create/update
- `deploy` - Deploy applications
- `root` - Full access
- `read:sensitive` - Access environment variables

## Routes

- **Web**: `routes/web.php` - Livewire (legacy) + Inertia routes (`/new/*` prefix)
- **API**: `routes/api.php` - REST API v1 (`/api/v1/*`)
- **Webhooks**: `routes/webhooks.php` - GitHub, GitLab, Bitbucket

## Documentation References

Detailed documentation lives in `.ai/` directory:
- `.ai/core/technology-stack.md` - Version numbers (single source of truth)
- `.ai/core/application-architecture.md` - System design details
- `.ai/development/testing-patterns.md` - Comprehensive testing patterns
- `.ai/patterns/` - Database, frontend, security patterns
