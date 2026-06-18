# satflux.io - deployment configuration
# Caddy + docker-compose.standalone.yml
#
# Set in .env.standalone:
#   SITE_ADDRESS=satflux.io          # For HTTPS (Let's Encrypt)
#   RELAY_SITE_ADDRESS=relay.satflux.io  # Evolu E2EE relay (DNS A record, optional)
#   SITE_ADDRESS=localhost:80        # For HTTP only (dev/test)
#   ACME_EMAIL=admin@example.com
#   STANDALONE_HTTP_PORT=80
#   STANDALONE_HTTPS_PORT=443

COMPOSE_FILE="docker-compose.standalone.yml"
PROJECT_NAME="satflux_standalone"
ENV_FILE=".env.standalone"
PHP_CONTAINER="satflux_php_standalone"
NGINX_CONTAINER="satflux_nginx_standalone"
CADDY_CONTAINER="satflux_caddy_standalone"
EVOLU_RELAY_CONTAINER="satflux_evolu_relay_standalone"

# Fixed container_name values from docker-compose.standalone.yml (must match compose file).
STANDALONE_CONTAINER_NAMES=(
    satflux_caddy_standalone
    satflux_nginx_standalone
    satflux_php_standalone
    satflux_reverb_standalone
    satflux_queue_standalone
    satflux_scheduler_standalone
    satflux_postgres_standalone
    satflux_redis_standalone
    satflux_evolu_relay_standalone
)
