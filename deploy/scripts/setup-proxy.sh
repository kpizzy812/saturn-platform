#!/bin/bash
# =============================================================================
# Saturn Platform - Traefik Proxy Config Update
# =============================================================================
# Updates Traefik dynamic configuration for multi-environment routing.
# Traefik auto-reloads when the file changes (providers.file.watch=true).
#
# Usage: bash setup-proxy.sh
#
# Routes:
#   dev.saturn.ac    → saturn-dev:8080
#   uat.saturn.ac    → saturn-staging:8080
#   saturn.ac        → saturn-production:8080
# =============================================================================

set -euo pipefail

RED='\033[0;31m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"

main() {
    echo ""
    echo "============================================"
    echo "Saturn Platform - Traefik Config Update"
    echo "============================================"
    echo ""

    # Ensure dynamic config directory exists
    mkdir -p /data/saturn/proxy/dynamic

    # Deploy multi-environment Traefik config
    log_info "Deploying Traefik dynamic config..."
    cp "${PROJECT_ROOT}/deploy/proxy/saturn.yaml" /data/saturn/proxy/dynamic/saturn.yaml
    log_success "Config deployed to /data/saturn/proxy/dynamic/saturn.yaml"

    # Traefik watches this directory — auto-reload
    log_info "Traefik will auto-reload the configuration"

    # Verify Traefik is running
    if docker ps --format '{{.Names}}' | grep -q "^saturn-proxy$"; then
        log_success "Traefik is running — config will be picked up automatically"
    else
        log_info "WARNING: saturn-proxy container not found. Start Traefik first."
    fi

    echo ""
    echo "============================================"
    echo -e "${GREEN}Traefik Config Updated!${NC}"
    echo "============================================"
    echo ""
    echo "Routes:"
    echo "  dev.saturn.ac    → saturn-dev:8080"
    echo "  uat.saturn.ac    → saturn-staging:8080"
    echo "  saturn.ac        → saturn-production:8080"
    echo ""
}

main "$@"
