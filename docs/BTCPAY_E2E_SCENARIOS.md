# BTCPay E2E scenáre cez Greenfield stub (P3 fáza 2)

Playwright pokrytie životného cyklu BTCPay obchodu a faktúry proti
in-memory Greenfield stubu - CI nemá reálny BTCPay Server (viď
[E2E_HARNESS.md](E2E_HARNESS.md)).

## Stub server

`e2e/btcpay-stub/server.mjs` - čistý `node:http`, žiadne závislosti,
in-memory stav. **Nie je to BTCPay emulátor** - implementuje presne tú
podmnožinu Greenfield endpointov, ktoré `BtcPayClient` volá, plus control
API pre testy.

Zapojenie je **len konfiguračné**: `BTCPAY_BASE_URL` ukazuje na stub
(`http://localhost:14142`), `BTCPAY_API_KEY` je ľubovoľný token (stub
akceptuje akékoľvek `Authorization`). Žiadna zmena aplikačného kódu.

### Greenfield podmnožina

Stores (create/list/get/delete), store users, webhooks (create vracia
`{id, secret}` - každé volanie dostane vlastný unikátny secret, ako reálny
BTCPay; jeden obchod ich môže mať viac), users + api-keys, invoices
(create → `{id, checkoutLink, status:'New'}`, get so stavom, list per
store), payment-methods/lightning-addresses/apps (prázdne).

### Control API (len pre testy)

- `POST /_stub/stores/{storeId}/invoices` - vytvor faktúru (setup scenára).
- `POST /_stub/invoices/{id}/settle` - stav `Settled` + **vystrelí podpísaný
  `InvoiceSettled` webhook** na `APP_URL/api/webhooks/btcpay` s hlavičkou
  `BTCPay-Sig: sha256=<hmac(rawBody, webhook.secret)>` a unikátnym
  `deliveryId` (replay ochrana panelu).
- `POST /_stub/invoices/{id}/expire` - stav `Expired` (bez webhooku).
- `GET /_stub/state` - dump stavu (stores, invoices, webhookDeliveries) na
  asserty.

Podpisový reťazec je overený end-to-end: stub podpisuje secretom, ktorý
panel počas provisioningu uložil do `store.webhook_secret`, a
`WebhookController` overuje `hash_hmac('sha256', rawBody, secret)` cez
`hash_equals` (node aj PHP HMAC dávajú identický výstup - cross-check
v P3-B).

## Scenáre (`e2e/btcpay.spec.ts`)

Serial, gate `E2E_BTCPAY=1` + bežiaci stub + seeder s `E2E_BTCPAY=1`:

1. Vytvorenie obchodu cez UI → provisioning reťazec beží proti stubu (store
   + webhook s vlastným secretom) → obchod v zozname.
2. Faktúra vytvorená na BTCPay strane sa objaví v zozname faktúr obchodu.
3. Settle → podpísaný webhook → panel ho prijme (status 200, `received`).
4. Settled stav viditeľný v UI.
5. Expirovaná faktúra → stav `Expired`.
6. Zmazanie obchodu (typed "DELETE") → zmizne aj zo stubu.

## Seeder

`E2eTestSeeder` s `E2E_BTCPAY=1` dosadí testovaciemu userovi fixné
`btcpay_user_id` + `btcpay_api_key` (stub akceptuje akýkoľvek token) -
stránka faktúr vyžaduje merchant kľúč a store creation priraďuje merchanta
podľa BTCPay user id. Seeder sa nikdy nespustí v `production`.

## Lokálny beh

```bash
# 1) stub (terminál A) - webhooky mieria na docker app na :8080
APP_URL=http://localhost:8080 node e2e/btcpay-stub/server.mjs

# 2) app + seed (docker beží) - E2E_BTCPAY=1 musí ísť DO kontajnera
docker compose exec -e E2E_BTCPAY=1 php php artisan db:seed --class=E2eTestSeeder
# .env aplikácie musí mať BTCPAY_BASE_URL na adresu stubu dostupnú
# Z KONTAJNERA (localhost v kontajneri nie je host!):
#   BTCPAY_BASE_URL=http://host.docker.internal:14142
# Na Linuxe pridaj php službe v docker-compose.yml:
#   extra_hosts: ["host.docker.internal:host-gateway"]

# 3) testy (terminál B, bežia na hoste)
E2E_SEEDED_USER=1 E2E_INVOICING_LOCAL_FIRST=1 E2E_BTCPAY=1 npm run test:e2e
```

## CI

`.github/workflows/ci.yml` job **E2E**: krok "Start BTCPay Greenfield stub"
naštartuje `node e2e/btcpay-stub/server.mjs` s fail-fast pollom (ako
`artisan serve`), `.env` dostane `BTCPAY_BASE_URL`/`BTCPAY_API_KEY`, seeder
beží s `E2E_BTCPAY=1`, playwright s `E2E_BTCPAY=1`.

## Vymedzenie

- Stub nemá platobnú parity (žiadne reálne BTC/LN, žiadne on-chain sumy) -
  overuje riadiaci tok a signatúrny reťazec, nie kryptomenovú logiku.
- Local-first invoicing BTCPay checkout (QR z Evolu faktúry) tu nie je -
  vyžaduje prejsť recovery-phrase flow v prehliadači; kandidát na follow-up.
