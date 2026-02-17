# Saturn CLI Tool

## Current Status

**CLI codebase forked from [coollabsio/coolify-cli](https://github.com/coollabsio/coolify-cli) and rebranded.**
Located at: `cli/` directory in the monorepo.

### What's Done
- [x] Go CLI source code in `cli/` (forked from coolify-cli v1.4.0)
- [x] Full rebranding: coolify → saturn (binary, config paths, GitHub URLs, user-facing strings)
- [x] Frontend pages: `/cli/setup` and `/cli/commands` (React/Inertia)
- [x] Frontend tests: 34 tests passing
- [x] Install scripts: `cli/scripts/install.sh` and `cli/scripts/install.ps1`
- [x] GoReleaser config: `.goreleaser.yml` (6 platform builds)
- [x] Web routes: `routes/web/misc.php`

### What's NOT Done Yet
- [ ] GitHub repository `saturn-platform/saturn-cli` (needed for releases & self-update)
- [ ] GoReleaser CI/CD pipeline (`.github/workflows/release-cli.yml`)
- [ ] Install URL `get.saturn.app` (needs DNS + hosting for install scripts)
- [ ] Homebrew tap for `brew install saturn-cli`
- [ ] Go build verification (Go not installed locally)
- [ ] API endpoint compatibility audit (Saturn API vs Coolify API)
- [ ] Phase 2: Direct image push from local

## Architecture

Go CLI (Cobra + Viper) with 3-layer architecture:
```
cli/
├── saturn/main.go          # Entry point
├── cmd/                    # Command layer (Cobra commands)
│   ├── root.go            # Root command + global flags
│   ├── application/       # app list/create/start/stop/restart/env
│   ├── database/          # database list/create/backup
│   ├── deployment/        # deploy uuid/batch/list/cancel
│   ├── service/           # service list/create/start/stop
│   ├── server/            # server list/get/validate
│   ├── project/           # project list/create
│   ├── context/           # context add/use/list/verify/set-token
│   ├── teams/             # teams list/current
│   ├── update/            # self-update
│   └── version/           # version info
├── internal/
│   ├── api/               # HTTP client for Saturn API
│   ├── config/            # Config management (~/.config/saturn/)
│   ├── models/            # Data models
│   ├── service/           # Business logic layer
│   ├── cli/               # CLI helpers
│   └── version/           # Version checking
├── scripts/
│   ├── install.sh         # Linux/macOS installer
│   └── install.ps1        # Windows installer
└── .goreleaser.yml        # Multi-platform build config
```

## Commands Reference

| Command | Description |
|---------|-------------|
| `saturn context add <name> <url> <token>` | Add Saturn instance |
| `saturn context list` | List all instances |
| `saturn context use <name>` | Switch instance |
| `saturn context verify` | Test connection |
| `saturn app list` | List applications |
| `saturn app create <type>` | Create application |
| `saturn app start/stop/restart <uuid>` | Control app lifecycle |
| `saturn app env list/sync <uuid>` | Manage env vars |
| `saturn deploy uuid <uuid>` | Deploy by UUID |
| `saturn deploy batch <uuids>` | Batch deploy |
| `saturn service list` | List services |
| `saturn service create <type>` | Create one-click service |
| `saturn database list` | List databases |
| `saturn database create <type>` | Create database |
| `saturn server list` | List servers |
| `saturn project list/create` | Manage projects |
| `saturn teams list/current` | Team info |
| `saturn version` | Show CLI version |
| `saturn update` | Self-update |

## Next Steps (Priority Order)

### 1. API Compatibility Audit
Compare Saturn API endpoints with what the CLI expects. Key areas:
- Application CRUD & lifecycle
- Service management
- Database operations
- Deployment triggering
- Server management

### 2. Build & Test
```bash
cd cli
go build -o saturn ./saturn/
go test ./internal/...
```

### 3. GitHub Release Pipeline
- Create `saturn-platform/saturn-cli` repo (or keep in monorepo)
- Set up GoReleaser GitHub Action
- Tag first release

### 4. Phase 2 — Direct Image Push (future)
Push locally-built Docker images directly to Saturn without external registry.
Needs new backend endpoints:
- `POST /api/v1/images/upload` — accept Docker image tar
- `POST /api/v1/deploy/from-source` — accept source code archive
