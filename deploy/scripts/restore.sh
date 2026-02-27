#!/bin/bash
# =============================================================================
# Saturn Platform - Database Restore Script
# =============================================================================
# Restores the Saturn database from a local pre-deploy backup or an S3 backup.
#
# Usage:
#   SATURN_ENV=production ./restore.sh                  # Interactive: list and pick
#   SATURN_ENV=production ./restore.sh --list           # List available backups
#   SATURN_ENV=production ./restore.sh --local FILENAME # Restore specific local file
#   SATURN_ENV=production ./restore.sh --s3 FILENAME    # Download from S3 and restore
#
# Requirements:
#   - SATURN_ENV must be set (dev|staging|production)
#   - S3 restore requires BACKUP_S3_* vars in /data/saturn/{env}/source/.env
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info()    { echo -e "${BLUE}[${SATURN_ENV}][INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[${SATURN_ENV}][OK]${NC} $1"; }
log_warn()    { echo -e "${YELLOW}[${SATURN_ENV}][WARN]${NC} $1"; }
log_error()   { echo -e "${RED}[${SATURN_ENV}][ERROR]${NC} $1"; }
log_step()    { echo -e "${CYAN}[${SATURN_ENV}][STEP]${NC} $1"; }

# =============================================================================
# Config
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

SATURN_ENV="${SATURN_ENV:?SATURN_ENV is required (dev|staging|production)}"
case "$SATURN_ENV" in
    dev|staging|production) ;;
    *) echo "ERROR: SATURN_ENV must be dev, staging, or production (got: $SATURN_ENV)"; exit 1 ;;
esac

SATURN_DATA="/data/saturn/${SATURN_ENV}"
ENV_FILE="${SATURN_DATA}/source/.env"
BACKUP_DIR="${SATURN_DATA}/backups"
CONTAINER_DB="saturn-db-${SATURN_ENV}"
CONTAINER_APP="saturn-${SATURN_ENV}"

ACTION="${1:-interactive}"
TARGET="${2:-}"

# =============================================================================
# Read config from .env
# =============================================================================
read_env() {
    local key="$1"
    grep -E "^${key}=" "${ENV_FILE}" 2>/dev/null | head -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//" || true
}

load_config() {
    [[ -f "${ENV_FILE}" ]] || { log_error ".env not found: ${ENV_FILE}"; exit 1; }

    DB_USER="${DB_USER:-$(read_env DB_USERNAME)}"
    DB_USER="${DB_USER:-saturn}"
    DB_NAME="${DB_NAME:-$(read_env DB_DATABASE)}"
    DB_NAME="${DB_NAME:-saturn}"
    DB_PASSWORD="${DB_PASSWORD:-$(read_env DB_PASSWORD)}"

    BACKUP_S3_BUCKET="${BACKUP_S3_BUCKET:-$(read_env BACKUP_S3_BUCKET)}"
    BACKUP_S3_ENDPOINT="${BACKUP_S3_ENDPOINT:-$(read_env BACKUP_S3_ENDPOINT)}"
    BACKUP_S3_KEY="${BACKUP_S3_KEY:-$(read_env BACKUP_S3_KEY)}"
    BACKUP_S3_SECRET="${BACKUP_S3_SECRET:-$(read_env BACKUP_S3_SECRET)}"
    BACKUP_S3_REGION="${BACKUP_S3_REGION:-$(read_env BACKUP_S3_REGION)}"
    BACKUP_S3_REGION="${BACKUP_S3_REGION:-us-east-1}"
}

# =============================================================================
# S3 helpers
# =============================================================================
s3_cmd() {
    local endpoint_arg=()
    [[ -n "${BACKUP_S3_ENDPOINT:-}" ]] && endpoint_arg=(--endpoint-url "${BACKUP_S3_ENDPOINT}")

    docker run --rm \
        -e AWS_ACCESS_KEY_ID="${BACKUP_S3_KEY}" \
        -e AWS_SECRET_ACCESS_KEY="${BACKUP_S3_SECRET}" \
        -e AWS_DEFAULT_REGION="${BACKUP_S3_REGION}" \
        amazon/aws-cli:2 \
        "${endpoint_arg[@]}" \
        "$@"
}

check_s3_config() {
    if [[ -z "${BACKUP_S3_BUCKET:-}" || -z "${BACKUP_S3_KEY:-}" || -z "${BACKUP_S3_SECRET:-}" ]]; then
        log_error "S3 not configured. Set in ${ENV_FILE}:"
        log_error "  BACKUP_S3_BUCKET, BACKUP_S3_KEY, BACKUP_S3_SECRET"
        log_error "  Optional: BACKUP_S3_ENDPOINT (for MinIO/Hetzner), BACKUP_S3_REGION"
        exit 1
    fi
}

list_s3_backups() {
    check_s3_config
    log_step "Listing S3 backups in s3://${BACKUP_S3_BUCKET}/saturn-backups/${SATURN_ENV}/ ..."
    s3_cmd s3 ls "s3://${BACKUP_S3_BUCKET}/saturn-backups/${SATURN_ENV}/" \
        | grep '\.sql' \
        | awk '{print $4}' \
        | sort -r \
        || true
}

download_from_s3() {
    local filename="$1"
    local dest="${BACKUP_DIR}/${filename}"
    local s3_key="saturn-backups/${SATURN_ENV}/${filename}"

    check_s3_config

    log_step "Downloading s3://${BACKUP_S3_BUCKET}/${s3_key} ..."

    s3_cmd s3 cp \
        "s3://${BACKUP_S3_BUCKET}/${s3_key}" \
        - \
        --no-progress \
    > "${dest}"

    log_success "Downloaded to ${dest}"
    echo "${dest}"
}

# =============================================================================
# List local backups
# =============================================================================
list_local_backups() {
    log_step "Local backups in ${BACKUP_DIR}:"
    ls -lt "${BACKUP_DIR}"/pre_deploy_*.sql 2>/dev/null \
        | awk '{print NR". "$NF" ("$5")"}' \
        | sed "s|${BACKUP_DIR}/||g" \
        || echo "  (none found)"
}

# =============================================================================
# Restore from file
# =============================================================================
do_restore() {
    local backup_file="$1"

    [[ -f "${backup_file}" ]] || { log_error "File not found: ${backup_file}"; exit 1; }

    local filesize
    filesize=$(du -sh "${backup_file}" | cut -f1)
    log_info "Backup file : $(basename "${backup_file}") (${filesize})"
    log_info "Target DB   : ${DB_NAME}@${CONTAINER_DB}"
    log_info "Environment : ${SATURN_ENV}"

    echo ""
    echo -e "${RED}⚠  WARNING: This will OVERWRITE the current database!${NC}"
    echo -e "${RED}   All data in '${DB_NAME}' will be replaced with the backup contents.${NC}"
    echo ""
    read -rp "Type YES to confirm: " confirm
    if [[ "${confirm}" != "YES" ]]; then
        log_warn "Aborted."
        exit 0
    fi

    # Ensure DB container is running
    log_step "Ensuring database container is running..."
    cd "${PROJECT_ROOT}"
    export SATURN_ENV SATURN_SLOT=""
    docker compose \
        -f docker-compose.env.yml \
        -p "saturn-${SATURN_ENV}" \
        --env-file "${ENV_FILE}" \
        up -d --wait postgres 2>/dev/null || true

    # Stop app to prevent writes during restore
    log_step "Stopping app container to prevent writes during restore..."
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q "^${CONTAINER_APP}$"; then
        docker exec "${CONTAINER_APP}" php artisan queue:stop 2>/dev/null || true
        sleep 3
        docker stop "${CONTAINER_APP}" 2>/dev/null || true
    fi

    # Run restore
    log_step "Restoring database..."
    if docker exec -i "${CONTAINER_DB}" \
        env PGPASSWORD="${DB_PASSWORD}" \
        psql -U "${DB_USER}" -d "${DB_NAME}" \
        < "${backup_file}"; then
        log_success "Database restored successfully from: $(basename "${backup_file}")"
    else
        log_error "Restore failed — database may be in inconsistent state!"
        log_error "Manual recovery: docker exec -i ${CONTAINER_DB} psql -U ${DB_USER} -d ${DB_NAME} < ${backup_file}"
        exit 1
    fi

    echo ""
    log_warn "App container was stopped. Bring it back with:"
    log_warn "  SATURN_ENV=${SATURN_ENV} ./deploy/scripts/deploy.sh"
    echo ""
}

# =============================================================================
# Interactive mode
# =============================================================================
interactive() {
    echo ""
    echo "============================================"
    echo -e "${CYAN}Saturn Restore — ${SATURN_ENV}${NC}"
    echo "============================================"
    echo ""

    local has_s3=false
    [[ -n "${BACKUP_S3_BUCKET:-}" && -n "${BACKUP_S3_KEY:-}" ]] && has_s3=true

    echo "Sources:"
    echo "  1) Local backup"
    [[ "${has_s3}" == "true" ]] && echo "  2) S3 backup (s3://${BACKUP_S3_BUCKET}/saturn-backups/${SATURN_ENV}/)"
    echo ""
    read -rp "Choose source [1$([[ "${has_s3}" == "true" ]] && echo "/2")]: " source_choice

    local backup_file=""

    if [[ "${source_choice}" == "1" ]]; then
        # Local
        list_local_backups
        echo ""
        read -rp "Enter backup filename (from list above): " fname
        backup_file="${BACKUP_DIR}/${fname}"

    elif [[ "${source_choice}" == "2" && "${has_s3}" == "true" ]]; then
        # S3
        echo ""
        mapfile -t s3_files < <(list_s3_backups)
        if [[ ${#s3_files[@]} -eq 0 ]]; then
            log_error "No backups found in S3"
            exit 1
        fi
        echo ""
        echo "Available S3 backups (newest first):"
        for i in "${!s3_files[@]}"; do
            echo "  $((i+1))) ${s3_files[$i]}"
        done
        echo ""
        read -rp "Choose backup [1-${#s3_files[@]}]: " idx
        idx=$((idx-1))
        if [[ $idx -lt 0 || $idx -ge ${#s3_files[@]} ]]; then
            log_error "Invalid choice"
            exit 1
        fi
        local chosen="${s3_files[$idx]}"
        backup_file=$(download_from_s3 "${chosen}")
    else
        log_error "Invalid choice"
        exit 1
    fi

    do_restore "${backup_file}"
}

# =============================================================================
# List mode
# =============================================================================
cmd_list() {
    echo ""
    echo "=== Local backups ==="
    list_local_backups
    echo ""
    if [[ -n "${BACKUP_S3_BUCKET:-}" && -n "${BACKUP_S3_KEY:-}" ]]; then
        echo "=== S3 backups ==="
        list_s3_backups
    else
        echo "=== S3 backups ==="
        echo "  (S3 not configured — set BACKUP_S3_BUCKET, BACKUP_S3_KEY, BACKUP_S3_SECRET)"
    fi
    echo ""
}

# =============================================================================
# Main
# =============================================================================
load_config

case "${ACTION}" in
    --list|-l)
        cmd_list
        ;;
    --local)
        [[ -z "${TARGET}" ]] && { log_error "Usage: $0 --local FILENAME"; exit 1; }
        do_restore "${BACKUP_DIR}/${TARGET}"
        ;;
    --s3)
        [[ -z "${TARGET}" ]] && { log_error "Usage: $0 --s3 FILENAME"; exit 1; }
        backup_file=$(download_from_s3 "${TARGET}")
        do_restore "${backup_file}"
        ;;
    interactive|"")
        interactive
        ;;
    *)
        echo "Usage:"
        echo "  SATURN_ENV=production $0              # Interactive"
        echo "  SATURN_ENV=production $0 --list       # List backups"
        echo "  SATURN_ENV=production $0 --local FILE # Restore local backup"
        echo "  SATURN_ENV=production $0 --s3 FILE    # Download from S3 and restore"
        exit 1
        ;;
esac
