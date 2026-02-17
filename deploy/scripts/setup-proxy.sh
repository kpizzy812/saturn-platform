#!/bin/bash
# =============================================================================
# Saturn Platform - Caddy Proxy Setup Script
# =============================================================================
# Sets up and starts the Caddy reverse proxy for all Saturn environments.
# Run this once on the VPS before deploying any environment.
#
# Usage: sudo bash setup-proxy.sh
#
# Prerequisites:
#   - Docker installed
#   - setup-server.sh already run
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

# =============================================================================
# Setup
# =============================================================================
main() {
    echo ""
    echo "============================================"
    echo "Saturn Platform - Caddy Proxy Setup"
    echo "============================================"
    echo ""

    # Ensure shared directories exist
    log_info "Creating shared directories..."
    mkdir -p /data/saturn/shared/proxy
    mkdir -p /data/saturn/shared/caddy-logs

    # Copy Caddyfile
    log_info "Copying Caddyfile..."
    cp "${PROJECT_ROOT}/deploy/proxy/Caddyfile" /data/saturn/shared/proxy/Caddyfile
    log_success "Caddyfile deployed to /data/saturn/shared/proxy/Caddyfile"

    # Create shared proxy network if not exists
    if ! docker network inspect saturn-proxy &>/dev/null 2>&1; then
        log_info "Creating Docker network 'saturn-proxy'..."
        docker network create saturn-proxy
        log_success "Network 'saturn-proxy' created"
    else
        log_info "Network 'saturn-proxy' already exists"
    fi

    # Start Caddy
    log_info "Starting Caddy proxy..."
    cd "$PROJECT_ROOT"
    docker compose -f docker-compose.proxy.yml up -d

    # Wait for healthy
    local retries=15
    while [[ $retries -gt 0 ]]; do
        if docker inspect --format='{{.State.Health.Status}}' saturn-proxy 2>/dev/null | grep -q "healthy"; then
            break
        fi
        sleep 2
        ((retries--))
    done

    if [[ $retries -eq 0 ]]; then
        log_warn "Caddy may not be healthy yet (check: docker logs saturn-proxy)"
    else
        log_success "Caddy proxy is running and healthy"
    fi

    echo ""
    echo "============================================"
    echo -e "${GREEN}Caddy Proxy Setup Complete!${NC}"
    echo "============================================"
    echo ""
    echo "Caddy routes:"
    echo "  dev.saturn.ac    → saturn-dev:8080"
    echo "  uat.saturn.ac    → saturn-staging:8080"
    echo "  saturn.ac        → saturn-production:8080"
    echo ""
    echo "Commands:"
    echo "  Logs:    docker logs -f saturn-proxy"
    echo "  Reload:  docker exec saturn-proxy caddy reload --config /etc/caddy/Caddyfile"
    echo "  Status:  docker inspect --format='{{.State.Health.Status}}' saturn-proxy"
    echo ""
    echo "NOTE: Ensure DNS A records point to this server:"
    echo "  dev.saturn.ac    → $(curl -sf https://ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP')"
    echo "  uat.saturn.ac    → $(curl -sf https://ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP')"
    echo "  saturn.ac        → $(curl -sf https://ifconfig.me 2>/dev/null || echo 'YOUR_SERVER_IP')"
    echo ""
}

main "$@"
