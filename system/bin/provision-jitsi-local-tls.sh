#!/usr/bin/env bash
set -euo pipefail

system_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
key_dir="$system_dir/docker/jitsi/config/web/keys"
key_file="$key_dir/cert.key"
cert_file="$key_dir/cert.crt"

if [[ -e "$key_file" || -e "$cert_file" ]]; then
    if [[ -f "$key_file" && -f "$cert_file" ]]; then
        chmod 600 "$key_file" "$cert_file"
        printf '%s\n' "Existing local Jitsi TLS certificate retained; files are mode 0600."
        exit 0
    fi
    printf '%s\n' "Refusing to replace an incomplete TLS key/certificate pair." >&2
    exit 1
fi

command -v openssl >/dev/null || { printf '%s\n' 'OpenSSL is required.' >&2; exit 1; }
install -d -m 700 "$key_dir"
openssl req -x509 -nodes -newkey rsa:3072 -sha256 -days 365 \
    -keyout "$key_file" \
    -out "$cert_file" \
    -subj '/CN=localhost' \
    -addext 'subjectAltName=DNS:localhost,IP:127.0.0.1,IP:::1' \
    -addext 'basicConstraints=critical,CA:FALSE' \
    -addext 'keyUsage=critical,digitalSignature,keyEncipherment' \
    -addext 'extendedKeyUsage=serverAuth' \
    >/dev/null 2>&1
chmod 600 "$key_file" "$cert_file"
printf '%s\n' "Generated local-only HTTPS certificate and key at docker/jitsi/config/web/keys (0600)."
