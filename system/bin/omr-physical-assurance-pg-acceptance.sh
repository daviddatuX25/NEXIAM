#!/usr/bin/env bash

set -Eeuo pipefail

ROOT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
COMPOSE_FILE="${ROOT_DIR}/system/compose.yaml"
PG_USER="${PGUSER:-nexiam}"
PG_ADMIN_DATABASE="${PGDATABASE_ADMIN:-postgres}"
SHARED_DATABASE="nexiam"

compose=(docker compose -f "${COMPOSE_FILE}")
created=0
temporary_database=""
shared_before=""

shared_fingerprint() {
    local table_count rows checksum

    table_count="$("${compose[@]}" exec -T postgres psql \
        -U "${PG_USER}" \
        -d "${SHARED_DATABASE}" \
        -Atqc "SELECT count(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'")"
    rows="$("${compose[@]}" exec -T postgres psql \
        -U "${PG_USER}" \
        -d "${SHARED_DATABASE}" \
        -Atq <<'SQL'
SELECT format($query$
    SELECT %L || ':' || count(*)::text FROM %I.%I
$query$, table_schema || '.' || table_name, table_schema, table_name)
FROM information_schema.tables
WHERE table_schema = 'public'
  AND table_type = 'BASE TABLE'
ORDER BY table_schema, table_name;
\gexec
SQL
    )"
    checksum="$(printf '%s\n' "${rows}" | LC_ALL=C sort | sha256sum | cut -d' ' -f1)"

    printf 'tables=%s checksum=%s' "${table_count}" "${checksum}"
}

cleanup() {
    local status=$?
    local shared_after
    local cleanup_failed=0

    trap - EXIT

    if shared_after="$(shared_fingerprint)"; then
        printf 'shared_db_after database=%s %s\n' "${SHARED_DATABASE}" "${shared_after}"
        if [[ -n "${shared_before}" && "${shared_after}" != "${shared_before}" ]]; then
            printf 'ERROR: shared database fingerprint changed\n' >&2
            cleanup_failed=1
        fi
    else
        printf 'ERROR: unable to fingerprint shared database after acceptance\n' >&2
        cleanup_failed=1
    fi

    if (( created == 1 )); then
        if ! "${compose[@]}" exec -T postgres psql \
            -U "${PG_USER}" \
            -d "${PG_ADMIN_DATABASE}" \
            -v ON_ERROR_STOP=1 \
            -Atq \
            -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '${temporary_database}' AND pid <> pg_backend_pid();" \
            >/dev/null; then
            printf 'ERROR: failed to terminate temporary database sessions %s\n' "${temporary_database}" >&2
            cleanup_failed=1
        elif ! "${compose[@]}" exec -T postgres psql \
            -U "${PG_USER}" \
            -d "${PG_ADMIN_DATABASE}" \
            -v ON_ERROR_STOP=1 \
            -Atq \
            -c "DROP DATABASE IF EXISTS \"${temporary_database}\";" \
            >/dev/null; then
            printf 'ERROR: failed to drop temporary database %s\n' "${temporary_database}" >&2
            cleanup_failed=1
        else
            printf 'temporary_database_dropped name=%s\n' "${temporary_database}"
        fi
    fi

    if (( cleanup_failed == 1 )); then
        status=1
    fi
    exit "${status}"
}

cd "${ROOT_DIR}"

if ! shared_before="$(shared_fingerprint)"; then
    printf 'ERROR: unable to fingerprint shared database before acceptance\n' >&2
    exit 1
fi
printf 'shared_db_before database=%s %s\n' "${SHARED_DATABASE}" "${shared_before}"

suffix="$(date -u +%Y%m%d%H%M%S)_${RANDOM}_${BASHPID}"
temporary_database="nexiam_omr_acceptance_${suffix}"
if [[ ! "${temporary_database}" =~ ^[a-z][a-z0-9_]{0,62}$ ]]; then
    printf 'ERROR: generated temporary database name is invalid\n' >&2
    exit 1
fi
printf 'temporary_database_pattern=nexiam_omr_acceptance_<utc>_<random>_<pid> driver=pgsql\n'
printf 'temporary_database_created name=%s\n' "${temporary_database}"

trap cleanup EXIT

"${compose[@]}" exec -T postgres psql \
    -U "${PG_USER}" \
    -d "${PG_ADMIN_DATABASE}" \
    -v ON_ERROR_STOP=1 \
    -Atq \
    -c "CREATE DATABASE \"${temporary_database}\" TEMPLATE template0;"
created=1

run_app() {
    "${compose[@]}" exec -T app env \
        DB_CONNECTION=pgsql \
        DB_DATABASE="${temporary_database}" \
        DB_HOST=postgres \
        DB_PORT=5432 \
        NEXIAM_TEST_PIN_SQLITE=0 \
        php "$@"
}

run_app artisan config:clear --ansi
run_app artisan migrate:fresh --seed --force
run_app vendor/bin/pest --configuration=phpunit.pg.xml
