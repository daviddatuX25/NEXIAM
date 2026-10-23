#!/usr/bin/env bash
set +x

# ──────────────────────────────────────────────────────────────────────────
# Fill these in, then run: bash system/configure-plunk-smtp.sh
# Leave blank to enter values through interactive/hidden prompts instead.
# Never commit this file with real credentials. Clear them after use.
# ──────────────────────────────────────────────────────────────────────────
PLUNK_HOST='smtp.useplunk.com'
PLUNK_PORT='465'
PLUNK_SCHEME='smtps'
PLUNK_USERNAME='plunk'
PLUNK_PASSWORD=''
PLUNK_FROM_ADDRESS='admissions@nexiam.dxtechph.online'
PLUNK_FROM_NAME='NEXIAM Admissions'

set -euo pipefail

# ──────────────────────────────────────────────────────────────────────────
# Wizard UI Helpers
# ──────────────────────────────────────────────────────────────────────────

if [[ -t 1 ]] && command -v tput >/dev/null 2>&1 && [[ "$(tput colors 2>/dev/null || echo 0)" -ge 8 ]]; then
  BOLD=$(tput bold); DIM=$(tput dim); RESET=$(tput sgr0)
  BLUE=$(tput setaf 4); GREEN=$(tput setaf 2); YELLOW=$(tput setaf 3); RED=$(tput setaf 1)
else
  BOLD=""; DIM=""; RESET=""; BLUE=""; GREEN=""; YELLOW=""; RED=""
fi

TOTAL_STAGES=3
_STAGE_INDEX=0
ENV_FILE="${ENV_FILE:-.env}"
WRITTEN_ENV=()

_clear() {
  [[ -t 1 ]] || return 0
  if command -v tput >/dev/null 2>&1; then tput clear; else printf '\033[2J\033[3J\033[H'; fi
}

stage() {
  _clear
  _STAGE_INDEX=$((_STAGE_INDEX + 1))
  printf '\n%s%s▸ Stage %s/%s · %s%s\n' \
    "$BOLD" "$BLUE" "$_STAGE_INDEX" "$TOTAL_STAGES" "$1" "$RESET"
}

say()  { printf '  %s\n' "$1"; }
step() { printf '  %s•%s %s\n' "$BLUE" "$RESET" "$1"; }
note() { printf '  %s%s%s\n' "$DIM" "$1" "$RESET"; }
warn() { printf '  %s⚠ %s%s\n' "$YELLOW" "$1" "$RESET"; }

confirm() {
  local reply=""
  printf '  %s? %s [y/N] ' "$YELLOW" "$1"
  read -r reply || true
  [[ "$reply" =~ ^[Yy] ]]
}

ask_secret() {
  local key="$1" prompt="$2" input
  printf '  %s%s%s ' "$BOLD" "$prompt" "$RESET"
  read -rs input || true
  printf '\n'
  printf -v "$key" '%s' "$input"
}

write_env() {
  local key="$1" value="$2" tmp
  touch "$ENV_FILE"
  tmp=$(mktemp)
  grep -vE "^${key}=" "$ENV_FILE" > "$tmp" || true
  printf '%s=%s\n' "$key" "$value" >> "$tmp"
  mv "$tmp" "$ENV_FILE"
  WRITTEN_ENV+=("$key")
  printf '  %s✓ wrote%s %s → %s\n' "$GREEN" "$RESET" "$key" "$ENV_FILE"
}

finish() {
  _clear
  printf '\n%s%s  ✓ Plunk SMTP Configuration Saved%s\n' "$BOLD" "$GREEN" "$RESET"
  (( ${#WRITTEN_ENV[@]} )) && note "saved credentials to environment file(s)"
  printf '\n'
}

# ──────────────────────────────────────────────────────────────────────────
# Main Logic
# ──────────────────────────────────────────────────────────────────────────

umask 077
if [[ "${1:-}" == --help ]]; then
    printf '%s\n' 'Usage: bash system/configure-plunk-smtp.sh [existing-env-file]'
    printf '%s\n' 'Default: system/.env. Prompts for Plunk SMTP settings and updates environment.'
    exit 0
fi
[[ $# -le 1 ]] || { printf '%s\n' 'Expected at most one environment file argument.' >&2; exit 1; }

system_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
primary_target="${1:-$system_dir/.env}"

validate_target() {
    local t="$1"
    [[ -f "$t" && ! -L "$t" && -O "$t" && -w "$t" ]] || {
        printf '%s\n' "Target '$t' must be an existing writable regular file owned by you, not a symlink." >&2
        return 1
    }
}

validate_target "$primary_target"
primary_target="$(cd -- "$(dirname -- "$primary_target")" && pwd)/$(basename -- "$primary_target")"

stage 'Plunk SMTP Server & Sender Details'
say "Primary Target: $primary_target"
say 'Press Enter to accept each default, or type a custom value:'

default_host="${PLUNK_HOST:-smtp.useplunk.com}"
read -r -p "  SMTP Host [Default: $default_host]: " input_host
host="${input_host:-$default_host}"

default_port="${PLUNK_PORT:-465}"
read -r -p "  SMTP Port [Default: $default_port]: " input_port
port="${input_port:-$default_port}"

default_scheme="${PLUNK_SCHEME:-smtps}"
read -r -p "  SMTP Scheme [Default: $default_scheme]: " input_scheme
scheme="${input_scheme:-$default_scheme}"

default_user="${PLUNK_USERNAME:-plunk}"
read -r -p "  SMTP Username [Default: $default_user]: " input_user
username="${input_user:-$default_user}"

default_from="${PLUNK_FROM_ADDRESS:-admissions@nexiam.dxtechph.online}"
read -r -p "  From Email Address [Default: $default_from]: " input_from
from_email="${input_from:-$default_from}"

default_name="${PLUNK_FROM_NAME:-NEXIAM Admissions}"
read -r -p "  From Name [Default: $default_name]: " input_name
from_name="${input_name:-$default_name}"

stage 'Plunk API / SMTP Secret Key'
say 'Get your Secret Key from Plunk Dashboard -> Settings / API Keys.'
password="$PLUNK_PASSWORD"
if [[ -z "$password" ]]; then
    ask_secret password 'Paste Plunk Secret Key (hidden):'
fi

# Sanitize input
password="$(echo -n "$password" | tr -d '\r\n' | sed -e 's/^[[:space:]"'\''"]*//' -e 's/[[:space:]"'\''"]*$//')"
if [[ -z "$password" ]]; then
    printf '%s\n' 'Error: Plunk password / API key cannot be empty. No changes written.' >&2
    exit 1
fi

save_to_env() {
    local target="$1"
    validate_target "$target" || exit 1
    exec 9>"${target}.plunk-smtp.lock"
    flock -x 9
    local staging_env
    staging_env="$(mktemp "$(dirname -- "$target")/.plunk-smtp.XXXXXX")"
    cp -- "$target" "$staging_env"
    chmod 600 "$staging_env"
    ENV_FILE="$staging_env"
    write_env MAIL_MAILER "smtp"
    write_env MAIL_HOST "$host"
    write_env MAIL_PORT "$port"
    write_env MAIL_SCHEME "$scheme"
    write_env MAIL_USERNAME "$username"
    write_env MAIL_PASSWORD "$password"
    write_env MAIL_FROM_ADDRESS "$from_email"
    write_env MAIL_FROM_NAME "\"$from_name\""
    mv -f -- "$staging_env" "$target"
    ENV_FILE="$target"
}

stage 'Save configuration to environment'
say "Target:      $primary_target"
say "Host:        $host:$port ($scheme)"
say "From:        $from_name <$from_email>"
say "Secret key:  [hidden]"
confirm "Replace mail settings in '$primary_target'?" || { say 'Cancelled; no changes written.'; exit 0; }

save_to_env "$primary_target"

if confirm "Would you like to write these same mail settings to another .env file as well?"; then
    read -r -p '  Enter path to second .env file: ' second_target
    if [[ -n "$second_target" ]]; then
        if validate_target "$second_target"; then
            second_target="$(cd -- "$(dirname -- "$second_target")" && pwd)/$(basename -- "$second_target")"
            save_to_env "$second_target"
            say "Successfully updated secondary target: $second_target"
        else
            warn "Could not update secondary target: '$second_target'. Skipping."
        fi
    fi
fi

finish

if command -v docker >/dev/null 2>&1; then
    say 'To activate the new mail configuration, containers must be recreated.'
    if confirm 'Recreate app and queue containers now?'; then
        say 'Recreating app, queue, scheduler...'
        (cd -- "$system_dir" && docker compose up -d --force-recreate app queue scheduler)
        if docker ps --format '{{.Names}}' 2>/dev/null | grep -q 'nexiam-system-https-test-app'; then
            say 'Recreating https-test containers...'
            (cd -- "$system_dir" && docker compose -f compose.yaml -f docker/jitsi/compose.https-test.yaml up -d --force-recreate https-test-app https-test-queue)
        fi
        (cd -- "$system_dir" && docker compose exec -T app php artisan config:clear || true)
        say '✓ Containers recreated and config cache cleared.'
        
        printf '\n'
        if confirm 'Would you like to send a real test email via Plunk right now?'; then
            read -r -p '  Recipient email address: ' recipient_email
            if [[ -n "$recipient_email" ]]; then
                say "Sending test email to $recipient_email..."
                (cd -- "$system_dir" && docker compose exec -T app php artisan tinker --execute "
                    \$to = '$recipient_email';
                    try {
                        Illuminate\Support\Facades\Mail::raw('Congratulations! NEXIAM Plunk SMTP integration is verified and working.', function(\$m) use (\$to) {
                            \$m->to(\$to)->subject('NEXIAM Plunk SMTP Test');
                        });
                        echo 'SUCCESS: Email dispatched via Plunk SMTP!' . PHP_EOL;
                    } catch (Throwable \$e) {
                        echo 'FAILED: ' . \$e->getMessage() . PHP_EOL;
                    }
                ")
            fi
        fi
    fi
fi

say 'Configuration complete. Clear real credentials from this script if you typed them at the top.'
