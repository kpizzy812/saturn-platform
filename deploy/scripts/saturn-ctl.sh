#!/bin/bash
# =============================================================================
# Saturn Platform - Interactive Control Panel
# =============================================================================
# Interactive terminal UI for managing Saturn Platform on the server.
#
# Usage:
#   ./saturn-ctl.sh              # Launch interactive menu
#   ./saturn-ctl.sh logs         # Quick access to logs menu
#   ./saturn-ctl.sh deploy       # Quick deploy
#
# =============================================================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
NC='\033[0m'
BOLD='\033[1m'

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
SATURN_DATA="/data/saturn"
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.prod.yml -f docker-compose.dev.override.yml"
ENV_FILE="${SATURN_DATA}/source/.env"

# Available containers
CONTAINERS=(
    "saturn-dev:Saturn Application"
    "saturn-db:PostgreSQL Database"
    "saturn-redis:Redis Cache"
    "saturn-soketi:Soketi WebSocket"
)

# =============================================================================
# Helper Functions
# =============================================================================

# Show header
show_header() {
    clear
    echo -e "${CYAN}${BOLD}"
    echo "╔════════════════════════════════════════════════════════════╗"
    echo "║          Saturn Platform - Control Panel                  ║"
    echo "╚════════════════════════════════════════════════════════════╝"
    echo -e "${NC}"
}

# Press any key to continue
press_any_key() {
    echo ""
    echo -e "${YELLOW}Press any key to continue...${NC}"
    read -n 1 -s
}

# Change directory to project root
cd_project() {
    cd "$PROJECT_ROOT"
}

# Execute docker compose command
docker_compose() {
    cd_project
    docker compose $COMPOSE_FILES --env-file "$ENV_FILE" "$@"
}

# =============================================================================
# Menu Functions
# =============================================================================

# Main Menu
show_main_menu() {
    show_header
    echo -e "${BOLD}Main Menu:${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} Deploy & Restart"
    echo "  ${GREEN}2)${NC} View Logs"
    echo "  ${GREEN}3)${NC} Service Control"
    echo "  ${GREEN}4)${NC} Build & Cache"
    echo "  ${GREEN}5)${NC} Database Operations"
    echo "  ${GREEN}6)${NC} System Information"
    echo ""
    echo "  ${RED}0)${NC} Exit"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1) menu_deploy ;;
        2) menu_logs ;;
        3) menu_service_control ;;
        4) menu_build_cache ;;
        5) menu_database ;;
        6) menu_system_info ;;
        0) exit 0 ;;
        *)
            log_error "Invalid option"
            press_any_key
            show_main_menu
            ;;
    esac
}

# =============================================================================
# Deploy Menu
# =============================================================================
menu_deploy() {
    show_header
    echo -e "${BOLD}Deploy & Restart${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} Full Deploy (pull + migrate + restart)"
    echo "  ${GREEN}2)${NC} Quick Restart (no pull, no migrate)"
    echo "  ${GREEN}3)${NC} Restart with Migrations"
    echo "  ${GREEN}4)${NC} Rollback to Previous Backup"
    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1)
            log_info "Running full deployment..."
            "${SCRIPT_DIR}/deploy.sh"
            press_any_key
            ;;
        2)
            log_info "Quick restart..."
            docker_compose restart
            log_success "Services restarted"
            press_any_key
            ;;
        3)
            log_info "Restarting with migrations..."
            docker_compose restart saturn
            sleep 5
            docker exec saturn-dev php artisan migrate --force
            docker exec saturn-dev php artisan config:cache
            log_success "Done"
            press_any_key
            ;;
        4)
            log_info "Running rollback..."
            "${SCRIPT_DIR}/deploy.sh" --rollback
            press_any_key
            ;;
        0) show_main_menu ;;
        *) menu_deploy ;;
    esac

    show_main_menu
}

# =============================================================================
# Logs Menu
# =============================================================================
menu_logs() {
    show_header
    echo -e "${BOLD}View Logs${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} All Containers (realtime)"
    echo "  ${GREEN}2)${NC} Select Container"
    echo "  ${GREEN}3)${NC} Last 1000 Lines + Follow"
    echo "  ${GREEN}4)${NC} Last 1000 Lines Only"
    echo "  ${GREEN}5)${NC} Search in Logs"
    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1)
            log_info "Showing all container logs (Ctrl+C to exit)..."
            sleep 1
            docker_compose logs -f --tail=100
            ;;
        2)
            select_container_for_logs
            ;;
        3)
            select_container_for_logs "follow"
            ;;
        4)
            select_container_for_logs "tail"
            ;;
        5)
            search_in_logs
            ;;
        0) show_main_menu ;;
        *) menu_logs ;;
    esac

    show_main_menu
}

# Select container for logs
select_container_for_logs() {
    local mode="${1:-follow}"

    show_header
    echo -e "${BOLD}Select Container:${NC}"
    echo ""

    local idx=1
    for container_info in "${CONTAINERS[@]}"; do
        IFS=':' read -r container_name container_desc <<< "$container_info"
        echo "  ${GREEN}${idx})${NC} ${container_desc} (${container_name})"
        ((idx++))
    done

    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select container: "
    read -r choice

    if [[ "$choice" == "0" ]]; then
        menu_logs
        return
    fi

    if [[ "$choice" -ge 1 && "$choice" -le "${#CONTAINERS[@]}" ]]; then
        local selected="${CONTAINERS[$((choice-1))]}"
        IFS=':' read -r container_name container_desc <<< "$selected"

        log_info "Viewing logs for: ${container_desc}"
        sleep 1

        case $mode in
            "follow")
                docker logs -f --tail=1000 "$container_name"
                ;;
            "tail")
                docker logs --tail=1000 "$container_name" | less
                ;;
            *)
                docker logs -f --tail=100 "$container_name"
                ;;
        esac
    else
        log_error "Invalid selection"
        press_any_key
        select_container_for_logs "$mode"
    fi
}

# Search in logs
search_in_logs() {
    show_header
    echo -e "${BOLD}Search in Logs${NC}"
    echo ""
    echo -n "Enter search term: "
    read -r search_term

    if [[ -z "$search_term" ]]; then
        log_warn "Search term cannot be empty"
        press_any_key
        return
    fi

    log_info "Searching for: ${search_term}"
    echo ""

    docker_compose logs --tail=5000 | grep -i --color=always "$search_term" | less -R

    press_any_key
}

# =============================================================================
# Service Control Menu
# =============================================================================
menu_service_control() {
    show_header
    echo -e "${BOLD}Service Control${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} Start All Services"
    echo "  ${GREEN}2)${NC} Stop All Services"
    echo "  ${GREEN}3)${NC} Restart All Services"
    echo "  ${GREEN}4)${NC} Restart Specific Service"
    echo "  ${GREEN}5)${NC} View Service Status"
    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1)
            log_info "Starting all services..."
            docker_compose up -d
            log_success "Services started"
            press_any_key
            ;;
        2)
            log_warn "Stopping all services..."
            docker_compose down
            log_success "Services stopped"
            press_any_key
            ;;
        3)
            log_info "Restarting all services..."
            docker_compose restart
            log_success "Services restarted"
            press_any_key
            ;;
        4)
            restart_specific_service
            ;;
        5)
            show_service_status
            ;;
        0) show_main_menu ;;
        *) menu_service_control ;;
    esac

    show_main_menu
}

# Restart specific service
restart_specific_service() {
    show_header
    echo -e "${BOLD}Restart Service:${NC}"
    echo ""

    local idx=1
    for container_info in "${CONTAINERS[@]}"; do
        IFS=':' read -r container_name container_desc <<< "$container_info"
        echo "  ${GREEN}${idx})${NC} ${container_desc}"
        ((idx++))
    done

    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select service: "
    read -r choice

    if [[ "$choice" == "0" ]]; then
        return
    fi

    if [[ "$choice" -ge 1 && "$choice" -le "${#CONTAINERS[@]}" ]]; then
        local selected="${CONTAINERS[$((choice-1))]}"
        IFS=':' read -r container_name container_desc <<< "$selected"

        log_info "Restarting: ${container_desc}"
        docker restart "$container_name"
        log_success "Service restarted"
    else
        log_error "Invalid selection"
    fi

    press_any_key
}

# Show service status
show_service_status() {
    show_header
    echo -e "${BOLD}Service Status${NC}"
    echo ""

    docker_compose ps

    echo ""
    echo -e "${BOLD}Container Health:${NC}"
    echo ""

    for container_info in "${CONTAINERS[@]}"; do
        IFS=':' read -r container_name container_desc <<< "$container_info"

        if docker ps --format '{{.Names}}' | grep -q "^${container_name}$"; then
            local health=$(docker inspect --format='{{.State.Health.Status}}' "$container_name" 2>/dev/null || echo "no healthcheck")
            local status=$(docker inspect --format='{{.State.Status}}' "$container_name" 2>/dev/null || echo "unknown")

            if [[ "$status" == "running" ]]; then
                if [[ "$health" == "healthy" ]] || [[ "$health" == "no healthcheck" ]]; then
                    echo -e "  ${GREEN}✓${NC} ${container_desc}: ${GREEN}${status}${NC}"
                else
                    echo -e "  ${YELLOW}!${NC} ${container_desc}: ${YELLOW}${status} (${health})${NC}"
                fi
            else
                echo -e "  ${RED}✗${NC} ${container_desc}: ${RED}${status}${NC}"
            fi
        else
            echo -e "  ${RED}✗${NC} ${container_desc}: ${RED}not running${NC}"
        fi
    done

    press_any_key
}

# =============================================================================
# Build & Cache Menu
# =============================================================================
menu_build_cache() {
    show_header
    echo -e "${BOLD}Build & Cache${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} Pull Latest Images"
    echo "  ${GREEN}2)${NC} Build with Cache"
    echo "  ${GREEN}3)${NC} Build without Cache (fresh)"
    echo "  ${GREEN}4)${NC} Clear Laravel Caches"
    echo "  ${GREEN}5)${NC} Rebuild Laravel Caches"
    echo "  ${GREEN}6)${NC} Clear + Rebuild All Caches"
    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1)
            log_info "Pulling latest images..."
            docker_compose pull
            log_success "Images updated"
            press_any_key
            ;;
        2)
            log_info "Building with cache..."
            docker_compose build
            log_success "Build complete"
            press_any_key
            ;;
        3)
            log_warn "Building without cache (this may take a while)..."
            docker_compose build --no-cache
            log_success "Fresh build complete"
            press_any_key
            ;;
        4)
            log_info "Clearing Laravel caches..."
            docker exec saturn-dev php artisan cache:clear
            docker exec saturn-dev php artisan config:clear
            docker exec saturn-dev php artisan route:clear
            docker exec saturn-dev php artisan view:clear
            log_success "Caches cleared"
            press_any_key
            ;;
        5)
            log_info "Rebuilding Laravel caches..."
            docker exec saturn-dev php artisan config:cache
            docker exec saturn-dev php artisan route:cache
            docker exec saturn-dev php artisan view:cache
            log_success "Caches rebuilt"
            press_any_key
            ;;
        6)
            log_info "Clearing all caches..."
            docker exec saturn-dev php artisan cache:clear
            docker exec saturn-dev php artisan config:clear
            docker exec saturn-dev php artisan route:clear
            docker exec saturn-dev php artisan view:clear

            log_info "Rebuilding caches..."
            docker exec saturn-dev php artisan config:cache
            docker exec saturn-dev php artisan route:cache
            docker exec saturn-dev php artisan view:cache

            log_success "All caches refreshed"
            press_any_key
            ;;
        0) show_main_menu ;;
        *) menu_build_cache ;;
    esac

    show_main_menu
}

# =============================================================================
# Database Menu
# =============================================================================
menu_database() {
    show_header
    echo -e "${BOLD}Database Operations${NC}"
    echo ""
    echo "  ${GREEN}1)${NC} Run Migrations"
    echo "  ${GREEN}2)${NC} Run Migrations (fresh)"
    echo "  ${GREEN}3)${NC} Run Seeders"
    echo "  ${GREEN}4)${NC} Create Backup"
    echo "  ${GREEN}5)${NC} List Backups"
    echo "  ${GREEN}6)${NC} Restore from Backup"
    echo "  ${GREEN}7)${NC} Database Shell (psql)"
    echo ""
    echo "  ${YELLOW}0)${NC} Back"
    echo ""
    echo -n "Select option: "
    read -r choice

    case $choice in
        1)
            log_info "Running migrations..."
            docker exec saturn-dev php artisan migrate --force
            log_success "Migrations complete"
            press_any_key
            ;;
        2)
            log_warn "This will DROP all tables and recreate them!"
            echo -n "Are you sure? (yes/no): "
            read -r confirm
            if [[ "$confirm" == "yes" ]]; then
                log_info "Running fresh migrations..."
                docker exec saturn-dev php artisan migrate:fresh --force
                log_success "Fresh migrations complete"
            else
                log_info "Cancelled"
            fi
            press_any_key
            ;;
        3)
            log_info "Running production seeder..."
            docker exec saturn-dev php artisan db:seed --class=ProductionSeeder --force
            log_success "Seeders complete"
            press_any_key
            ;;
        4)
            create_database_backup
            ;;
        5)
            list_backups
            ;;
        6)
            restore_database_backup
            ;;
        7)
            log_info "Opening database shell (type \\q to exit)..."
            sleep 1
            docker exec -it saturn-db psql -U saturn -d saturn
            ;;
        0) show_main_menu ;;
        *) menu_database ;;
    esac

    show_main_menu
}

# Create database backup
create_database_backup() {
    local backup_dir="${SATURN_DATA}/backups"
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local backup_file="${backup_dir}/manual_${timestamp}.sql"

    log_info "Creating backup..."
    mkdir -p "$backup_dir"

    docker exec saturn-db pg_dump -U saturn -d saturn > "$backup_file"

    log_success "Backup created: manual_${timestamp}.sql"
    log_info "Location: ${backup_file}"

    press_any_key
}

# List backups
list_backups() {
    local backup_dir="${SATURN_DATA}/backups"

    show_header
    echo -e "${BOLD}Available Backups${NC}"
    echo ""

    if [[ ! -d "$backup_dir" ]] || [[ -z "$(ls -A "$backup_dir" 2>/dev/null)" ]]; then
        log_warn "No backups found"
    else
        ls -lh "$backup_dir"/*.sql 2>/dev/null || log_warn "No SQL backups found"
    fi

    press_any_key
}

# Restore database backup
restore_database_backup() {
    local backup_dir="${SATURN_DATA}/backups"

    show_header
    echo -e "${BOLD}Restore Database${NC}"
    echo ""

    if [[ ! -d "$backup_dir" ]]; then
        log_error "Backup directory not found"
        press_any_key
        return
    fi

    local backups=($(ls -t "$backup_dir"/*.sql 2>/dev/null))

    if [[ ${#backups[@]} -eq 0 ]]; then
        log_error "No backups found"
        press_any_key
        return
    fi

    echo "Available backups:"
    echo ""

    local idx=1
    for backup in "${backups[@]}"; do
        local size=$(du -h "$backup" | cut -f1)
        echo "  ${GREEN}${idx})${NC} $(basename "$backup") (${size})"
        ((idx++))
    done

    echo ""
    echo "  ${YELLOW}0)${NC} Cancel"
    echo ""
    echo -n "Select backup to restore: "
    read -r choice

    if [[ "$choice" == "0" ]]; then
        return
    fi

    if [[ "$choice" -ge 1 && "$choice" -le "${#backups[@]}" ]]; then
        local selected_backup="${backups[$((choice-1))]}"

        log_warn "This will REPLACE the current database!"
        echo -n "Are you sure? (yes/no): "
        read -r confirm

        if [[ "$confirm" == "yes" ]]; then
            log_info "Restoring from: $(basename "$selected_backup")"
            docker exec -i saturn-db psql -U saturn -d saturn < "$selected_backup"
            log_success "Database restored"
        else
            log_info "Cancelled"
        fi
    else
        log_error "Invalid selection"
    fi

    press_any_key
}

# =============================================================================
# System Info Menu
# =============================================================================
menu_system_info() {
    show_header
    echo -e "${BOLD}System Information${NC}"
    echo ""

    # Docker version
    echo -e "${CYAN}Docker Version:${NC}"
    docker --version
    docker compose version
    echo ""

    # Disk usage
    echo -e "${CYAN}Disk Usage:${NC}"
    df -h "$SATURN_DATA" 2>/dev/null || df -h /
    echo ""

    # Docker disk usage
    echo -e "${CYAN}Docker Disk Usage:${NC}"
    docker system df
    echo ""

    # Container resources
    echo -e "${CYAN}Container Resources:${NC}"
    docker stats --no-stream --format "table {{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.NetIO}}"
    echo ""

    # Application info
    if docker ps --format '{{.Names}}' | grep -q "^saturn-dev$"; then
        echo -e "${CYAN}Application Info:${NC}"
        docker exec saturn-dev php artisan --version 2>/dev/null || echo "Unable to get Laravel version"
        echo ""
    fi

    press_any_key
}

# =============================================================================
# Quick Commands (CLI arguments)
# =============================================================================
if [[ $# -gt 0 ]]; then
    case "$1" in
        logs)
            menu_logs
            ;;
        deploy)
            "${SCRIPT_DIR}/deploy.sh"
            ;;
        status)
            show_service_status
            ;;
        restart)
            log_info "Restarting all services..."
            docker_compose restart
            log_success "Services restarted"
            ;;
        *)
            log_error "Unknown command: $1"
            echo ""
            echo "Available commands:"
            echo "  logs     - View logs menu"
            echo "  deploy   - Run deployment"
            echo "  status   - Show service status"
            echo "  restart  - Restart all services"
            echo ""
            echo "Run without arguments for interactive menu"
            exit 1
            ;;
    esac
    exit 0
fi

# =============================================================================
# Main Entry Point
# =============================================================================
show_main_menu
