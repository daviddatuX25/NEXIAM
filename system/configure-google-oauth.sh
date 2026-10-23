#!/usr/bin/env bash
set +x

# ──────────────────────────────────────────────────────────────────────────
# Fill these in, then run: bash system/configure-google-oauth.sh
# Leave blank to enter values through interactive/hidden prompts instead.
# Never commit this file with real credentials. Clear them after use.
# ──────────────────────────────────────────────────────────────────────────
GOOGLE_CLIENT_ID=''
GOOGLE_CLIENT_SECRET=''
GOOGLE_REDIRECT_URI=''

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

TOTAL_STAGES=4
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

open_url() {
  local url="$1"
  printf '  %s↗ opening%s %s\n' "$GREEN" "$RESET" "$url"
  { if   command -v wslview     >/dev/null 2>&1; then wslview "$url"
    elif command -v explorer.exe >/dev/null 2>&1; then explorer.exe "$url"
    elif command -v xdg-open    >/dev/null 2>&1; then xdg-open "$url"
    elif command -v open        >/dev/null 2>&1; then open "$url"
    else warn "couldn't open a browser; visit it manually: $url"; fi
  } >/dev/null 2>&1 || warn "couldn't open a browser, so visit it manually: $url"
}

pause() {
  printf '  %s%s%s ' "$DIM" "${1:-Press Enter to continue}" "$RESET"
  read -r _ || true
}

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
  printf '\n%s%s  ✓ Environment Configuration Saved%s\n' "$BOLD" "$GREEN" "$RESET"
  (( ${#WRITTEN_ENV[@]} )) && note "saved credentials to environment file(s)"
  printf '\n'
}

# ──────────────────────────────────────────────────────────────────────────
# Main Logic
# ──────────────────────────────────────────────────────────────────────────

umask 077
if [[ "${1:-}" == --help ]]; then
    printf '%s\n' 'Usage: bash system/configure-google-oauth.sh [existing-env-file]'
    printf '%s\n' 'Default: system/.env. Fill the blank settings at the top or use hidden prompts.'
    printf '%s\n' 'Supports updating primary and optional second .env, plus container reload/rebuild.'
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

stage 'Choose callback URI'
say "Primary Target: $primary_target"
say 'Only GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI will change.'
say 'Default callback: https://nexiam.dxtechph.online/auth/google/callback'
say 'Local callback:   http://localhost:18000/auth/google/callback'
callback="$GOOGLE_REDIRECT_URI"
if [[ -z "$callback" ]]; then
    read -r -p 'Callback URI [Enter uses default]: ' callback
fi
callback="${callback:-https://nexiam.dxtechph.online/auth/google/callback}"
[[ "$callback" =~ ^https://[A-Za-z0-9.-]+(:[0-9]+)?/auth/google/callback$ ||
   "$callback" =~ ^http://(localhost|127\.0\.0\.1)(:[0-9]+)?/auth/google/callback$ ]] || {
    printf '%s\n' 'Use an HTTPS callback, or HTTP localhost/127.0.0.1, ending /auth/google/callback.' >&2
    exit 1
}

stage 'Copy Google credentials'
open_url 'https://console.cloud.google.com/auth/clients'
client_id="$GOOGLE_CLIENT_ID"
client_secret="$GOOGLE_CLIENT_SECRET"
if [[ -z "$client_id" ]]; then
    ask_secret client_id 'Paste Client ID (hidden):'
fi
if [[ -z "$client_secret" ]]; then
    ask_secret client_secret 'Paste Client secret (hidden):'
fi

# Strip accidental whitespace, carriage returns, or surrounding quotes from clipboard
client_id="$(echo -n "$client_id" | tr -d '\r\n' | sed -e 's/^[[:space:]"'\''"]*//' -e 's/[[:space:]"'\''"]*$//')"
client_secret="$(echo -n "$client_secret" | tr -d '\r\n' | sed -e 's/^[[:space:]"'\''"]*//' -e 's/[[:space:]"'\''"]*$//')"

if [[ -z "$client_id" || -z "$client_secret" ]]; then
    printf '%s\n' 'Error: Client ID or Client Secret was empty. No changes written.' >&2
    exit 1
fi

save_to_env() {
    local target="$1"
    validate_target "$target" || exit 1
    exec 9>"${target}.google-oauth.lock"
    flock -x 9
    local staging_env
    staging_env="$(mktemp "$(dirname -- "$target")/.google-oauth.XXXXXX")"
    cp -- "$target" "$staging_env"
    chmod 600 "$staging_env"
    while IFS= read -r line || [[ -n "$line" ]]; do
        if [[ "$line" =~ ^[[:space:]]*(export[[:space:]]+)?(GOOGLE_CLIENT_ID|GOOGLE_CLIENT_SECRET|GOOGLE_REDIRECT_URI)[[:space:]]*= &&
              ! "$line" =~ ^(GOOGLE_CLIENT_ID|GOOGLE_CLIENT_SECRET|GOOGLE_REDIRECT_URI)= ]]; then
            printf '%s\n' "Normalize Google assignments in $target to KEY=value (no export or leading spaces), then retry." >&2
            rm -f -- "$staging_env"
            exit 1
        fi
    done < "$target"
    ENV_FILE="$staging_env"
    write_env GOOGLE_CLIENT_ID "$client_id"
    write_env GOOGLE_CLIENT_SECRET "$client_secret"
    write_env GOOGLE_REDIRECT_URI "$callback"
    mv -f -- "$staging_env" "$target"
    ENV_FILE="$target"
}

stage 'Save configuration to environment'
say "Primary Target: $primary_target"
say "Callback:       $callback"
say 'Credentials will not be printed. No backup containing old secrets will be created.'
confirm "Replace the three Google settings in '$primary_target'?" || { say 'Cancelled; no changes written.'; exit 0; }

save_to_env "$primary_target"

# Optional second .env file for connecting multiple environments
if confirm "Would you like to write these same Google OAuth credentials to another .env file as well?"; then
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

stage 'Container & Cache Reload'
if command -v docker >/dev/null 2>&1; then
    say 'To activate the new environment variables, running containers must reload .env.'
    say 'Select an option:'
    say '  [1] Recreate running containers (picks up new .env without building images)'
    say '  [2] Rebuild app image and recreate containers (runs docker compose build app)'
    say '  [3] Skip container recreation for now (manual restart later)'
    read -r -p '  Choice [1/2/3, default: 3]: ' container_action
    
    # Check if public-ispsc-app is running
    has_public_ispsc=0
    if docker ps --format '{{.Names}}' 2>/dev/null | grep -q 'nexiam-system-public-ispsc-app'; then
        has_public_ispsc=1
    fi

    case "${container_action:-3}" in
        1)
            say 'Recreating Compose containers...'
            (cd -- "$system_dir" && docker compose up -d --force-recreate app queue scheduler)
            if [[ "$has_public_ispsc" -eq 1 ]]; then
                say 'Recreating public-ispsc-app container...'
                (cd -- "$system_dir" && docker compose -f compose.yaml -f docker/jitsi/compose.public-ispsc.yaml up -d --force-recreate public-ispsc-app)
            fi
            (cd -- "$system_dir" && docker compose exec -T app php artisan config:clear || true)
            say '✓ Containers recreated and Laravel config cache cleared.'
            ;;
        2)
            say 'Rebuilding app image and recreating containers...'
            (cd -- "$system_dir" && docker compose build app)
            (cd -- "$system_dir" && docker compose up -d --force-recreate app queue scheduler)
            if [[ "$has_public_ispsc" -eq 1 ]]; then
                say 'Recreating public-ispsc-app container with new build...'
                (cd -- "$system_dir" && docker compose -f compose.yaml -f docker/jitsi/compose.public-ispsc.yaml up -d --force-recreate public-ispsc-app)
            fi
            (cd -- "$system_dir" && docker compose exec -T app php artisan config:clear || true)
            say '✓ App image rebuilt, containers recreated, and config cache cleared.'
            ;;
        *)
            say 'Skipped container changes. Recreate containers manually when ready.'
            ;;
    esac
else
    say 'Docker binary not found in PATH; recreate containers manually when ready.'
fi

say 'Configuration complete. Clear real credentials from this script if you typed them at the top.'
