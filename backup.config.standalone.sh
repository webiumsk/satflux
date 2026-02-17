# satflux.io Standalone Backup Configuration
# Save this as backup.config.sh on the server to use these settings

COMPOSE_FILE="docker-compose.standalone.yml"
PROJECT_NAME="satflux_standalone"
ENV_FILE=".env.standalone"
POSTGRES_CONTAINER="satflux_postgres_standalone"
REDIS_CONTAINER="satflux_redis_standalone"
BACKUP_DIR="./backups"

# To use this with the backup scripts, you can symlink it:
# ln -sf backup.config.standalone.sh backup.config.sh
