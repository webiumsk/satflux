#!/bin/bash

# satflux.io Production Deployment Script
# This script automates the deployment process for production updates

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Load configuration if exists
if [ -f "deploy.config.sh" ]; then
    source deploy.config.sh
fi

# Configuration (standalone is default)
ENV_FILE="${ENV_FILE:-.env.standalone}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.standalone.yml}"
PROJECT_NAME="${PROJECT_NAME:-satflux_standalone}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-}"

# Sanitize variables (remove CR and extra whitespace)
COMPOSE_FILE=$(echo "$COMPOSE_FILE" | tr -d '\r' | xargs)
PROJECT_NAME=$(echo "$PROJECT_NAME" | tr -d '\r' | xargs)

# Override from env file (not in git; survives every deploy)
if [ -f "$ENV_FILE" ]; then
    ENV_COMPOSE=$(grep "^COMPOSE_FILE=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs)
    [ -n "$ENV_COMPOSE" ] && COMPOSE_FILE="$ENV_COMPOSE"
    ENV_DEPLOY_BRANCH=$(grep "^DEPLOY_BRANCH=" "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs)
    [ -n "$ENV_DEPLOY_BRANCH" ] && DEPLOY_BRANCH="$ENV_DEPLOY_BRANCH"
fi

PHP_SERVICE="php"  # Service name
PHP_CONTAINER="${PHP_CONTAINER:-satflux_php_standalone}"

# Define the base docker compose command
DC_CMD="docker compose -f $COMPOSE_FILE --env-file $ENV_FILE --project-name $PROJECT_NAME"

NGINX_CONTAINER="${NGINX_CONTAINER:-satflux_nginx_standalone}"
CADDY_CONTAINER="${CADDY_CONTAINER:-satflux_caddy_standalone}"
EVOLU_RELAY_CONTAINER="${EVOLU_RELAY_CONTAINER:-satflux_evolu_relay_standalone}"

# Remove fixed-name containers owned by a different compose project (e.g. bare `docker compose up` without --project-name).
remove_conflicting_compose_containers() {
    local names=("$@")
    local c proj
    for c in "${names[@]}"; do
        if ! docker inspect "$c" >/dev/null 2>&1; then
            continue
        fi
        proj=$(docker inspect "$c" --format '{{index .Config.Labels "com.docker.compose.project"}}' 2>/dev/null || true)
        if [ -n "$proj" ] && [ "$proj" != "$PROJECT_NAME" ]; then
            echo -e "${YELLOW}Removing $c (compose project '$proj', expected '$PROJECT_NAME')${NC}"
            docker rm -f "$c" 2>/dev/null || true
        fi
    done
}

write_evolu_relay_caddyfile() {
    RELAY_SITE_ADDRESS=$(grep -E '^RELAY_SITE_ADDRESS=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || true)
    if [ -n "$RELAY_SITE_ADDRESS" ]; then
        echo -e "${YELLOW}Evolu relay Caddy site: ${RELAY_SITE_ADDRESS}${NC}"
        cat > docker/caddy/Caddyfile.relay <<EOF
${RELAY_SITE_ADDRESS} {
    reverse_proxy evolu-relay:4000 {
        flush_interval -1
    }
    log {
        output stdout
        format console
    }
}
EOF
    else
        echo -e "${YELLOW}Warning: RELAY_SITE_ADDRESS not set - Evolu relay will not get a Caddy TLS site${NC}"
        cat > docker/caddy/Caddyfile.relay <<'EOF'
# Evolu relay TLS site disabled (set RELAY_SITE_ADDRESS in deploy env file).
EOF
    fi
}

ensure_compose_stack() {
    echo -e "${YELLOW}Ensuring Docker Compose stack ($COMPOSE_FILE, project $PROJECT_NAME)...${NC}"
    local -a container_names
    if [ "${#STANDALONE_CONTAINER_NAMES[@]}" -gt 0 ]; then
        container_names=("${STANDALONE_CONTAINER_NAMES[@]}")
    else
        container_names=(
            satflux_caddy_standalone satflux_nginx_standalone satflux_php_standalone
            satflux_reverb_standalone satflux_queue_standalone satflux_scheduler_standalone
            satflux_postgres_standalone satflux_redis_standalone satflux_evolu_relay_standalone
        )
    fi
    if [ "$COMPOSE_FILE" = "docker-compose.standalone.yml" ]; then
        remove_conflicting_compose_containers "${container_names[@]}"
    fi
    if ! $DC_CMD up -d --remove-orphans; then
        echo -e "${YELLOW}Retrying after removing conflicting containers...${NC}"
        if [ "$COMPOSE_FILE" = "docker-compose.prod.yml" ]; then
            docker rm -f satflux_redis_prod satflux_postgres_prod satflux_php_prod satflux_nginx_prod satflux_reverb_prod 2>/dev/null || true
        else
            for c in "${container_names[@]}"; do
                docker rm -f "$c" 2>/dev/null || true
            done
        fi
        $DC_CMD up -d --remove-orphans
    fi
    if grep -q "evolu-relay:" "$COMPOSE_FILE"; then
        $DC_CMD up -d evolu-relay
    fi
}

wait_for_container_running() {
    local name=$1
    local max=${2:-30}
    local i
    for i in $(seq 1 "$max"); do
        if docker ps --format "{{.Names}}" | grep -q "^${name}$"; then
            if docker inspect "$name" --format '{{.State.Status}}' 2>/dev/null | grep -q "running"; then
                return 0
            fi
        fi
        sleep 1
    done
    return 1
}

wait_for_container_healthy() {
    local name=$1
    local max=${2:-30}
    local i status
    for i in $(seq 1 "$max"); do
        status=$(docker inspect "$name" --format '{{.State.Health.Status}}' 2>/dev/null || echo "none")
        if [ "$status" = "healthy" ] || [ "$status" = "none" ]; then
            if docker inspect "$name" --format '{{.State.Status}}' 2>/dev/null | grep -q "running"; then
                return 0
            fi
        fi
        sleep 2
    done
    return 1
}

verify_nginx_asset_bytes() {
    local asset=$1
    local min_bytes=${2:-100}
    local empty=0 i sz
    for i in 1 2 3 4 5; do
        sz=$(docker exec "$NGINX_CONTAINER" wget -qO- "http://127.0.0.1/build/assets/${asset}" 2>/dev/null | wc -c | tr -d ' ')
        if [ "${sz:-0}" -lt "$min_bytes" ]; then
            empty=$((empty + 1))
        fi
    done
    [ "$empty" -eq 0 ]
}

caddy_tls_asset_bytes() {
    local host=$1 asset=$2
    curl -s --http1.1 --max-time 8 --resolve "${host}:443:127.0.0.1" -k \
        "https://${host}/build/assets/${asset}" 2>/dev/null | wc -c | tr -d ' '
}

# Caddy may return empty responses while TLS listeners reload after restart; wait for stability.
wait_for_caddy_tls_ready() {
    local host=$1 asset=$2 min_bytes=$3
    local max_wait=${4:-60}
    local consecutive=0 elapsed=0 sz
    echo -e "${YELLOW}Waiting for Caddy TLS (post-restart reload)...${NC}"
    while [ "$elapsed" -lt "$max_wait" ]; do
        sz=$(caddy_tls_asset_bytes "$host" "$asset")
        if [ "${sz:-0}" -ge "$min_bytes" ]; then
            consecutive=$((consecutive + 1))
            if [ "$consecutive" -ge 3 ]; then
                echo -e "${GREEN}✓ Caddy TLS ready${NC}"
                return 0
            fi
        else
            consecutive=0
        fi
        sleep 1
        elapsed=$((elapsed + 1))
    done
    return 1
}

verify_caddy_tls_asset_bytes() {
    local host=$1 asset=$2 min_bytes=$3
    local empty=0 i sz
    for i in 1 2 3 4 5; do
        sz=$(caddy_tls_asset_bytes "$host" "$asset")
        if [ "${sz:-0}" -lt "$min_bytes" ]; then
            empty=$((empty + 1))
        fi
        sleep 0.5
    done
    [ "$empty" -eq 0 ]
}

echo -e "${GREEN}Starting satflux deployment...${NC}"

# Check if env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: $ENV_FILE not found!${NC}"
    echo "Please create $ENV_FILE and configure it (e.g. copy from .env.example)."
    exit 1
fi

# Check if compose file exists
if [ ! -f "$COMPOSE_FILE" ]; then
    echo -e "${RED}Error: $COMPOSE_FILE not found!${NC}"
    exit 1
fi

# Step 1: Pull latest changes
echo -e "${YELLOW}Step 1: Pulling latest changes from Git...${NC}"
if [ -n "$DEPLOY_BRANCH" ]; then
    echo -e "${GREEN}  → Branch: ${DEPLOY_BRANCH}${NC}"
else
    echo -e "${GREEN}  → Branch: main / master (default)${NC}"
fi
git fetch origin

# Check if there are local changes (including untracked files that might conflict)
if [ -n "$(git status --porcelain)" ]; then
    echo -e "${YELLOW}Local changes detected. Stashing changes...${NC}"
    # Stash including untracked files to avoid conflicts
    git stash push -u -m "Auto-stash before deployment $(date +%Y-%m-%d_%H:%M:%S)" || {
        echo -e "${YELLOW}Note: Stash may be empty, continuing...${NC}"
    }
fi

# Determine branch to deploy
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [ -n "$DEPLOY_BRANCH" ]; then
    echo -e "${YELLOW}Deploying branch: $DEPLOY_BRANCH (from config)${NC}"
    if git rev-parse --verify "origin/$DEPLOY_BRANCH" >/dev/null 2>&1; then
        git checkout "$DEPLOY_BRANCH" 2>/dev/null || git checkout -b "$DEPLOY_BRANCH" "origin/$DEPLOY_BRANCH"
        git pull origin "$DEPLOY_BRANCH"
        BRANCH="$DEPLOY_BRANCH"
    else
        echo -e "${RED}Error: Branch origin/$DEPLOY_BRANCH not found. Run: git fetch origin${NC}"
        exit 1
    fi
else
    # Default: deploy main or master (checkout that branch first, then pull)
    DEFAULT_BRANCH=""
    if git rev-parse --verify "origin/master" >/dev/null 2>&1; then
        DEFAULT_BRANCH="master"
    elif git rev-parse --verify "origin/main" >/dev/null 2>&1; then
        DEFAULT_BRANCH="main"
    fi
    if [ -z "$DEFAULT_BRANCH" ]; then
        echo -e "${RED}Error: Neither origin/master nor origin/main found. Run: git fetch origin${NC}"
        exit 1
    fi
    echo -e "${YELLOW}Deploying default branch: $DEFAULT_BRANCH${NC}"
    git checkout "$DEFAULT_BRANCH" 2>/dev/null || git checkout -b "$DEFAULT_BRANCH" "origin/$DEFAULT_BRANCH"
    if ! git pull origin "$DEFAULT_BRANCH"; then
        echo -e "${YELLOW}Attempting to resolve by resetting to remote state...${NC}"
        git reset --hard "origin/$DEFAULT_BRANCH" 2>/dev/null || {
            echo -e "${RED}Fatal: Cannot resolve Git conflicts automatically.${NC}"
            exit 1
        }
        echo -e "${GREEN}✓ Reset to remote branch $DEFAULT_BRANCH${NC}"
    fi
    BRANCH="$DEFAULT_BRANCH"
fi

echo -e "${GREEN}✓ Git pull completed → deployed branch: $BRANCH${NC}"

write_evolu_relay_caddyfile

# Step 2a: Build PHP image (picks up Dockerfile changes like new extensions)
echo -e "${YELLOW}Step 2a: Building PHP image...${NC}"
$DC_CMD build php

# Step 2: Ensuring containers are running
ensure_compose_stack

# Wait for services to be healthy
echo -e "${YELLOW}Waiting for services to be healthy...${NC}"
sleep 10

# Wait for PHP container to be ready
echo -e "${YELLOW}Waiting for PHP container to be ready...${NC}"
if ! wait_for_container_running "$PHP_CONTAINER" 30; then
    echo -e "${RED}✗ PHP container failed to start!${NC}"
    $DC_CMD ps
    docker logs "$PHP_CONTAINER" 2>&1 | tail -20
    exit 1
fi
echo -e "${GREEN}✓ PHP container is running${NC}"

if grep -q "evolu-relay:" "$COMPOSE_FILE"; then
    echo -e "${YELLOW}Waiting for evolu-relay...${NC}"
    if ! wait_for_container_running "$EVOLU_RELAY_CONTAINER" 30; then
        echo -e "${RED}✗ evolu-relay failed to start!${NC}"
        $DC_CMD ps evolu-relay
        docker logs "$EVOLU_RELAY_CONTAINER" 2>&1 | tail -20
        exit 1
    fi
    echo -e "${GREEN}✓ evolu-relay is running${NC}"
fi

# Ensure storage and bootstrap/cache exist and are writable by www-data (queue/scheduler/reverb start early and need this)
echo -e "${YELLOW}Preparing storage and cache directories...${NC}"
docker exec --user root "$PHP_CONTAINER" mkdir -p /var/www/storage/logs /var/www/storage/framework/views /var/www/storage/framework/sessions /var/www/storage/framework/cache /var/www/bootstrap/cache
docker exec --user root "$PHP_CONTAINER" chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker exec --user root "$PHP_CONTAINER" chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Step 3: Install/update PHP dependencies
echo -e "${YELLOW}Step 3: Installing/updating PHP dependencies...${NC}"
# Clear package manifest so discovery runs with current vendor only (avoids loading dev-only Collision)
docker exec --user root "$PHP_CONTAINER" rm -f /var/www/bootstrap/cache/packages.php /var/www/bootstrap/cache/services.php 2>/dev/null || true
docker exec --user root "$PHP_CONTAINER" composer install --optimize-autoloader --no-dev --no-interaction

# Step 4: Install/update Node dependencies and build assets
echo -e "${YELLOW}Step 4: Building frontend assets...${NC}"

# Warn when local-first flags are mismatched (paired rollout)
_vite_lf=$(grep -E '^VITE_INVOICING_LOCAL_FIRST=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || true)
_srv_lf=$(grep -E '^INVOICING_LOCAL_FIRST=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || true)
if [ "$_vite_lf" = "true" ] && [ "$_srv_lf" != "true" ]; then
    echo -e "${YELLOW}Warning: VITE_INVOICING_LOCAL_FIRST=true but INVOICING_LOCAL_FIRST is not true in $ENV_FILE${NC}"
    echo -e "${YELLOW}  → Set both flags for production local-first (see docs/INVOICING_LOCAL_FIRST_ROLLOUT.md)${NC}"
elif [ "$_srv_lf" = "true" ] && [ "$_vite_lf" != "true" ]; then
    echo -e "${YELLOW}Warning: INVOICING_LOCAL_FIRST=true but VITE_INVOICING_LOCAL_FIRST is not true in $ENV_FILE${NC}"
    echo -e "${YELLOW}  → Frontend will stay on server invoicing until VITE flag is set and assets are rebuilt${NC}"
fi

# Check if node_modules exists, if not install dependencies
if ! docker exec "$PHP_CONTAINER" test -d node_modules; then
    docker exec --user root "$PHP_CONTAINER" npm ci
else
    docker exec --user root "$PHP_CONTAINER" npm install
fi
# Vite bakes VITE_* at build time; pass deploy env file so flags in .env.standalone win over a stale .env on disk
docker exec --env-file "$ENV_FILE" --user root "$PHP_CONTAINER" npm run build

echo -e "${YELLOW}Verifying Vite build output...${NC}"
docker exec "$PHP_CONTAINER" test -s /var/www/public/build/manifest.json || {
    echo -e "${RED}Error: public/build/manifest.json missing after npm run build${NC}"
    exit 1
}
_build_js_count=$(docker exec "$PHP_CONTAINER" sh -c 'find /var/www/public/build/assets -name "*.js" -size +0 2>/dev/null | wc -l' | tr -d ' ')
if [ "${_build_js_count:-0}" -lt 1 ]; then
    echo -e "${RED}Error: no JS assets in public/build/assets after npm run build${NC}"
    exit 1
fi
_zero_js_count=$(docker exec "$PHP_CONTAINER" sh -c 'find /var/www/public/build/assets -name "*.js" -size 0 2>/dev/null | wc -l' | tr -d ' ')
if [ "${_zero_js_count:-0}" -gt 0 ]; then
    echo -e "${RED}Error: found ${_zero_js_count} empty .js files in public/build/assets${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Vite build OK (${_build_js_count} JS assets)${NC}"
docker exec --user root "$PHP_CONTAINER" chmod -R a+rX /var/www/public/build
docker exec --user root "$PHP_CONTAINER" chown -R www-data:www-data /var/www/public/build 2>/dev/null || true

# Blade @vite() hashes must match manifest immediately after build
echo -e "${YELLOW}Refreshing compiled views (@vite asset hashes)...${NC}"
docker exec --user root "$PHP_CONTAINER" php artisan view:clear
docker exec --user root "$PHP_CONTAINER" php artisan view:cache

# Step 5: Run database migrations
echo -e "${YELLOW}Step 5: Running database migrations...${NC}"
docker exec --user root "$PHP_CONTAINER" php artisan migrate --force

# Step 5b: Create storage symlink (public/storage -> storage/app/public)
echo -e "${YELLOW}Step 5b: Creating storage symlink...${NC}"
docker exec --user root "$PHP_CONTAINER" php artisan storage:link --force 2>/dev/null || true

# Step 6: Clear and optimize Laravel caches
echo -e "${YELLOW}Step 6: Optimizing Laravel...${NC}"
docker exec --user root "$PHP_CONTAINER" php artisan config:clear
docker exec --user root "$PHP_CONTAINER" php artisan route:clear
docker exec --user root "$PHP_CONTAINER" php artisan view:clear
docker exec --user root "$PHP_CONTAINER" php artisan cache:clear

# Rebuild caches
docker exec --user root "$PHP_CONTAINER" php artisan config:cache
docker exec --user root "$PHP_CONTAINER" php artisan route:cache
docker exec --user root "$PHP_CONTAINER" php artisan view:cache

# Fix permissions for storage and cache directories
echo -e "${YELLOW}Setting correct permissions...${NC}"
docker exec --user root "$PHP_CONTAINER" mkdir -p /var/www/storage/framework/views /var/www/storage/framework/sessions /var/www/storage/framework/cache
docker exec --user root "$PHP_CONTAINER" chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker exec --user root "$PHP_CONTAINER" chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Step 7: Restart relevant containers (relay before caddy; caddy last)
echo -e "${YELLOW}Step 7: Restarting containers...${NC}"

if grep -q "caddy:" "$COMPOSE_FILE"; then
    echo -e "${YELLOW}Validating Caddy config...${NC}"
    _site_address=$(grep -E '^SITE_ADDRESS=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || echo localhost:80)
    docker run --rm \
        -v "$(pwd)/docker/caddy:/etc/caddy:ro" \
        -e "SITE_ADDRESS=${_site_address}" \
        caddy:alpine caddy validate --config /etc/caddy/Caddyfile || {
            echo -e "${RED}Caddy config invalid - fix docker/caddy/Caddyfile* before restart${NC}"
            exit 1
        }
    echo -e "${GREEN}✓ Caddy config valid${NC}"
fi

if grep -q "evolu-relay:" "$COMPOSE_FILE"; then
    $DC_CMD restart evolu-relay
    wait_for_container_running "$EVOLU_RELAY_CONTAINER" 30 || {
        echo -e "${RED}✗ evolu-relay failed after restart${NC}"
        docker logs "$EVOLU_RELAY_CONTAINER" 2>&1 | tail -20
        exit 1
    }
fi

$DC_CMD restart php nginx
if grep -q "reverb:" "$COMPOSE_FILE"; then
    $DC_CMD restart reverb queue scheduler
fi
if grep -q "caddy:" "$COMPOSE_FILE"; then
    $DC_CMD restart caddy
    wait_for_container_running "$CADDY_CONTAINER" 30 || {
        echo -e "${RED}✗ caddy failed after restart${NC}"
        docker logs "$CADDY_CONTAINER" 2>&1 | tail -20
        exit 1
    }
    sleep 3
fi

# Step 8: Verify deployment
echo -e "${YELLOW}Step 8: Verifying deployment...${NC}"
if ! wait_for_container_healthy "$NGINX_CONTAINER" 30; then
    echo -e "${RED}✗ nginx failed to become healthy${NC}"
    docker logs "$NGINX_CONTAINER" 2>&1 | tail -20
    exit 1
fi
sleep 2

if ! $DC_CMD ps --status running 2>/dev/null | grep -qE 'Up|running'; then
    echo -e "${RED}✗ Some containers are not running!${NC}"
    $DC_CMD ps
    exit 1
fi
echo -e "${GREEN}✓ Containers are running${NC}"

_public_js=$(docker exec "$PHP_CONTAINER" node -p "require('/var/www/public/build/manifest.json')['resources/js/public.ts'].file.split('/').pop()" 2>/dev/null || true)
if [ -z "$_public_js" ]; then
    echo -e "${RED}✗ Could not resolve Vite entry from manifest.json${NC}"
    exit 1
fi

_public_bytes=$(docker exec "$PHP_CONTAINER" stat -c%s "/var/www/public/build/assets/$_public_js" 2>/dev/null || echo 0)
if [ "${_public_bytes:-0}" -lt 100 ]; then
    echo -e "${RED}✗ public/build/assets/$_public_js is only ${_public_bytes} bytes on disk${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Entry asset $_public_js (${_public_bytes} bytes on disk)${NC}"

if ! verify_nginx_asset_bytes "$_public_js" 100; then
    echo -e "${RED}✗ nginx returned empty entry JS - check docker/nginx/prod.conf /build/ block${NC}"
    exit 1
fi
echo -e "${GREEN}✓ nginx serves /build/assets/$_public_js reliably (5/5)${NC}"

_site_address=$(grep -E '^SITE_ADDRESS=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || true)
_site_host="${_site_address%%:*}"
if [ -n "$_site_host" ] && [ "$_site_host" != "localhost" ] && grep -q "caddy:" "$COMPOSE_FILE"; then
    if ! wait_for_caddy_tls_ready "$_site_host" "$_public_js" 100 60; then
        echo -e "${RED}✗ Caddy/TLS did not become ready within 60s${NC}"
        docker logs "$CADDY_CONTAINER" 2>&1 | tail -20
        exit 1
    fi
    if ! verify_caddy_tls_asset_bytes "$_site_host" "$_public_js" 100; then
        echo -e "${RED}✗ Caddy/TLS returned empty entry JS after ready - check Caddy/nginx proxy${NC}"
        docker logs "$CADDY_CONTAINER" 2>&1 | tail -20
        exit 1
    fi
    echo -e "${GREEN}✓ Caddy serves /build/assets/$_public_js over TLS reliably (5/5)${NC}"
fi

RELAY_SITE_ADDRESS=$(grep -E '^RELAY_SITE_ADDRESS=' "$ENV_FILE" 2>/dev/null | cut -d '=' -f2- | tr -d '"' | tr -d "'" | tr -d '\r' | xargs || true)
if [ -n "$RELAY_SITE_ADDRESS" ]; then
    if ! wait_for_container_running "$EVOLU_RELAY_CONTAINER" 10; then
        echo -e "${RED}✗ evolu-relay is not running (RELAY_SITE_ADDRESS=${RELAY_SITE_ADDRESS})${NC}"
        exit 1
    fi
    _relay_ok=0
    for _i in 1 2 3; do
        _relay_ws=$(curl -s -o /dev/null -w '%{http_code}' --max-time 8 --http1.1 \
            -H "Connection: Upgrade" -H "Upgrade: websocket" \
            -H "Sec-WebSocket-Version: 13" -H "Sec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==" \
            "https://${RELAY_SITE_ADDRESS}/" 2>/dev/null || echo "000")
        if [ "$_relay_ws" = "101" ]; then
            _relay_ok=1
            break
        fi
        sleep 2
    done
    if [ "$_relay_ok" -ne 1 ]; then
        echo -e "${RED}✗ Evolu relay WSS returned HTTP ${_relay_ws} (expected 101)${NC}"
        docker logs "$EVOLU_RELAY_CONTAINER" 2>&1 | tail -20
        docker logs "$CADDY_CONTAINER" 2>&1 | tail -10
        exit 1
    fi
    echo -e "${GREEN}✓ Evolu relay WSS https://${RELAY_SITE_ADDRESS}/ (HTTP 101)${NC}"
fi

echo -e "${GREEN}Deployment completed successfully!${NC}"