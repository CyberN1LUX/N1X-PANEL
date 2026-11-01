#!/usr/bin/env bash
# N1X PANEL Linux Master Launcher

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

echo "==================================================="
echo "         N1X PANEL - LINUX MASTER LAUNCHER         "
echo " Multi-Language Hosting Control Panel (Docker-Free)"
echo "==================================================="

# 1. Start Native Application Runner
echo "[+] Starting N1X Native Application Runner on port 8080..."
cd "$SCRIPT_DIR/n1x-runner"
node src/index.js &
RUNNER_PID=$!
echo "[+] N1X Runner active (PID: $RUNNER_PID)"

# 2. Return to root and start Web Panel
cd "$SCRIPT_DIR"
echo "[+] Starting N1X Web Control Panel on port 8000..."
echo "[*] Open your browser at http://localhost:8000"

php -S 0.0.0.0:8000 -t public

