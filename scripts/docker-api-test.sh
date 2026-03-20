#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if docker compose ps -q api 2>/dev/null | grep -q .; then
  echo "Using running api container (docker compose exec)..."
  docker compose exec -T api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
else
  echo "No api container; using docker compose run --no-deps..."
  docker compose run --rm --no-deps api bash -lc "composer install --no-interaction && composer run lint && php artisan test"
fi
