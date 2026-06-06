#!/usr/bin/env bash
#
# Server-side deployment for Family Timeline (Hostinger shared hosting).
#
# Frontend assets are built locally and committed (public/build/), because the
# server has no Node.js. This script runs the PHP-side steps after the code is
# on the server. Run it over SSH from the app directory:
#
#     bash deploy.sh
#
# Override the PHP / Composer binaries if they aren't named `php` / `composer`
# on the server's PATH (Hostinger often exposes e.g. /usr/bin/php8.2):
#
#     PHP_BIN=/usr/bin/php8.2 COMPOSER_BIN="php /usr/bin/composer" bash deploy.sh
#
set -euo pipefail
cd "$(dirname "$0")"

# ─────────────────────────────────────────────────────────────────────────────
# PRODUCTION DATA SAFETY — production holds real user data.
# This script only ever runs additive `migrate --force`. NEVER run any of these
# against production: `migrate:fresh`, `migrate:rollback`, `db:wipe`, or
# `db:seed` (the seeders create a default super-admin and add every user to the
# demo group — dev fixtures only, gated to non-production in DatabaseSeeder).
# Keep migrations additive; never destructive `drop`/`truncate` of data tables.
#
# Every run takes a timestamped snapshot of the SQLite DB into storage/backups/
# BEFORE migrating, and refuses to migrate if it cannot make one. To roll back a
# bad migration, stop the app and copy the newest snapshot back over the live DB:
#     cp storage/backups/database-YYYYMMDD-HHMMSS.sqlite database/database.sqlite
# Snapshots live on the server — pull one off-box periodically (scp) so a server
# loss doesn't take the backups with it.
# ─────────────────────────────────────────────────────────────────────────────

PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
DB_PATH="${DB_PATH:-database/database.sqlite}"
BACKUP_DIR="${BACKUP_DIR:-storage/backups}"
BACKUP_KEEP="${BACKUP_KEEP:-10}"

echo "==> Pulling latest code (fast-forward only)"
git pull --ff-only origin main

echo "==> Installing PHP dependencies (production, no dev)"
$COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction

echo "==> Backing up the database before migrating"
if [ -f "$DB_PATH" ]; then
    mkdir -p "$BACKUP_DIR"
    BACKUP_FILE="$BACKUP_DIR/database-$(date +%Y%m%d-%H%M%S).sqlite"
    if command -v sqlite3 >/dev/null 2>&1; then
        # .backup is consistent even if the DB is being written to
        sqlite3 "$DB_PATH" ".backup '$BACKUP_FILE'"
    else
        cp "$DB_PATH" "$BACKUP_FILE"
    fi
    echo "    Saved $BACKUP_FILE"
    # Retention: keep the newest $BACKUP_KEEP snapshots, prune the rest.
    # Sort by filename (timestamps sort chronologically) so it's deterministic.
    ls -1 "$BACKUP_DIR"/database-*.sqlite 2>/dev/null | sort -r | tail -n +$((BACKUP_KEEP + 1)) | xargs -r rm -f
else
    echo "    ERROR: database '$DB_PATH' not found — refusing to migrate without a backup." >&2
    echo "           If the DB lives elsewhere: DB_PATH=/path/to/db.sqlite bash deploy.sh" >&2
    exit 1
fi

echo "==> Running database migrations"
$PHP_BIN artisan migrate --force

# Generate Passport OAuth keys only if they don't exist yet. Never regenerate —
# that would invalidate every issued MCP access token.
if [ ! -f storage/oauth-private.key ]; then
    echo "==> Generating Passport keys (first run)"
    $PHP_BIN artisan passport:keys --no-interaction
fi

echo "==> Rebuilding framework caches"
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache

echo "==> Deploy complete."
echo "    REST:  /api/groups/{slug}/events  (Bearer token)"
echo "    MCP:   /mcp                        (Streamable HTTP, Bearer token)"
echo "    Verify the MCP route survived route:cache:"
echo "      curl -s -o /dev/null -w '%{http_code}\\n' -X POST <domain>/mcp"
echo "    (expect 401 unauthenticated, NOT 404)"
