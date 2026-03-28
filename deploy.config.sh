# satflux.io — deployment configuration
# Caddy + docker-compose.standalone.yml
#
# Nastav v .env.standalone:
#   SITE_ADDRESS=satflux.io          # Pre HTTPS (Let's Encrypt)
#   SITE_ADDRESS=localhost:80        # Pre HTTP only (dev/test)
#   ACME_EMAIL=admin@example.com
#   STANDALONE_HTTP_PORT=80
#   STANDALONE_HTTPS_PORT=443

COMPOSE_FILE="docker-compose.standalone.yml"
PROJECT_NAME="satflux_standalone"
ENV_FILE=".env.standalone"
PHP_CONTAINER="satflux_php_standalone"
