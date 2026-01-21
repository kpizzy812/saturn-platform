#!/bin/bash
# =============================================================================
# Saturn Platform - Server Setup Script
# =============================================================================
# This script prepares a fresh Ubuntu/Debian VPS for Saturn deployment.
# Run this once on a new server before first deployment.
#
# Usage: curl -sSL <raw-url> | sudo bash
# Or:    sudo bash setup-server.sh
# =============================================================================

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

log_info() { echo -e "${BLUE}[INFO]${NC} $1"; }
log_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# =============================================================================
# Configuration
# =============================================================================
SATURN_USER="${SATURN_USER:-saturn}"
SATURN_HOME="/home/${SATURN_USER}"
SATURN_DATA="/data/saturn"

# =============================================================================
# Pre-flight checks
# =============================================================================
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

check_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        VERSION=$VERSION_ID
        log_info "Detected OS: $OS $VERSION"
    else
        log_error "Cannot detect OS. This script supports Ubuntu/Debian."
        exit 1
    fi

    if [[ "$OS" != "ubuntu" && "$OS" != "debian" ]]; then
        log_warn "This script is tested on Ubuntu/Debian. Proceed with caution."
    fi
}

# =============================================================================
# System Setup
# =============================================================================
update_system() {
    log_info "Updating system packages..."
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get upgrade -y -qq
    log_success "System updated"
}

install_dependencies() {
    log_info "Installing dependencies..."
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        curl \
        wget \
        git \
        unzip \
        htop \
        vim \
        jq \
        ca-certificates \
        gnupg \
        lsb-release \
        ufw \
        fail2ban \
        logrotate
    log_success "Dependencies installed"
}

# =============================================================================
# Docker Installation
# =============================================================================
install_docker() {
    if command -v docker &> /dev/null; then
        log_info "Docker already installed: $(docker --version)"
        return
    fi

    log_info "Installing Docker..."

    # Add Docker's official GPG key
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/$OS/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    # Add the repository
    echo \
        "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/$OS \
        $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null

    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq \
        docker-ce \
        docker-ce-cli \
        containerd.io \
        docker-buildx-plugin \
        docker-compose-plugin

    # Enable and start Docker
    systemctl enable docker
    systemctl start docker

    log_success "Docker installed: $(docker --version)"
}

# =============================================================================
# User Setup
# =============================================================================
create_saturn_user() {
    if id "${SATURN_USER}" &>/dev/null; then
        log_info "User ${SATURN_USER} already exists"
    else
        log_info "Creating user ${SATURN_USER}..."
        useradd -m -s /bin/bash "${SATURN_USER}"
        log_success "User ${SATURN_USER} created"
    fi

    # Add user to docker group
    usermod -aG docker "${SATURN_USER}"
    log_info "User ${SATURN_USER} added to docker group"
}

# =============================================================================
# Directory Structure
# =============================================================================
create_directories() {
    log_info "Creating Saturn directory structure..."

    mkdir -p "${SATURN_DATA}"/{source,ssh,applications,databases,services,backups}
    mkdir -p "${SATURN_HOME}/saturn"

    # Set permissions
    chown -R "${SATURN_USER}:${SATURN_USER}" "${SATURN_DATA}"
    chown -R "${SATURN_USER}:${SATURN_USER}" "${SATURN_HOME}/saturn"

    log_success "Directory structure created at ${SATURN_DATA}"
}

# =============================================================================
# Docker Network
# =============================================================================
create_docker_network() {
    if docker network inspect saturn &>/dev/null; then
        log_info "Docker network 'saturn' already exists"
    else
        log_info "Creating Docker network 'saturn'..."
        docker network create saturn
        log_success "Docker network 'saturn' created"
    fi
}

# =============================================================================
# Firewall Setup
# =============================================================================
setup_firewall() {
    log_info "Configuring firewall (UFW)..."

    # Reset UFW to defaults
    ufw --force reset

    # Default policies
    ufw default deny incoming
    ufw default allow outgoing

    # Allow SSH
    ufw allow ssh

    # Allow HTTP/HTTPS
    ufw allow 80/tcp
    ufw allow 443/tcp

    # Allow Saturn ports (adjust based on your needs)
    ufw allow 8000:8010/tcp  # Saturn app ports for multiple environments
    ufw allow 6001/tcp       # Soketi WebSocket

    # Enable firewall
    ufw --force enable

    log_success "Firewall configured"
}

# =============================================================================
# Fail2ban Setup
# =============================================================================
setup_fail2ban() {
    log_info "Configuring fail2ban..."

    cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 1h
findtime = 10m
maxretry = 5

[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 3
EOF

    systemctl enable fail2ban
    systemctl restart fail2ban

    log_success "fail2ban configured"
}

# =============================================================================
# Swap Setup (for low-memory VPS)
# =============================================================================
setup_swap() {
    if [[ -f /swapfile ]]; then
        log_info "Swap already exists"
        return
    fi

    # Get total memory in MB
    TOTAL_MEM=$(free -m | awk '/^Mem:/{print $2}')

    # Create swap if memory < 4GB
    if [[ $TOTAL_MEM -lt 4096 ]]; then
        log_info "Creating 2GB swap file..."
        fallocate -l 2G /swapfile
        chmod 600 /swapfile
        mkswap /swapfile
        swapon /swapfile
        echo '/swapfile none swap sw 0 0' >> /etc/fstab
        log_success "Swap file created"
    else
        log_info "System has sufficient memory, skipping swap setup"
    fi
}

# =============================================================================
# SSH Hardening (optional)
# =============================================================================
harden_ssh() {
    log_info "Hardening SSH configuration..."

    # Backup original config
    cp /etc/ssh/sshd_config /etc/ssh/sshd_config.backup

    # Apply hardening (but keep password auth for now - user should configure keys manually)
    sed -i 's/#PermitRootLogin yes/PermitRootLogin prohibit-password/' /etc/ssh/sshd_config
    sed -i 's/#MaxAuthTries 6/MaxAuthTries 3/' /etc/ssh/sshd_config

    systemctl restart sshd

    log_success "SSH hardened"
}

# =============================================================================
# Generate Deploy Key
# =============================================================================
generate_deploy_key() {
    DEPLOY_KEY_PATH="${SATURN_DATA}/ssh/deploy_key"

    if [[ -f "${DEPLOY_KEY_PATH}" ]]; then
        log_info "Deploy key already exists"
        return
    fi

    log_info "Generating deploy key..."

    ssh-keygen -t ed25519 -f "${DEPLOY_KEY_PATH}" -N "" -C "saturn-deploy@$(hostname)"
    chown "${SATURN_USER}:${SATURN_USER}" "${DEPLOY_KEY_PATH}"*
    chmod 600 "${DEPLOY_KEY_PATH}"

    log_success "Deploy key generated"
    log_info "Public key (add this to your GitHub deploy keys):"
    echo ""
    cat "${DEPLOY_KEY_PATH}.pub"
    echo ""
}

# =============================================================================
# Print Summary
# =============================================================================
print_summary() {
    echo ""
    echo "============================================================================="
    echo -e "${GREEN}Saturn Platform Server Setup Complete!${NC}"
    echo "============================================================================="
    echo ""
    echo "Created directories:"
    echo "  - ${SATURN_DATA}/source    - Application source code"
    echo "  - ${SATURN_DATA}/ssh       - SSH keys"
    echo "  - ${SATURN_DATA}/applications - Application data"
    echo "  - ${SATURN_DATA}/databases - Database files"
    echo "  - ${SATURN_DATA}/backups   - Backup files"
    echo ""
    echo "User: ${SATURN_USER}"
    echo "Docker network: saturn"
    echo ""
    echo "Next steps:"
    echo "  1. Add the deploy key to your GitHub repository"
    echo "  2. Clone your repository to ${SATURN_DATA}/source"
    echo "  3. Copy .env.example to .env and configure"
    echo "  4. Run: docker compose -f docker-compose.yml -f docker-compose.staging.yml up -d"
    echo ""
    echo "Firewall ports opened:"
    echo "  - 22 (SSH)"
    echo "  - 80, 443 (HTTP/HTTPS)"
    echo "  - 8000-8010 (Saturn environments)"
    echo "  - 6001 (WebSocket)"
    echo ""
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo ""
    echo "============================================================================="
    echo "Saturn Platform - Server Setup Script"
    echo "============================================================================="
    echo ""

    check_root
    check_os

    update_system
    install_dependencies
    install_docker
    create_saturn_user
    create_directories
    create_docker_network
    setup_swap
    setup_firewall
    setup_fail2ban
    harden_ssh
    generate_deploy_key

    print_summary
}

main "$@"
