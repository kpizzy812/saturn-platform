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

# Previous image tag — captured before pull, used for auto-rollback on health check failure
PREVIOUS_IMAGE=""

# Last backup file path — set by backup_database(), used by rollback_to_previous()
LAST_BACKUP_FILE=""

# S3 backup config (read from .env file or environment)
# Set BACKUP_S3_BUCKET in /data/saturn/{env}/source/.env to enable offsite backup.
BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-}"
BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-}"  # MinIO / S3-compatible: https://s3.example.com
BACKUP_S3_KEY="${BACKUP_S3_KEY:-}"
BACKUP_S3_SECRET="${BACKUP_S3_SECRET:-}"
BACKUP_S3_REGION="${BACKUP_S3_REGION:-us-east-1}"

# Active compose slot for blue-green: "" = main container, "-next" = canary
SATURN_SLOT=""

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
    export SATURN_ENV SATURN_IMAGE SATURN_SLOT
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
    mkdir -p "${SATURN_DATA}/ssh/mux"
    mkdir -p "${SATURN_DATA}/applications"
    mkdir -p "${SATURN_DATA}/databases"
    mkdir -p "${SATURN_DATA}/services"
    mkdir -p "${SATURN_DATA}/backups"
    mkdir -p "${SATURN_DATA}/uploads"
    mkdir -p "${SATURN_DATA}/logs"

    # Fix SSH directory ownership for www-data (uid 9999 inside container)
    fix_ssh_permissions

    # Logs must be writable by www-data (uid 9999) inside the container
    chown -R 9999:9999 "${SATURN_DATA}/logs"

    # Warn if Sentry DSN is not configured in production.
    # Errors will not be tracked until SENTRY_DSN is set in .env.
    if [[ "${SATURN_ENV}" == "production" ]]; then
        local sentry_dsn
        sentry_dsn=$(grep -E '^SENTRY_DSN=' "${SATURN_DATA}/source/.env" 2>/dev/null | cut -d= -f2- | tr -d '"' | tr -d "'" || true)
        if [[ -z "${sentry_dsn}" ]]; then
            log_warn "SENTRY_DSN is not set — production errors will not be tracked!"
            log_warn "Set SENTRY_DSN in ${SATURN_DATA}/source/.env to enable error monitoring."
        fi
    fi

    log_success "Prerequisites OK"
}

# =============================================================================
# SSH Key Permissions
# =============================================================================
fix_ssh_permissions() {
    log_info "Fixing SSH key permissions for container user (uid 9999)..."

    # The container runs as www-data (uid=9999). SSH keys on the host volume
    # must be readable by this user, otherwise SSH connections fail.
    chown -R 9999:9999 "${SATURN_DATA}/ssh/"
    chmod 700 "${SATURN_DATA}/ssh/keys" "${SATURN_DATA}/ssh/mux"
    chmod 600 "${SATURN_DATA}/ssh/keys/"* 2>/dev/null || true

    log_success "SSH key permissions fixed"
}

# =============================================================================
# Deployment Functions
# =============================================================================
backup_database() {
    log_step "Creating database backup..."

    local backup_dir="${SATURN_DATA}/backups"
    local timestamp
    timestamp=$(date +%Y%m%d_%H%M%S)

    mkdir -p "$backup_dir"

    if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_DB}$"; then
        local backup_file="${backup_dir}/pre_deploy_${timestamp}.sql"
        # --clean --if-exists: dump includes DROP statements → safe full restore on rollback
        docker exec "${CONTAINER_DB}" pg_dump -U saturn -d saturn --clean --if-exists \
            > "$backup_file" 2>/dev/null || true
        LAST_BACKUP_FILE="$backup_file"
        log_success "Backup: pre_deploy_${timestamp}.sql"

        upload_backup_to_s3 "$backup_file"
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

    # Gracefully stop queue workers before bringing containers down.
    # Without this, active deployment jobs are killed mid-run and may leave orphaned containers.
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${CONTAINER_APP}$"; then
        log_info "Stopping Horizon queue workers gracefully..."
        docker exec "${CONTAINER_APP}" php artisan queue:stop 2>/dev/null || true
        # Give workers up to 15s to finish their current jobs before hard kill
        sleep 5
    fi

    compose_cmd down --remove-orphans 2>/dev/null || true

    log_success "Services stopped"
}

start_infrastructure() {
    log_step "Starting postgres, redis, soketi (NOT pgbouncer yet)..."

    # Start postgres first and wait for it to be healthy.
    # PgBouncer is started separately AFTER sync_db_password — this is critical:
    # the postgres volume may retain a password from a previous initdb, while .env
    # may have a different password. PgBouncer would fail auth if started before the
    # password is synced. Correct order: postgres → sync password → pgbouncer.
    compose_cmd up -d --wait postgres redis soketi

    log_success "Core infrastructure healthy"
}

sync_db_password() {
    log_step "Syncing database password..."

    # PostgreSQL only sets POSTGRES_PASSWORD on first initdb.
    # If the .env password was changed, the DB volume still has the old one.
    # This ensures they always match BEFORE PgBouncer starts.
    local db_user db_password db_name
    db_user=$(grep '^DB_USERNAME=' "${SATURN_DATA}/source/.env" | cut -d= -f2- || echo "saturn")
    db_password=$(grep '^DB_PASSWORD=' "${SATURN_DATA}/source/.env" | cut -d= -f2-)
    db_name=$(grep '^DB_DATABASE=' "${SATURN_DATA}/source/.env" | cut -d= -f2- || echo "saturn")

    if [[ -n "$db_password" ]]; then
        docker exec "${CONTAINER_DB}" psql -U "${db_user}" -d "${db_name}" \
            -c "ALTER USER \"${db_user}\" PASSWORD '${db_password}';" > /dev/null 2>&1 || true
        log_success "Database password synced"
    fi
}

wait_for_db() {
    log_step "Waiting for PgBouncer backend pool to be ready..."

    local retries=20
    local pgbouncer_container="saturn-pgbouncer-${SATURN_ENV}"
    local db_user db_password db_name

    db_user=$(grep '^DB_USERNAME=' "${SATURN_DATA}/source/.env" | cut -d= -f2- || echo "saturn")
    db_password=$(grep '^DB_PASSWORD=' "${SATURN_DATA}/source/.env" | cut -d= -f2-)
    db_name=$(grep '^DB_DATABASE=' "${SATURN_DATA}/source/.env" | cut -d= -f2- || echo "saturn")

    while [[ $retries -gt 0 ]]; do
        # Run psql INSIDE pgbouncer container — tests actual backend connectivity.
        # env sets PGPASSWORD at runtime (not via docker exec -e which Docker Compose
        # might substitute from host env). Connects through PgBouncer → postgres.
        if docker exec "$pgbouncer_container" \
            env PGPASSWORD="$db_password" \
            psql -h 127.0.0.1 -p 5432 -U "$db_user" -d "$db_name" \
            -c 'SELECT 1' -q > /dev/null 2>&1; then
            log_success "PgBouncer backend pool ready"
            return 0
        fi
        log_info "Waiting for backend pool... ($retries retries left)"
        sleep 2
        ((retries--))
    done

    log_error "PgBouncer backend pool failed to initialize"
    log_error "Diagnostics: docker logs $pgbouncer_container"
    exit 1
}

start_pgbouncer() {
    log_step "Starting PgBouncer (after password sync)..."

    # PgBouncer starts here — AFTER postgres has the correct password from .env.
    # First backend connection attempt will succeed → no server_login_retry quarantine.
    compose_cmd up -d --wait pgbouncer

    log_success "PgBouncer healthy"
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

restore_proxy_config() {
    log_step "Restoring multi-environment Traefik config..."

    local proxy_config="${PROJECT_ROOT}/deploy/proxy/saturn.yaml"
    local target_path="/data/saturn/proxy/dynamic/saturn.yaml"

    if [[ -f "$proxy_config" ]]; then
        cp "$proxy_config" "$target_path"
        log_success "Traefik config restored to ${target_path}"
    else
        log_warn "Proxy config not found at ${proxy_config}, skipping"
    fi
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

# =============================================================================
# Blue-Green Swap
# =============================================================================
# Starts a canary container (saturn-{env}-next), waits for it to be healthy,
# then atomically switches Traefik traffic and removes the old container.
# Traefik hot-reloads the file provider within ~2s → near-zero downtime.
#
# Requires: infrastructure (postgres, redis, pgbouncer, soketi) already running.
# Falls back gracefully: if canary fails health check, current container is kept.
# =============================================================================
blue_green_swap() {
    local main_container="${CONTAINER_APP}"
    local next_container="${CONTAINER_APP}-next"
    local proxy_target="/data/saturn/proxy/dynamic/saturn.yaml"

    log_step "Blue-green: starting canary (${next_container})..."

    # 1. Start canary alongside the live container using a SEPARATE project name.
    #    Using the same project name (saturn-dev) would cause Docker Compose to
    #    reconcile the 'saturn' service: seeing container_name changed from
    #    saturn-dev to saturn-dev-next, it would STOP the live container before
    #    starting the canary — defeating the purpose of blue-green.
    #    A separate project (saturn-dev-canary) is isolated from saturn-dev,
    #    so Docker Compose creates saturn-dev-next without touching saturn-dev.
    SATURN_SLOT="-next" docker compose \
        -f "${PROJECT_ROOT}/docker-compose.env.yml" \
        -p "saturn-${SATURN_ENV}-canary" \
        --env-file "${SATURN_DATA}/source/.env" \
        up -d --no-deps saturn

    # 2. Wait for canary to pass its internal health check
    local max_retries=30
    local retry=0
    while [[ $retry -lt $max_retries ]]; do
        if docker exec "${next_container}" curl -sf "http://127.0.0.1:8080/api/health" > /dev/null 2>&1; then
            log_success "Canary is healthy"
            break
        fi
        ((retry++))
        log_info "Waiting for canary... (${retry}/${max_retries})"
        sleep 2
    done

    if [[ $retry -ge $max_retries ]]; then
        log_error "Canary failed health check — aborting blue-green, current container preserved"
        # Remove canary container and tear down the temporary canary compose project
        docker stop "${next_container}" 2>/dev/null || true
        docker rm "${next_container}" 2>/dev/null || true
        SATURN_SLOT="-next" docker compose \
            -f "${PROJECT_ROOT}/docker-compose.env.yml" \
            -p "saturn-${SATURN_ENV}-canary" \
            --env-file "${SATURN_DATA}/source/.env" \
            down --remove-orphans 2>/dev/null || true
        exit 1
    fi

    # 3. Switch Traefik file provider to canary.
    #    Traefik polls the file every 2s → traffic shifts within ~2 seconds.
    log_step "Switching Traefik traffic → canary..."
    if [[ -f "${proxy_target}" ]]; then
        sed -i "s|http://${main_container}:8080|http://${next_container}:8080|g" "${proxy_target}"
        sleep 3  # Let Traefik pick up the change before draining the old container
        log_success "Traefik now routing to canary"
    else
        log_warn "Proxy config not found at ${proxy_target} — skipping live traffic switch"
    fi

    # 4. Drain and stop the old container
    log_info "Draining old container (${main_container})..."
    docker exec "${main_container}" php artisan queue:stop 2>/dev/null || true
    sleep 5
    docker stop "${main_container}" 2>/dev/null || true
    docker rm "${main_container}" 2>/dev/null || true

    # 5. Rename canary to the canonical name so future deploys work without changes
    docker rename "${next_container}" "${main_container}"

    # 6. Restore Traefik config to canonical container name.
    #    Docker DNS resolves the renamed container immediately.
    if [[ -f "${proxy_target}" ]]; then
        sed -i "s|http://${next_container}:8080|http://${main_container}:8080|g" "${proxy_target}"
        sleep 2
    fi

    # 7. Remove the temporary canary compose project (no containers remain after rename).
    #    This prevents orphan network entries from accumulating across deploys.
    SATURN_SLOT="-next" docker compose \
        -f "${PROJECT_ROOT}/docker-compose.env.yml" \
        -p "saturn-${SATURN_ENV}-canary" \
        --env-file "${SATURN_DATA}/source/.env" \
        down --remove-orphans 2>/dev/null || true

    log_success "Blue-green swap complete — near-zero downtime!"
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
    log_step "Rolling back to previous version..."

    local backup_dir="${SATURN_DATA}/backups"
    local latest_backup
    latest_backup=$(ls -t "${backup_dir}"/pre_deploy_*.sql 2>/dev/null | head -1)

    if [[ -z "$latest_backup" ]]; then
        log_error "No pre-deploy backup found in ${backup_dir}"
        log_error "Cannot rollback without a backup."
        exit 1
    fi

    # Step 1: Restore database
    log_info "Restoring database from: $(basename "$latest_backup")"
    # Ensure postgres is running before restore (it might be down after a failed deploy)
    compose_cmd up -d --wait postgres 2>/dev/null || true
    if docker exec -i "${CONTAINER_DB}" psql -U saturn -d saturn < "$latest_backup"; then
        log_success "Database restored to pre-deploy state"
    else
        log_error "Database restore failed — manual intervention may be required"
        log_error "Backup file: ${latest_backup}"
        exit 1
    fi

    # Step 2: Restore previous Docker image
    local previous_image_file="${backup_dir}/.previous_image"
    if [[ -f "$previous_image_file" ]]; then
        local prev_image
        prev_image=$(cat "$previous_image_file")
        if [[ -n "$prev_image" ]]; then
            log_info "Restoring previous image: ${prev_image}"
            export SATURN_IMAGE="$prev_image"
        else
            log_warn ".previous_image file is empty — restarting with current image"
        fi
    else
        log_warn "No .previous_image file found — restarting with current image (no image rollback)"
        log_warn "Run a fresh deploy after fixing the issue, or specify SATURN_IMAGE manually."
    fi

    # Step 3: Restart all services with the (possibly rolled-back) image
    compose_cmd down 2>/dev/null || true
    start_infrastructure
    sync_db_password
    start_pgbouncer
    wait_for_db
    start_app

    if health_check; then
        log_success "Rollback completed — services are healthy"
        log_success "Image: ${SATURN_IMAGE}"
    else
        log_error "Services failed health check after rollback — manual intervention required"
        log_error "Logs: docker logs ${CONTAINER_APP}"
        exit 1
    fi
}

# =============================================================================
# S3 Backup Upload
# =============================================================================
load_backup_config() {
    # Read S3 backup vars from the environment .env file (if not already set via env).
    # Vars: BACKUP_S3_BUCKET, BACKUP_S3_ENDPOINT, BACKUP_S3_KEY, BACKUP_S3_SECRET, BACKUP_S3_REGION
    local env_file="${SATURN_DATA}/source/.env"
    [[ -f "$env_file" ]] || return 0

    # grep returns exit code 1 when key is absent — || true prevents set -e from killing the script
    _read_env_var() { grep -E "^$1=" "$env_file" 2>/dev/null | head -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//" || true; }

    BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-$(_read_env_var BACKUP_S3_BUCKET)}"
    BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-$(_read_env_var BACKUP_S3_ENDPOINT)}"
    BACKUP_S3_KEY="${BACKUP_S3_KEY:-$(_read_env_var BACKUP_S3_KEY)}"
    BACKUP_S3_SECRET="${BACKUP_S3_SECRET:-$(_read_env_var BACKUP_S3_SECRET)}"
    BACKUP_S3_REGION="${BACKUP_S3_REGION:-$(_read_env_var BACKUP_S3_REGION)}"
}

upload_backup_to_s3() {
    local backup_file="$1"

    if [[ -z "$BACKUP_S3_BUCKET" || -z "$BACKUP_S3_KEY" || -z "$BACKUP_S3_SECRET" ]]; then
        log_warn "S3 backup not configured — set BACKUP_S3_BUCKET, BACKUP_S3_KEY, BACKUP_S3_SECRET in .env"
        return 0
    fi

    log_step "Uploading backup to S3 (${BACKUP_S3_BUCKET})..."

    local s3_key="saturn-backups/${SATURN_ENV}/$(basename "$backup_file")"
    local endpoint_arg=()
    [[ -n "$BACKUP_S3_ENDPOINT" ]] && endpoint_arg=(--endpoint-url "$BACKUP_S3_ENDPOINT")

    # Uses official aws-cli Docker image — no host installation required.
    # Supports AWS S3, Hetzner Object Storage, MinIO, and any S3-compatible API.
    if docker run --rm \
        -v "${backup_file}:${backup_file}:ro" \
        -e AWS_ACCESS_KEY_ID="${BACKUP_S3_KEY}" \
        -e AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET}" \
        -e AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
        amazon/aws-cli:2 s3 cp "${backup_file}" "s3://${BACKUP_S3_BUCKET}/${s3_key}" \
        "${endpoint_arg[@]}" --no-progress 2>&1; then
        log_success "Backup uploaded: s3://${BACKUP_S3_BUCKET}/${s3_key}"
    else
        log_warn "S3 upload failed — backup still available locally: ${backup_file}"
    fi
}

# =============================================================================
# Auto-rollback on Deployment Failure
# =============================================================================
rollback_to_previous() {
    set +e  # Do not exit on error during rollback — we want to attempt recovery

    log_warn "============================================"
    log_warn "  HEALTH CHECK FAILED — INITIATING ROLLBACK"
    log_warn "============================================"

    if [[ -z "${PREVIOUS_IMAGE:-}" ]]; then
        log_error "No previous image captured — cannot auto-rollback (first deploy?)"
        log_error "Check logs: docker logs ${CONTAINER_APP}"
        exit 1
    fi

    log_warn "Failed image:    ${SATURN_IMAGE}"
    log_warn "Restoring image: ${PREVIOUS_IMAGE}"

    # 1. Stop failed deployment
    compose_cmd down 2>/dev/null || true

    # 2. Restore database from pre-deploy backup (dump includes DROP statements via --clean)
    local backup_file="${LAST_BACKUP_FILE:-}"
    if [[ -z "$backup_file" ]]; then
        # Fallback: find the most recent backup
        backup_file=$(ls -t "${SATURN_DATA}/backups"/pre_deploy_*.sql 2>/dev/null | head -1)
    fi

    if [[ -n "$backup_file" && -f "$backup_file" ]]; then
        log_info "Restoring DB from: $(basename "$backup_file")"
        compose_cmd up -d --wait postgres
        if docker exec -i "${CONTAINER_DB}" psql -U saturn -d saturn < "$backup_file"; then
            log_success "Database restored to pre-deploy state"
        else
            log_error "DB restore failed — database may be in inconsistent state!"
            log_error "Manual restore: docker exec -i ${CONTAINER_DB} psql -U saturn -d saturn < ${backup_file}"
        fi
    else
        log_warn "No pre-deploy backup found — DB retains new schema (proceed with caution)"
    fi

    # 3. Start previous image (PgBouncer + Redis + Soketi will start via depends_on)
    export SATURN_IMAGE="${PREVIOUS_IMAGE}"
    compose_cmd up -d

    log_info "Waiting for previous version to become healthy..."
    sleep 10

    if health_check; then
        log_success "Rollback successful — previous version is running"
        log_success "Investigate the failed deploy before pushing a fix."
    else
        log_error "Previous version also failed health check — manual intervention required!"
        log_error "Logs: docker logs ${CONTAINER_APP}"
    fi

    exit 1
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
    load_backup_config

    if [[ "$ACTION" == "--rollback" ]]; then
        rollback
        exit 0
    fi

    # Capture the currently running image before pulling a new one.
    # Used by rollback_to_previous() (auto) and rollback() (manual --rollback flag).
    PREVIOUS_IMAGE=$(docker inspect --format='{{.Config.Image}}' "${CONTAINER_APP}" 2>/dev/null || echo "")
    if [[ -n "$PREVIOUS_IMAGE" ]]; then
        log_info "Previous image: ${PREVIOUS_IMAGE}"
        # Persist to disk so manual rollback (./deploy.sh --rollback) can use it
        # even after this process has exited.
        mkdir -p "${SATURN_DATA}/backups"
        echo "$PREVIOUS_IMAGE" > "${SATURN_DATA}/backups/.previous_image"
    fi

    backup_database
    pull_images

    # Determine if infrastructure is already running (incremental deploy) or needs cold start.
    local infra_running=false
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${CONTAINER_DB}$"; then
        infra_running=true
    fi

    if [[ "$infra_running" == "true" && -n "${PREVIOUS_IMAGE:-}" ]]; then
        # ── BLUE-GREEN PATH ────────────────────────────────────────────────────
        # Infrastructure (postgres, redis, pgbouncer, soketi) is already healthy.
        # Run migrations via one-shot container, then swap app via blue-green.
        log_info "Infrastructure already running — using blue-green swap (near-zero downtime)"
        sync_db_password
        run_migrations
        blue_green_swap
        run_seeders
        fix_ssh_permissions
        clear_caches
        restore_proxy_config
        cleanup_old_backups
    else
        # ── COLD START PATH ───────────────────────────────────────────────────
        # First deploy or infra not running — full stop → start cycle.
        log_info "Cold start: bringing up all services"
        stop_services
        start_infrastructure   # postgres, redis, soketi
        sync_db_password        # ensure postgres has the password from .env
        start_pgbouncer         # now PgBouncer can connect to postgres successfully
        wait_for_db             # block until PgBouncer backend pool is established
        run_migrations
        start_app
        run_seeders
        fix_ssh_permissions    # Re-fix after seeders (may create new key files)
        clear_caches
        restore_proxy_config   # Restore multi-env Traefik config (old images may overwrite it)
        cleanup_old_backups
    fi

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
        rollback_to_previous
    fi
}

deploy "$@"
