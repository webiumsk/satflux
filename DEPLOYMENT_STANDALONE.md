# Standalone Deployment - D21Panel

Samostatné nasadenie bez závislosti na Traefik. Caddy slúži ako reverse proxy s automatickým HTTPS cez Let's Encrypt.

## Rozdiel oproti produkčnému nasadeniu

| Produkcia (docker-compose.prod.yml) | Standalone (docker-compose.standalone.yml) |
|-------------------------------------|-------------------------------------------|
| Nginx + Traefik (externý)           | Nginx + Caddy (všetko v jednom stacku)    |
| Porty: interné, Traefik na 80/443   | Porty: 80/443 alebo 8090/8443             |
| Sieť: passbolt_default (Traefik)    | Sieť: izolovaný satflux bridge            |

## Rýchly štart (nový server)

1. **Klónuj repozitár** na cieľový server
2. **Skopíruj .env** – z `.env.example` vytvor `.env.production`
3. **Vyplň premenné** v `.env.production`:
   ```bash
   # Pre produkciu s doménou
   SITE_ADDRESS=satflux.io
   ACME_EMAIL=admin@satflux.io
   
   # Porty – na čistom serveri použite 80/443
   STANDALONE_HTTP_PORT=80
   STANDALONE_HTTPS_PORT=443
   
   # DB, Redis, APP_URL, atď.
   POSTGRES_PASSWORD=<silné_heslo>
   DB_PASSWORD=<silné_heslo>
   APP_URL=https://satflux.io
   ```
4. **Nastav DNS** – A záznam domény smeruje na IP servera
5. **Spusti nasadenie:**
   ```bash
   ln -sf deploy.config.standalone.sh deploy.config.sh
   ./deploy.sh
   ```

## Režimy nasadenia

### 1. Produkcia s HTTPS (doména)

- `SITE_ADDRESS=satflux.io` – Caddy získava Let's Encrypt certifikát
- `STANDALONE_HTTP_PORT=80`, `STANDALONE_HTTPS_PORT=443`
- Port 80 musí byť prístupný z internetu (ACME HTTP challenge)

### 2. Dev / test (bez SSL)

- `SITE_ADDRESS=localhost:80` – len HTTP
- `STANDALONE_HTTP_PORT=8090`, `STANDALONE_HTTPS_PORT=8443`
- Aplikácia: `http://localhost:8090`

### 3. Server s už bežiacim Traefik (porty 80/443 obsadené)

- `SITE_ADDRESS=satflux.io`
- `STANDALONE_HTTP_PORT=8090`, `STANDALONE_HTTPS_PORT=8443`
- Aplikácia: `https://satflux.io:8443`
- **Let's Encrypt:** Traefik už drží 80/443, ACME challenge cez Caddy nebude fungovať na štandardnom porte. Možnosti:
  - DNS challenge (Caddy pluginy pre Cloudflare, …)
  - Nasadiť standalone na iný server
  - Postupne presunúť routing z Traefiku na Caddy

## deploy.config.standalone.sh

```bash
COMPOSE_FILE="docker-compose.standalone.yml"
PROJECT_NAME="satflux_standalone"

# Voliteľne pre .env.production:
# SITE_ADDRESS=satflux.io
# ACME_EMAIL=admin@example.com
# STANDALONE_HTTP_PORT=80
# STANDALONE_HTTPS_PORT=443
```

Symlink pre používanie s deploy scriptom:

```bash
ln -sf deploy.config.standalone.sh deploy.config.sh
```

## Štruktúra kontajnerov

| Kontajner                     | Služba   | Popis                          |
|------------------------------|----------|--------------------------------|
| satflux_caddy_standalone     | Caddy    | Reverse proxy, SSL             |
| satflux_nginx_standalone     | Nginx    | PHP-FPM, statické súbory       |
| satflux_php_standalone       | PHP-FPM  | Laravel aplikácia              |
| satflux_postgres_standalone  | Postgres | Databáza                       |
| satflux_redis_standalone     | Redis    | Cache, sessions, queue         |

Názvy sú odlíšené od produkčného stacku (`satflux_*_prod`), takže môžu bežať súčasne na jednom serveri (s rôznymi portami).

## Riešenie problémov

### Caddy neštartuje

- Skontroluj `docker compose logs caddy`
- Over, či je `SITE_ADDRESS` správne (doména alebo `localhost:80`)

### Let's Encrypt certifikát sa nepodarí získať

- Port 80 musí byť prístupný z internetu
- Doména musí smerovať na IP servera (platné A záznamy)
- Skontroluj firewall (ufw, iptables)

### Konflikt portov

- Ak beží Traefik alebo iný reverse proxy na 80/443, nastav:
  - `STANDALONE_HTTP_PORT=8090`
  - `STANDALONE_HTTPS_PORT=8443`
- Potom aplikácia bude na `https://tvojadomena:8443`

### Kontajner satflux_nginx_prod už existuje

- Standalone používa `satflux_nginx_standalone`
- Ak stále vidíš konflikt, zastav produkčný stack:
  ```bash
  docker compose -f docker-compose.prod.yml down
  ```
