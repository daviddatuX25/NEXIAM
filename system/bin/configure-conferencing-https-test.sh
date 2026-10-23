#!/usr/bin/env bash
set +x
set -euo pipefail
umask 077
system_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
env_file="$system_dir/.env"
[[ -f "$env_file" && ! -L "$env_file" ]] || { printf '%s\n' 'A regular existing system/.env is required.' >&2; exit 1; }
exec 9>"$system_dir/docker/jitsi/.env-provision.lock"
flock -x 9
app_url="${1:?Provide the HTTPS application origin}"
media_url="${2:?Provide the HTTPS media origin}"
bridge_ips="${3:?Provide reachable LAN/ZeroTier video bridge IP addresses}"
proxy_ips="${4:?Provide the exact trusted local proxy addresses}"
[[ "$app_url" == https://* && "$media_url" == https://* ]] || { printf '%s\n' 'Both browser origins must use HTTPS.' >&2; exit 1; }
declare -A updates=(
    [NEXIAM_TEST_APP_URL]="$app_url"
    [NEXIAM_TEST_TRUSTED_PROXIES]="$proxy_ips"
    [JITSI_ORIGIN]="$media_url"
    [JITSI_PUBLIC_URL]="$media_url"
    [JVB_ADVERTISE_IPS]="$bridge_ips"
)
if [[ "${5:-}" == '--rotate-exposed-local-secrets' ]]; then
    updates[JITSI_APP_SECRET]="$(openssl rand -hex 32)"
    updates[JITSI_ADMISSION_SECRET]="$(openssl rand -hex 32)"
elif [[ -n "${5:-}" ]]; then
    printf '%s\n' 'Unsupported configuration option.' >&2
    exit 1
fi
declare -A written=()
tmp="$(mktemp "$system_dir/.env.conferencing.XXXXXX")"
trap 'rm -f -- "$tmp"' EXIT
while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" =~ ^([A-Za-z_][A-Za-z0-9_]*)= ]]; then
        name="${BASH_REMATCH[1]}"
        if [[ -v updates[$name] ]]; then
            [[ ! -v written[$name] ]] || { printf 'Duplicate environment key: %s\n' "$name" >&2; exit 1; }
            printf '%s=%s\n' "$name" "${updates[$name]}" >> "$tmp"
            written[$name]=1
            continue
        fi
    fi
    printf '%s\n' "$line" >> "$tmp"
done < "$env_file"
for name in "${!updates[@]}"; do
    if [[ ! -v written[$name] ]]; then
        printf '%s=%s\n' "$name" "${updates[$name]}" >> "$tmp"
    fi
done
chmod 600 "$tmp"
mv -f -- "$tmp" "$env_file"
trap - EXIT
printf '%s\n' 'Configured private HTTPS testing environment; no secret values were displayed.'
