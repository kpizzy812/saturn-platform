#!/bin/bash
# =============================================================================
# Saturn Platform - Deployment Script (Multi-Environment)
# =============================================================================
# Deploys Saturn to an isolated environment on the server.
# Each environment has its own DB, Redis, network, and data directory.
#
# Usage:
#   SATURN_ENV=dev ./deploy.sh              # Deploy dev environment
#   SATURN_ENV=staging ./deploy.sh          # Deploy staging environment
#   SATURN_ENV=production ./deploy.sh       # Deploy production environment
#   SATURN_ENV=dev ./deploy.sh --rollback   # Rollback dev environment
#
# Environment variables:
#   SATURN_ENV        - Required: dev|staging|production
#   SATURN_IMAGE      - Docker image (default: ghcr.io/kpizzy812/saturn-platform:latest)
# =============================================================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[${SATURN_ENV}][INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[${SATURN_ENV}][OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[${SATURN_ENV}][WARN]${NC} $1"; }
log_error() { echo -e "${RED}[${SATURN_ENV}][ERROR]${NC} $1"; }
log_step() { echo -e "${CYAN}[${SATURN_ENV}][STEP]${NC} $1"; }

# =============================================================================
# Configuration
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# Validate SATURN_ENV
SATURN_ENV="${SATURN_ENV:?SATURN_ENV is required (dev|staging|production)}"
case "$SATURN_ENV" in
    dev|staging|production) ;;
    *) echo "ERROR: SATURN_ENV must be dev, staging, or production (got: $SATURN_ENV)"; exit 1 ;;
esac

# Data directory — isolated per environment
SATURN_DATA="/data/saturn/${SATURN_ENV}"

# Docker image
SATURN_IMAGE="${SATURN_IMAGE:-ghcr.io/kpizzy812/saturn-platform:latest}"

# Container names
CONTAINER_APP="saturn-${SATURN_ENV}"
CONTAINER_DB="saturn-db-${SATURN_ENV}"
CONTAINER_REDIS="saturn-redis-${SATURN_ENV}"
CONTAINER_REALTIME="saturn-realtime-${SATURN_ENV}"

# Action
ACTION="${1:-deploy}"

# =============================================================================
# Docker Compose Helper
# =============================================================================
compose_cmd() {
    cd "$PROJECT_ROOT"
    export SATURN_ENV SATURN_IMAGE
    docker compose \
        -f docker-compose.env.yml \
        -p "saturn-${SATURN_ENV}" \
        --env-file "${SATURN_DATA}/source/.env" \
        "$@"
}

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

    # Ensure shared network exists (Traefik proxy)
    if ! docker network inspect saturn &>/dev/null 2>&1; then
        log_info "Creating shared network 'saturn'..."
        docker network create saturn
        log_success "Network 'saturn' created"
    fi

    # Check .env file
    if [[ ! -f "${SATURN_DATA}/source/.env" ]]; then
        log_error ".env file not found at ${SATURN_DATA}/source/.env"
        log_info "Copy deploy/environments/${SATURN_ENV}/.env.example to ${SATURN_DATA}/source/.env"
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
    mkdir -p "${SATURN_DATA}/uploads"

    log_success "Prerequisites OK"
}

# =============================================================================
# Deployment Functions
# =============================================================================
backup_database() {
    log_step "Creating database backup..."

    local backup_dir="${SATURN_DATA}/backups"
    local timestamp=$(date +%Y%m%d_%H%M%S)

    mkdir -p "$backup_dir"

    if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_DB}$"; then
        docker exec "${CONTAINER_DB}" pg_dump -U saturn -d saturn \
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

    log_success "Services stopped"
}

start_infrastructure() {
    log_step "Starting infrastructure (postgres, redis, soketi)..."

    compose_cmd up -d --wait postgres redis soketi

    log_success "Infrastructure healthy"
}

sync_db_password() {
    log_step "Syncing database password..."

    # PostgreSQL only sets POSTGRES_PASSWORD on first initdb.
    # If the .env password was changed, the DB volume still has the old one.
    # This ensures they always match.
    local db_password
    db_password=$(grep '^DB_PASSWORD=' "${SATURN_DATA}/source/.env" | cut -d= -f2-)

    if [[ -n "$db_password" ]]; then
        docker exec "${CONTAINER_DB}" psql -U saturn -d saturn \
            -c "ALTER USER saturn PASSWORD '${db_password}';" > /dev/null 2>&1 || true
        log_success "Database password synced"
    fi
}

run_migrations() {
    log_step "Running migrations (before app starts)..."

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
        if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_APP}$"; then
            break
        fi
        sleep 1
        ((retries--))
    done

    if [[ $retries -eq 0 ]]; then
        log_error "Container ${CONTAINER_APP} did not start"
        exit 1
    fi

    # Wait for container health
    log_info "Waiting for app container to be healthy..."
    sleep 5

    # Create storage symlink for public uploads
    docker exec "${CONTAINER_APP}" php artisan storage:link --force 2>/dev/null || true

    log_success "App started"
}

run_seeders() {
    log_step "Running seeders..."

    docker exec "${CONTAINER_APP}" php artisan db:seed --class=ProductionSeeder --force || {
        log_warn "ProductionSeeder failed (may be expected on first run)"
    }

    log_success "Seeders done"
}

clear_caches() {
    log_step "Rebuilding caches..."

    docker exec "${CONTAINER_APP}" php artisan config:cache || true
    docker exec "${CONTAINER_APP}" php artisan route:cache || true
    docker exec "${CONTAINER_APP}" php artisan view:cache || true

    log_success "Caches rebuilt"
}

health_check() {
    log_step "Health check..."

    local max_retries=30
    local retry=0

    while [[ $retry -lt $max_retries ]]; do
        # Health check via docker exec (no host ports exposed)
        if docker exec "${CONTAINER_APP}" curl -sf "http://127.0.0.1:8080/api/health" > /dev/null 2>&1; then
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
    docker exec -i "${CONTAINER_DB}" psql -U saturn -d saturn < "$latest_backup"

    log_success "Rollback completed"
}

# =============================================================================
# Main
# =============================================================================
deploy() {
    echo ""
    echo "============================================"
    echo -e "${CYAN}Saturn Platform - Deploy [${SATURN_ENV}]${NC}"
    echo "============================================"
    echo "Env:   ${SATURN_ENV}"
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
    start_infrastructure
    sync_db_password
    run_migrations
    start_app
    run_seeders
    clear_caches
    cleanup_old_backups

    if health_check; then
        echo ""
        echo "============================================"
        echo -e "${GREEN}[${SATURN_ENV}] Deployment Successful!${NC}"
        echo "============================================"
        echo ""
        echo "Commands:"
        echo "  Logs:  docker logs -f ${CONTAINER_APP}"
        echo "  Shell: docker exec -it ${CONTAINER_APP} sh"
        echo "  DB:    docker exec -it ${CONTAINER_DB} psql -U saturn"
        echo ""
    else
        log_error "Deployment done but health check failed"
        log_error "Check: docker logs ${CONTAINER_APP}"
        exit 1
    fi
}

deploy "$@"
