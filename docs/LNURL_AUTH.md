# LNURL-auth: zmena .env a prečo „nič sa nedeje“

## 1. Zmena `LNURL_AUTH_ENABLED` v `.env` sa neprejaví

Laravel v produkcii často používa **cachovaný config** (`php artisan config:cache`). Potom sa hodnoty z `.env` načítajú **len pri vytvorení cache**, nie pri každom requeste.

**Čo urobiť po zmene `.env`:**
```bash
php artisan config:clear
```
Potom **reštartovať PHP** (FPM / worker), aby sa načítal nový config:
```bash
# príklad Docker
docker exec satflux_php_standalone php artisan config:clear
# a reštart PHP kontajnera alebo FPM
```

Ak pri deployi beží `config:cache`, musí sa spustiť **až po** aktualizácii `.env` (alebo upraviť `.env` pred deployom).

**Frontend:** Stránka volá `GET /api/lnurl-auth/enabled` s cache-busterom. Po `config:clear` a reštarte PHP by mala dostať aktuálnu hodnotu. Ak stále nie, v DevTools → Network skontrolovať, či request ide na správny server a akú odpoveď vráti.

---

## 2. Po prihlásení v peňaženke sa na webe nič neudeje

**Podmienka:** Prehliadač (kde je otvorený /login) a peňaženka musia volať **tú istú doménu**.  
QR kód obsahuje URL z `LNURL_AUTH_DOMAIN`. Ak je napr. `LNURL_AUTH_DOMAIN=https://satflux.io`, peňaženka volá `https://satflux.io/...`. Ak máte v prehliadači `http://localhost:8080`, polling ide na localhost a **inú inštanciu / DB** – challenge tam nebude aktualizovaný.

**Riešenie:** Testovať tak, že **prehliadač aj QR smerujú na ten istý server** (napr. oboje na `https://satflux.io`).

**Kontrola:** V DevTools → Network pri otvorenej stránke s QR:
- Po podpise v peňaženke by mali pribúdať requesty `GET /api/lnurl-auth/challenge-status/{k1}`.
- Jedna z odpovedí by mala byť `{"status":"pending_email","user_id":123}`.
- Ak stále vidíte len `"status":"pending"`, buď peňaženka nevolá tú istú doménu, alebo challenge sa na serveri neaktualizuje (logy na serveri pri volaní `/api/lnurl-auth/verify`).
