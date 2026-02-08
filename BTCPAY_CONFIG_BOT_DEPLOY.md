# BTCPay Config Bot – nasadenie na live server

Bot beží **na hoste** (nie v Dockeri). Nasadenie panelu cez `deploy.sh` nezahŕňa bota – ten musíš spustiť samostatne.

## Krátky postup

1. **Lokálne:** `git add . && git commit -m "BTCPay config bot" && git push origin master`
2. **SSH na server:** `ssh uzivatel@server`
3. **Deploy panelu:** `cd /home/.../D21Panel && ./deploy.sh`
4. **Bot (prvýkrát):** `cd scripts/btcpay-config-bot && npm install && npx playwright install chromium`
5. **Pridať do .env.production:** `PANEL_BOT_TOKEN`, `PANEL_BOT_PASSWORD`, `BTCPAY_BOT_EMAIL`, `BTCPAY_BOT_PASSWORD`, `BTCPAY_BASE_URL`, `PANEL_URL` (alebo `APP_URL`), `BTCPAY_BOT_HEADLESS=true`
6. **Cron:** `*/2 * * * * cd /path/to/scripts/btcpay-config-bot && node poll.js --once >> /tmp/btcpay-bot.log 2>&1`

---

## 1. Lokálne: push zmien

```bash
git add .
git status   # skontroluj
git commit -m "BTCPay config bot: Lightning setup automation"
git push origin master
```

## 2. Na serveri: deploy panelu cez deploy.sh

```bash
ssh uzivatel@tvoj-server.sk
cd /home/peterhorvath/apps/bitcoin/D21Panel   # alebo kde máš projekt
./deploy.sh
```

`deploy.sh` spraví:

- `git pull` (master/main)
- `docker compose up -d`
- composer, npm build, migrácie
- cache clear

## 3. Na serveri: nastavenie bota

Bot beží mimo Dockeru a volá panel cez API.

### 3.1 Node.js

```bash
node -v   # potrebuješ 18+
```

Ak nemáš Node 18+:

```bash
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt-get install -y nodejs
```

### 3.2 Inštalácia závislostí bota

```bash
cd /home/peterhorvath/apps/bitcoin/D21Panel/scripts/btcpay-config-bot
npm install
npx playwright install chromium   # len Chromium, stačí na headless
```

### 3.3 Premenné prostredia

Doplň do `.env.production` (alebo `.env` na serveri) v **koreni projektu**:

```bash
# BTCPay Config Bot
PANEL_BOT_TOKEN=1|xxxxxxxxxxxxxxxxxxxx
PANEL_BOT_PASSWORD=tvoje_heslo_bot_uctu
BTCPAY_BOT_EMAIL=bot@satflux.io
BTCPAY_BOT_PASSWORD=heslo_pre_btcpay
BTCPAY_BASE_URL=https://pay.dvadsatjeden.org
BTCPAY_BOT_HEADLESS=true
```

**PANEL_URL** – bot načítava z `PANEL_URL`, `BTCPAY_BOT_PANEL_URL` alebo `APP_URL`. Na live použij napr. `https://satflux.io` (alebo tvoju produkčnú doménu), aby bot vedel, kam volať API.

Ak chceš mať URL pre bota oddelene, pridaj napr.:

```bash
PANEL_URL=https://satflux.io
```

**PANEL_BOT_TOKEN** – vytvor cez tinker:

```bash
docker exec -it satflux_php_prod php artisan tinker
$u = \App\Models\User::where('email','bot@satflux.io')->first();
echo $u->createToken('btcpay-config-bot')->plainTextToken;
```

Skopíruj token a vlož do `.env.production` ako `PANEL_BOT_TOKEN=1|xxx...`.

**BTCPAY_BOT_EMAIL / BTCPAY_BOT_PASSWORD** – BTCPay účet s prístupom ku všetkým stores (napr. superadmin).

### 3.4 Spustenie pollera

Jednorazový beh:

```bash
cd /home/peterhorvath/apps/bitcoin/D21Panel/scripts/btcpay-config-bot
PANEL_BOT_TOKEN="1|xxx" node poll.js --once
```

Trvalý beh na pozadí (screen/tmux):

```bash
screen -S btcpay-bot
cd /home/peterhorvath/apps/bitcoin/D21Panel/scripts/btcpay-config-bot
npm run poll
# Ctrl+A, D – odpojiť
```

### 3.5 Cron (odporúčané)

```bash
crontab -e
```

Pridať (napr. každé 2 minúty):

```cron
*/2 * * * * cd /home/peterhorvath/apps/bitcoin/D21Panel/scripts/btcpay-config-bot && /usr/bin/node poll.js --once >> /tmp/btcpay-bot.log 2>&1
```

Uisti sa, že `node` je na PATH alebo použij plnú cestu (`which node`).

Bot načíta `.env` z koreňa projektu (`../../.env`), takže `PANEL_BOT_TOKEN`, `BTCPAY_BOT_EMAIL` atď. musia byť v `.env.production` (alebo v `.env`, ak ten používaš na produkcii).

## 4. Skontrolovanie behu

```bash
tail -f /tmp/btcpay-bot.log
```

Očakávané riadky (JSON):

- `poll_start` – spustenie pollu
- `poll_fetched` – počet connections s `needs_support`
- `panel_reveal`, `btcpay_login`, `btcpay_lightning`, `panel_mark_connected`, `bot_done` – pri úspešnom behu
