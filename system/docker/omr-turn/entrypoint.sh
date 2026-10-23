#!/bin/sh
set -eu
umask 077

fail() {
    printf '%s\n' "OMR TURN configuration rejected: $1" >&2
    exit 1
}

secret=${OMR_CAMERA_TURN_SHARED_SECRET:-}
realm=${OMR_CAMERA_TURN_REALM:-}
external_ip=${OMR_CAMERA_TURN_EXTERNAL_IP:-}

[ "${#secret}" -ge 32 ] || fail 'a random shared secret of at least 32 characters is required'
case "$secret" in *[!A-Za-z0-9_+/=-]*) fail 'shared secret must use base64 or URL-safe characters' ;; esac
[ -n "$realm" ] || fail 'the verified relay hostname/realm is required'
case "$realm" in *[!A-Za-z0-9.-]*) fail 'realm must be a hostname without whitespace' ;; esac
[ -n "$external_ip" ] || fail 'the verified public IPv4 address is required'
case "$external_ip" in *[!0-9./]*) fail 'external IP must be IPv4 or public/private IPv4 mapping' ;; esac
[ -r /run/omr-turn-tls/cert.pem ] || fail 'a readable TLS certificate is required'
[ -r /run/omr-turn-tls/key.pem ] || fail 'a readable TLS private key is required'

config=/run/omr-turn/turnserver.conf
{
    printf '%s\n' \
        'listening-ip=0.0.0.0' \
        'listening-port=3478' \
        'tls-listening-port=5349' \
        'min-port=49160' \
        'max-port=49200' \
        'fingerprint' \
        'use-auth-secret' \
        'stale-nonce=600' \
        'max-allocate-lifetime=600' \
        'user-quota=4' \
        'total-quota=100' \
        'max-bps=1048576' \
        'bps-capacity=52428800' \
        'no-cli' \
        'no-multicast-peers' \
        'no-tlsv1' \
        'no-tlsv1_1' \
        'no-dtls' \
        'cert=/run/omr-turn-tls/cert.pem' \
        'pkey=/run/omr-turn-tls/key.pem' \
        'log-file=stdout' \
        'simple-log' \
        'pidfile=/run/omr-turn/turnserver.pid' \
        'denied-peer-ip=0.0.0.0-0.255.255.255' \
        'denied-peer-ip=10.0.0.0-10.255.255.255' \
        'denied-peer-ip=100.64.0.0-100.127.255.255' \
        'denied-peer-ip=127.0.0.0-127.255.255.255' \
        'denied-peer-ip=169.254.0.0-169.254.255.255' \
        'denied-peer-ip=172.16.0.0-172.31.255.255' \
        'denied-peer-ip=192.168.0.0-192.168.255.255' \
        'denied-peer-ip=224.0.0.0-255.255.255.255' \
        'denied-peer-ip=::1' \
        'denied-peer-ip=fc00::-fdff:ffff:ffff:ffff:ffff:ffff:ffff:ffff' \
        'denied-peer-ip=fe80::-febf:ffff:ffff:ffff:ffff:ffff:ffff:ffff'
    printf 'realm=%s\nexternal-ip=%s\nstatic-auth-secret=%s\n' "$realm" "$external_ip" "$secret"
} > "$config"
unset secret OMR_CAMERA_TURN_SHARED_SECRET
exec turnserver -c "$config"
