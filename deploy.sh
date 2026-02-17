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

# Configuration
ENV_FILE="${ENV_FILE:-.env.production}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
PROJECT_NAME="${PROJECT_NAME:-satflux_prod}"
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
PHP_CONTAINER="${PHP_CONTAINER:-satflux_php_prod}"

# Define the base docker compose command
DC_CMD="docker compose -f $COMPOSE_FILE --env-file $ENV_FILE --project-name $PROJECT_NAME"

echo -e "${GREEN}Starting satflux deployment...${NC}"

# Check if env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}Error: $ENV_FILE not found!${NC}"
    echo "Please create $ENV_FILE and configure it (e.g. copy from .env.production.example)."
    exit 1
fi

# Check if docker-compose.prod.yml exists
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

# Step 2a: Build PHP image (picks up Dockerfile changes like new extensions)
echo -e "${YELLOW}Step 2a: Building PHP image...${NC}"
$DC_CMD build php

# Step 2: Ensuring containers are running
echo -e "${YELLOW}Step 2: Ensuring containers are running ($COMPOSE_FILE)...${NC}"
# Use --force-recreate to avoid "container name already in use" when run from different project context
$DC_CMD up -d --force-recreate 2>/dev/null || {
    echo -e "${YELLOW}Retrying after removing orphaned containers...${NC}"
    if [ "$COMPOSE_FILE" = "docker-compose.standalone.yml" ]; then
        for c in satflux_caddy_standalone satflux_nginx_standalone satflux_php_standalone satflux_reverb_standalone satflux_queue_standalone satflux_scheduler_standalone satflux_postgres_standalone satflux_redis_standalone; do
            docker rm -f "$c" 2>/dev/null || true
        done
    else
        for c in satflux_redis_prod satflux_postgres_prod satflux_php_prod satflux_nginx_prod satflux_reverb_prod; do
            docker rm -f "$c" 2>/dev/null || true
        done
    fi
    $DC_CMD up -d
}

# Wait for services to be healthy
echo -e "${YELLOW}Waiting for services to be healthy...${NC}"
sleep 10

# Wait for PHP container to be ready
echo -e "${YELLOW}Waiting for PHP container to be ready...${NC}"
for i in {1..30}; do
    # Check if container exists and is running
    if docker ps --format "{{.Names}}" | grep -q "^${PHP_CONTAINER}$"; then
        if docker inspect "$PHP_CONTAINER" --format '{{.State.Status}}' | grep -q "running"; then
            echo -e "${GREEN}✓ PHP container is running${NC}"
            break
        fi
    fi
    if [ $i -eq 30 ]; then
        echo -e "${RED}✗ PHP container failed to start!${NC}"
        $DC_CMD ps
        docker logs "$PHP_CONTAINER" 2>&1 | tail -20
        exit 1
    fi
    sleep 1
done

# Step 3: Install/update PHP dependencies
echo -e "${YELLOW}Step 3: Installing/updating PHP dependencies...${NC}"
# Clear package manifest so discovery runs with current vendor only (avoids loading dev-only Collision in prod)
docker exec --user root "$PHP_CONTAINER" rm -f /var/www/bootstrap/cache/packages.php /var/www/bootstrap/cache/services.php 2>/dev/null || true
docker exec --user root "$PHP_CONTAINER" composer install --optimize-autoloader --no-dev --no-interaction

# Step 4: Install/update Node dependencies and build assets
echo -e "${YELLOW}Step 4: Building frontend assets...${NC}"
# Check if node_modules exists, if not install dependencies
if ! docker exec "$PHP_CONTAINER" test -d node_modules; then
    docker exec --user root "$PHP_CONTAINER" npm ci
else
    docker exec --user root "$PHP_CONTAINER" npm install
fi
docker exec --user root "$PHP_CONTAINER" npm run build

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

# Step 7: Restart relevant containers
echo -e "${YELLOW}Step 7: Restarting containers...${NC}"
RESTART_SERVICES="php nginx"
if grep -q "caddy:" "$COMPOSE_FILE"; then
    RESTART_SERVICES="$RESTART_SERVICES caddy"
fi
$DC_CMD restart $RESTART_SERVICES

# Step 8: Verify deployment
echo -e "${YELLOW}Step 8: Verifying deployment...${NC}"
sleep 3

# Check if containers are running
if $DC_CMD ps | grep -q "Up"; then
    echo -e "${GREEN}✓ Containers are running${NC}"
else
    echo -e "${RED}✗ Some containers are not running!${NC}"
    $DC_CMD ps
    exit 1
fi

# Health check
if [ "$COMPOSE_FILE" = "docker-compose.standalone.yml" ] || grep -q "caddy:" "$COMPOSE_FILE"; then
    echo -e "${YELLOW}Checking health via local proxy...${NC}"
    # Add health check logic here if needed
else
    echo -e "${YELLOW}Note: Health check skipped (using external reverse proxy)${NC}"
fi

echo -e "${GREEN}Deployment completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Verify the application is accessible at your domain"
echo "2. Check logs if needed: docker compose -f $COMPOSE_FILE --project-name $PROJECT_NAME logs -f"
echo "3. Monitor application for any issues"