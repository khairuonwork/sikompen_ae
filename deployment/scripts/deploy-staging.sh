#!/usr/bin/env bash

set -Eeuo pipefail

readonly deploy_path="${SIKOMPEN_DEPLOY_PATH:?SIKOMPEN_DEPLOY_PATH must point to the server checkout.}"
readonly deploy_branch="${SIKOMPEN_DEPLOY_BRANCH:-staging}"
readonly deploy_ref="${SIKOMPEN_DEPLOY_REF:?SIKOMPEN_DEPLOY_REF must be the commit SHA approved for deployment.}"
readonly lock_path="${SIKOMPEN_DEPLOY_LOCK_PATH:-/tmp/sikompen-staging-deploy.lock}"

if ! command -v docker >/dev/null 2>&1; then
    printf 'Docker is not available for this deployment user.\n' >&2
    exit 1
fi

if ! command -v flock >/dev/null 2>&1; then
    printf 'flock is required to prevent concurrent deployments.\n' >&2
    exit 1
fi

exec 9>"${lock_path}"
if ! flock -n 9; then
    printf 'Another Sikompen staging deployment is still running.\n' >&2
    exit 1
fi

cd "${deploy_path}"

if ! git diff --quiet || ! git diff --cached --quiet; then
    printf 'Server checkout has local changes. Resolve them before deploying.\n' >&2
    exit 1
fi

git fetch --no-tags origin "+refs/heads/${deploy_branch}:refs/remotes/origin/${deploy_branch}"

if ! git merge-base --is-ancestor "${deploy_ref}" "origin/${deploy_branch}"; then
    printf 'Requested commit is not contained in origin/%s: %s\n' "${deploy_branch}" "${deploy_ref}" >&2
    exit 1
fi

readonly previous_ref="$(git rev-parse HEAD)"
git switch --detach "${deploy_ref}"

printf 'Building Sikompen staging images for %s. Previous revision: %s\n' "${deploy_ref}" "${previous_ref}"
docker compose build
docker compose run --rm --no-deps app php artisan migrate --force
docker compose up -d --remove-orphans
docker compose exec -T app php artisan optimize
bash deployment/scripts/healthcheck-staging.sh

printf 'Deployment completed successfully at revision %s.\n' "${deploy_ref}"
