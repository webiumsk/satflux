# backup.config.sh
COMPOSE_FILE="docker-compose.prod.yml"
POSTGRES_CONTAINER="satflux_postgres_prod"
REDIS_CONTAINER="satflux_redis_prod"
BACKUP_DIR="./backups"
RETENTION_DAYS=7
RETENTION_WEEKS=4

# Voliteľné nastavenia
BACKUP_REDIS=false
BACKUP_FILES=true
BACKUP_ENV=true

# Vzdialené úložisko - AWS S3
REMOTE_STORAGE_TYPE="s3"
REMOTE_STORAGE_PATH="s3://satflux.io/backups"