#!/bin/bash

# UZOL21 Production Deployment Script
# This script automates the deployment process for production updates

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.prod.yml"
PROJECT_NAME="uzol21_prod"
PHP_SERVICE="php"  # Service name in docker-compose.prod.yml
PHP_CONTAINER="uzol21_php_prod"  # Container name (from container_name in docker-compose.prod.yml)

echo -e "${GREEN}Starting UZOL21 deployment...${NC}"

# Check if .env.production exists
if [ ! -f .env.production ]; then
    echo -e "${RED}Error: .env.production file not found!${NC}"
    echo "Please copy .env.production.example to .env.production and configure it."
    exit 1
fi

# Check if docker-compose.prod.yml exists
if [ ! -f "$COMPOSE_FILE" ]; then
    echo -e "${RED}Error: $COMPOSE_FILE not found!${NC}"
    exit 1
fi

# Step 1: Pull latest changes
echo -e "${YELLOW}Step 1: Pulling latest changes from Git...${NC}"
git fetch origin
git pull origin main || git pull origin master

# Step 2: Ensure containers are running
echo -e "${YELLOW}Step 2: Ensuring containers are running...${NC}"
docker compose -f "$COMPOSE_FILE" --project-name "$PROJECT_NAME" up -d

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
        docker compose -f "$COMPOSE_FILE" --project-name "$PROJECT_NAME" ps
        docker logs "$PHP_CONTAINER" 2>&1 | tail -20
        exit 1
    fi
    sleep 1
done

# Step 3: Install/update PHP dependencies
echo -e "${YELLOW}Step 3: Installing/updating PHP dependencies...${NC}"
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

# Step 7: Restart PHP and Nginx containers
echo -e "${YELLOW}Step 7: Restarting containers...${NC}"
docker compose -f "$COMPOSE_FILE" --project-name "$PROJECT_NAME" restart php nginx

# Step 8: Verify deployment
echo -e "${YELLOW}Step 8: Verifying deployment...${NC}"
sleep 3

# Check if containers are running
if docker compose -f "$COMPOSE_FILE" --project-name "$PROJECT_NAME" ps | grep -q "Up"; then
    echo -e "${GREEN}✓ Containers are running${NC}"
else
    echo -e "${RED}✗ Some containers are not running!${NC}"
    docker compose -f "$COMPOSE_FILE" --project-name "$PROJECT_NAME" ps
    exit 1
fi

# Health check (skip if using Traefik, as nginx doesn't expose ports)
echo -e "${YELLOW}Note: Health check skipped (using Traefik reverse proxy)${NC}"

echo -e "${GREEN}Deployment completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Verify the application is accessible at your domain"
echo "2. Check logs if needed: docker compose -f $COMPOSE_FILE --project-name $PROJECT_NAME logs -f"
echo "3. Monitor application for any issues"

