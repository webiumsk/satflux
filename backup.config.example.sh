# Copy to backup.config.sh and adjust (backup.config.sh is gitignored).
# backup.sh and restore.sh source backup.config.sh when present.

COMPOSE_FILE="docker-compose.standalone.yml"
ENV_FILE=".env.standalone"
POSTGRES_CONTAINER="satflux_postgres_standalone"
REDIS_CONTAINER="satflux_redis_standalone"
BACKUP_DIR="./backups"
RETENTION_DAYS=7
RETENTION_WEEKS=4

BACKUP_REDIS=false
BACKUP_FILES=true
BACKUP_ENV=true

# Optional: upload completed backups (requires AWS CLI credentials in the environment)
# Leave empty to skip remote upload.
REMOTE_STORAGE_TYPE=""
# REMOTE_STORAGE_TYPE="s3"
# REMOTE_STORAGE_PATH="s3://your-bucket-name/backups"
