# How to Release Saturn CLI

## Prerequisites

- Write access to `kpizzy812/saturn-cli` repository
- All changes merged to `main` branch
- All tests passing (`go test ./...`)

## Release Process

### 1. Create a GitHub Release

1. Go to https://github.com/kpizzy812/saturn-cli/releases/new
2. Click "Choose a tag" and create a new tag:
   - **Tag name**: `v1.x.x` (must start with `v`)
   - **Target**: `main`
3. **Release title**: `v1.x.x`
4. **Description**: Write release notes
5. Click "Publish release"

### 2. Automated Build Process

Once you publish the release:

1. GitHub Actions triggers `release-cli.yml`
2. GoReleaser builds binaries for: Linux, macOS, Windows (amd64 + arm64)
3. Binaries are uploaded to the release
4. Homebrew formula is auto-updated (if `HOMEBREW_TAP_GITHUB_TOKEN` is set)
5. Version is bumped in `internal/version/checker.go`

### 3. Verify the Release

```bash
# Check release artifacts
gh release view v1.x.x --repo kpizzy812/saturn-cli

# Test install
curl -fsSL https://saturn.ac/install.sh | sh
saturn --version
```

## Homebrew Tap

**Repo:** https://github.com/kpizzy812/homebrew-saturn
**Formula:** `Formula/saturn-cli.rb`

Users install via:
```bash
brew install kpizzy812/saturn/saturn-cli
```

### Auto-update setup

For GoReleaser to auto-update the Homebrew formula on each release:

1. Create a GitHub PAT (Personal Access Token) with `repo` scope
2. Add it as a secret in `kpizzy812/saturn-cli`:
   - Go to Settings > Secrets and variables > Actions
   - Create secret: `HOMEBREW_TAP_GITHUB_TOKEN` = your PAT value
3. Done — next release will auto-update the formula

**Without this secret:** GoReleaser builds binaries fine, but formula must be updated manually:
1. Download `checksums.txt` from the new release
2. Update SHA256 hashes and version in `Formula/saturn-cli.rb`
3. Push to `kpizzy812/homebrew-saturn`

## Install Methods

| Platform | Command |
|----------|---------|
| macOS | `brew install kpizzy812/saturn/saturn-cli` |
| Linux | `curl -fsSL https://saturn.ac/install.sh \| sh` |
| Windows | `iwr https://saturn.ac/install.ps1 -useb \| iex` |
| Go | `go install github.com/saturn-platform/saturn-cli/saturn@latest` |

Saturn Platform also serves redirect routes `/install.sh` and `/install.ps1` that point to the GitHub raw scripts, so the URL adapts per environment (`dev.saturn.ac`, `uat.saturn.ac`, `saturn.ac`).

## Configuration Files

| File | Purpose |
|------|---------|
| `.goreleaser.yml` | Build matrix, archives, Homebrew tap config |
| `.github/workflows/release-cli.yml` | Release workflow (GoReleaser + version bump) |
| `.github/workflows/test.yml` | CI tests (gofmt, golangci-lint, go test) |
| `scripts/install.sh` | Install script for macOS/Linux |
| `scripts/install.ps1` | Install script for Windows |
| `internal/version/checker.go` | Version string + auto-update checker |

## Notes

- CLI has auto-update checking built-in (checks every 10 minutes)
- Users can manually update with `saturn update`
- Install scripts support version pinning: `install.sh v1.2.3`
- Releases are immutable — create a new patch version to fix issues
