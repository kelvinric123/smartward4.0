#!/bin/bash
# =============================================================================
# SmartWard Health Check Script
# Checks all services and reports status
# =============================================================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/env.conf" ]]; then
    source "${SCRIPT_DIR}/env.conf"
fi

APP_NAME="${APP_NAME:-smartward}"
APP_DIR="${APP_DIR:-/var/www/smartward}"
APP_PORT="${APP_PORT:-80}"

# Output mode
QUIET="${1:-}"

print_status() {
    local status=$1
    local message=$2
    
    if [[ "$status" == "ok" ]]; then
        echo -e "${GREEN}✓${NC} $message"
    elif [[ "$status" == "warn" ]]; then
        echo -e "${YELLOW}○${NC} $message"
    else
        echo -e "${RED}✗${NC} $message"
    fi
}

# =============================================================================
# System Resources
# =============================================================================
check_system() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}System Resources${NC}"
        echo "────────────────────────────────────"
    fi
    
    # CPU Load
    load=$(uptime | awk -F'load average:' '{print $2}' | awk '{print $1}' | tr -d ',')
    cores=$(nproc)
    load_pct=$(echo "$load $cores" | awk '{printf "%.0f", ($1/$2)*100}')
    
    if [[ $load_pct -lt 70 ]]; then
        print_status "ok" "CPU Load: ${load} (${load_pct}% of ${cores} cores)"
    elif [[ $load_pct -lt 90 ]]; then
        print_status "warn" "CPU Load: ${load} (${load_pct}% of ${cores} cores)"
    else
        print_status "fail" "CPU Load: ${load} (${load_pct}% of ${cores} cores)"
    fi
    
    # Memory
    mem_total=$(free -m | awk '/^Mem:/{print $2}')
    mem_used=$(free -m | awk '/^Mem:/{print $3}')
    mem_pct=$((mem_used * 100 / mem_total))
    
    if [[ $mem_pct -lt 80 ]]; then
        print_status "ok" "Memory: ${mem_used}MB / ${mem_total}MB (${mem_pct}%)"
    elif [[ $mem_pct -lt 90 ]]; then
        print_status "warn" "Memory: ${mem_used}MB / ${mem_total}MB (${mem_pct}%)"
    else
        print_status "fail" "Memory: ${mem_used}MB / ${mem_total}MB (${mem_pct}%)"
    fi
    
    # Disk
    disk_pct=$(df -h / | awk 'NR==2 {print $5}' | tr -d '%')
    disk_avail=$(df -h / | awk 'NR==2 {print $4}')
    
    if [[ $disk_pct -lt 80 ]]; then
        print_status "ok" "Disk: ${disk_pct}% used (${disk_avail} available)"
    elif [[ $disk_pct -lt 90 ]]; then
        print_status "warn" "Disk: ${disk_pct}% used (${disk_avail} available)"
    else
        print_status "fail" "Disk: ${disk_pct}% used (${disk_avail} available)"
    fi
}

# =============================================================================
# Services
# =============================================================================
check_services() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}Services${NC}"
        echo "────────────────────────────────────"
    fi
    
    local errors=0
    
    # PHP
    if php -v &> /dev/null; then
        php_ver=$(php -v | head -1 | awk '{print $2}')
        print_status "ok" "PHP: v${php_ver}"
    else
        print_status "fail" "PHP: not installed"
        ((errors++))
    fi
    
    # Swoole
    if php -m 2>/dev/null | grep -qi swoole; then
        swoole_ver=$(php --ri swoole 2>/dev/null | grep "Swoole Version" | awk '{print $NF}')
        print_status "ok" "Swoole: v${swoole_ver:-installed}"
    else
        print_status "fail" "Swoole: not installed"
        ((errors++))
    fi
    
    # MySQL/MariaDB
    if systemctl is-active --quiet mariadb; then
        print_status "ok" "MariaDB: running"
    elif systemctl is-active --quiet mysql; then
        print_status "ok" "MySQL: running"
    else
        print_status "fail" "MySQL/MariaDB: not running"
        ((errors++))
    fi
    
    # Redis
    if systemctl is-active --quiet redis-server; then
        redis_ver=$(redis-server --version | awk '{print $3}' | cut -d= -f2)
        print_status "ok" "Redis: v${redis_ver} (running)"
    else
        print_status "fail" "Redis: not running"
        ((errors++))
    fi
    
    # Supervisor
    if systemctl is-active --quiet supervisor; then
        print_status "ok" "Supervisor: running"
    else
        print_status "warn" "Supervisor: not running"
    fi
    
    # Nginx (optional)
    if command -v nginx &> /dev/null; then
        if systemctl is-active --quiet nginx; then
            print_status "ok" "Nginx: running"
        else
            print_status "warn" "Nginx: not running"
        fi
    fi
    
    return $errors
}

# =============================================================================
# Application
# =============================================================================
check_application() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}Application${NC}"
        echo "────────────────────────────────────"
    fi
    
    local errors=0
    
    # Check application directory
    if [[ -d "${APP_DIR}" ]]; then
        print_status "ok" "App Directory: ${APP_DIR}"
    else
        print_status "fail" "App Directory: not found"
        ((errors++))
        return $errors
    fi
    
    # Check .env file
    if [[ -f "${APP_DIR}/.env" ]]; then
        print_status "ok" ".env file: present"
    else
        print_status "fail" ".env file: missing"
        ((errors++))
    fi
    
    # Check Octane process
    if command -v supervisorctl &> /dev/null; then
        octane_status=$(supervisorctl status ${APP_NAME}-octane 2>/dev/null | awk '{print $2}')
        if [[ "$octane_status" == "RUNNING" ]]; then
            print_status "ok" "Laravel Octane: running"
        else
            print_status "fail" "Laravel Octane: ${octane_status:-not configured}"
            ((errors++))
        fi
        
        # Queue workers
        queue_status=$(supervisorctl status ${APP_NAME}-queue:* 2>/dev/null | grep -c "RUNNING" || echo 0)
        if [[ $queue_status -gt 0 ]]; then
            print_status "ok" "Queue Workers: ${queue_status} running"
        else
            print_status "warn" "Queue Workers: none running"
        fi
        
        # Scheduler
        sched_status=$(supervisorctl status ${APP_NAME}-scheduler 2>/dev/null | awk '{print $2}')
        if [[ "$sched_status" == "RUNNING" ]]; then
            print_status "ok" "Scheduler: running"
        else
            print_status "warn" "Scheduler: ${sched_status:-not configured}"
        fi
    fi
    
    # Check HTTP response
    if command -v curl &> /dev/null; then
        http_code=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:${APP_PORT}/health" 2>/dev/null || echo "000")
        if [[ "$http_code" == "200" ]]; then
            print_status "ok" "HTTP Health: OK (port ${APP_PORT})"
        elif [[ "$http_code" == "000" ]]; then
            print_status "fail" "HTTP Health: not responding (port ${APP_PORT})"
            ((errors++))
        else
            print_status "warn" "HTTP Health: status ${http_code}"
        fi
    fi
    
    return $errors
}

# =============================================================================
# Database Connectivity
# =============================================================================
check_database() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}Database${NC}"
        echo "────────────────────────────────────"
    fi
    
    if [[ ! -f "${APP_DIR}/.env" ]]; then
        print_status "warn" "Cannot check database (no .env file)"
        return 0
    fi
    
    cd "${APP_DIR}"
    
    # Test database connection via artisan
    if php artisan db:show --counts 2>/dev/null | grep -q "Database"; then
        print_status "ok" "Database connection: OK"
    else
        # Fallback check
        db_status=$(php artisan tinker --execute="DB::connection()->getPdo(); echo 'OK';" 2>/dev/null || echo "FAIL")
        if [[ "$db_status" == *"OK"* ]]; then
            print_status "ok" "Database connection: OK"
        else
            print_status "fail" "Database connection: FAILED"
            return 1
        fi
    fi
    
    return 0
}

# =============================================================================
# Redis Connectivity
# =============================================================================
check_redis() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}Redis${NC}"
        echo "────────────────────────────────────"
    fi
    
    if command -v redis-cli &> /dev/null; then
        ping_result=$(redis-cli ping 2>/dev/null)
        if [[ "$ping_result" == "PONG" ]]; then
            print_status "ok" "Redis connection: OK"
            
            # Memory usage
            redis_mem=$(redis-cli info memory 2>/dev/null | grep "used_memory_human" | cut -d: -f2 | tr -d '\r')
            if [[ -n "$redis_mem" ]]; then
                print_status "ok" "Redis memory: ${redis_mem}"
            fi
        else
            print_status "fail" "Redis connection: FAILED"
            return 1
        fi
    else
        print_status "warn" "redis-cli not available"
    fi
    
    return 0
}

# =============================================================================
# Log Errors
# =============================================================================
check_logs() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}Recent Errors${NC}"
        echo "────────────────────────────────────"
    fi
    
    # Check Laravel log
    laravel_log="${APP_DIR}/storage/logs/laravel.log"
    if [[ -f "$laravel_log" ]]; then
        error_count=$(grep -c "ERROR\|CRITICAL\|ALERT\|EMERGENCY" "$laravel_log" 2>/dev/null | tail -1 || echo 0)
        recent_errors=$(tail -100 "$laravel_log" 2>/dev/null | grep -c "ERROR\|CRITICAL" || echo 0)
        
        if [[ $recent_errors -eq 0 ]]; then
            print_status "ok" "Laravel log: no recent errors"
        else
            print_status "warn" "Laravel log: ${recent_errors} errors in last 100 lines"
        fi
    else
        print_status "warn" "Laravel log: not found"
    fi
    
    # Check Octane log
    octane_log="/var/log/supervisor/${APP_NAME}-octane-error.log"
    if [[ -f "$octane_log" ]]; then
        octane_errors=$(tail -50 "$octane_log" 2>/dev/null | grep -ci "error\|exception\|fatal" || echo 0)
        if [[ $octane_errors -eq 0 ]]; then
            print_status "ok" "Octane log: no recent errors"
        else
            print_status "warn" "Octane log: ${octane_errors} errors in last 50 lines"
        fi
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo -e "${CYAN}════════════════════════════════════${NC}"
        echo -e "${CYAN}  SmartWard Health Check${NC}"
        echo -e "${CYAN}  $(date '+%Y-%m-%d %H:%M:%S')${NC}"
        echo -e "${CYAN}════════════════════════════════════${NC}"
    fi
    
    local total_errors=0
    
    check_system
    
    check_services
    total_errors=$((total_errors + $?))
    
    check_application
    total_errors=$((total_errors + $?))
    
    check_database
    total_errors=$((total_errors + $?))
    
    check_redis
    total_errors=$((total_errors + $?))
    
    check_logs
    
    if [[ "$QUIET" != "-q" ]]; then
        echo ""
        echo "────────────────────────────────────"
        if [[ $total_errors -eq 0 ]]; then
            echo -e "${GREEN}All checks passed!${NC}"
        else
            echo -e "${RED}${total_errors} check(s) failed${NC}"
        fi
        echo ""
    fi
    
    exit $total_errors
}

main "$@"












