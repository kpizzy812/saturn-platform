#!/bin/bash
# =============================================================================
# Saturn Platform - Install Shell Aliases
# =============================================================================
# Automatically configures convenient shell aliases for Saturn Platform
#
# Usage:
#   ./install-aliases.sh
#
# =============================================================================

set -euo pipefail

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[OK]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }

# Detect shell
SHELL_RC=""
if [[ -n "${BASH_VERSION:-}" ]]; then
    SHELL_RC="$HOME/.bashrc"
elif [[ -n "${ZSH_VERSION:-}" ]]; then
    SHELL_RC="$HOME/.zshrc"
else
    # Try to detect from SHELL variable
    case "$SHELL" in
        */bash)
            SHELL_RC="$HOME/.bashrc"
            ;;
        */zsh)
            SHELL_RC="$HOME/.zshrc"
            ;;
        *)
            log_warn "Could not detect shell type. Please specify manually."
            echo -n "Enter path to your shell rc file (e.g., ~/.bashrc): "
            read -r SHELL_RC
            ;;
    esac
fi

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# Aliases to add
ALIASES="
# ============================================================================
# Saturn Platform Aliases
# ============================================================================
# Interactive control panel
alias saturn='cd ${SCRIPT_DIR} && ./saturn-ctl.sh'

# Quick commands
alias saturn-logs='cd ${SCRIPT_DIR} && ./saturn-ctl.sh logs'
alias saturn-deploy='cd ${SCRIPT_DIR} && ./deploy.sh'
alias saturn-status='cd ${SCRIPT_DIR} && ./saturn-ctl.sh status'

# Direct container access
alias saturn-shell='docker exec -it saturn-dev sh'
alias saturn-db='docker exec -it saturn-db psql -U saturn -d saturn'
alias saturn-redis='docker exec -it saturn-redis redis-cli'
alias saturn-artisan='docker exec saturn-dev php artisan'

# Logs shortcuts
alias saturn-app-logs='docker logs -f --tail=1000 saturn-dev'
alias saturn-db-logs='docker logs -f --tail=1000 saturn-db'
alias saturn-redis-logs='docker logs -f --tail=1000 saturn-redis'
alias saturn-soketi-logs='docker logs -f --tail=1000 saturn-soketi'

# Useful shortcuts
alias saturn-restart='docker restart saturn-dev'
alias saturn-ps='docker ps --format \"table {{.Names}}\\\t{{.Status}}\\\t{{.Ports}}\" | grep saturn'
alias saturn-stats='docker stats --no-stream | grep saturn'
# ============================================================================
"

echo ""
echo -e "${CYAN}Saturn Platform - Alias Installer${NC}"
echo ""
log_info "Shell RC file: ${SHELL_RC}"
log_info "Script directory: ${SCRIPT_DIR}"
echo ""

# Check if aliases already exist
if grep -q "Saturn Platform Aliases" "$SHELL_RC" 2>/dev/null; then
    log_warn "Saturn aliases already exist in ${SHELL_RC}"
    echo -n "Do you want to update them? (yes/no): "
    read -r response

    if [[ "$response" != "yes" ]]; then
        log_info "Installation cancelled"
        exit 0
    fi

    # Remove old aliases
    log_info "Removing old aliases..."
    sed -i.bak '/# Saturn Platform Aliases/,/# ============================================================================$/d' "$SHELL_RC"
fi

# Add aliases
log_info "Adding Saturn aliases to ${SHELL_RC}..."
echo "$ALIASES" >> "$SHELL_RC"

log_success "Aliases installed successfully!"
echo ""
echo -e "${CYAN}Available commands:${NC}"
echo ""
echo "  ${GREEN}saturn${NC}              - Open interactive control panel"
echo "  ${GREEN}saturn-logs${NC}         - View logs menu"
echo "  ${GREEN}saturn-deploy${NC}       - Deploy new version"
echo "  ${GREEN}saturn-status${NC}       - Show service status"
echo ""
echo "  ${GREEN}saturn-shell${NC}        - Open Saturn app shell"
echo "  ${GREEN}saturn-db${NC}           - Open PostgreSQL shell"
echo "  ${GREEN}saturn-artisan${NC}      - Run Laravel Artisan commands"
echo ""
echo "  ${GREEN}saturn-app-logs${NC}     - View app logs (1000 lines + follow)"
echo "  ${GREEN}saturn-db-logs${NC}      - View database logs"
echo "  ${GREEN}saturn-restart${NC}      - Quick restart app container"
echo "  ${GREEN}saturn-ps${NC}           - Show Saturn containers"
echo ""
echo -e "${YELLOW}To activate aliases in current session:${NC}"
echo ""
echo "  source ${SHELL_RC}"
echo ""
log_info "Aliases will be automatically available in new terminal sessions"
