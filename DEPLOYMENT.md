# Production Deployment Guide

Tento dokument popisuje postup nasadenia UZOL21 aplikácie na produkčný server.

## Deployment Možnosti

1. **VPS s Dockerom** (odporúčané pre časté updatovanie) - pozri [VPS Docker Deployment](#vps-docker-deployment)
2. **Zdieľaný hosting** (Hetzner alebo iný) - pozri [Shared Hosting Deployment](#shared-hosting-deployment)

---

## VPS Docker Deployment

Táto metóda je odporúčaná pre VPS servery s Dockerom, kde už bežia iné aplikácie. Umožňuje jednoduché a rýchle updatovanie.

### Požiadavky

- VPS s root prístupom
- Docker a Docker Compose nainštalované
- Git prístup k repository
  - **Pre private repozitáre:** Nastavte SSH Deploy Key - pozri [DEPLOYMENT_SSH.md](DEPLOYMENT_SSH.md)
- Reverse proxy (Nginx alebo Traefik) na hoste pre SSL/TLS (voliteľné)

### Fáza 1: Prvé nasadenie

#### 1.1 Klonovanie repository

```bash
cd /opt  # alebo iný vhodný adresár
git clone <repository-url> uzol21
cd uzol21
```

**Dôležité:** Ak používate Git v kontajneri a nastane problém s ownership, spustite:

```bash
docker compose -f docker-compose.prod.yml exec --user root php git config --global --add safe.directory /var/www
```

#### 1.2 Konfigurácia environment variables

```bash
cp .env.production.example .env.production
nano .env.production  # alebo vim .env.production
```

Upravte všetky potrebné hodnoty v `.env.production`, najmä:

- `APP_KEY` - vygenerujte cez `docker compose -f docker-compose.prod.yml exec --user root php php artisan key:generate` (po prvom spustení kontajnerov)
- `POSTGRES_PASSWORD` - silné heslo pre PostgreSQL databázu (**povinné!**)
  - **Dôležité:** Docker Compose expanduje `${VAR}` z shell prostredia, nie z `env_file`. Preto musíte nastaviť `POSTGRES_PASSWORD` priamo v `.env.production` (nie len `DB_PASSWORD`).
  - Alternatíva: Exportujte v shell pred spustením: `export POSTGRES_PASSWORD="your_password"`
  - Ak nie je nastavené, použije sa fallback `XXXXXXXX` (nie je bezpečné pre produkciu!)
- `DB_PASSWORD` - použite rovnakú hodnotu ako `POSTGRES_PASSWORD` (pre Laravel aplikáciu)
- `BTCPAY_API_KEY` - API kľúč pre BTCPay Server
- `REDIS_PASSWORD` - heslo pre Redis (voliteľné, môže zostať `null`)
- Mail nastavenia

**Príklad v `.env.production`:**

```bash
DB_PASSWORD=TUyRjtv47HIjfeMt      # Pre Laravel aplikáciu
POSTGRES_PASSWORD=TUyRjtv47HIjfeMt # Pre Docker Compose (rovnaká hodnota!)
```

#### 1.3 Prvé spustenie

```bash
# Spustite kontajnery
docker compose -f docker-compose.prod.yml up -d

# Počkajte, kým sa služby spustia (30 sekúnd)
sleep 30

# Inštalácia dependencies (ako root kvôli oprávneniam)
docker compose -f docker-compose.prod.yml exec --user root php composer install --optimize-autoloader --no-dev
docker compose -f docker-compose.prod.yml exec --user root php npm ci
docker compose -f docker-compose.prod.yml exec --user root php npm run build

# Generovanie aplikačného kľúča (ako root)
docker compose -f docker-compose.prod.yml exec --user root php php artisan key:generate

# Spustenie migrations (ako root)
docker compose -f docker-compose.prod.yml exec --user root php php artisan migrate --force

# Optimalizácia Laravel (ako root)
docker compose -f docker-compose.prod.yml exec --user root php php artisan config:cache
docker compose -f docker-compose.prod.yml exec --user root php php artisan route:cache
docker compose -f docker-compose.prod.yml exec --user root php php artisan view:cache

# Oprava oprávnení pre storage a cache (ako root)
docker compose -f docker-compose.prod.yml exec --user root php chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose -f docker-compose.prod.yml exec --user root php chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

#### 1.4 Reverse Proxy konfigurácia (ak používate)

Ak už máte Nginx alebo iný reverse proxy na hoste, pridajte konfiguráciu:

**Nginx na hoste:**

```nginx
server {
    listen 80;
    server_name uzol.dvadsatjeden.org;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name uzol.dvadsatjeden.org;

    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host $host;
        proxy_set_header X-Forwarded-Port $server_port;
    }
}
```

### Fáza 2: Updatovanie aplikácie

Pre časté updatovanie použite automatizovaný deployment script:

```bash
./deploy.sh
```

Script automaticky:

1. Stiahne najnovšie zmeny z Gitu
2. Aktualizuje PHP a Node dependencies (správne oprávnenia)
3. Zostaví frontend assets
4. Spustí databázové migrácie
5. Optimalizuje Laravel cache
6. Nastaví správne oprávnenia pre storage a cache
7. Reštartuje kontajnery

**Poznámka:** Script používa `--user root` pre všetky build príkazy, aby zabezpečil správne oprávnenia súborov.

### Fáza 3: Backupy

#### 3.1 Manuálny backup

**Základný backup (databáza + súbory):**

```bash
./backup.sh
```

**Backup s Redis:**

```bash
BACKUP_REDIS=true ./backup.sh
```

**Backup cez Laravel Artisan:**

```bash
docker compose -f docker-compose.prod.yml exec php php artisan backup:run
```

**Backup s Redis cez Laravel:**

```bash
docker compose -f docker-compose.prod.yml exec php php artisan backup:run --redis
```

#### 3.2 Automatizované backupy

**Možnosť A: Laravel Scheduler (odporúčané)**

Laravel scheduler automaticky spúšťa backupy každý deň o 2:30. Uistite sa, že Laravel scheduler beží:

```bash
# V docker-compose.prod.yml pridajte scheduler service alebo spustite manuálne:
docker compose -f docker-compose.prod.yml exec php php artisan schedule:work
```

**Možnosť B: Cron (alternatíva)**

Pridajte do crontab:

```bash
crontab -e
```

Pridajte riadok pre denné backupy o 2:00:

```
0 2 * * * cd /opt/uzol21 && ./backup.sh >> /var/log/uzol21_backup.log 2>&1
```

#### 3.3 Zoznam a verifikácia záloh

**Zoznam záloh:**

```bash
docker compose -f docker-compose.prod.yml exec php php artisan backup:list
```

**Verifikácia záloh:**

```bash
# Verifikovať najnovšiu zálohu
docker compose -f docker-compose.prod.yml exec php php artisan backup:verify

# Verifikovať konkrétnu zálohu
docker compose -f docker-compose.prod.yml exec php php artisan backup:verify 20240101_020000

# Verifikovať všetky zálohy
docker compose -f docker-compose.prod.yml exec php php artisan backup:verify --all
```

#### 3.4 Obnova zálohy

**Interaktívna obnova:**

```bash
./restore.sh
```

Skript vás prevedie procesom:

1. Zobrazí zoznam dostupných záloh
2. Vyberiete zálohu podľa čísla
3. Overí integritu zálohy
4. Požiada o potvrdenie
5. Obnoví databázu, súbory a voliteľne Redis

**Dry-run (testovanie bez zmien):**

```bash
./restore.sh --dry-run
```

**Po obnove:**

```bash
# Reštartujte kontajnery
docker compose -f docker-compose.prod.yml restart
```

#### 3.5 Konfigurácia zálohovania

Vytvorte voliteľný konfiguračný súbor `backup.config.sh`:

```bash
# backup.config.sh
COMPOSE_FILE="docker-compose.prod.yml"
POSTGRES_CONTAINER="uzol21_postgres_prod"
REDIS_CONTAINER="uzol21_redis_prod"
BACKUP_DIR="./backups"
RETENTION_DAYS=7
RETENTION_WEEKS=4

# Voliteľné nastavenia
BACKUP_REDIS=false  # true pre zálohovanie Redis
BACKUP_FILES=true    # false pre preskočenie súborov
BACKUP_ENV=true      # false pre preskočenie env súborov

# Vzdialené úložisko (voliteľné)
# REMOTE_STORAGE_TYPE="s3"
# REMOTE_STORAGE_PATH="s3://my-bucket/backups"
```

#### 3.6 Štruktúra záloh

Zálohy sú uložené v `./backups/` adresári:

```
backups/
├── database/          # PostgreSQL dumpy (.sql.gz)
├── files/             # Súbory a env (.tar.gz)
├── redis/             # Redis dumpy (.rdb.gz, voliteľné)
└── metadata/           # Metadata JSON súbory
```

Každá záloha obsahuje:

- `database_YYYYMMDD_HHMMSS.sql.gz` - databázový dump
- `files_YYYYMMDD_HHMMSS.tar.gz` - súbory (storage + env)
- `redis_YYYYMMDD_HHMMSS.rdb.gz` - Redis dump (voliteľné)
- `metadata_YYYYMMDD_HHMMSS.json` - informácie o zálohovaní

#### 3.7 Retention policy

- **Denné zálohy**: Uchovávajú sa 7 dní
- **Týždenné zálohy**: Jeden backup za týždeň sa uchováva 4 týždne
- Staršie zálohy sa automaticky odstraňujú

### Fáza 4: Monitoring a logy

#### 4.1 Zobrazenie logov

```bash
# Všetky logy
docker-compose -f docker-compose.prod.yml logs -f

# Len PHP logy
docker-compose -f docker-compose.prod.yml logs -f php

# Laravel logy
docker-compose -f docker-compose.prod.yml exec php tail -f storage/logs/laravel.log
```

#### 4.2 Kontrola stavu kontajnerov

```bash
docker-compose -f docker-compose.prod.yml ps
```

#### 4.3 Health check

```bash
curl http://localhost:8080/health
```

### Výhody VPS Docker deploymentu

- **Jednoduché updatovanie**: `./deploy.sh` alebo `git pull && docker-compose up -d`
- **Izolácia**: Aplikácia beží oddelene od ostatných služieb
- **Konzistentné prostredie**: Rovnaké prostredie ako v development
- **Jednoduchý rollback**: `git checkout <previous-commit> && ./deploy.sh`
- **Mieriteľnosť**: Ľahké pridať ďalšie kontajnery (queue worker, scheduler)

---

## Shared Hosting Deployment

## Požiadavky

### Server požiadavky

- **PHP**: 8.3 alebo vyššia verzia
- **PHP Extensions**: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `json`, `bcmath`, `gd`, `fileinfo`
- **PostgreSQL**: 12 alebo vyššia verzia
- **Composer**: 2.x
- **Node.js**: 18 alebo vyššia verzia (pre build frontend assets)
- **npm**: 9 alebo vyššia verzia
- **Web server**: Nginx alebo Apache s PHP-FPM
- **SSL certifikát**: Let's Encrypt alebo Cloudflare Origin Certificate

### Prístup

- SSH prístup na server
- Prístup k PostgreSQL databáze
- FTP/SFTP alebo Git prístup pre upload súborov
- Root prístup (pre VPS) alebo hosting panel (pre zdieľaný hosting)

## Fáza 1: Príprava na lokálnom stroji

### 1.1 Build frontend assets

Pred nasadením musíte vytvoriť produkčné frontend assets:

```bash
npm install
npm run build
```

Toto vytvorí optimalizované assets v `public/build/` adresári.

### 1.2 Príprava deployment package

**Možnosť A: Git deployment (odporúčané)**

Ak má váš server Git podporu, môžete jednoducho klonovať repository:

```bash
git clone <repository-url> panel
cd panel
```

**Možnosť B: FTP/SFTP upload**

Ak používate FTP/SFTP, nahrajte všetky súbory okrem:

- `node_modules/` (nie je potrebné na serveri, assets sú už built)
- `.env` (vytvoríte na serveri)
- `storage/logs/*` (prázdne adresáre, ale logy nie)
- `.git/` (ak nepoužívate Git na serveri)
- `tests/` (voliteľné)

## Fáza 2: Príprava servera

### 2.1 Vytvorenie PostgreSQL databázy

Vytvorte PostgreSQL databázu cez hosting panel alebo SSH:

```sql
CREATE DATABASE uzol21;
CREATE USER uzol21 WITH PASSWORD 'strong_password_here';
GRANT ALL PRIVILEGES ON DATABASE uzol21 TO uzol21;
```

Poznačte si:

- Host (zvyčajne `localhost` alebo `127.0.0.1`)
- Port (zvyčajne `5432`)
- Databázu názov
- Používateľa
- Heslo

### 2.2 DNS konfigurácia (Cloudflare)

1. Prihláste sa do Cloudflare
2. Vyberte doménu `dvadsatjeden.org`
3. Pridajte A record:
   - **Name**: `panel`
   - **IPv4 address**: IP adresa vášho servera
   - **Proxy status**: Proxied (pomarančová ikona) alebo DNS only (šedá ikona)
4. Počkajte na propagáciu DNS (zvyčajne 1-5 minút)

**SSL/TLS nastavenie**:

- Ak používate Cloudflare Proxy (Proxied): SSL/TLS encryption mode = **Full (strict)**
- Ak používate DNS only: Potrebujete SSL certifikát na serveri (Let's Encrypt)

## Fáza 3: Upload aplikácie

### 3.1 SSH/SFTP pripojenie

Pripojte sa na server:

```bash
ssh username@your-server-ip
# alebo
sftp username@your-server-ip
```

### 3.2 Upload súborov

**Git metóda:**

```bash
cd /path/to/webroot
git clone <repository-url> panel
cd panel
```

**FTP/SFTP metóda:**

Nahrajte všetky súbory do webroot adresára (napr. `/home/username/public_html/panel` alebo `/var/www/panel`).

## Fáza 4: Konfigurácia na serveri

### 4.1 Vytvorenie .env súboru

```bash
cd /path/to/panel
cp .env.production.example .env
nano .env  # alebo vim .env
```

Upravte nasledujúce hodnoty:

```env
APP_NAME="UZOL21"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uzol.dvadsatjeden.org

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=uzol21
DB_USERNAME=uzol21
DB_PASSWORD=your_database_password

SESSION_DRIVER=file
SESSION_DOMAIN=uzol.dvadsatjeden.org
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

CACHE_STORE=file
QUEUE_CONNECTION=sync

BTCPAY_BASE_URL=https://satflux.org
BTCPAY_API_KEY=your_btcpay_api_key
BTCPAY_WEBHOOK_SECRET=your_webhook_secret

LNURL_AUTH_ENABLED=true
LNURL_AUTH_DOMAIN=https://uzol.dvadsatjeden.org

MAIL_MAILER=smtp  # alebo log pre testovanie
MAIL_HOST=your_smtp_host
MAIL_PORT=587
MAIL_USERNAME=your_smtp_username
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@uzol.dvadsatjeden.org"
MAIL_FROM_NAME="UZOL21"
```

### 4.2 Nastavenie oprávnení

```bash
# Nastavte vlastníka (podľa vašej konfigurácie)
sudo chown -R www-data:www-data /path/to/panel
# alebo
sudo chown -R username:username /path/to/panel

# Nastavte oprávnenia pre storage a cache
chmod -R 775 storage bootstrap/cache
```

### 4.3 Inštalácia dependencies

```bash
# PHP dependencies (bez dev dependencies)
composer install --optimize-autoloader --no-dev

# Frontend dependencies (ak ešte nie sú built assets)
npm install
npm run build
```

### 4.4 Generovanie aplikačného kľúča

```bash
php artisan key:generate
```

### 4.5 Spustenie migrations

```bash
php artisan migrate --force
```

### 4.6 Optimalizácia Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Fáza 5: Web server konfigurácia

### 5.1 Nginx konfigurácia

Vytvorte virtual host súbor (napr. `/etc/nginx/sites-available/uzol.dvadsatjeden.org`):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name uzol.dvadsatjeden.org;

    # Redirect HTTP to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name uzol.dvadsatjeden.org;

    root /path/to/panel/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/ssl/cert.pem;
    ssl_certificate_key /path/to/ssl/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;  # Upravte podľa PHP verzie
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size if needed
    client_max_body_size 20M;
}
```

Aktivujte konfiguráciu:

```bash
sudo ln -s /etc/nginx/sites-available/uzol.dvadsatjeden.org /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5.2 Apache konfigurácia

Vytvorte virtual host súbor (napr. `/etc/apache2/sites-available/uzol.dvadsatjeden.org.conf`):

```apache
<VirtualHost *:80>
    ServerName uzol.dvadsatjeden.org
    Redirect permanent / https://uzol.dvadsatjeden.org/
</VirtualHost>

<VirtualHost *:443>
    ServerName uzol.dvadsatjeden.org
    DocumentRoot /path/to/panel/public

    SSLEngine on
    SSLCertificateFile /path/to/ssl/cert.pem
    SSLCertificateKeyFile /path/to/ssl/key.pem

    <Directory /path/to/panel/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/panel-error.log
    CustomLog ${APACHE_LOG_DIR}/panel-access.log combined
</VirtualHost>
```

Aktivujte konfiguráciu:

```bash
sudo a2ensite uzol.dvadsatjeden.org
sudo a2enmod ssl rewrite
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### 5.3 SSL certifikát

**Možnosť A: Let's Encrypt (certbot)**

```bash
sudo apt-get update
sudo apt-get install certbot python3-certbot-nginx
# alebo pre Apache:
sudo apt-get install certbot python3-certbot-apache

# Pre Nginx:
sudo certbot --nginx -d uzol.dvadsatjeden.org

# Pre Apache:
sudo certbot --apache -d uzol.dvadsatjeden.org
```

**Možnosť B: Cloudflare Origin Certificate**

1. Prihláste sa do Cloudflare
2. SSL/TLS → Origin Server → Create Certificate
3. Nastavte doménu: `uzol.dvadsatjeden.org`
4. Stiahnite certifikát a privátny kľúč
5. Uložte ich na server (napr. `/etc/ssl/cloudflare/`)
6. Upravte Nginx/Apache konfiguráciu, aby používali tieto súbory

**Možnosť C: SSL od hostingu**

Niektorí hostitelia poskytujú SSL certifikáty cez hosting panel. Postupujte podľa inštrukcií vášho hostingu.

## Fáza 6: Testovanie

### 6.1 Základné testy

1. **HTTPS prístup**: Otvorte `https://uzol.dvadsatjeden.org` v prehliadači
2. **API health check**: `https://uzol.dvadsatjeden.org/api/health`
3. **Registrácia**: Vyskúšajte vytvorenie nového účtu
4. **Login**: Prihláste sa
5. **LNURL-auth**: Vyskúšajte Lightning autentifikáciu (teraz by mal fungovať s HTTPS)

### 6.2 Kontrola logov

```bash
# Laravel logy
tail -f storage/logs/laravel.log

# Web server logy (Nginx)
sudo tail -f /var/log/nginx/error.log

# Web server logy (Apache)
sudo tail -f /var/log/apache2/error.log
```

### 6.3 Kontrola oprávnení

```bash
# Overte, že storage a cache sú zapisovateľné
ls -la storage/
ls -la bootstrap/cache/
```

## Maintenance

### Cache clearing

Po zmenách v konfigurácii:

```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

A potom znovu cache:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Backup databázy

```bash
pg_dump -h localhost -U uzol21 uzol21 > backup_$(date +%Y%m%d).sql
```

### Aktualizácia aplikácie

```bash
# Git metóda
git pull origin main
composer install --optimize-autoloader --no-dev
php artisan migrate --force
npm install
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

### Časté problémy

**1. 500 Internal Server Error**

- Skontrolujte `storage/logs/laravel.log`
- Overte oprávnenia: `chmod -R 775 storage bootstrap/cache`
- Overte `.env` súbor (správne hodnoty)

**2. Database connection error**

- Overte PostgreSQL je spustený: `sudo systemctl status postgresql`
- Overte database credentials v `.env`
- Overte firewall pravidlá

**3. SSL certifikát nefunguje**

- Overte certifikát je na správnom mieste
- Overte Nginx/Apache konfiguráciu
- Overte DNS propagáciu: `dig uzol.dvadsatjeden.org`

**4. LNURL-auth nefunguje**

- Overte `LNURL_AUTH_ENABLED=true` v `.env`
- Overte `LNURL_AUTH_DOMAIN=https://uzol.dvadsatjeden.org` (HTTPS!)
- Overte SSL certifikát je platný

**5. Frontend assets chýbajú**

- Spustite `npm run build`
- Overte `public/build/` adresár obsahuje súbory
- Overte web server má prístup k `public/` adresáru

### Debug mode (iba pre testovanie!)

Pre troubleshooting môžete dočasne zapnúť debug mode:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

**POZOR**: Nikdy nenechajte `APP_DEBUG=true` v produkcii kvôli bezpečnostným dôvodom!

## Bezpečnostné poznámky

1. **Nikdy necommitnite `.env` súbor** do Git repository
2. **Použite silné heslá** pre databázu a API kľúče
3. **Udržujte aplikáciu aktuálnu** (composer update, npm update)
4. **Nastavte firewall** (iba potrebné porty)
5. **Pravidelné backupy** - používajte `./backup.sh` alebo Laravel scheduler (`backup:run`)
6. **Monitorujte logy** pre podezrivé aktivity
7. **Použite HTTPS** (povinné pre LNURL-auth)

## Kontakt a podpora

Pre problémy s nasadením skontrolujte:

- Laravel dokumentáciu: https://laravel.com/docs
- PostgreSQL dokumentáciu: https://www.postgresql.org/docs/
- Nginx dokumentáciu: https://nginx.org/en/docs/
