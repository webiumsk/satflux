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

# Porty
STANDALONE_HTTP_PORT=8090
STANDALONE_HTTPS_PORT=8443

# Automatická voľba standalone režimu pre deploy.sh
COMPOSE_FILE=docker-compose.standalone.yml
```

### 2. Trvalé nastavenie (Sticky configuration)

Ak nechcete upravovať `.env.production`, môžete použiť konfiguračný súbor:

```bash
ln -sf deploy.config.standalone.sh deploy.config.sh
ln -sf backup.config.standalone.sh backup.config.sh
```

Po tomto prelinkovaní bude stačiť spustiť `./deploy.sh` a automaticky sa použije standalone verzia.

### 3. Spustenie aplikácie

Pre spustenie alebo aktualizáciu stačí spustiť:
```bash
./deploy.sh
```
(Ak ste v kroku 2 nastavili `deploy.config.sh`, skript automaticky použije standalone verziu.)

## Testovanie iného branchu na live serveri (napr. Inertia)

Ak chcete na live serveri otestovať konkrétny branch (napr. `Inertia`) namiesto `main`:

1. **Pushni branch na origin** (lokálne):
   ```bash
   git push origin Inertia
   ```

2. **Na live serveri** v `deploy.config.sh` (alebo v súbore, ktorý source-uje deploy.sh) pridaj:
   ```bash
   DEPLOY_BRANCH=Inertia
   ```
   Ak ešte nemáš `deploy.config.sh`, vytvor ho (napr. skopíruj z `deploy.config.standalone.sh`) a doplň tento riadok.

3. **Spusti deploy** na serveri:
   ```bash
   ./deploy.sh
   ```
   Skript stiahne `Inertia` branch, v Dockeri spustí `composer install`, `npm install`, `npm run build`, migrácie a reštart. Aplikácia beží na kóde z branchu `Inertia`.

4. **Návrat na main** po otestovaní:
   - V `deploy.config.sh` odstráň riadok `DEPLOY_BRANCH=Inertia` (alebo zmeň na `DEPLOY_BRANCH=`).
   - Spusti znova `./deploy.sh` – stiahne a nasadí `main` (alebo aktuálny branch podľa pôvodnej logiky).

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
