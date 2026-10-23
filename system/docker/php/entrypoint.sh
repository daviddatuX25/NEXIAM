#!/usr/bin/env bash
set -e

# First arg is `-f` or `--some-option`, prepend php (mirrors official php docker entrypoint)
if [ "${1#-}" != "$1" ]; then
    set -- php "$@"
fi

# Wait for PostgreSQL database when configured
if [ "${DB_CONNECTION:-pgsql}" = "pgsql" ] && [ -n "${DB_HOST:-}" ] && [ "${SKIP_DB_WAIT:-0}" != "1" ]; then
    DB_PORT="${DB_PORT:-5432}"
    DB_USER="${DB_USERNAME:-nexiam}"
    MAX_WAIT="${DB_WAIT_TIMEOUT:-60}"
    attempt=1

    echo "==> [entrypoint] Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT} (timeout: ${MAX_WAIT}s)..."

    if command -v pg_isready >/dev/null 2>&1; then
        while ! pg_isready -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -t 2 >/dev/null 2>&1; do
            if [ "$attempt" -ge "$MAX_WAIT" ]; then
                echo "==> [entrypoint] ERROR: Database ${DB_HOST}:${DB_PORT} unreachable after ${MAX_WAIT} attempts." >&2
                exit 1
            fi
            sleep 2
            attempt=$((attempt + 1))
        done
    else
        while ! php -r "try { new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE:-nexiam}', '${DB_USER}', '${DB_PASSWORD:-}'); exit(0); } catch (Throwable \$e) { exit(1); }" >/dev/null 2>&1; do
            if [ "$attempt" -ge "$MAX_WAIT" ]; then
                echo "==> [entrypoint] ERROR: Database ${DB_HOST}:${DB_PORT} unreachable after ${MAX_WAIT} attempts." >&2
                exit 1
            fi
            sleep 2
            attempt=$((attempt + 1))
        done
    fi
    echo "==> [entrypoint] PostgreSQL (${DB_HOST}:${DB_PORT}) is accepting connections."
fi

if { [ ! -L /var/www/html/public/storage ] && [ ! -d /var/www/html/public/storage ]; } || { [ -L /var/www/html/public/storage ] && [ ! -e /var/www/html/public/storage ]; }; then
    ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage || true
fi

exec "$@"
