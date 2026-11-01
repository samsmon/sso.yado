#!/usr/bin/env bash
# Sync the tech stack from docs/SRS.md to the STACK_START..STACK_END section in .claude/CLAUDE.md

set -euo pipefail

SRS="docs/SRS.md"
CLAUDE=".claude/CLAUDE.md"
LOG_DIR="logs"
LOG_FILE="$LOG_DIR/sync.log"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

log() { echo -e "${GREEN}[sync]${NC} $1" | tee -a "$LOG_FILE"; }
warn() { echo -e "${YELLOW}[sync warn]${NC} $1" | tee -a "$LOG_FILE"; }
error() { echo -e "${RED}[sync error]${NC} $1" | tee -a "$LOG_FILE"; exit 1; }

mkdir -p "$LOG_DIR"
echo "--- sync $(date '+%Y-%m-%d %H:%M:%S') ---" >> "$LOG_FILE"

[ -f "$SRS" ] || error "$SRS not found."
[ -f "$CLAUDE" ] || error "$CLAUDE not found."

# Extract the Tech Stack table from SRS.md (second column, skip the header and separator)
STACK=$(awk '/^## Tech Stack/{found=1; next} found && /^\|/{
    if ($0 ~ /^[|][-]+/) next;
    if ($0 ~ /Layer.*Choice/) next;
    split($0, a, "|");
    gsub(/^[ \t]+|[ \t]+$/, "", a[3]);
    if (a[3] != "") print "- " a[3]
} found && /^---/{exit}' "$SRS")

if [ -z "$STACK" ]; then
    warn "No Tech Stack table found in $SRS. CLAUDE.md was not changed."
    exit 0
fi

TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')
NEW_BLOCK="<!-- STACK_START -->\n## Stack (auto-synced from SRS.md)\n\n$STACK\n\n_Last synced: $TIMESTAMP_\n<!-- STACK_END -->"

perl -i -0pe "s|<!-- STACK_START -->.*?<!-- STACK_END -->|$NEW_BLOCK|s" "$CLAUDE"

log "Stack synced to $CLAUDE successfully"
log "Synced stack:"
echo "$STACK" | while IFS= read -r line; do log "  $line"; done
