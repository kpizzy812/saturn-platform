#!/bin/bash
# =============================================================================
# Saturn Platform - Deployment Script
# =============================================================================
# Deploys Saturn to /data/saturn on the server.
#
# Usage:
#   ./deploy.sh              # Deploy (default)
#   ./deploy.sh --rollback   # Rollback to previous backup
#
# Environment variables:
#   SATURN_IMAGE_TAG  - Docker image tag (default: main)
#   REGISTRY_URL      - Docker registry (default: ghcr.io)
# =============================================================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }
log_step() { echo -e "${CYAN}[STEP]${NC} $1"; }

# =============================================================================
# Configuration
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Data directory on server
SATURN_DATA="/data/saturn"

# Docker settings - Saturn image from GHCR
SATURN_IMAGE="${SATURN_IMAGE:-ghcr.io/kpizzy812/saturn-platform:latest}"

# Action
ACTION="${1:-deploy}"

# =============================================================================
# Validation
# =============================================================================
validate_prerequisites() {
    log_step "Validating prerequisites..."

    if ! command -v docker &> /dev/null; then
        log_error "Docker is not installed"
        exit 1
    fi

    if ! docker compose version &> /dev/null; then
        log_error "Docker Compose is not installed"
        exit 1
    fi

    # Check .env file
    if [[ ! -f "${SATURN_DATA}/source/.env" ]]; then
        log_error ".env file not found at ${SATURN_DATA}/source/.env"
        log_info "Copy deploy/environments/dev/.env.example to ${SATURN_DATA}/source/.env"
        log_info "Then configure it with your settings"
        exit 1
    fi

    # Create required directories
    log_info "Ensuring data directories exist..."
    mkdir -p "${SATURN_DATA}/ssh/keys"
    mkdir -p "${SATURN_DATA}/applications"
    mkdir -p "${SATURN_DATA}/databases"
    mkdir -p "${SATURN_DATA}/services"
    mkdir -p "${SATURN_DATA}/backups"
    mkdir -p "${SATURN_DATA}/uploads"  # For avatars, logos, and other public uploads

    log_success "Prerequisites OK"
}

# =============================================================================
# Docker Compose Helper
# =============================================================================
compose_cmd() {
    cd "$PROJECT_ROOT"
    export SATURN_IMAGE
    docker compose \
        -f docker-compose.yml \
        -f docker-compose.prod.yml \
        -f docker-compose.dev.override.yml \
        --env-file "${SATURN_DATA}/source/.env" \
        "$@"
}

# =============================================================================
# Deployment Functions
# =============================================================================
backup_database() {
    log_step "Creating database backup..."

    local backup_dir="${SATURN_DATA}/backups"
    local timestamp=$(date +%Y%m%d_%H%M%S)

    mkdir -p "$backup_dir"

    if docker ps --format '{{.Names}}' | grep -q "^saturn-db$"; then
        docker exec saturn-db pg_dump -U saturn -d saturn \
            > "${backup_dir}/pre_deploy_${timestamp}.sql" 2>/dev/null || true
        log_success "Backup: pre_deploy_${timestamp}.sql"
    else
        log_warn "Database container not running, skipping backup"
    fi
}

pull_images() {
    log_step "Pulling images..."

    docker pull "${SATURN_IMAGE}" || {
        log_error "Failed to pull: ${SATURN_IMAGE}"
        exit 1
    }

    # Pull realtime image
    docker pull "ghcr.io/coollabsio/coolify-realtime:1.0.10" || true

    log_success "Images pulled"
}

stop_services() {
    log_step "Stopping services..."

    compose_cmd down --remove-orphans 2>/dev/null || true

    # Clean up any orphaned containers with mangled names
    for name in saturn-dev saturn-db saturn-redis saturn-realtime; do
        docker ps -a --format '{{.Names}}' | grep "_${name}$" | xargs -r docker rm -f 2>/dev/null || true
    done

    log_success "Services stopped"
}

start_infrastructure() {
    log_step "Starting infrastructure (postgres, redis, soketi)..."

    compose_cmd up -d --wait postgres redis soketi

    log_success "Infrastructure healthy"
}

run_migrations() {
    log_step "Running migrations (before app starts)..."

    # Run migrations via temporary container — app is NOT serving traffic yet
    compose_cmd run --rm --no-deps saturn php artisan migrate --force || {
        log_error "Migration failed"
        exit 1
    }

    log_success "Migrations done"
}

start_app() {
    log_step "Starting Saturn app..."

    compose_cmd up -d

    # Wait for app container to be ready
    local retries=30
    while [[ $retries -gt 0 ]]; do
        if docker ps --format '{{.Names}}' | grep -q "^saturn-dev$"; then
            break
        fi
        sleep 1
        ((retries--))
    done

    if [[ $retries -eq 0 ]]; then
        log_error "Container saturn-dev did not start"
        exit 1
    fi

    # Wait for container health before running post-deploy commands
    log_info "Waiting for app container to be healthy..."
    sleep 5

    # Create storage symlink for public uploads (avatars, logos)
    docker exec saturn-dev php artisan storage:link --force 2>/dev/null || true

    log_success "App started"
}

run_seeders() {
    log_step "Running seeders..."

    docker exec saturn-dev php artisan db:seed --class=ProductionSeeder --force || {
        log_warn "ProductionSeeder failed (may be expected on first run)"
    }

    log_success "Seeders done"
}

clear_caches() {
    log_step "Rebuilding caches..."

    docker exec saturn-dev php artisan config:cache || true
    docker exec saturn-dev php artisan route:cache || true
    docker exec saturn-dev php artisan view:cache || true

    log_success "Caches rebuilt"
}

health_check() {
    log_step "Health check..."

    local max_retries=30
    local retry=0

    while [[ $retry -lt $max_retries ]]; do
        if curl -sf "http://127.0.0.1:8000/api/health" > /dev/null 2>&1; then
            log_success "Health check passed!"
            return 0
        fi
        ((retry++))
        log_info "Waiting... (${retry}/${max_retries})"
        sleep 2
    done

    log_error "Health check failed"
    return 1
}

cleanup_old_backups() {
    log_step "Cleaning up old backups (keeping last 10)..."

    local backup_dir="${SATURN_DATA}/backups"
    local count=$(ls -1 "${backup_dir}"/pre_deploy_*.sql 2>/dev/null | wc -l)

    if [[ $count -gt 10 ]]; then
        ls -t "${backup_dir}"/pre_deploy_*.sql | tail -n +11 | xargs rm -f
        log_success "Removed $((count - 10)) old backup(s)"
    else
        log_info "No old backups to clean up ($count total)"
    fi
}

rollback() {
    log_step "Rolling back..."

    local backup_dir="${SATURN_DATA}/backups"
    local latest_backup=$(ls -t "${backup_dir}"/pre_deploy_*.sql 2>/dev/null | head -1)

    if [[ -z "$latest_backup" ]]; then
        log_error "No backup found"
        exit 1
    fi

    log_info "Restoring from: $(basename $latest_backup)"
    docker exec -i saturn-db psql -U saturn -d saturn < "$latest_backup"

    log_success "Rollback completed"
}

# =============================================================================
# Main
# =============================================================================
deploy() {
    echo ""
    echo "============================================"
    echo -e "${CYAN}Saturn Platform - Deploy${NC}"
    echo "============================================"
    echo "Data:  ${SATURN_DATA}"
    echo "Image: ${SATURN_IMAGE}"
    echo ""

    validate_prerequisites

    if [[ "$ACTION" == "--rollback" ]]; then
        rollback
        exit 0
    fi

    backup_database
    pull_images
    stop_services
    start_infrastructure    # 1. Start DB, Redis, Soketi (wait for healthy)
    run_migrations          # 2. Run migrations BEFORE app starts serving traffic
    start_app               # 3. Start Saturn app (schema is already up to date)
    run_seeders
    clear_caches
    cleanup_old_backups

    if health_check; then
        echo ""
        echo "============================================"
        echo -e "${GREEN}Deployment Successful!${NC}"
        echo "============================================"
        echo ""
        echo "Saturn is running at: http://YOUR_SERVER_IP:8000"
        echo ""
        echo "Commands:"
        echo "  Logs:  docker logs -f saturn-dev"
        echo "  Shell: docker exec -it saturn-dev sh"
        echo "  DB:    docker exec -it saturn-db psql -U saturn"
        echo ""
    else
        log_error "Deployment done but health check failed"
        log_error "Check: docker logs saturn-dev"
        exit 1
    fi
}

deploy "$@"
