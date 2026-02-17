# Nasadenie D21Panel (standalone)

Caddy ako reverse proxy s automatickým HTTPS (Let's Encrypt). Všetko v jednom Docker stacku.

## Požiadavky

- VPS s Dockerom a Docker Compose
- Git prístup k repozitáru (pre private repo: [DEPLOYMENT_SSH.md](DEPLOYMENT_SSH.md))
- Doména s A záznamom na IP servera (pre HTTPS)

## Rýchly štart

1. **Klonovanie** (napr. do `/opt/satflux`):
   ```bash
   git clone <repo-url> /opt/satflux
   cd /opt/satflux
   ```

2. **Env súbor** – z `.env.example` vytvor `.env.standalone` a vyplň:
   - `APP_KEY`, `APP_URL`
   - `POSTGRES_PASSWORD`, `DB_PASSWORD` (rovnaké)
   - `SITE_ADDRESS=satflux.io`, `ACME_EMAIL=admin@example.com`
   - `STANDALONE_HTTP_PORT=80`, `STANDALONE_HTTPS_PORT=443`
   - Redis, Reverb, mail podľa potreby

3. **Deploy:**
   ```bash
   ./deploy.sh
   ```

Skript používa `deploy.config.sh` a `.env.standalone` (default v `deploy.sh`). Manuálne príkazy vždy s env súborom:

```bash
docker compose -f docker-compose.standalone.yml --env-file .env.standalone --project-name satflux_standalone up -d
docker compose -f docker-compose.standalone.yml --env-file .env.standalone --project-name satflux_standalone logs -f
```

## Režimy

- **HTTPS (doména):** `SITE_ADDRESS=satflux.io`, porty 80/443. Port 80 musí byť z internetu prístupný (ACME).
- **Len HTTP (dev):** `SITE_ADDRESS=localhost:80`, napr. `STANDALONE_HTTP_PORT=8090`.
- **Iný reverse proxy na 80/443:** `STANDALONE_HTTP_PORT=8090`, `STANDALONE_HTTPS_PORT=8443`; Let's Encrypt cez Caddy potom môže byť problematický (ACME potrebuje 80).

## Štruktúra kontajnerov

| Kontajner                    | Služba   |
|-----------------------------|----------|
| satflux_caddy_standalone    | Caddy (SSL, reverse proxy) |
| satflux_nginx_standalone    | Nginx (PHP-FPM, statické súbory) |
| satflux_php_standalone      | PHP-FPM (Laravel) |
| satflux_reverb_standalone   | Laravel Reverb (WebSockets) |
| satflux_queue_standalone    | Queue worker |
| satflux_scheduler_standalone| Scheduler |
| satflux_postgres_standalone | PostgreSQL |
| satflux_redis_standalone    | Redis |

## Záloha a obnova

- **Záloha:** `./backup.sh` (používa `backup.config.sh`, voliteľne upload do S3).
- **Obnova:** `./restore.sh`, zvoľ zálohu z `backups/metadata/`.

## Riešenie problémov

- **Let's Encrypt neprebehne:** Port 80 prístupný z internetu, platný A záznam, firewall (ufw) povolí 80/443.
- **WARN premenné nie sú set:** Pri manuálnom `docker compose` vždy pridaj `--env-file .env.standalone`.
