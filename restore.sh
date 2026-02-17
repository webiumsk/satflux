#!/bin/bash

# satflux.io Restore Script
# This script restores backups created by backup.sh

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Load configuration if exists
if [ -f "backup.config.sh" ]; then
    source backup.config.sh
fi

# Configuration
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.standalone.yml}"
POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-satflux_postgres_standalone}"
REDIS_CONTAINER="${REDIS_CONTAINER:-satflux_redis_standalone}"
BACKUP_DIR="${BACKUP_DIR:-./backups}"

# Get database credentials from environment
# ENV_FILE may be set by backup.config.sh; else .env.standalone or .env
if [ -z "$ENV_FILE" ]; then
    if [ -f ".env.standalone" ]; then
        ENV_FILE=".env.standalone"
    elif [ -f ".env" ]; then
        ENV_FILE=".env"
    fi
fi

if [ -n "$ENV_FILE" ]; then
    if grep -q "^DB_DATABASE=" "$ENV_FILE"; then
        DB_DATABASE=$(grep "^DB_DATABASE=" "$ENV_FILE" | cut -d '=' -f2 | tr -d '"' | tr -d "'" | tr -d '\r' | xargs)
    fi
    if grep -q "^DB_USERNAME=" "$ENV_FILE"; then
        DB_USERNAME=$(grep "^DB_USERNAME=" "$ENV_FILE" | cut -d '=' -f2 | tr -d '"' | tr -d "'" | tr -d '\r' | xargs)
    fi
fi

DB_NAME="${DB_DATABASE:-satflux}"
DB_USER="${DB_USERNAME:-satflux}"

# Detect docker compose command
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker-compose"
else
    log_error "Neither 'docker compose' nor 'docker-compose' found!"
    exit 1
fi

# Function to log errors
log_error() {
    echo -e "${RED}ERROR: $1${NC}" >&2
}

# Function to log warnings
log_warning() {
    echo -e "${YELLOW}WARNING: $1${NC}"
}

# Function to log info
log_info() {
    echo -e "${BLUE}INFO: $1${NC}"
}

# Function to prompt for confirmation
confirm() {
    local prompt="$1"
    local response
    read -p "$(echo -e ${YELLOW}"$prompt (y/N): "${NC})" response
    case "$response" in
        [yY][eE][sS]|[yY])
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

# Function to list available backups
list_backups() {
    local backups=()
    local count=0
    
    if [ ! -d "$BACKUP_DIR/metadata" ]; then
        log_error "Backup directory not found: $BACKUP_DIR/metadata"
        return 1
    fi
    
    echo -e "${GREEN}Available backups:${NC}"
    echo ""
    
    # Find all metadata files and sort by date (newest first)
    while IFS= read -r metadata_file; do
        if [ -f "$metadata_file" ]; then
            count=$((count + 1))
            timestamp=$(basename "$metadata_file" | sed 's/satflux.io_backup_\(.*\)\.json/\1/')
            created_at=$(grep -o '"created_at": "[^"]*"' "$metadata_file" | cut -d'"' -f4 || echo "unknown")
            components=$(grep -o '"components": \[[^]]*\]' "$metadata_file" | sed 's/"components": \[\(.*\)\]/\1/' || echo "unknown")
            
            echo -e "${BLUE}[$count]${NC} $timestamp"
            echo "   Created: $created_at"
            echo "   Components: $components"
            echo ""
            
            backups+=("$metadata_file")
        fi
    done < <(find "$BACKUP_DIR/metadata" -name "satflux.io_backup_*.json" -type f | sort -r)
    
    if [ $count -eq 0 ]; then
        log_error "No backups found in $BACKUP_DIR/metadata"
        return 1
    fi
    
    # Return selected backup via global variable
    SELECTED_BACKUP=""
    while true; do
        read -p "$(echo -e ${YELLOW}"Select backup number (1-$count): "${NC})" selection
        if [[ "$selection" =~ ^[0-9]+$ ]] && [ "$selection" -ge 1 ] && [ "$selection" -le $count ]; then
            SELECTED_BACKUP="${backups[$((selection - 1))]}"
            break
        else
            log_error "Invalid selection. Please enter a number between 1 and $count."
        fi
    done
}

# Function to load backup metadata
load_metadata() {
    local metadata_file="$1"
    
    if [ ! -f "$metadata_file" ]; then
        log_error "Metadata file not found: $metadata_file"
        return 1
    fi
    
    # Extract backup prefix
    BACKUP_PREFIX=$(basename "$metadata_file" .json)
    
    # Extract components
    COMPONENTS=$(grep -o '"components": \[[^]]*\]' "$metadata_file" | sed 's/"components": \[\(.*\)\]/\1/' | tr -d '"' | tr ',' ' ')
    
    # Extract file paths
    DB_FILE=$(grep -o '"file": "[^"]*"' "$metadata_file" | head -1 | cut -d'"' -f4)
    FILES_FILE=$(grep -o '"file": "[^"]*"' "$metadata_file" | sed -n '2p' | cut -d'"' -f4 || echo "")
    REDIS_FILE=$(grep -o '"file": "[^"]*"' "$metadata_file" | sed -n '3p' | cut -d'"' -f4 || echo "")
    
    # Check if files exist
    DB_PATH="$BACKUP_DIR/database/$DB_FILE"
    FILES_PATH="$BACKUP_DIR/files/$FILES_FILE"
    REDIS_PATH="$BACKUP_DIR/redis/$REDIS_FILE"
    
    if [ ! -f "$DB_PATH" ]; then
        log_error "Database backup file not found: $DB_PATH"
        return 1
    fi
    
    return 0
}

# Function to verify backup integrity
verify_backup() {
    local metadata_file="$1"
    
    log_info "Verifying backup integrity..."
    
    # Check if jq is available for JSON parsing
    if command -v jq >/dev/null 2>&1; then
        # Verify checksums if jq is available
        if [ -f "$DB_PATH" ]; then
            expected_checksum=$(jq -r '.database.checksum' "$metadata_file" 2>/dev/null || echo "")
            if [ -n "$expected_checksum" ] && [ "$expected_checksum" != "null" ]; then
                if command -v sha256sum >/dev/null 2>&1; then
                    actual_checksum=$(sha256sum "$DB_PATH" | cut -d' ' -f1)
                elif command -v shasum >/dev/null 2>&1; then
                    actual_checksum=$(shasum -a 256 "$DB_PATH" | cut -d' ' -f1)
                else
                    actual_checksum=""
                fi
                
                if [ -n "$actual_checksum" ] && [ "$actual_checksum" != "$expected_checksum" ]; then
                    log_error "Database backup checksum mismatch!"
                    return 1
                fi
            fi
        fi
    fi
    
    # Verify gzip files
    if [ -f "$DB_PATH" ] && [[ "$DB_PATH" == *.gz ]]; then
        if ! gzip -t "$DB_PATH" 2>/dev/null; then
            log_error "Database backup file is corrupted!"
            return 1
        fi
    fi
    
    if [ -n "$FILES_PATH" ] && [ -f "$FILES_PATH" ] && [[ "$FILES_PATH" == *.gz ]]; then
        if ! gzip -t "$FILES_PATH" 2>/dev/null; then
            log_error "Files backup is corrupted!"
            return 1
        fi
    fi
    
    if [ -n "$REDIS_PATH" ] && [ -f "$REDIS_PATH" ] && [[ "$REDIS_PATH" == *.gz ]]; then
        if ! gzip -t "$REDIS_PATH" 2>/dev/null; then
            log_error "Redis backup is corrupted!"
            return 1
        fi
    fi
    
    echo -e "${GREEN}✓ Backup integrity verified${NC}"
    return 0
}

# Function to restore database
restore_database() {
    log_info "Restoring database..."
    
    if ! docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^${POSTGRES_CONTAINER}$"; then
        log_error "PostgreSQL container is not running!"
        return 1
    fi
    
    # Ask for confirmation
    if ! confirm "This will REPLACE the current database. Continue?"; then
        log_info "Database restore cancelled"
        return 1
    fi
    
    # Restore database
    echo -e "${YELLOW}Restoring database from $DB_FILE...${NC}"
    gunzip -c "$DB_PATH" | docker exec -i "$POSTGRES_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✓ Database restored successfully${NC}"
        return 0
    else
        log_error "Database restore failed!"
        return 1
    fi
}

# Function to restore files
restore_files() {
    if [ -z "$FILES_PATH" ] || [ ! -f "$FILES_PATH" ]; then
        log_info "No files backup to restore"
        return 0
    fi
    
    log_info "Restoring files..."
    
    # Ask for confirmation
    if ! confirm "This will REPLACE existing files in storage/app. Continue?"; then
        log_info "Files restore cancelled"
        return 1
    fi
    
    # Create temporary directory
    TMP_DIR=$(mktemp -d)
    trap "rm -rf $TMP_DIR" EXIT
    
    # Extract files
    echo -e "${YELLOW}Extracting files from $FILES_FILE...${NC}"
    tar -xzf "$FILES_PATH" -C "$TMP_DIR"
    
    # Restore storage files
    if [ -d "$TMP_DIR/storage/app/exports" ]; then
        mkdir -p storage/app/exports
        cp -r "$TMP_DIR/storage/app/exports/"* storage/app/exports/ 2>/dev/null || true
        echo -e "${GREEN}✓ Restored storage/app/exports${NC}"
    fi
    
    if [ -d "$TMP_DIR/storage/app/public" ]; then
        mkdir -p storage/app/public
        cp -r "$TMP_DIR/storage/app/public/"* storage/app/public/ 2>/dev/null || true
        echo -e "${GREEN}✓ Restored storage/app/public${NC}"
    fi
    
    # Restore env files (with confirmation)
    if [ -d "$TMP_DIR/env" ]; then
        if confirm "Restore environment files (${ENV_FILE:-.env.standalone})?"; then
            if [ -n "$ENV_FILE" ] && [ -f "$TMP_DIR/env/$ENV_FILE" ]; then
                cp "$TMP_DIR/env/$ENV_FILE" "${ENV_FILE}.backup.$(date +%Y%m%d_%H%M%S)" 2>/dev/null || true
                cp "$TMP_DIR/env/$ENV_FILE" "$ENV_FILE"
                echo -e "${GREEN}✓ Restored $ENV_FILE (old file backed up)${NC}"
            elif [ -f "$TMP_DIR/env/.env.standalone" ]; then
                cp "$TMP_DIR/env/.env.standalone" .env.standalone.backup.$(date +%Y%m%d_%H%M%S) 2>/dev/null || true
                cp "$TMP_DIR/env/.env.standalone" .env.standalone
                echo -e "${GREEN}✓ Restored .env.standalone (old file backed up)${NC}"
            elif [ -f "$TMP_DIR/env/.env.production" ]; then
                [ -f .env.standalone ] && cp .env.standalone .env.standalone.backup.$(date +%Y%m%d_%H%M%S) 2>/dev/null || true
                cp "$TMP_DIR/env/.env.production" .env.standalone
                echo -e "${GREEN}✓ Restored from .env.production into .env.standalone (old backup)${NC}"
            elif [ -f "$TMP_DIR/env/.env" ]; then
                cp "$TMP_DIR/env/.env" .env.backup.$(date +%Y%m%d_%H%M%S) 2>/dev/null || true
                cp "$TMP_DIR/env/.env" .env
                echo -e "${GREEN}✓ Restored .env (old file backed up)${NC}"
            fi
        fi
    fi
    
    rm -rf "$TMP_DIR"
    trap - EXIT
    
    echo -e "${GREEN}✓ Files restored successfully${NC}"
    return 0
}

# Function to restore Redis
restore_redis() {
    if [ -z "$REDIS_PATH" ] || [ ! -f "$REDIS_PATH" ]; then
        log_info "No Redis backup to restore"
        return 0
    fi
    
    log_info "Restoring Redis..."
    
    # Check if redis container is running
    if ! $DOCKER_COMPOSE_CMD -f "$COMPOSE_FILE" ps 2>/dev/null | grep -q "$REDIS_CONTAINER.*Up"; then
        log_error "Redis container is not running!"
        return 1
    fi
    
    # Ask for confirmation
    if ! confirm "This will REPLACE the current Redis data. Continue?"; then
        log_info "Redis restore cancelled"
        return 1
    fi
    
    # Stop Redis (gracefully)
    echo -e "${YELLOW}Stopping Redis container...${NC}"
    docker stop "$REDIS_CONTAINER" || true
    
    # Extract Redis dump
    TMP_RDB=$(mktemp)
    gunzip -c "$REDIS_PATH" > "$TMP_RDB"
    
    # Copy RDB file to container
    echo -e "${YELLOW}Copying Redis dump to container...${NC}"
    docker cp "$TMP_RDB" "$REDIS_CONTAINER:/data/dump.rdb" || {
        log_error "Failed to copy Redis dump to container"
        rm -f "$TMP_RDB"
        docker start "$REDIS_CONTAINER" || true
        return 1
    }
    
    # Set correct permissions
    docker exec --user root "$REDIS_CONTAINER" chown redis:redis /data/dump.rdb 2>/dev/null || true
    
    # Start Redis
    echo -e "${YELLOW}Starting Redis container...${NC}"
    docker start "$REDIS_CONTAINER"
    
    # Wait for Redis to be ready
    sleep 2
    
    # Cleanup
    rm -f "$TMP_RDB"
    
    echo -e "${GREEN}✓ Redis restored successfully${NC}"
    return 0
}

# Main restore process
main() {
    echo -e "${GREEN}=== satflux.io Restore ===${NC}"
    echo ""
    
    # Check for dry-run mode
    DRY_RUN=false
    if [ "$1" = "--dry-run" ]; then
        DRY_RUN=true
        log_info "DRY-RUN mode: No changes will be made"
    fi
    
    # List and select backup
    if ! list_backups; then
        exit 1
    fi
    
    # Load metadata
    if ! load_metadata "$SELECTED_BACKUP"; then
        exit 1
    fi
    
    echo ""
    echo -e "${GREEN}Selected backup: $BACKUP_PREFIX${NC}"
    echo "Components: $COMPONENTS"
    echo ""
    
    # Verify backup
    if ! verify_backup "$SELECTED_BACKUP"; then
        log_error "Backup verification failed. Aborting."
        exit 1
    fi
    
    if [ "$DRY_RUN" = true ]; then
        echo ""
        log_info "DRY-RUN: Would restore:"
        echo "  - Database: $DB_PATH"
        [ -n "$FILES_PATH" ] && [ -f "$FILES_PATH" ] && echo "  - Files: $FILES_PATH"
        [ -n "$REDIS_PATH" ] && [ -f "$REDIS_PATH" ] && echo "  - Redis: $REDIS_PATH"
        exit 0
    fi
    
    # Confirm restore
    echo ""
    if ! confirm "Proceed with restore?"; then
        log_info "Restore cancelled"
        exit 0
    fi
    
    echo ""
    
    # Restore components
    RESTORE_FAILED=false
    
    # Restore database
    if echo "$COMPONENTS" | grep -q "database"; then
        if ! restore_database; then
            RESTORE_FAILED=true
        fi
    fi
    
    # Restore files
    if echo "$COMPONENTS" | grep -q "files"; then
        if ! restore_files; then
            RESTORE_FAILED=true
        fi
    fi
    
    # Restore Redis
    if echo "$COMPONENTS" | grep -q "redis"; then
        if ! restore_redis; then
            RESTORE_FAILED=true
        fi
    fi
    
    echo ""
    if [ "$RESTORE_FAILED" = true ]; then
        log_error "Some restore operations failed. Please check the output above."
        exit 1
    else
        echo -e "${GREEN}=== Restore completed successfully! ===${NC}"
        echo ""
        log_info "You may need to restart your application containers:"
        echo "  $DOCKER_COMPOSE_CMD -f $COMPOSE_FILE restart"
    fi
}

# Run main function
main "$@"

