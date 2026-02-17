# Saturn CLI Tool

## Goal
CLI utility for deploying services/containers to Saturn from local machine, similar to `heroku push` / `railway up`.

## User Stories
- Developer builds a service locally (proxy, postback API, etc.) and deploys it to Saturn with one command
- Developer pushes Docker image from local to Saturn without intermediate registry
- Developer triggers redeploy of existing service from terminal

## Phase 1 — Minimal CLI (bash/shell wrapper over API)

### Commands
```bash
saturn login                         # Save API token to ~/.saturn/config
saturn deploy --image myapp:latest   # Deploy Docker image
saturn deploy --compose ./docker-compose.yml  # Deploy compose file
saturn services                      # List services
saturn logs <service-uuid>           # Stream service logs
saturn redeploy <service-uuid>       # Trigger redeploy
```

### Implementation
- Simple bash script or Python CLI (click/typer)
- Uses existing REST API v1 (`/api/v1/services`, `/api/v1/deploy`, etc.)
- Config stored in `~/.saturn/config.json` (token, server URL)
- No new backend changes needed

### API Endpoints Already Available
- `POST /api/v1/services` — create service
- `POST /api/v1/deploy` — trigger deploy by UUID
- `GET /api/v1/services` — list services
- `GET /api/v1/applications` — list applications

## Phase 2 — Direct Image Push (requires backend work)

### Goal
Push locally-built Docker image directly to Saturn without needing an external registry.

### Approach Options
1. **Built-in Docker Registry** — Run a private registry on Saturn server, CLI pushes to it
2. **Image tar upload** — `docker save` → upload tar → `docker load` on server
3. **Buildpack on server** — Push source code, build on server (like Heroku)

### Backend Changes Needed
- New API endpoint: `POST /api/v1/images/upload` — accept Docker image tar
- New API endpoint: `POST /api/v1/deploy/from-source` — accept source code archive
- Server-side: `docker load` from uploaded tar
- Optional: integrated Docker registry service

## Phase 3 — Full-Featured CLI (Go/Rust binary)

### Additional Commands
```bash
saturn init                          # Initialize project config (.saturn.yml)
saturn env set KEY=VALUE             # Manage environment variables
saturn env list
saturn domains add example.com       # Manage domains
saturn status                        # Show service status
saturn ssh <service>                 # SSH into container
saturn up                            # Deploy from current directory (auto-detect)
```

### Distribution
- Homebrew tap: `brew install saturn-cli`
- Direct binary download
- npm package: `npx @saturn/cli`

## Priority
- Phase 1: **HIGH** — can be done in 1-2 days, immediate value
- Phase 2: **MEDIUM** — requires backend work, 3-5 days
- Phase 3: **LOW** — nice to have, 1-2 weeks

## Notes
- Phase 1 is essentially a thin wrapper around existing API — no backend changes
- For "push from local" workflow right now: build → push to GHCR/DockerHub → set docker-image type in Saturn → trigger deploy via API
