#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
exec docker compose run --rm --no-deps -T \
  -v "$ROOT:/var/www/html" \
  -v "$ROOT/vendor:/var/www/html/vendor" \
  app php artisan boost:mcp
