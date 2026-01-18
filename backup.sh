#!/bin/bash

# UZOL21 Database Backup Script
# This script creates automated backups of the PostgreSQL database

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
COMPOSE_FILE="docker-compose.prod.yml"
POSTGRES_CONTAINER="uzol21_postgres_prod"
BACKUP_DIR="./backups"
RETENTION_DAYS=7
RETENTION_WEEKS=4

# Get database credentials from environment
DB_NAME="${DB_DATABASE:-uzol21}"
DB_USER="${DB_USERNAME:-uzol21}"

# Create backup directory if it doesn't exist
mkdir -p "$BACKUP_DIR"

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/uzol21_backup_$TIMESTAMP.sql"
BACKUP_FILE_COMPRESSED="$BACKUP_FILE.gz"

echo -e "${GREEN}Starting database backup...${NC}"

# Check if postgres container is running
if ! docker-compose -f "$COMPOSE_FILE" ps | grep -q "$POSTGRES_CONTAINER.*Up"; then
    echo -e "${RED}Error: PostgreSQL container is not running!${NC}"
    exit 1
fi

# Create backup
echo -e "${YELLOW}Creating database backup...${NC}"
docker-compose -f "$COMPOSE_FILE" exec -T "$POSTGRES_CONTAINER" pg_dump -U "$DB_USER" -d "$DB_NAME" > "$BACKUP_FILE"

# Compress backup
echo -e "${YELLOW}Compressing backup...${NC}"
gzip "$BACKUP_FILE"
BACKUP_FILE="$BACKUP_FILE_COMPRESSED"

# Get backup size
BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

echo -e "${GREEN}✓ Backup created successfully: $BACKUP_FILE ($BACKUP_SIZE)${NC}"

# Cleanup old backups
echo -e "${YELLOW}Cleaning up old backups...${NC}"

# Remove backups older than retention period
find "$BACKUP_DIR" -name "uzol21_backup_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

# Keep only weekly backups for longer retention
# This keeps one backup per week for the retention_weeks period
if [ -d "$BACKUP_DIR" ]; then
    # Find all backups
    BACKUPS=$(find "$BACKUP_DIR" -name "uzol21_backup_*.sql.gz" -type f | sort)
    
    # Group by week and keep only the latest in each week
    CURRENT_WEEK=""
    for backup in $BACKUPS; do
        BACKUP_DATE=$(stat -c %Y "$backup" 2>/dev/null || stat -f %m "$backup" 2>/dev/null)
        BACKUP_WEEK=$(date -d "@$BACKUP_DATE" +%Y-W%V 2>/dev/null || date -r "$BACKUP_DATE" +%Y-W%V 2>/dev/null)
        
        if [ "$BACKUP_WEEK" != "$CURRENT_WEEK" ]; then
            CURRENT_WEEK="$BACKUP_WEEK"
            # Keep this backup
            continue
        else
            # Remove older backup from same week
            rm -f "$backup"
        fi
    done
fi

echo -e "${GREEN}Backup completed successfully!${NC}"
echo ""
echo "Backup location: $BACKUP_FILE"
echo "Backup size: $BACKUP_SIZE"
echo ""
echo "To restore a backup, use:"
echo "  gunzip < $BACKUP_FILE | docker-compose -f $COMPOSE_FILE exec -T $POSTGRES_CONTAINER psql -U $DB_USER -d $DB_NAME"

