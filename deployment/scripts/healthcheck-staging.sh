#!/usr/bin/env bash

set -Eeuo pipefail

readonly required_services=(
    app
    web
    database
    queue
    imports
    exports
    api-sync
    scheduler
    api-mini-app
)

mapfile -t running_services < <(docker compose ps --status running --services)

for service in "${required_services[@]}"; do
    if [[ ! " ${running_services[*]} " =~ " ${service} " ]]; then
        printf 'Required service is not running: %s\n' "${service}" >&2
        exit 1
    fi
done

docker compose exec -T web wget -q -O /dev/null http://127.0.0.1/up
docker compose exec -T api-mini-app python -c 'import urllib.request; urllib.request.urlopen("http://127.0.0.1:8000/health", timeout=10).read()'

printf 'Staging health checks passed.\n'
