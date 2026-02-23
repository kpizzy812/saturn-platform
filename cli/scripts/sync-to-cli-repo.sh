#!/usr/bin/env bash
set -euo pipefail

# Syncs CLI source from saturn-platform/cli/ to the standalone saturn-cli repo.
# Usage:
#   ./scripts/sync-to-cli-repo.sh                    # sync only
#   ./scripts/sync-to-cli-repo.sh --release v1.5.0   # sync + create GitHub release

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
CLI_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
PLATFORM_REPO="kpizzy812/saturn-platform"
CLI_REPO="kpizzy812/saturn-cli"
CLI_CLONE_DIR="${SATURN_CLI_REPO_DIR:-/tmp/saturn-cli-sync}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

info()  { echo -e "${GREEN}[INFO]${NC} $*"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $*"; }
error() { echo -e "${RED}[ERROR]${NC} $*" >&2; }

# Parse args
RELEASE_TAG=""
DRY_RUN=false

while [[ $# -gt 0 ]]; do
    case "$1" in
        --release)
            RELEASE_TAG="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        -h|--help)
            echo "Usage: $0 [--release vX.Y.Z] [--dry-run]"
            echo ""
            echo "Syncs CLI code from saturn-platform/cli/ to saturn-cli repo."
            echo ""
            echo "Options:"
            echo "  --release vX.Y.Z  After sync, create a GitHub release with this tag"
            echo "  --dry-run         Show what would be done without making changes"
            echo "  -h, --help        Show this help"
            exit 0
            ;;
        *)
            error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Validate release tag format
if [[ -n "$RELEASE_TAG" ]] && ! [[ "$RELEASE_TAG" =~ ^v[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    error "Invalid release tag: $RELEASE_TAG (expected format: vX.Y.Z)"
    exit 1
fi

# Preflight checks
command -v gh >/dev/null 2>&1 || { error "gh CLI not installed"; exit 1; }
command -v git >/dev/null 2>&1 || { error "git not installed"; exit 1; }
command -v rsync >/dev/null 2>&1 || { error "rsync not installed"; exit 1; }

# Ensure we're in the CLI directory
if [[ ! -f "$CLI_DIR/go.mod" ]]; then
    error "Cannot find go.mod in $CLI_DIR — run from cli/ directory"
    exit 1
fi

# Run tests before sync
info "Running tests..."
cd "$CLI_DIR"
go test ./... -count=1 -short 2>&1 | tail -5
info "Tests passed"

# Clone or update the CLI repo
if [[ -d "$CLI_CLONE_DIR/.git" ]]; then
    info "Updating existing clone at $CLI_CLONE_DIR..."
    cd "$CLI_CLONE_DIR"
    git fetch origin
    git checkout main
    git reset --hard origin/main
else
    info "Cloning $CLI_REPO to $CLI_CLONE_DIR..."
    rm -rf "$CLI_CLONE_DIR"
    gh repo clone "$CLI_REPO" "$CLI_CLONE_DIR"
    cd "$CLI_CLONE_DIR"
    git checkout main
fi

# Sync files (exclude .git, node_modules, build artifacts)
info "Syncing files from $CLI_DIR to $CLI_CLONE_DIR..."
rsync -av --delete \
    --exclude='.git' \
    --exclude='.git/' \
    --exclude='node_modules/' \
    --exclude='dist/' \
    --exclude='*.exe' \
    --exclude='saturn-cli' \
    "$CLI_DIR/" "$CLI_CLONE_DIR/"

# Check for changes
cd "$CLI_CLONE_DIR"
if git diff --quiet && git diff --cached --quiet && [[ -z "$(git ls-files --others --exclude-standard)" ]]; then
    info "No changes to sync — CLI repo is already up to date"
    if [[ -n "$RELEASE_TAG" ]]; then
        warn "No code changes, but will create release $RELEASE_TAG from current state"
    else
        exit 0
    fi
else
    # Show diff summary
    echo ""
    info "Changes to sync:"
    git status --short
    echo ""

    if [[ "$DRY_RUN" == true ]]; then
        info "[DRY RUN] Would commit and push these changes"
    else
        # Get the latest platform commit message for context
        PLATFORM_COMMIT=$(cd "$CLI_DIR" && git log -1 --format='%h %s')

        # Stage and commit
        git add -A
        git commit -m "sync: update from saturn-platform

Source commit: $PLATFORM_COMMIT"

        info "Pushing to $CLI_REPO main..."
        git push origin main
        info "Push complete"
    fi
fi

# Create release if requested
if [[ -n "$RELEASE_TAG" ]]; then
    if [[ "$DRY_RUN" == true ]]; then
        info "[DRY RUN] Would create release $RELEASE_TAG on $CLI_REPO"
    else
        info "Creating release $RELEASE_TAG on $CLI_REPO..."

        # Generate changelog from platform repo since last tag
        CHANGELOG=""
        LAST_TAG=$(gh release list --repo "$CLI_REPO" --limit 1 --json tagName --jq '.[0].tagName' 2>/dev/null || echo "")
        if [[ -n "$LAST_TAG" ]]; then
            CHANGELOG=$(cd "$CLI_DIR" && git log --oneline "${LAST_TAG}..HEAD" -- . 2>/dev/null | head -20 || echo "")
        fi

        RELEASE_NOTES="## What's Changed

${CHANGELOG:-No detailed changelog available.}

**Full sync from saturn-platform/cli/**"

        gh release create "$RELEASE_TAG" \
            --repo "$CLI_REPO" \
            --title "$RELEASE_TAG" \
            --notes "$RELEASE_NOTES" \
            --target main

        info "Release $RELEASE_TAG created!"
        info "GoReleaser will build binaries automatically via GitHub Actions"
        info "Users can update with: saturn update"
        echo ""
        info "Verify: gh release view $RELEASE_TAG --repo $CLI_REPO"
    fi
fi

echo ""
info "Done!"
