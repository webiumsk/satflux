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
PHP_CONTAINER="uzol21_php_prod"

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
docker-compose -f "$COMPOSE_FILE" up -d

# Wait for services to be healthy
echo -e "${YELLOW}Waiting for services to be healthy...${NC}"
sleep 5

# Step 3: Install/update PHP dependencies
echo -e "${YELLOW}Step 3: Installing/updating PHP dependencies...${NC}"
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" composer install --optimize-autoloader --no-dev --no-interaction

# Step 4: Install/update Node dependencies and build assets
echo -e "${YELLOW}Step 4: Building frontend assets...${NC}"
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" npm ci --production
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" npm run build

# Step 5: Run database migrations
echo -e "${YELLOW}Step 5: Running database migrations...${NC}"
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan migrate --force

# Step 6: Clear and optimize Laravel caches
echo -e "${YELLOW}Step 6: Optimizing Laravel...${NC}"
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan config:clear
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan route:clear
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan view:clear
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan cache:clear

# Rebuild caches
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan config:cache
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan route:cache
docker-compose -f "$COMPOSE_FILE" exec -T "$PHP_CONTAINER" php artisan view:cache

# Step 7: Restart PHP and Nginx containers
echo -e "${YELLOW}Step 7: Restarting containers...${NC}"
docker-compose -f "$COMPOSE_FILE" restart php nginx

# Step 8: Verify deployment
echo -e "${YELLOW}Step 8: Verifying deployment...${NC}"
sleep 3

# Check if containers are running
if docker-compose -f "$COMPOSE_FILE" ps | grep -q "Up"; then
    echo -e "${GREEN}✓ Containers are running${NC}"
else
    echo -e "${RED}✗ Some containers are not running!${NC}"
    docker-compose -f "$COMPOSE_FILE" ps
    exit 1
fi

# Health check
if curl -f http://localhost:8080/health > /dev/null 2>&1; then
    echo -e "${GREEN}✓ Health check passed${NC}"
else
    echo -e "${YELLOW}⚠ Health check failed (this might be normal if using reverse proxy)${NC}"
fi

echo -e "${GREEN}Deployment completed successfully!${NC}"
echo ""
echo "Next steps:"
echo "1. Verify the application is accessible at your domain"
echo "2. Check logs if needed: docker-compose -f $COMPOSE_FILE logs -f"
echo "3. Monitor application for any issues"

