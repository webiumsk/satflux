#!/bin/bash

# UZOL21 Comprehensive Backup Script
# This script creates automated backups of PostgreSQL database, files, and optionally Redis

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

# Configuration - auto-detect which compose file is being used
if [ -z "$COMPOSE_FILE" ]; then
    # Check which containers are actually running (use docker ps for accurate detection)
    # First check for prod containers (priority in production)
    if docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^uzol21_postgres_prod$"; then
        # Prod environment is running
        COMPOSE_FILE="docker-compose.prod.yml"
        POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-uzol21_postgres_prod}"
        REDIS_CONTAINER="${REDIS_CONTAINER:-uzol21_redis_prod}"
    elif docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^uzol21_postgres$"; then
        # Dev environment (docker-compose.yml) is running
        COMPOSE_FILE="docker-compose.yml"
        POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-uzol21_postgres}"
        REDIS_CONTAINER="${REDIS_CONTAINER:-uzol21_redis}"
    else
        # Default to prod
        COMPOSE_FILE="docker-compose.prod.yml"
        POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-uzol21_postgres_prod}"
        REDIS_CONTAINER="${REDIS_CONTAINER:-uzol21_redis_prod}"
    fi
else
    # COMPOSE_FILE is set, use defaults for container names
    POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-uzol21_postgres_prod}"
    REDIS_CONTAINER="${REDIS_CONTAINER:-uzol21_redis_prod}"
    
    # If using docker-compose.yml, adjust container names
    if [ "$COMPOSE_FILE" = "docker-compose.yml" ]; then
        POSTGRES_CONTAINER="${POSTGRES_CONTAINER:-uzol21_postgres}"
        REDIS_CONTAINER="${REDIS_CONTAINER:-uzol21_redis}"
    fi
fi
BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${RETENTION_DAYS:-7}"
RETENTION_WEEKS="${RETENTION_WEEKS:-4}"

# Backup options (can be overridden by environment or config)
BACKUP_REDIS="${BACKUP_REDIS:-false}"
BACKUP_FILES="${BACKUP_FILES:-true}"
BACKUP_ENV="${BACKUP_ENV:-true}"

# Get database credentials from environment
# Try to load from .env file if exists
if [ -f ".env" ]; then
    # Simple approach: read values directly
    if grep -q "^DB_DATABASE=" .env; then
        DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    fi
    if grep -q "^DB_USERNAME=" .env; then
        DB_USERNAME=$(grep "^DB_USERNAME=" .env | cut -d '=' -f2 | tr -d '"' | tr -d "'" | xargs)
    fi
fi

DB_NAME="${DB_DATABASE:-uzol21}"
DB_USER="${DB_USERNAME:-uzol21}"

# Create backup directory structure
mkdir -p "$BACKUP_DIR/database"
mkdir -p "$BACKUP_DIR/files"
mkdir -p "$BACKUP_DIR/redis"
mkdir -p "$BACKUP_DIR/metadata"

# Generate backup filename with timestamp
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_PREFIX="uzol21_backup_$TIMESTAMP"

# Initialize metadata
METADATA_FILE="$BACKUP_DIR/metadata/${BACKUP_PREFIX}.json"
COMPONENTS=()

# Function to log errors
log_error() {
    echo -e "${RED}ERROR: $1${NC}" >&2
}

# Function to log info
log_info() {
    echo -e "${BLUE}INFO: $1${NC}"
}

# Function to calculate checksum
calculate_checksum() {
    local file="$1"
    if command -v sha256sum >/dev/null 2>&1; then
        sha256sum "$file" | cut -d' ' -f1
    elif command -v shasum >/dev/null 2>&1; then
        shasum -a 256 "$file" | cut -d' ' -f1
    else
        echo "unknown"
    fi
}

# Function to verify backup file
verify_backup() {
    local file="$1"
    if [ ! -f "$file" ]; then
        log_error "Backup file not found: $file"
        return 1
    fi
    
    if [ ! -s "$file" ]; then
        log_error "Backup file is empty: $file"
        return 1
    fi
    
    # Check if compressed file is valid
    if [[ "$file" == *.gz ]]; then
        if ! gzip -t "$file" 2>/dev/null; then
            log_error "Backup file is corrupted (gzip): $file"
            return 1
        fi
    fi
    
    return 0
}

echo -e "${GREEN}=== Starting UZOL21 Backup ===${NC}"
echo "Timestamp: $TIMESTAMP"
echo "Backup directory: $BACKUP_DIR"
echo ""

# Detect docker compose command (docker compose or docker-compose)
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE_CMD="docker-compose"
else
    log_error "Neither 'docker compose' nor 'docker-compose' found!"
    exit 1
fi

# Check if postgres container is running
# Use docker ps for accurate container name detection (not docker compose ps which shows service names)
if ! docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^${POSTGRES_CONTAINER}$"; then
    # Try alternative container name (dev vs prod)
    if [ "$POSTGRES_CONTAINER" = "uzol21_postgres_prod" ]; then
        POSTGRES_CONTAINER="uzol21_postgres"
        COMPOSE_FILE="docker-compose.yml"
    elif [ "$POSTGRES_CONTAINER" = "uzol21_postgres" ]; then
        POSTGRES_CONTAINER="uzol21_postgres_prod"
        COMPOSE_FILE="docker-compose.prod.yml"
    fi
    
    # Check again with alternative name
    if ! docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^${POSTGRES_CONTAINER}$"; then
        log_error "PostgreSQL container is not running!"
        log_error "Tried: $POSTGRES_CONTAINER"
        log_error "Available containers: $(docker ps --format '{{.Names}}' | grep postgres | tr '\n' ' ')"
        exit 1
    fi
fi

# 1. Database Backup
echo -e "${YELLOW}[1/4] Creating database backup...${NC}"
DB_BACKUP_FILE="$BACKUP_DIR/database/${BACKUP_PREFIX}.sql"
DB_BACKUP_COMPRESSED="${DB_BACKUP_FILE}.gz"

# Use docker exec directly with container name (more reliable when multiple compose files are running)
docker exec "$POSTGRES_CONTAINER" pg_dump -U "$DB_USER" -d "$DB_NAME" > "$DB_BACKUP_FILE"

# Compress database backup
gzip "$DB_BACKUP_FILE"
DB_BACKUP_FILE="$DB_BACKUP_COMPRESSED"

# Verify database backup
if ! verify_backup "$DB_BACKUP_FILE"; then
    log_error "Database backup verification failed!"
    exit 1
fi

DB_SIZE=$(du -h "$DB_BACKUP_FILE" | cut -f1)
DB_CHECKSUM=$(calculate_checksum "$DB_BACKUP_FILE")
COMPONENTS+=("database")

echo -e "${GREEN}✓ Database backup created: $DB_BACKUP_FILE ($DB_SIZE)${NC}"

# 2. Files Backup
if [ "$BACKUP_FILES" = "true" ]; then
    echo -e "${YELLOW}[2/4] Creating files backup...${NC}"
    FILES_BACKUP_FILE="$BACKUP_DIR/files/${BACKUP_PREFIX}.tar.gz"
    
    # Create temporary directory for files to backup
    TMP_DIR=$(mktemp -d)
    trap "rm -rf $TMP_DIR" EXIT
    
    # Backup storage/app/exports if exists
    if [ -d "storage/app/exports" ] && [ "$(ls -A storage/app/exports 2>/dev/null)" ]; then
        mkdir -p "$TMP_DIR/storage/app/exports"
        cp -r storage/app/exports/* "$TMP_DIR/storage/app/exports/" 2>/dev/null || true
    fi
    
    # Backup storage/app/public if exists
    if [ -d "storage/app/public" ] && [ "$(ls -A storage/app/public 2>/dev/null)" ]; then
        mkdir -p "$TMP_DIR/storage/app/public"
        cp -r storage/app/public/* "$TMP_DIR/storage/app/public/" 2>/dev/null || true
    fi
    
    # Backup env files if enabled
    if [ "$BACKUP_ENV" = "true" ]; then
        if [ -f ".env.production" ]; then
            mkdir -p "$TMP_DIR/env"
            cp .env.production "$TMP_DIR/env/.env.production"
        fi
        if [ -f ".env" ] && [ ! -f ".env.production" ]; then
            mkdir -p "$TMP_DIR/env"
            cp .env "$TMP_DIR/env/.env"
        fi
    fi
    
    # Create tar archive
    if [ -d "$TMP_DIR" ] && [ "$(ls -A $TMP_DIR 2>/dev/null)" ]; then
        tar -czf "$FILES_BACKUP_FILE" -C "$TMP_DIR" .
        
        # Verify files backup
        if ! verify_backup "$FILES_BACKUP_FILE"; then
            log_error "Files backup verification failed!"
            exit 1
        fi
        
        FILES_SIZE=$(du -h "$FILES_BACKUP_FILE" | cut -f1)
        FILES_CHECKSUM=$(calculate_checksum "$FILES_BACKUP_FILE")
        COMPONENTS+=("files")
        
        echo -e "${GREEN}✓ Files backup created: $FILES_BACKUP_FILE ($FILES_SIZE)${NC}"
    else
        log_info "No files to backup (directories are empty or don't exist)"
    fi
    
    rm -rf "$TMP_DIR"
    trap - EXIT
else
    log_info "Files backup skipped (BACKUP_FILES=false)"
fi

# 3. Redis Backup (optional)
if [ "$BACKUP_REDIS" = "true" ]; then
    echo -e "${YELLOW}[3/4] Creating Redis backup...${NC}"
    
    # Check if redis container is running
    # Use docker ps for accurate container name detection
    if docker ps --format "{{.Names}}" 2>/dev/null | grep -q "^${REDIS_CONTAINER}$"; then
        REDIS_BACKUP_FILE="$BACKUP_DIR/redis/${BACKUP_PREFIX}.rdb"
        REDIS_BACKUP_COMPRESSED="${REDIS_BACKUP_FILE}.gz"
        
        # Get Redis password from environment if set
        REDIS_PASSWORD="${REDIS_PASSWORD:-}"
        REDIS_AUTH=""
        if [ -n "$REDIS_PASSWORD" ] && [ "$REDIS_PASSWORD" != "null" ]; then
            REDIS_AUTH="-a \"$REDIS_PASSWORD\""
        fi
        
        # Use docker exec directly with container name (more reliable)
        # Create Redis dump using SAVE command
        if [ -n "$REDIS_PASSWORD" ] && [ "$REDIS_PASSWORD" != "null" ]; then
            docker exec "$REDIS_CONTAINER" redis-cli -a "$REDIS_PASSWORD" SAVE > /dev/null 2>&1 || {
                log_error "Failed to create Redis dump"
                exit 1
            }
        else
            docker exec "$REDIS_CONTAINER" redis-cli SAVE > /dev/null 2>&1 || {
                log_error "Failed to create Redis dump"
                exit 1
            }
        fi
        
        # Copy RDB file from container (use docker cp directly)
        # Redis stores RDB in /data/dump.rdb by default
        docker cp "$REDIS_CONTAINER:/data/dump.rdb" "$REDIS_BACKUP_FILE" 2>/dev/null || {
            log_error "Failed to copy Redis dump file"
            exit 1
        }
        
        # Compress Redis backup
        if [ -f "$REDIS_BACKUP_FILE" ] && [ -s "$REDIS_BACKUP_FILE" ]; then
            gzip "$REDIS_BACKUP_FILE"
            REDIS_BACKUP_FILE="$REDIS_BACKUP_COMPRESSED"
            
            # Verify Redis backup
            if ! verify_backup "$REDIS_BACKUP_FILE"; then
                log_error "Redis backup verification failed!"
                exit 1
            fi
            
            REDIS_SIZE=$(du -h "$REDIS_BACKUP_FILE" | cut -f1)
            REDIS_CHECKSUM=$(calculate_checksum "$REDIS_BACKUP_FILE")
            COMPONENTS+=("redis")
            
            echo -e "${GREEN}✓ Redis backup created: $REDIS_BACKUP_FILE ($REDIS_SIZE)${NC}"
        else
            log_error "Redis dump file is empty or not found"
            exit 1
        fi
    else
        log_error "Redis container is not running!"
        exit 1
    fi
else
    log_info "Redis backup skipped (BACKUP_REDIS=false)"
fi

# 4. Create metadata file
echo -e "${YELLOW}[4/4] Creating metadata...${NC}"

# Calculate total size
TOTAL_SIZE=0
if [ -f "$DB_BACKUP_FILE" ]; then
    TOTAL_SIZE=$((TOTAL_SIZE + $(stat -f%z "$DB_BACKUP_FILE" 2>/dev/null || stat -c%s "$DB_BACKUP_FILE" 2>/dev/null || echo 0)))
fi
if [ -f "$FILES_BACKUP_FILE" ]; then
    TOTAL_SIZE=$((TOTAL_SIZE + $(stat -f%z "$FILES_BACKUP_FILE" 2>/dev/null || stat -c%s "$FILES_BACKUP_FILE" 2>/dev/null || echo 0)))
fi
if [ -f "$REDIS_BACKUP_FILE" ]; then
    TOTAL_SIZE=$((TOTAL_SIZE + $(stat -f%z "$REDIS_BACKUP_FILE" 2>/dev/null || stat -c%s "$REDIS_BACKUP_FILE" 2>/dev/null || echo 0)))
fi

# Create metadata JSON
# Build components array without jq (bash alternative)
COMPONENTS_JSON="["
for i in "${!COMPONENTS[@]}"; do
    if [ $i -gt 0 ]; then
        COMPONENTS_JSON+=", "
    fi
    COMPONENTS_JSON+="\"${COMPONENTS[$i]}\""
done
COMPONENTS_JSON+="]"

cat > "$METADATA_FILE" <<EOF
{
  "timestamp": "$TIMESTAMP",
  "backup_prefix": "$BACKUP_PREFIX",
  "created_at": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "components": $COMPONENTS_JSON,
  "database": {
    "file": "$(basename $DB_BACKUP_FILE)",
    "size": "$DB_SIZE",
    "size_bytes": $(stat -f%z "$DB_BACKUP_FILE" 2>/dev/null || stat -c%s "$DB_BACKUP_FILE" 2>/dev/null || echo 0),
    "checksum": "$DB_CHECKSUM"
  },
  "files": $([ -f "$FILES_BACKUP_FILE" ] && echo "{
    \"file\": \"$(basename $FILES_BACKUP_FILE)\",
    \"size\": \"$FILES_SIZE\",
    \"size_bytes\": $(stat -f%z "$FILES_BACKUP_FILE" 2>/dev/null || stat -c%s "$FILES_BACKUP_FILE" 2>/dev/null || echo 0),
    \"checksum\": \"$FILES_CHECKSUM\"
  }" || echo "null"),
  "redis": $([ -f "$REDIS_BACKUP_FILE" ] && echo "{
    \"file\": \"$(basename $REDIS_BACKUP_FILE)\",
    \"size\": \"$REDIS_SIZE\",
    \"size_bytes\": $(stat -f%z "$REDIS_BACKUP_FILE" 2>/dev/null || stat -c%s "$REDIS_BACKUP_FILE" 2>/dev/null || echo 0),
    \"checksum\": \"$REDIS_CHECKSUM\"
  }" || echo "null"),
  "total_size_bytes": $TOTAL_SIZE,
  "version": "2.0"
}
EOF

# Set restrictive permissions on backup files
chmod 600 "$DB_BACKUP_FILE" 2>/dev/null || true
[ -f "$FILES_BACKUP_FILE" ] && chmod 600 "$FILES_BACKUP_FILE" 2>/dev/null || true
[ -f "$REDIS_BACKUP_FILE" ] && chmod 600 "$REDIS_BACKUP_FILE" 2>/dev/null || true
chmod 600 "$METADATA_FILE" 2>/dev/null || true

echo -e "${GREEN}✓ Metadata created: $METADATA_FILE${NC}"

# Optional: Remote storage upload
if [ -n "$REMOTE_STORAGE_TYPE" ]; then
    echo -e "${YELLOW}Uploading to remote storage ($REMOTE_STORAGE_TYPE)...${NC}"
    
    case "$REMOTE_STORAGE_TYPE" in
        s3)
            if command -v aws >/dev/null 2>&1; then
                # Verify AWS credentials are available
                if ! aws sts get-caller-identity >/dev/null 2>&1; then
                    log_error "AWS credentials not configured or invalid"
                    log_error "Run 'aws configure' or set AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY"
                    log_warning "Skipping S3 upload. Local backup completed successfully."
                else
                    # Remove s3:// prefix if present and extract bucket and path
                    S3_PATH="${REMOTE_STORAGE_PATH#s3://}"
                    S3_BUCKET=$(echo "$S3_PATH" | cut -d'/' -f1)
                    S3_PREFIX=$(echo "$S3_PATH" | cut -d'/' -f2-)
                
                # Build full S3 paths (only for files that exist)
                if [ -n "$S3_PREFIX" ]; then
                    S3_DB_PATH="s3://${S3_BUCKET}/${S3_PREFIX}/database/$(basename "$DB_BACKUP_FILE")"
                    [ -f "$FILES_BACKUP_FILE" ] && S3_FILES_PATH="s3://${S3_BUCKET}/${S3_PREFIX}/files/$(basename "$FILES_BACKUP_FILE")"
                    [ -f "$REDIS_BACKUP_FILE" ] && S3_REDIS_PATH="s3://${S3_BUCKET}/${S3_PREFIX}/redis/$(basename "$REDIS_BACKUP_FILE")"
                    S3_META_PATH="s3://${S3_BUCKET}/${S3_PREFIX}/metadata/$(basename "$METADATA_FILE")"
                else
                    S3_DB_PATH="s3://${S3_BUCKET}/database/$(basename "$DB_BACKUP_FILE")"
                    [ -f "$FILES_BACKUP_FILE" ] && S3_FILES_PATH="s3://${S3_BUCKET}/files/$(basename "$FILES_BACKUP_FILE")"
                    [ -f "$REDIS_BACKUP_FILE" ] && S3_REDIS_PATH="s3://${S3_BUCKET}/redis/$(basename "$REDIS_BACKUP_FILE")"
                    S3_META_PATH="s3://${S3_BUCKET}/metadata/$(basename "$METADATA_FILE")"
                fi
                
                # Track upload success
                UPLOAD_SUCCESS=true
                
                aws s3 cp "$DB_BACKUP_FILE" "$S3_DB_PATH" || {
                    log_error "S3 upload failed for database"
                    UPLOAD_SUCCESS=false
                }
                
                if [ -f "$FILES_BACKUP_FILE" ] && [ -n "$S3_FILES_PATH" ]; then
                    aws s3 cp "$FILES_BACKUP_FILE" "$S3_FILES_PATH" || {
                        log_error "S3 upload failed for files"
                        UPLOAD_SUCCESS=false
                    }
                fi
                
                if [ -f "$REDIS_BACKUP_FILE" ] && [ -n "$S3_REDIS_PATH" ]; then
                    aws s3 cp "$REDIS_BACKUP_FILE" "$S3_REDIS_PATH" || {
                        log_error "S3 upload failed for redis"
                        UPLOAD_SUCCESS=false
                    }
                fi
                
                aws s3 cp "$METADATA_FILE" "$S3_META_PATH" || {
                    log_error "S3 upload failed for metadata"
                    UPLOAD_SUCCESS=false
                }
                
                    if [ "$UPLOAD_SUCCESS" = true ]; then
                        echo -e "${GREEN}✓ Remote upload completed${NC}"
                    else
                        log_warning "Some S3 uploads failed. Check AWS credentials and permissions."
                    fi
                fi
            else
                log_error "AWS CLI not found. Install it to use S3 storage."
            fi
            ;;
        ftp|sftp)
            log_info "FTP/SFTP upload not yet implemented"
            ;;
        *)
            log_error "Unknown remote storage type: $REMOTE_STORAGE_TYPE"
            ;;
    esac
fi

# Cleanup old backups
echo -e "${YELLOW}Cleaning up old backups...${NC}"

# Remove backups older than retention period
find "$BACKUP_DIR" -name "${BACKUP_PREFIX%_*}_*.sql.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "${BACKUP_PREFIX%_*}_*.tar.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR" -name "${BACKUP_PREFIX%_*}_*.rdb.gz" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true
find "$BACKUP_DIR/metadata" -name "${BACKUP_PREFIX%_*}_*.json" -type f -mtime +$RETENTION_DAYS -delete 2>/dev/null || true

# Keep only weekly backups for longer retention
if [ -d "$BACKUP_DIR" ]; then
    # Find all backup prefixes
    BACKUP_PREFIXES=$(find "$BACKUP_DIR/metadata" -name "uzol21_backup_*.json" -type f -exec basename {} \; | sed 's/uzol21_backup_\([0-9]\{8\}\).*/\1/' | sort -u)
    
    # Group by week and keep only the latest in each week
    CURRENT_WEEK=""
    for prefix in $BACKUP_PREFIXES; do
        # Get the first backup file with this date prefix
        BACKUP_DATE=$(find "$BACKUP_DIR/metadata" -name "uzol21_backup_${prefix}_*.json" -type f | head -1)
        if [ -n "$BACKUP_DATE" ]; then
            BACKUP_DATE=$(stat -c %Y "$BACKUP_DATE" 2>/dev/null || stat -f %m "$BACKUP_DATE" 2>/dev/null)
            BACKUP_WEEK=$(date -d "@$BACKUP_DATE" +%Y-W%V 2>/dev/null || date -r "$BACKUP_DATE" +%Y-W%V 2>/dev/null)
            
            if [ "$BACKUP_WEEK" != "$CURRENT_WEEK" ]; then
                CURRENT_WEEK="$BACKUP_WEEK"
                # Keep this backup - find all files with this prefix and keep the latest
                LATEST=$(find "$BACKUP_DIR" -name "uzol21_backup_${prefix}_*" -type f | sort | tail -1)
                if [ -n "$LATEST" ]; then
                    LATEST_PREFIX=$(basename "$LATEST" | sed 's/\(uzol21_backup_[0-9]\{8\}_[0-9]\{6\}\).*/\1/')
                    # Keep all files with this prefix
                    continue
                fi
            else
                # Remove older backups from same week (keep only latest)
                find "$BACKUP_DIR" -name "uzol21_backup_${prefix}_*" -type f -delete 2>/dev/null || true
            fi
        fi
    done
fi

echo ""
echo -e "${GREEN}=== Backup completed successfully! ===${NC}"
echo ""
echo "Backup components: ${COMPONENTS[*]}"
echo "Database: $DB_BACKUP_FILE ($DB_SIZE)"
[ -f "$FILES_BACKUP_FILE" ] && echo "Files: $FILES_BACKUP_FILE ($FILES_SIZE)"
[ -f "$REDIS_BACKUP_FILE" ] && echo "Redis: $REDIS_BACKUP_FILE ($REDIS_SIZE)"
echo "Metadata: $METADATA_FILE"
echo ""
echo "To restore a backup, use:"
echo "  ./restore.sh"
