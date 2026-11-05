#!/usr/bin/env bash
# ==============================================================================
#                        N1LUX PETRO PANEL (N1X PANEL)
#              Automated Installer, Setup & Port Manager (Debian/Ubuntu)
# ==============================================================================
# Usage (as root):
#   bash <(curl -fsSL https://raw.githubusercontent.com/CyberN1LUX/N1X-PANEL/main/install.sh)
#
# Menu:
#   1) Clone / update the panel code
#   2) Install all dependencies and configure everything
#   3) Manage the panel port and services
# ==============================================================================

set -o pipefail

APP_NAME="N1LUX PETRO"
APP_VERSION="2.0"
REPO_URL="https://github.com/CyberN1LUX/N1X-PANEL.git"
SELF_URL="https://raw.githubusercontent.com/CyberN1LUX/N1X-PANEL/main/install.sh"
BRANCH="main"

RUNNER_PORT=8080
DEFAULT_PANEL_PORT=8000
APP_PORT_START=5000
APP_PORT_END=7999

DEFAULT_DIR="/opt/n1x-panel"

# ---------- Colors ----------
RESET=$'\033[0m'
BOLD=$'\033[1m'
DIM=$'\033[2m'
RED=$'\033[31m'
GREEN=$'\033[32m'
YELLOW=$'\033[33m'
BLUE=$'\033[34m'
MAGENTA=$'\033[35m'
CYAN=$'\033[36m'
WHITE=$'\033[37m'
BRIGHT_RED=$'\033[91m'
BRIGHT_GREEN=$'\033[92m'
BRIGHT_YELLOW=$'\033[93m'
BRIGHT_BLUE=$'\033[94m'
BRIGHT_MAGENTA=$'\033[95m'
BRIGHT_CYAN=$'\033[96m'
BRIGHT_WHITE=$'\033[97m'

# ==============================================================================
# Interactive terminal check
# ==============================================================================
if [ ! -t 0 ]; then
    echo -e "${BRIGHT_RED}This installer needs an interactive terminal.${RESET}"
    echo "Run it with:"
    echo "  bash <(curl -fsSL ${SELF_URL})"
    exit 1
fi

# ==============================================================================
# Root check / automatic sudo elevation
# ==============================================================================
if [ "$(id -u)" -ne 0 ]; then
    if ! command -v sudo >/dev/null 2>&1; then
        echo -e "${BRIGHT_RED}This installer must run as root.${RESET}"
        echo "Log in as root (or install sudo) and run it again."
        exit 1
    fi

    ELEVATED_TMP="$(mktemp /tmp/n1x-installer.XXXXXX.sh)"
    if [ -f "$0" ]; then
        cp "$0" "$ELEVATED_TMP" || exit 1
    else
        curl -fsSL "$SELF_URL" -o "$ELEVATED_TMP" || {
            echo "Could not download the installer. Run it as root instead."
            rm -f "$ELEVATED_TMP"
            exit 1
        }
    fi

    echo "Root privileges are required. Re-running with sudo..."
    sudo -E bash "$ELEVATED_TMP" "$@"
    ELEVATED_RC=$?
    rm -f "$ELEVATED_TMP"
    exit "$ELEVATED_RC"
fi

export DEBIAN_FRONTEND=noninteractive
export COMPOSER_ALLOW_SUPERUSER=1

# ==============================================================================
# State / logging
# ==============================================================================
STATE_DIR="${N1X_STATE_DIR:-$HOME/.n1x-panel}"
mkdir -p "$STATE_DIR" 2>/dev/null || STATE_DIR="/tmp/n1x-panel-${UID}"
STATE_FILE="${STATE_DIR}/state.conf"
LOG_FILE="${STATE_DIR}/installer.log"
mkdir -p "$STATE_DIR" 2>/dev/null || true
touch "$LOG_FILE" 2>/dev/null || LOG_FILE="/tmp/n1x-panel-installer.log"

INSTALL_DIR="${N1X_DIR:-$DEFAULT_DIR}"
PANEL_PORT="$DEFAULT_PANEL_PORT"
SERVER_IP=""

if [ -f "$STATE_FILE" ]; then
    # shellcheck disable=SC1090
    . "$STATE_FILE" 2>/dev/null || true
fi

INSTALL_DIR="${N1X_DIR:-${INSTALL_DIR:-$DEFAULT_DIR}}"
PANEL_PORT="${PANEL_PORT:-$DEFAULT_PANEL_PORT}"

run_in_dir() {
    local dir="$1"
    shift
    ( cd "$dir" && "$@" )
}

save_state() {
    {
        printf 'INSTALL_DIR=%q\n' "$INSTALL_DIR"
        printf 'PANEL_PORT=%q\n' "$PANEL_PORT"
        printf 'SERVER_IP=%q\n' "$SERVER_IP"
    } > "$STATE_FILE" 2>/dev/null || true
}

# ==============================================================================
# Helpers
# ==============================================================================
banner() {
    clear 2>/dev/null || true
    echo ""
    echo -e "  ${BRIGHT_GREEN}${BOLD}  _   _ ___ __  __   ____   _    _   _ _____ _     ${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD} | \\ | |_ _|\\ \\/ /  |  _ \\ / \\  | \\ | | ____| |    ${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD} |  \\| || |  \\  /   | |_) / _ \\ |  \\| |  _| | |    ${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD} | |\\  || |  /  \\   |  __/ ___ \\| |\\  | |___| |___ ${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD} |_| \\_|___|/_/\\_\\  |_| /_/   \\_\\_| \\_|_____|_____|${RESET}"
    echo ""
    echo -e "  ${BRIGHT_CYAN}${BOLD}N1LUX PETRO PANEL${RESET} ${DIM}installer v${APP_VERSION}${RESET}"
    echo -e "  ${DIM}Docker-free multi-language hosting control panel${RESET}"
    echo -e "  ${BRIGHT_BLUE}${BOLD}============================================================${RESET}"
    echo ""
}

section() {
    echo ""
    echo -e "  ${BRIGHT_CYAN}${BOLD}>> $1${RESET}"
    echo -e "  ${DIM}------------------------------------------------------------${RESET}"
}

info() {
    echo -e "  ${BRIGHT_BLUE}[INFO]${RESET} $1"
}

success() {
    echo -e "  ${BRIGHT_GREEN}[ OK ]${RESET} $1"
}

warn() {
    echo -e "  ${BRIGHT_YELLOW}[WARN]${RESET} $1"
}

status_fail() {
    echo -e "  ${BRIGHT_RED}[FAIL]${RESET} $1"
}

fatal() {
    echo -e "  ${BRIGHT_RED}[FAIL]${RESET} $1"
    exit 1
}

pause_screen() {
    echo ""
    if [ -t 0 ]; then
        read -r -p "  Press ENTER to continue..." _
    fi
}

run_silent() {
    local label="$1"
    shift
    local tmp
    local pid
    local spin='|/-\'
    local i=0

    tmp="$(mktemp "${TMPDIR:-/tmp}/n1x-task.XXXXXX")" || return 1
    printf "  %-54s " "$label"

    "$@" >"$tmp" 2>&1 &
    pid=$!

    if [ -t 1 ]; then
        while kill -0 "$pid" 2>/dev/null; do
            printf '\b%s' "${spin:i++%4:1}"
            sleep 0.15
        done
    fi

    if wait "$pid"; then
        printf '\b%s\n' "${GREEN}OK${RESET}"
        cat "$tmp" >> "$LOG_FILE" 2>/dev/null || true
        rm -f "$tmp"
        return 0
    fi

    printf '\b%s\n' "${RED}FAIL${RESET}"
    {
        echo ""
        echo "===== FAILED: $label ($(date '+%Y-%m-%d %H:%M:%S')) ====="
        cat "$tmp" 2>/dev/null || true
        echo "===== END: $label ====="
    } >> "$LOG_FILE" 2>/dev/null || true
    rm -f "$tmp"
    echo -e "  ${DIM}Full log: ${LOG_FILE}${RESET}"
    return 1
}

systemd_available() {
    [ -d /run/systemd/system ] && command -v systemctl >/dev/null 2>&1
}

detect_ip() {
    local ip=""
    if command -v curl >/dev/null 2>&1; then
        ip="$(curl -4 -fsS --max-time 5 https://api.ipify.org 2>/dev/null || true)"
    fi
    if [ -z "$ip" ]; then
        ip="$(hostname -I 2>/dev/null | awk '{print $1}')"
    fi
    if [ -z "$ip" ]; then
        ip="127.0.0.1"
    fi
    printf '%s' "$ip"
}

env_get() {
    local key="$1"
    local file="${2:-$INSTALL_DIR/.env}"
    [ -f "$file" ] || return 0
    grep -m1 -E "^${key}=" "$file" 2>/dev/null \
        | cut -d= -f2- \
        | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

set_env() {
    local key="$1"
    local value="$2"
    local file="${3:-$INSTALL_DIR/.env}"
    local tmp="${file}.tmp.$$"

    [ -f "$file" ] || touch "$file"

    awk -v k="$key" -v v="$value" '
        BEGIN { found = 0 }
        index($0, k "=") == 1 { print k "=" v; found = 1; next }
        { print }
        END { if (!found) print k "=" v }
    ' "$file" > "$tmp" && mv "$tmp" "$file"
}

port_in_use() {
    local port="$1"
    if command -v ss >/dev/null 2>&1; then
        ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "[:.]${port}$"
    elif command -v lsof >/dev/null 2>&1; then
        lsof -nP -iTCP:"$port" -sTCP:LISTEN >/dev/null 2>&1
    elif command -v fuser >/dev/null 2>&1; then
        fuser -s "${port}/tcp" >/dev/null 2>&1
    else
        return 1
    fi
}

is_reserved_port() {
    case "$1" in
        22|80|443|2022|3306|6379|8080) return 0 ;;
        *) return 1 ;;
    esac
}

PORT_ERROR=""
validate_panel_port() {
    local port="$1"
    local current="${2:-}"

    PORT_ERROR=""

    if ! [[ "$port" =~ ^[0-9]+$ ]]; then
        PORT_ERROR="Port must be a number."
        return 1
    fi
    if [ "$port" -lt 1 ] || [ "$port" -gt 65535 ]; then
        PORT_ERROR="Port must be between 1 and 65535."
        return 1
    fi
    if is_reserved_port "$port"; then
        PORT_ERROR="Port ${port} is reserved (SSH, HTTP, panel services or the runner)."
        return 1
    fi
    if [ "$port" -ge "$APP_PORT_START" ] && [ "$port" -le "$APP_PORT_END" ]; then
        PORT_ERROR="Ports ${APP_PORT_START}-${APP_PORT_END} are reserved for hosted applications."
        return 1
    fi
    if [ -n "$current" ] && [ "$port" = "$current" ]; then
        return 0
    fi
    if port_in_use "$port"; then
        PORT_ERROR="Port ${port} is already in use by another service."
        return 1
    fi
    return 0
}

prompt_panel_port() {
    local current="${1:-$DEFAULT_PANEL_PORT}"
    local input=""

    while true; do
        if [ -t 0 ]; then
            read -r -p "  Panel port [${current}]: " input
        else
            input=""
        fi
        input="${input:-$current}"

        if validate_panel_port "$input" "$current"; then
            PANEL_PORT="$input"
            return 0
        fi

        warn "$PORT_ERROR"
        if [ ! -t 0 ]; then
            PANEL_PORT="$current"
            return 0
        fi
    done
}

current_panel_port() {
    local port=""
    if [ -f "$INSTALL_DIR/.env" ]; then
        port="$(env_get PANEL_PORT)"
    fi
    printf '%s' "${port:-${PANEL_PORT:-$DEFAULT_PANEL_PORT}}"
}

service_state() {
    local svc="$1"
    local state=""
    if ! systemd_available; then
        printf 'unknown'
        return
    fi
    state="$(systemctl is-active "$svc" 2>/dev/null || true)"
    printf '%s' "${state:-inactive}"
}

open_firewall_port() {
    local port="$1"
    command -v ufw >/dev/null 2>&1 || return 0
    ufw status 2>/dev/null | grep -q "Status: active" || return 0
    if ufw allow "${port}/tcp" >/dev/null 2>&1; then
        success "UFW: allowed TCP port ${port}"
    else
        warn "UFW: could not open TCP port ${port}"
    fi
}

# ==============================================================================
# System information panel
# ==============================================================================
human_mb() {
    local mb="${1:-0}"
    awk -v mb="$mb" 'BEGIN {
        if (mb >= 1024) printf "%.1f GB", mb / 1024;
        else printf "%d MB", mb;
    }'
}

ram_stats() {
    local total=0 used=0
    if [ -r /proc/meminfo ]; then
        total="$(awk '/^MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null)"
        local available
        available="$(awk '/^MemAvailable:/ {print $2}' /proc/meminfo 2>/dev/null)"
        total="${total:-0}"
        available="${available:-0}"
        used=$(( (total - available) / 1024 ))
        total=$(( total / 1024 ))
    fi
    printf '%s|%s' "$used" "$total"
}

disk_stats() {
    local used=0 total=0 percent=0
    if command -v df >/dev/null 2>&1; then
        read -r used total percent < <(df -Pm / 2>/dev/null | awk 'NR==2 {gsub(/%/,"",$5); print $3, $2, $5}')
    fi
    printf '%s|%s|%s' "${used:-0}" "${total:-0}" "${percent:-0}"
}

uptime_short() {
    awk '{
        s = int($1);
        d = int(s / 86400); s %= 86400;
        h = int(s / 3600); s %= 3600;
        m = int(s / 60);
        if (d > 0) printf "%dd %dh", d, h;
        else if (h > 0) printf "%dh %dm", h, m;
        else printf "%dm", m;
    }' /proc/uptime 2>/dev/null || printf 'unknown'
}

# ==============================================================================
# Main menu header
# ==============================================================================
header() {
    banner

    local port
    local panel_state
    local runner_state
    local installed="no"
    local ram_used="0"
    local ram_total="0"
    local disk_used="0"
    local disk_total="0"
    local disk_percent="0"
    local ram_info=""
    local disk_info=""

    port="$(current_panel_port)"
    [ -f "$INSTALL_DIR/artisan" ] && installed="yes"

    panel_state="$(service_state n1x-panel)"
    runner_state="$(service_state n1x-runner)"

    ram_info="$(ram_stats)"
    IFS='|' read -r ram_used ram_total <<< "$ram_info"
    disk_info="$(disk_stats)"
    IFS='|' read -r disk_used disk_total disk_percent <<< "$disk_info"

    echo -e "  ${DIM}Directory :${RESET} ${WHITE}${INSTALL_DIR}${RESET}  ${DIM}(${installed})${RESET}"
    echo -e "  ${DIM}Panel port:${RESET} ${WHITE}${port}${RESET}  ${DIM}Runner port:${RESET} ${WHITE}${RUNNER_PORT}${RESET}"
    echo -e "  ${DIM}Services  :${RESET} panel ${WHITE}${panel_state}${RESET}  ${DIM}|${RESET}  runner ${WHITE}${runner_state}${RESET}"
    echo -e "  ${DIM}Resources :${RESET} RAM ${WHITE}$(human_mb "$ram_used") / $(human_mb "$ram_total")${RESET}  ${DIM}|${RESET}  Disk ${WHITE}$(human_mb "$disk_used") / $(human_mb "$disk_total") (${disk_percent}%)${RESET}  ${DIM}|${RESET}  UP ${WHITE}$(uptime_short)${RESET}"
    echo ""
    echo -e "  ${BRIGHT_BLUE}${BOLD}============================================================${RESET}"
    echo -e "  ${BRIGHT_WHITE}${BOLD}MAIN MENU${RESET}"
    echo -e "  ${BRIGHT_BLUE}${BOLD}============================================================${RESET}"
    echo ""
    echo -e "  ${BRIGHT_CYAN}${BOLD}01${RESET}  ${BRIGHT_WHITE}${BOLD}Clone / Update Code${RESET}"
    echo -e "      ${DIM}Clone the N1X PANEL repository or pull the latest main branch${RESET}"
    echo ""
    echo -e "  ${BRIGHT_GREEN}${BOLD}02${RESET}  ${BRIGHT_WHITE}${BOLD}Install Everything & Setup${RESET}"
    echo -e "      ${DIM}PHP, Composer, Node.js, Yarn, MariaDB, Redis, Nginx,${RESET}"
    echo -e "      ${DIM}frontend build, database, runner, admin and systemd services${RESET}"
    echo ""
    echo -e "  ${BRIGHT_YELLOW}${BOLD}03${RESET}  ${BRIGHT_WHITE}${BOLD}Port Management${RESET}"
    echo -e "      ${DIM}Change the panel port, restart services, status and firewall${RESET}"
    echo ""
    echo -e "  ${DIM}${BOLD}00${RESET}  ${BRIGHT_WHITE}${BOLD}Exit${RESET}"
    echo ""
    echo -e "  ${BRIGHT_BLUE}${BOLD}============================================================${RESET}"
}

# ==============================================================================
# 01 - Clone / update code
# ==============================================================================
clone_repository() {
    local input=""
    local target=""

    section "01 . CLONE / UPDATE CODE"

    if [ -t 0 ]; then
        read -r -p "  Install directory [${INSTALL_DIR}]: " input
    fi
    target="${input:-$INSTALL_DIR}"
    target="${target%/}"

    if [ -z "$target" ] || [ "$target" = "/" ]; then
        status_fail "Invalid install directory."
        pause_screen
        return 1
    fi

    INSTALL_DIR="$target"
    save_state

    if ! command -v git >/dev/null 2>&1; then
        info "Installing git first..."
        apt-get update -y -q >/dev/null 2>&1 || true
        if ! run_silent "Installing git" apt-get install -y -q git; then
            status_fail "Could not install git."
            pause_screen
            return 1
        fi
    fi

    if [ -d "$INSTALL_DIR/.git" ]; then
        info "Existing repository found. Updating from origin/${BRANCH}..."
        if run_silent "Fetching origin/${BRANCH}" git -C "$INSTALL_DIR" fetch origin "$BRANCH" \
            && run_silent "Pulling latest code" git -C "$INSTALL_DIR" pull --ff-only origin "$BRANCH"; then
            success "Code updated to the latest ${BRANCH}."
        else
            warn "Could not fast-forward automatically (local changes?)."
            warn "Your code was left untouched. Review it manually if needed."
        fi
    elif [ -f "$INSTALL_DIR/artisan" ]; then
        warn "Directory already contains the panel but has no .git folder."
        warn "Using it as-is: $INSTALL_DIR"
    else
        if [ -d "$INSTALL_DIR" ] && [ -n "$(ls -A "$INSTALL_DIR" 2>/dev/null)" ]; then
            status_fail "Directory is not empty and is not a git repository:"
            echo -e "      ${WHITE}${INSTALL_DIR}${RESET}"
            pause_screen
            return 1
        fi

        mkdir -p "$(dirname "$INSTALL_DIR")" || {
            status_fail "Could not create parent directory for ${INSTALL_DIR}."
            pause_screen
            return 1
        }

        if run_silent "Cloning ${REPO_URL}" git clone --depth 1 -b "$BRANCH" "$REPO_URL" "$INSTALL_DIR"; then
            success "Repository cloned to ${INSTALL_DIR}"
        else
            status_fail "Clone failed. Check your network connection."
            pause_screen
            return 1
        fi
    fi

    save_state
    success "Code is ready. Next: option 02 to install and configure."
    pause_screen
    return 0
}

# ==============================================================================
# Dependency installers
# ==============================================================================
check_os() {
    if ! command -v apt-get >/dev/null 2>&1; then
        status_fail "Unsupported OS. This installer supports Debian/Ubuntu only."
        return 1
    fi
    return 0
}

install_base_packages() {
    if ! run_silent "Updating package index" apt-get update -y -q; then
        status_fail "apt-get update failed."
        return 1
    fi

    if ! run_silent "Installing base packages" apt-get install -y -q \
        curl wget git unzip tar ca-certificates lsb-release gnupg \
        apt-transport-https software-properties-common build-essential \
        openssl cron procps python3 python3-pip python3-venv; then
        status_fail "Could not install base packages."
        return 1
    fi

    success "Base packages installed."
    return 0
}

install_php() {
    local existing_id=0
    local codename=""
    local candidate=""

    PHP_VER=""
    PHP_BIN=""

    if command -v php >/dev/null 2>&1; then
        existing_id="$(php -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)"
        if [ "$existing_id" -ge 80200 ] && [ "$existing_id" -lt 80400 ]; then
            PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null)"
        fi
    fi

    if [ -z "$PHP_VER" ]; then
        info "Adding the PHP package repository..."

        codename="$(. /etc/os-release 2>/dev/null && printf '%s' "$VERSION_CODENAME")"
        [ -n "$codename" ] || codename="$(lsb_release -sc 2>/dev/null || echo stable)"

        if grep -qi '^ID=ubuntu' /etc/os-release 2>/dev/null; then
            if ! run_silent "Adding Ondrej PHP PPA" add-apt-repository -y ppa:ondrej/php; then
                status_fail "Could not add the PHP PPA."
                return 1
            fi
        else
            if ! run_silent "Adding Sury PHP repository" bash -c \
                "curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /usr/share/keyrings/sury-php.gpg --yes && echo 'deb [signed-by=/usr/share/keyrings/sury-php.gpg] https://packages.sury.org/php/ ${codename} main' > /etc/apt/sources.list.d/sury-php.list"; then
                status_fail "Could not add the Sury PHP repository."
                return 1
            fi
        fi

        if ! run_silent "Refreshing package index" apt-get update -y -q; then
            status_fail "apt-get update failed after adding the PHP repository."
            return 1
        fi

        if apt-cache show php8.3-cli >/dev/null 2>&1; then
            candidate="8.3"
        elif apt-cache show php8.2-cli >/dev/null 2>&1; then
            candidate="8.2"
        else
            status_fail "No supported PHP package (8.2/8.3) is available."
            return 1
        fi

        PHP_VER="$candidate"
    fi

    info "Using PHP ${PHP_VER}."

    if ! run_silent "Installing PHP ${PHP_VER} & extensions" apt-get install -y -q \
        "php${PHP_VER}-cli" \
        "php${PHP_VER}-common" \
        "php${PHP_VER}-mysql" \
        "php${PHP_VER}-mbstring" \
        "php${PHP_VER}-xml" \
        "php${PHP_VER}-bcmath" \
        "php${PHP_VER}-curl" \
        "php${PHP_VER}-zip" \
        "php${PHP_VER}-intl" \
        "php${PHP_VER}-gd" \
        "php${PHP_VER}-opcache"; then
        status_fail "Could not install PHP ${PHP_VER} packages."
        return 1
    fi

    # posix is usually built into PHP, but some builds split it out.
    if ! php -m 2>/dev/null | grep -qix 'posix'; then
        apt-get install -y -q "php${PHP_VER}-posix" >>"$LOG_FILE" 2>&1 || true
    fi

    if command -v update-alternatives >/dev/null 2>&1; then
        update-alternatives --set php "/usr/bin/php${PHP_VER}" >/dev/null 2>&1 || true
    fi

    PHP_BIN="$(command -v php 2>/dev/null || true)"
    if [ -z "$PHP_BIN" ]; then
        status_fail "PHP binary was not found after installation."
        return 1
    fi

    local missing=()
    local ext
    for ext in json mbstring pdo_mysql zip bcmath curl xml gd intl tokenizer posix; do
        if ! php -m 2>/dev/null | grep -qix "$ext"; then
            missing+=("$ext")
        fi
    done

    if [ "${#missing[@]}" -gt 0 ]; then
        warn "Missing PHP extensions: ${missing[*]}"
        warn "Composer may report these as missing platform requirements."
    else
        success "PHP $(php -r 'echo PHP_VERSION;') is ready."
    fi

    return 0
}

install_composer() {
    if command -v composer >/dev/null 2>&1; then
        success "Composer already installed: $(composer --version 2>/dev/null | head -n1)"
        return 0
    fi

    if ! run_silent "Installing Composer" bash -c \
        "curl -fsSL https://getcomposer.org/installer -o /tmp/n1x-composer-setup.php && php /tmp/n1x-composer-setup.php --install-dir=/usr/local/bin --filename=composer && rm -f /tmp/n1x-composer-setup.php"; then
        status_fail "Composer installation failed."
        return 1
    fi

    hash -r
    success "Composer installed."
    return 0
}

install_node() {
    local major=0

    if command -v node >/dev/null 2>&1; then
        major="$(node -v 2>/dev/null | sed 's/^v//' | cut -d. -f1)"
    fi

    if ! [[ "$major" =~ ^[0-9]+$ ]] || [ "$major" -lt 22 ]; then
        info "Installing Node.js 22 LTS..."
        mkdir -p /etc/apt/keyrings

        if ! run_silent "Adding NodeSource repository" bash -c \
            "curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg --yes && echo 'deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main' > /etc/apt/sources.list.d/nodesource.list && apt-get update -y -q"; then
            status_fail "Could not add the NodeSource repository."
            return 1
        fi

        if ! run_silent "Installing Node.js 22" apt-get install -y -q nodejs; then
            status_fail "Could not install Node.js."
            return 1
        fi
    fi

    hash -r
    major="$(node -v 2>/dev/null | sed 's/^v//' | cut -d. -f1)"
    if ! [[ "$major" =~ ^[0-9]+$ ]] || [ "$major" -lt 22 ]; then
        status_fail "Node.js 22+ is required but not active."
        return 1
    fi
    success "Node.js $(node -v) is ready."

    if ! command -v yarn >/dev/null 2>&1; then
        if command -v corepack >/dev/null 2>&1; then
            corepack enable >>"$LOG_FILE" 2>&1 || true
            corepack prepare yarn@1.22.22 --activate >>"$LOG_FILE" 2>&1 || true
        fi

        if ! command -v yarn >/dev/null 2>&1 || ! yarn --version >/dev/null 2>&1; then
            if ! run_silent "Installing Yarn" npm install -g yarn@1.22.22 --no-audit --no-fund; then
                status_fail "Yarn installation failed."
                return 1
            fi
        fi
    fi

    hash -r
    success "Yarn $(yarn --version 2>/dev/null) is ready."
    return 0
}

install_runtime_services() {
    if ! run_silent "Installing MariaDB" apt-get install -y -q mariadb-server mariadb-client; then
        status_fail "Could not install MariaDB."
        return 1
    fi

    if ! run_silent "Installing Redis" apt-get install -y -q redis-server; then
        status_fail "Could not install Redis."
        return 1
    fi

    if ! run_silent "Installing Nginx" apt-get install -y -q nginx; then
        status_fail "Could not install Nginx."
        return 1
    fi

    if ! systemd_available; then
        warn "systemd was not detected. Databases and services could not be started automatically."
        return 0
    fi

    systemctl enable --now mariadb >>"$LOG_FILE" 2>&1 || systemctl enable --now mysql >>"$LOG_FILE" 2>&1 || true
    systemctl enable --now redis-server >>"$LOG_FILE" 2>&1 || true
    systemctl enable --now nginx >>"$LOG_FILE" 2>&1 || true

    if ! systemctl is-active --quiet mariadb && ! systemctl is-active --quiet mysql; then
        status_fail "MariaDB is not running. Check: journalctl -u mariadb -n 50"
        return 1
    fi
    success "MariaDB is running."

    if ! systemctl is-active --quiet redis-server; then
        warn "Redis is not running. Queue processing may fail until it is fixed."
    else
        success "Redis is running."
    fi

    if ! systemctl is-active --quiet nginx; then
        warn "Nginx is not running. Hosted applications will not get reverse proxy configs."
    else
        success "Nginx is running."
    fi

    return 0
}

# ==============================================================================
# Environment and database
# ==============================================================================
setup_environment() {
    local env_file="$INSTALL_DIR/.env"
    local example="$INSTALL_DIR/.env.example"
    local existing_salt=""
    local existing_pass=""
    local existing_db=""
    local existing_user=""
    local created_env="no"

    if [ ! -f "$env_file" ]; then
        created_env="yes"
        if [ -f "$example" ]; then
            cp "$example" "$env_file" || {
                status_fail "Could not create .env from .env.example."
                return 1
            }
            success "Created .env from .env.example."
        else
            touch "$env_file" || {
                status_fail "Could not create .env."
                return 1
            }
        fi
    fi

    existing_salt="$(env_get HASHIDS_SALT)"
    existing_pass="$(env_get DB_PASSWORD)"
    existing_db="$(env_get DB_DATABASE)"
    existing_user="$(env_get DB_USERNAME)"

    HASHIDS_SALT_VALUE="$existing_salt"
    if [ -z "$HASHIDS_SALT_VALUE" ]; then
        HASHIDS_SALT_VALUE="$(openssl rand -hex 24)"
    fi

    DB_NAME="$existing_db"
    if [ -z "$DB_NAME" ] || { [ "$created_env" = "yes" ] && [ "$DB_NAME" = "panel" ]; }; then
        DB_NAME="n1x_panel"
    fi

    DB_USER="$existing_user"
    if [ -z "$DB_USER" ] || { [ "$created_env" = "yes" ] && [ "$DB_USER" = "pterodactyl" ]; }; then
        DB_USER="n1x"
    fi

    DB_PASS="$existing_pass"
    if [ -z "$DB_PASS" ]; then
        DB_PASS="$(openssl rand -hex 16)"
    fi

    set_env APP_NAME '"N1LUX PETRO"' "$env_file"
    set_env APP_ENV 'production' "$env_file"
    set_env APP_DEBUG 'false' "$env_file"
    set_env APP_ENVIRONMENT_ONLY 'false' "$env_file"
    set_env APP_URL "http://${SERVER_IP}:${PANEL_PORT}" "$env_file"
    set_env PANEL_PORT "$PANEL_PORT" "$env_file"
    set_env PANEL_HOST '0.0.0.0' "$env_file"

    set_env APPLICATIONS_ROOT '/var/lib/n1x/applications' "$env_file"
    set_env APPLICATION_DEFAULT_PORT "$APP_PORT_START" "$env_file"
    set_env APPLICATION_PORT_RANGE_START "$APP_PORT_START" "$env_file"
    set_env APPLICATION_PORT_RANGE_END "$APP_PORT_END" "$env_file"
    set_env APPLICATION_DEFAULT_RUNTIME 'node' "$env_file"
    set_env APPLICATION_MAX_COUNT '20' "$env_file"

    set_env PROCESS_MANAGER 'systemd' "$env_file"
    set_env NODE_PROCESS_MANAGER 'pm2' "$env_file"
    set_env NGINX_ENABLED 'true' "$env_file"
    set_env NGINX_CONFIG_PATH '/etc/nginx/conf.d' "$env_file"

    set_env DB_CONNECTION 'mysql' "$env_file"
    set_env DB_HOST '127.0.0.1' "$env_file"
    set_env DB_PORT '3306' "$env_file"
    set_env DB_DATABASE "$DB_NAME" "$env_file"
    set_env DB_USERNAME "$DB_USER" "$env_file"
    set_env DB_PASSWORD "\"${DB_PASS}\"" "$env_file"

    set_env REDIS_HOST '127.0.0.1' "$env_file"
    set_env REDIS_PASSWORD 'null' "$env_file"
    set_env REDIS_PORT '6379' "$env_file"

    set_env CACHE_DRIVER 'file' "$env_file"
    set_env QUEUE_CONNECTION 'redis' "$env_file"
    set_env SESSION_DRIVER 'file' "$env_file"
    set_env HASHIDS_SALT "$HASHIDS_SALT_VALUE" "$env_file"
    set_env LOG_CHANNEL 'daily' "$env_file"
    set_env APPLICATION_LOG_PATH '/var/log/n1x' "$env_file"

    chmod 640 "$env_file" 2>/dev/null || true
    success "Environment file configured."
    return 0
}

setup_database() {
    local db_pass_sql=""

    if ! command -v mysql >/dev/null 2>&1; then
        status_fail "MariaDB client (mysql) was not found."
        return 1
    fi

    if ! mysql -e "SELECT 1" >/dev/null 2>&1; then
        status_fail "Cannot connect to MariaDB as root (socket authentication)."
        echo -e "      ${DIM}Try: mysql -u root -e 'SELECT 1'${RESET}"
        return 1
    fi

    db_pass_sql="$(printf '%s' "$DB_PASS" | sed "s/'/''/g")"

    if ! mysql >>"$LOG_FILE" 2>&1 <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${db_pass_sql}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${db_pass_sql}';
ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${db_pass_sql}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${db_pass_sql}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
    then
        status_fail "Could not create the database or database user."
        echo -e "      ${DIM}See: ${LOG_FILE}${RESET}"
        return 1
    fi

    success "Database '${DB_NAME}' and user '${DB_USER}' are ready."
    return 0
}

# ==============================================================================
# Build, migrate and configure the application
# ==============================================================================
prepare_directories() {
    mkdir -p \
        "$INSTALL_DIR/storage/app/public" \
        "$INSTALL_DIR/storage/framework/cache" \
        "$INSTALL_DIR/storage/framework/sessions" \
        "$INSTALL_DIR/storage/framework/views" \
        "$INSTALL_DIR/storage/logs" \
        "$INSTALL_DIR/bootstrap/cache" \
        "$INSTALL_DIR/public/assets" \
        /var/lib/n1x/applications \
        /var/log/n1x \
        /etc/nginx/conf.d || {
        status_fail "Could not create the required directories."
        return 1
    }

    chmod -R 775 "$INSTALL_DIR/storage" "$INSTALL_DIR/bootstrap/cache" 2>/dev/null || true
    chmod 755 /var/lib/n1x /var/lib/n1x/applications /var/log/n1x 2>/dev/null || true
    success "Directories are ready."
    return 0
}

configure_runner_json() {
    local cfg="$INSTALL_DIR/n1x-runner/config.json"
    local panel_url="http://${SERVER_IP}:${PANEL_PORT}"

    [ -d "$INSTALL_DIR/n1x-runner" ] || return 0

    if [ ! -f "$cfg" ]; then
        printf '{}\n' > "$cfg"
    fi

    php -r '
        $path = $argv[1];
        $panelUrl = $argv[2];
        $cfg = array();
        if (is_file($path)) {
            $decoded = json_decode((string) file_get_contents($path), true);
            if (is_array($decoded)) {
                $cfg = $decoded;
            }
        }
        $cfg["host"] = "0.0.0.0";
        $cfg["port"] = 8080;
        $cfg["panel_url"] = $panelUrl;
        $cfg["applications_root"] = "/var/lib/n1x/applications";
        $cfg["port_range_start"] = 5000;
        $cfg["port_range_end"] = 7999;
        $cfg["nginx_enabled"] = true;
        $cfg["nginx_conf_dir"] = "/etc/nginx/conf.d";
        $cfg["user_prefix"] = "n1x-app";
        $cfg["log_dir"] = "/var/log/n1x";
        file_put_contents($path, json_encode($cfg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    ' "$cfg" "$panel_url" >>"$LOG_FILE" 2>&1 || {
        warn "Could not update n1x-runner/config.json."
        return 0
    }

    success "Runner configuration synchronized."
    return 0
}

install_composer_packages() {
    if ! run_silent "Installing PHP packages (Composer)" \
        run_in_dir "$INSTALL_DIR" composer install --no-dev --optimize-autoloader --no-interaction --no-scripts; then
        status_fail "Composer install failed."
        return 1
    fi

    if [ -z "$(env_get APP_KEY)" ]; then
        if ! run_silent "Generating application key" \
            run_in_dir "$INSTALL_DIR" php artisan key:generate --force; then
            status_fail "Could not generate APP_KEY."
            return 1
        fi
    fi

    run_silent "Discovering Laravel packages" \
        run_in_dir "$INSTALL_DIR" php artisan package:discover --ansi \
        || warn "Package discovery reported an issue; continuing."

    success "PHP packages are ready."
    return 0
}

build_frontend() {
    export NODE_OPTIONS="${NODE_OPTIONS:---max-old-space-size=4096}"

    mkdir -p "$INSTALL_DIR/public/assets"

    if ! run_silent "Installing frontend packages (Yarn)" \
        run_in_dir "$INSTALL_DIR" yarn install --frozen-lockfile --network-timeout 600000; then
        warn "Frozen lockfile install failed, retrying normal install..."
        if ! run_silent "Installing frontend packages (Yarn retry)" \
            run_in_dir "$INSTALL_DIR" yarn install --network-timeout 600000; then
            status_fail "Yarn install failed."
            return 1
        fi
    fi

    if ! run_silent "Building frontend assets (production)" \
        run_in_dir "$INSTALL_DIR" yarn build:production; then
        status_fail "Frontend build failed."
        return 1
    fi

    success "Frontend assets built."
    return 0
}

migrate_and_setup_app() {
    local key_value=""

    key_value="$(env_get APP_KEY)"
    if [ -z "$key_value" ]; then
        if ! run_silent "Generating application key" \
            run_in_dir "$INSTALL_DIR" php artisan key:generate --force; then
            status_fail "Could not generate APP_KEY."
            return 1
        fi
    fi

    run_silent "Clearing old caches" \
        run_in_dir "$INSTALL_DIR" php artisan optimize:clear \
        || true

    if ! run_silent "Running database migrations & seeders" \
        run_in_dir "$INSTALL_DIR" php artisan migrate --seed --force; then
        status_fail "Database migration/seed failed."
        return 1
    fi

    if ! run_silent "Configuring the local runner node" \
        run_in_dir "$INSTALL_DIR" php artisan p:node:auto --force; then
        warn "Automatic node configuration failed. The panel will still start."
        warn "You can retry later with: cd ${INSTALL_DIR} && php artisan p:node:auto --force"
    fi

    run_silent "Linking storage directory" \
        run_in_dir "$INSTALL_DIR" php artisan storage:link \
        || true

    success "Database and application setup complete."
    return 0
}

install_runner_dependencies() {
    [ -d "$INSTALL_DIR/n1x-runner" ] || return 0

    if [ -f "$INSTALL_DIR/n1x-runner/package.json" ]; then
        if ! run_silent "Installing runner dependencies" \
            run_in_dir "$INSTALL_DIR/n1x-runner" npm install --omit=dev --no-audit --no-fund; then
            status_fail "Runner dependency installation failed."
            return 1
        fi
        success "Runner dependencies installed."
    fi

    return 0
}

create_admin_user() {
    local count=""
    local email=""
    local username=""
    local password=""
    local input=""

    count="$(mysql -N -B -e "SELECT COUNT(*) FROM \`${DB_NAME}\`.users;" 2>/dev/null || echo "")"

    if [ -z "$count" ]; then
        warn "Could not read the users table. Skipping admin creation."
        return 0
    fi

    if [ "$count" -gt 0 ] 2>/dev/null; then
        success "Admin/user account already exists (${count} user(s))."
        return 0
    fi

    info "No users found. Create the first administrator account."

    if [ -t 0 ]; then
        read -r -p "  Admin email [admin@n1x.local]: " email
        read -r -p "  Admin username [admin]: " username
        read -r -s -p "  Admin password [N1luxAdmin123!]: " password
        echo ""
    fi

    email="${email:-admin@n1x.local}"
    username="${username:-admin}"
    password="${password:-N1luxAdmin123!}"

    if [ "${#password}" -lt 8 ]; then
        warn "Password is too short (minimum 8 characters). Using the default."
        password="N1luxAdmin123!"
    fi

    if ! run_in_dir "$INSTALL_DIR" php artisan p:user:make \
        --email="$email" \
        --username="$username" \
        --name-first="N1X" \
        --name-last="Admin" \
        --password="$password" \
        --admin=1 >>"$LOG_FILE" 2>&1; then
        status_fail "Could not create the administrator account."
        echo -e "      ${DIM}Retry later: cd ${INSTALL_DIR} && php artisan p:user:make${RESET}"
        return 0
    fi

    ADMIN_CREATED="yes"
    ADMIN_EMAIL="$email"
    ADMIN_USERNAME="$username"
    ADMIN_PASSWORD="$password"

    success "Administrator created: ${username} / ${email}"
    return 0
}

# ==============================================================================
# systemd services
# ==============================================================================
install_systemd_services() {
    local php_bin="${PHP_BIN:-$(command -v php 2>/dev/null || true)}"
    local node_bin="$(command -v node 2>/dev/null || true)"

    [ -n "$php_bin" ] || php_bin="/usr/bin/php"
    [ -n "$node_bin" ] || node_bin="/usr/bin/node"

    if ! systemd_available; then
        warn "systemd is not available. Skipping service registration."
        echo -e "      ${DIM}Start manually:${RESET}"
        echo -e "      cd ${INSTALL_DIR} && ${php_bin} artisan serve --host=0.0.0.0 --port=${PANEL_PORT} --no-reload"
        echo -e "      cd ${INSTALL_DIR}/n1x-runner && ${node_bin} src/index.js"
        return 0
    fi

    cat > /etc/systemd/system/n1x-panel.service <<EOF
[Unit]
Description=N1X PANEL Web Panel
After=network-online.target mariadb.service redis-server.service
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}
Environment=APP_ENV=production
Environment=PHP_CLI_SERVER_WORKERS=4
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ExecStart=${php_bin} artisan serve --host=0.0.0.0 --port=${PANEL_PORT} --no-reload
Restart=always
RestartSec=3
TimeoutStopSec=30
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    cat > /etc/systemd/system/n1x-runner.service <<EOF
[Unit]
Description=N1X PANEL Native Application Runner
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}/n1x-runner
Environment=NODE_ENV=production
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ExecStart=${node_bin} src/index.js
Restart=always
RestartSec=3
LimitNOFILE=65535
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    cat > /etc/systemd/system/n1x-queue.service <<EOF
[Unit]
Description=N1X PANEL Queue Worker
After=network-online.target mariadb.service redis-server.service
Wants=network-online.target

[Service]
Type=simple
User=root
WorkingDirectory=${INSTALL_DIR}
Environment=APP_ENV=production
Environment=PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
ExecStart=${php_bin} artisan queue:work --sleep=3 --tries=3 --timeout=90 --max-time=3600
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
EOF

    if [ -d /etc/cron.d ]; then
        systemctl enable --now cron >>"$LOG_FILE" 2>&1 || true
        cat > /etc/cron.d/n1x-panel <<EOF
* * * * * root cd ${INSTALL_DIR} && ${php_bin} artisan schedule:run >> /var/log/n1x/schedule.log 2>&1
EOF
        chmod 644 /etc/cron.d/n1x-panel 2>/dev/null || true
    fi

    systemctl daemon-reload >>"$LOG_FILE" 2>&1 || true

    if ! run_silent "Enabling N1X services" \
        systemctl enable n1x-panel.service n1x-runner.service n1x-queue.service; then
        warn "Could not enable all services."
    fi

    if ! run_silent "Starting N1X services" \
        systemctl restart n1x-runner.service n1x-queue.service n1x-panel.service; then
        status_fail "Some N1X services failed to start."
    fi

    sleep 2

    local panel_state
    local runner_state
    local queue_state

    panel_state="$(service_state n1x-panel.service)"
    runner_state="$(service_state n1x-runner.service)"
    queue_state="$(service_state n1x-queue.service)"

    if [ "$panel_state" = "active" ]; then
        success "n1x-panel.service is running on port ${PANEL_PORT}."
    else
        status_fail "n1x-panel.service is ${panel_state}."
        journalctl -u n1x-panel.service -n 20 --no-pager >>"$LOG_FILE" 2>&1 || true
    fi

    if [ "$runner_state" = "active" ]; then
        success "n1x-runner.service is running on port ${RUNNER_PORT}."
    else
        status_fail "n1x-runner.service is ${runner_state}."
        journalctl -u n1x-runner.service -n 20 --no-pager >>"$LOG_FILE" 2>&1 || true
    fi

    if [ "$queue_state" = "active" ]; then
        success "n1x-queue.service is running."
    else
        warn "n1x-queue.service is ${queue_state}."
    fi

    return 0
}

# ==============================================================================
# Install summary
# ==============================================================================
show_summary() {
    local panel_state
    local runner_state

    panel_state="$(service_state n1x-panel.service)"
    runner_state="$(service_state n1x-runner.service)"

    echo ""
    echo -e "  ${BRIGHT_GREEN}${BOLD}============================================================${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD}  N1X PANEL INSTALLATION COMPLETE${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD}============================================================${RESET}"
    echo ""
    echo -e "  ${BOLD}Panel URL      :${RESET} ${BRIGHT_CYAN}http://${SERVER_IP}:${PANEL_PORT}${RESET}"
    echo -e "  ${BOLD}Runner health  :${RESET} ${BRIGHT_BLUE}http://${SERVER_IP}:${RUNNER_PORT}/health${RESET}"
    echo -e "  ${BOLD}Install dir    :${RESET} ${WHITE}${INSTALL_DIR}${RESET}"
    echo -e "  ${BOLD}Database       :${RESET} ${WHITE}${DB_NAME}${RESET} (user: ${DB_USER})"
    echo -e "  ${BOLD}Panel service  :${RESET} ${WHITE}${panel_state}${RESET}"
    echo -e "  ${BOLD}Runner service :${RESET} ${WHITE}${runner_state}${RESET}"
    echo ""

    if [ "${ADMIN_CREATED:-no}" = "yes" ]; then
        echo -e "  ${BRIGHT_YELLOW}${BOLD}Administrator account${RESET}"
        echo -e "  ${BOLD}Username / Email:${RESET} ${WHITE}${ADMIN_USERNAME}${RESET} / ${WHITE}${ADMIN_EMAIL}${RESET}"
        echo -e "  ${BOLD}Password        :${RESET} ${WHITE}${ADMIN_PASSWORD}${RESET}"
        echo -e "  ${DIM}Change it after logging in from the admin panel.${RESET}"
    else
        echo -e "  ${DIM}A user account already existed; no new admin was created.${RESET}"
    fi

    echo ""
    echo -e "  ${BOLD}Useful commands${RESET}"
    echo -e "  ${CYAN}systemctl status n1x-panel${RESET}"
    echo -e "  ${CYAN}systemctl restart n1x-panel n1x-runner n1x-queue${RESET}"
    echo -e "  ${CYAN}journalctl -u n1x-panel -f${RESET}"
    echo -e "  ${CYAN}journalctl -u n1x-runner -f${RESET}"
    echo ""
    echo -e "  ${DIM}Use menu option 03 to change the panel port or manage services.${RESET}"
    echo -e "  ${BRIGHT_GREEN}${BOLD}============================================================${RESET}"
    echo ""
}

# ==============================================================================
# 02 - Install everything
# ==============================================================================
install_everything() {
    section "02 . INSTALL EVERYTHING & SETUP"

    if [ ! -f "$INSTALL_DIR/artisan" ]; then
        status_fail "Panel code was not found in: ${INSTALL_DIR}"
        echo -e "      ${DIM}Run option 01 first to clone the repository.${RESET}"
        pause_screen
        return 1
    fi

    check_os || { pause_screen; return 1; }

    SERVER_IP="${SERVER_IP:-$(detect_ip)}"
    save_state

    info "Install directory: ${INSTALL_DIR}"
    info "Server IP        : ${SERVER_IP}"

    # Read the existing port (if any), then let the user choose one.
    PANEL_PORT="$(current_panel_port)"
    if is_reserved_port "$PANEL_PORT" \
        || { [ "$PANEL_PORT" -ge "$APP_PORT_START" ] 2>/dev/null && [ "$PANEL_PORT" -le "$APP_PORT_END" ] 2>/dev/null; }; then
        PANEL_PORT="$DEFAULT_PANEL_PORT"
    fi
    if [ -t 0 ]; then
        prompt_panel_port "$PANEL_PORT"
    fi
    save_state

    install_base_packages || { pause_screen; return 1; }
    install_php || { pause_screen; return 1; }
    install_composer || { pause_screen; return 1; }
    install_node || { pause_screen; return 1; }
    install_runtime_services || { pause_screen; return 1; }

    setup_environment || { pause_screen; return 1; }
    setup_database || { pause_screen; return 1; }
    prepare_directories || { pause_screen; return 1; }
    configure_runner_json

    install_composer_packages || { pause_screen; return 1; }
    build_frontend || { pause_screen; return 1; }
    migrate_and_setup_app || { pause_screen; return 1; }
    install_runner_dependencies || { pause_screen; return 1; }
    create_admin_user

    # Keep the runner config in sync with the final values.
    configure_runner_json

    install_systemd_services || { pause_screen; return 1; }
    open_firewall_port "$PANEL_PORT"

    save_state
    show_summary
    pause_screen
    return 0
}

# ==============================================================================
# 03 - Port management
# ==============================================================================
change_panel_port() {
    local old_port=""
    local new_port=""
    local input_port=""

    section "03 . CHANGE PANEL PORT"

    if [ ! -f "$INSTALL_DIR/.env" ]; then
        status_fail "Panel is not installed yet. Run option 02 first."
        pause_screen
        return 1
    fi

    old_port="$(current_panel_port)"
    info "Current panel port: ${old_port}"

    new_port="$old_port"
    if [ -t 0 ]; then
        echo -n "  New panel port [Press ENTER to keep ${old_port}]: "
        read -r input_port
        new_port="${input_port:-$old_port}"
    fi

    if [ "$new_port" = "$old_port" ]; then
        info "Panel port unchanged."
        pause_screen
        return 0
    fi

    if ! validate_panel_port "$new_port" "$old_port"; then
        status_fail "$PORT_ERROR"
        pause_screen
        return 1
    fi

    PANEL_PORT="$new_port"

    set_env PANEL_PORT "$PANEL_PORT" "$INSTALL_DIR/.env"
    set_env APP_URL "http://${SERVER_IP}:${PANEL_PORT}" "$INSTALL_DIR/.env"

    if systemd_available && [ -f /etc/systemd/system/n1x-panel.service ]; then
        local php_bin
        php_bin="$(command -v php 2>/dev/null || echo /usr/bin/php)"
        sed -i "s|^ExecStart=.*|ExecStart=${php_bin} artisan serve --host=0.0.0.0 --port=${PANEL_PORT} --no-reload|" \
            /etc/systemd/system/n1x-panel.service
        systemctl daemon-reload >>"$LOG_FILE" 2>&1 || true
    fi

    configure_runner_json

    run_in_dir "$INSTALL_DIR" php artisan optimize:clear >>"$LOG_FILE" 2>&1 || true

    if systemd_available && [ -f /etc/systemd/system/n1x-panel.service ]; then
        if run_silent "Restarting N1X services" \
            systemctl restart n1x-runner.service n1x-queue.service n1x-panel.service; then
            success "Services restarted on port ${PANEL_PORT}."
        else
            status_fail "Services could not be restarted."
        fi
    else
        warn "systemd services are not installed yet; only .env was updated."
    fi

    open_firewall_port "$PANEL_PORT"
    save_state

    success "Panel port changed: ${old_port} -> ${PANEL_PORT}"
    echo -e "  ${BOLD}Open:${RESET} ${BRIGHT_CYAN}http://${SERVER_IP}:${PANEL_PORT}${RESET}"
    pause_screen
    return 0
}

restart_services() {
    section "03 . RESTART SERVICES"

    if ! systemd_available; then
        status_fail "systemd is not available on this system."
        pause_screen
        return 1
    fi

    if run_silent "Restarting n1x-panel, n1x-runner and n1x-queue" \
        systemctl restart n1x-runner.service n1x-queue.service n1x-panel.service; then
        sleep 1
        success "Panel : $(service_state n1x-panel.service)"
        success "Runner: $(service_state n1x-runner.service)"
        success "Queue : $(service_state n1x-queue.service)"
    else
        status_fail "Restart command failed. Check: journalctl -u n1x-panel -n 50"
    fi

    pause_screen
    return 0
}

show_service_status() {
    section "03 . SERVICE STATUS"

    local port
    local http_code=""
    local svc

    port="$(current_panel_port)"

    for svc in n1x-panel.service n1x-runner.service n1x-queue.service; do
        printf "  %-24s %s\n" "$svc" "$(service_state "$svc")"
    done

    echo ""
    info "Panel port: ${port}"

    if command -v curl >/dev/null 2>&1; then
        http_code="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://127.0.0.1:${port}/" 2>/dev/null || true)"
        if [ "$http_code" = "200" ] || [ "$http_code" = "302" ]; then
            success "HTTP check: ${http_code} on http://127.0.0.1:${port}/"
        else
            warn "HTTP check returned: ${http_code:-no response}"
        fi
    fi

    echo ""
    echo -e "  ${DIM}Logs: journalctl -u n1x-panel -f${RESET}"
    echo -e "  ${DIM}Logs: journalctl -u n1x-runner -f${RESET}"
    echo -e "  ${DIM}Full installer log: ${LOG_FILE}${RESET}"

    pause_screen
    return 0
}

open_port_firewall() {
    local port
    port="$(current_panel_port)"

    section "03 . OPEN FIREWALL PORT"

    if ! command -v ufw >/dev/null 2>&1; then
        warn "UFW is not installed. Open TCP port ${port} in your firewall manually."
        pause_screen
        return 0
    fi

    if ! ufw status 2>/dev/null | grep -q "Status: active"; then
        warn "UFW is installed but not active. Nothing to open."
        pause_screen
        return 0
    fi

    if run_silent "Allowing TCP port ${port} in UFW" ufw allow "${port}/tcp"; then
        success "TCP port ${port} is open in UFW."
    else
        status_fail "Could not add the UFW rule."
    fi

    pause_screen
    return 0
}

port_menu() {
    local choice=""

    while true; do
        header
        section "03 . PORT MANAGEMENT"

        echo -e "  ${BRIGHT_CYAN}${BOLD}01${RESET}  ${WHITE}Change Panel Port${RESET}"
        echo -e "      ${DIM}Update .env, systemd and restart the panel${RESET}"
        echo ""
        echo -e "  ${BRIGHT_CYAN}${BOLD}02${RESET}  ${WHITE}Restart All Services${RESET}"
        echo -e "      ${DIM}Restart n1x-panel, n1x-runner and n1x-queue${RESET}"
        echo ""
        echo -e "  ${BRIGHT_CYAN}${BOLD}03${RESET}  ${WHITE}Show Service Status${RESET}"
        echo -e "      ${DIM}Service state and local HTTP check${RESET}"
        echo ""
        echo -e "  ${BRIGHT_CYAN}${BOLD}04${RESET}  ${WHITE}Open Port in UFW${RESET}"
        echo -e "      ${DIM}Allow the current panel port through the firewall${RESET}"
        echo ""
        echo -e "  ${DIM}${BOLD}00${RESET}  ${WHITE}Back${RESET}"
        echo ""
        echo -e "  ${BRIGHT_BLUE}${BOLD}============================================================${RESET}"

        read -r -p "  Select [00-04] > " choice || choice="00"

        case "$choice" in
            1|01) change_panel_port ;;
            2|02) restart_services ;;
            3|03) show_service_status ;;
            4|04) open_port_firewall ;;
            0|00) return 0 ;;
            *) warn "Invalid option."; sleep 1 ;;
        esac
    done
}

# ==============================================================================
# Main menu
# ==============================================================================
main_menu() {
    local choice=""

    SERVER_IP="${SERVER_IP:-$(detect_ip)}"
    save_state

    while true; do
        header
        read -r -p "  Select [00-03] > " choice || choice="00"

        case "$choice" in
            1|01) clone_repository ;;
            2|02) install_everything ;;
            3|03) port_menu ;;
            0|00)
                clear 2>/dev/null || true
                echo ""
                echo -e "  ${BRIGHT_CYAN}${BOLD}${APP_NAME}${RESET} installer closed."
                echo ""
                exit 0
                ;;
            *)
                warn "Invalid option."
                sleep 1
                ;;
        esac
    done
}

main_menu
