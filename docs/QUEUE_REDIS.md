# Chyba: Redis – „getaddrinfo for redis failed: Name or service not known“

Queue worker (`php artisan queue:work redis`) a cache používajú Redis. Chyba znamená, že hostname `redis` sa v prostredí, kde worker beží, nerozpoznáva.

## Kedy to nastáva

- Worker beží **mimo** Docker Compose (napr. na hoste alebo v inom kontajneri), kde hostname `redis` neexistuje.
- Redis kontajner nebeží alebo nie je v rovnakej Docker sieti.
- V `.env` / `.env.production` máte `REDIS_HOST=redis` (typické pre Docker), ale worker nebeží v tej istej sieti.

## Riešenie A: Redis má bežať (Docker)

- Skontrolujte, že v `docker-compose` beží služba `redis` a že queue worker má `depends_on: redis` a je na rovnakej sieti.
- Pri spustení cez `docker-compose up` by mal názov `redis` v kontajneri fungovať.

## Riešenie B: Bez Redis (database front + file cache)

Ak Redis nemáte alebo nechcete používať:

1. V **`.env.production`** (alebo env, ktorý používa queue worker) nastavte:
   ```env
   QUEUE_CONNECTION=database
   CACHE_STORE=file
   ```
2. Vytvorte tabuľku pre fronty (ak ešte neexistuje):
   ```bash
   php artisan queue:table
   php artisan migrate
   ```
3. Worker musí bežať s connection **database**, nie **redis**:
   - V Docker Compose je command už nastavený tak, že používa premennú `QUEUE_CONNECTION` (fallback `redis`). Ak máte v env `QUEUE_CONNECTION=database`, worker spustí `queue:work database`.
   - Ak spúšťate worker ručne:  
     `php artisan queue:work database --queue=webhooks,exports ...`

Po zmene env reštartujte queue worker (a pri použití `config:cache` spravte `php artisan config:clear` resp. znova `config:cache`).
