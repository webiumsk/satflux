# D21Panel Standalone Deployment Configuration
# Samostatné nasadenie - Caddy namiesto Traefik
#
# Použitie:
#   ln -sf deploy.config.standalone.sh deploy.config.sh
#   ./deploy.sh
#
# Nastav v .env.production:
#   SITE_ADDRESS=satflux.io          # Pre HTTPS s doménou (Let's Encrypt)
#   SITE_ADDRESS=localhost:80        # Pre HTTP only (dev/test)
#   ACME_EMAIL=admin@example.com     # Email pre Let's Encrypt
#   STANDALONE_HTTP_PORT=80          # 80 pre čistý server, 8090 ak 80 obsadený
#   STANDALONE_HTTPS_PORT=443        # 443 pre čistý server, 8443 ak 443 obsadený

COMPOSE_FILE="docker-compose.standalone.yml"
PROJECT_NAME="satflux_standalone"
