# Standalone Deployment Guide

Tento dokument popisuje postup nasadenia aplikácie v "standalone" režime, ktorý nie je závislý na externom Traefiku ani sieťach. Používa lokálny **Caddy** ako reverzný proxy pre automatické SSL.

## Hlavné zmeny oproti štandardnému deploymentu

- Používa sa súbor `docker-compose.standalone.yml`.
- SSL zabezpečuje Caddy (namiesto Traefiku).
- Porty sú konfigurovateľné v `.env.production` (prednastavené na 8080/8443, aby sa nebili s Passboltom).

## Postup nasadenia

### 1. Príprava environment variables

V súbore `.env.production` nastavte nasledujúce premenné:

```env
# Doména pre SSL (Caddy)
APP_DOMAIN=vassa-domena.sk
ACME_EMAIL=vas@email.sk

# Porty (ak máte na 80/443 Passbolt, nechajte tieto alebo zmeňte)
STANDALONE_HTTP_PORT=8080
STANDALONE_HTTPS_PORT=8443
```

### 2. Spustenie aplikácie

Pre prvé spustenie alebo aktualizáciu použite:

```bash
COMPOSE_FILE=docker-compose.standalone.yml ./deploy.sh
```

### 3. Konfigurácia zálohovania

Aby fungovali skripty `backup.sh` a `restore.sh` automaticky so standalone verziou:

```bash
ln -sf backup.config.standalone.sh backup.config.sh
```

## Troubleshooting

### Ako skontrolovať logy?
```bash
COMPOSE_FILE=docker-compose.standalone.yml docker compose logs -f
```

### Konflikt portov
Ak sa Caddy nespustí kvôli konfliktu portov, zmeňte `STANDALONE_HTTP_PORT` alebo `STANDALONE_HTTPS_PORT` v `.env.production` a reštartujte:
```bash
COMPOSE_FILE=docker-compose.standalone.yml docker compose up -d
```

### Prístup cez IP (pre testovanie)
Ak ešte nemáte nasmerovanú doménu, môžete v Caddyfile dočasne zmeniť doménu na `:80` (HTTP) a pristupovať cez IP:Port.
