#!/usr/bin/env bash
set +x
set -euo pipefail
umask 077

system_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$system_dir/.env"
lock_file="$system_dir/docker/jitsi/.env-provision.lock"

command -v openssl >/dev/null || { printf '%s\n' 'OpenSSL is required.' >&2; exit 1; }
command -v flock >/dev/null || { printf '%s\n' 'flock is required.' >&2; exit 1; }
exec 9>"$lock_file"
flock -x 9

if [[ -L "$env_file" ]]; then
    printf '%s\n' 'Refusing to provision a symlinked system/.env.' >&2
    exit 1
fi

if [[ -e "$env_file" && ! -f "$env_file" ]]; then
    printf '%s\n' 'Refusing to provision a non-regular system/.env.' >&2
    exit 1
fi

declare -A defaults=(
    [JITSI_DOMAIN]='meet.local'
    [JITSI_ORIGIN]='https://localhost:18443'
    [JITSI_PUBLIC_URL]='https://localhost:18443'
    [JITSI_APP_ID]='nexiam'
    [JITSI_JWT_AUDIENCE]='nexiam-consultation'
    [JITSI_ADMISSION_URL]='http://app:8000/consultation/internal/jitsi/admission'
    [JITSI_CONTROL_URL]='http://prosody:5280/nexiam_admission'
    [HTTP_PORT]='18080'
    [HTTPS_PORT]='18443'
    [JVB_PORT]='10000'
    [JVB_ADVERTISE_IPS]='127.0.0.1'
)
declare -a secret_names=(
    JITSI_APP_SECRET
    JITSI_ADMISSION_SECRET
    JITSI_CONTROL_TOKEN
    JICOFO_AUTH_PASSWORD
    JVB_AUTH_PASSWORD
)
declare -A values=()
declare -A counts=()
declare -A original_lines=()
declare -A original_nonempty=()

if [[ -f "$env_file" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            name="${BASH_REMATCH[1]}"
            if [[ -v defaults[$name] || "$name" == JITSI_APP_SECRET || "$name" == JITSI_ADMISSION_SECRET \
                || "$name" == JITSI_CONTROL_TOKEN || "$name" == JICOFO_AUTH_PASSWORD || "$name" == JVB_AUTH_PASSWORD ]]; then
                counts[$name]=$(( ${counts[$name]:-0} + 1 ))
                raw_value="${BASH_REMATCH[2]}"
                case "$raw_value" in
                    \"*\") value="${raw_value:1:${#raw_value}-2}" ;;
                    \'*\') value="${raw_value:1:${#raw_value}-2}" ;;
                    *) value="${raw_value%%#*}"; value="${value%"${value##*[![:space:]]}"}" ;;
                esac
                values[$name]="$value"
                original_lines[$name]="$line"
                if [[ -n "$value" ]]; then
                    original_nonempty[$name]=1
                fi
            fi
        fi
    done < "$env_file"
fi

for name in "${!counts[@]}"; do
    if (( counts[$name] > 1 )); then
        printf 'Refusing duplicate definitions for %s in system/.env.\n' "$name" >&2
        exit 1
    fi
done

for name in "${secret_names[@]}"; do
    if [[ -v values[$name] ]]; then
        if (( ${#values[$name]} < 32 )); then
            printf 'Existing %s is shorter than 32 bytes; refusing to replace it.\n' "$name" >&2
            exit 1
        fi
    else
        values[$name]="$(openssl rand -hex 32)"
    fi
done

for name in "${!defaults[@]}"; do
    if [[ ! -v values[$name] || -z "${values[$name]}" ]]; then
        values[$name]="${defaults[$name]}"
    fi
done

if [[ "${values[JITSI_ORIGIN]}" != "${values[JITSI_PUBLIC_URL]}" ]]; then
    printf '%s\n' 'JITSI_ORIGIN and JITSI_PUBLIC_URL must match for the configured browser media origin.' >&2
    exit 1
fi

if [[ "${values[JITSI_CONTROL_URL]}" != 'http://prosody:5280/nexiam_admission' \
    || "${values[JITSI_ADMISSION_URL]}" != 'http://app:8000/consultation/internal/jitsi/admission' ]]; then
    printf '%s\n' 'The local Jitsi control and admission URLs must use the fixed internal Compose service endpoints.' >&2
    exit 1
fi

temp_file="$(mktemp "$system_dir/.env.jitsi.XXXXXX")"
trap 'rm -f -- "$temp_file"' EXIT
chmod 600 "$temp_file"

declare -A written=()
if [[ -f "$env_file" ]]; then
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
            name="${BASH_REMATCH[1]}"
            if [[ -v defaults[$name] || "$name" == JITSI_APP_SECRET || "$name" == JITSI_ADMISSION_SECRET \
                || "$name" == JITSI_CONTROL_TOKEN || "$name" == JICOFO_AUTH_PASSWORD || "$name" == JVB_AUTH_PASSWORD ]]; then
                if [[ -v original_nonempty[$name] ]]; then
                    printf '%s\n' "${original_lines[$name]}" >> "$temp_file"
                else
                    printf '%s=%s\n' "$name" "${values[$name]}" >> "$temp_file"
                fi
                written[$name]=1
                continue
            fi
        fi
        printf '%s\n' "$line" >> "$temp_file"
    done < "$env_file"
fi

for name in "${!defaults[@]}" "${secret_names[@]}"; do
    if [[ ! -v written[$name] ]]; then
        printf '%s=%s\n' "$name" "${values[$name]}" >> "$temp_file"
    fi
done

chmod 600 "$temp_file"
mv -f -- "$temp_file" "$env_file"
trap - EXIT
printf '%s\n' 'Provisioned local Jitsi environment in system/.env (mode 0600); secret values were not displayed.'
printf '%s\n' 'Create/update the app services first, then start pinned Jitsi with:'
printf '%s\n' '  docker compose --env-file .env -f docker/jitsi/docker-compose.jitsi.yml up -d'
