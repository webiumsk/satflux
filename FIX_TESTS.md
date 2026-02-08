# Riešenie problémov s testami

## Problém 1: PHP verzia ✅ VYRIEŠENÉ

- **Chyba:** Composer vyžaduje PHP >= 8.3.0, ale máte 8.1.2
- **Riešenie:** Aktualizovať PHP na 8.3 (pozri UPGRADE_PHP.md)

## Problém 2: Unit testy ✅ VYRIEŠENÉ

- **Chyba:** `WalletConnectionValidatorTest` zlyhával kvôli nesprávnemu formátu connection stringu
- **Riešenie:**
  - Opravený test aby používal správny formát: `type=blink;server=...;api-key=...;wallet-id=...`
  - Opravený `validate()` aby vracal správny formát s `type` a `error` kľúčmi
  - Opravený typ z `aqua_boltz` na `aqua_descriptor`

## Problém 3: Feature testy - SQLite driver

- **Chyba:** `could not find driver (Connection: sqlite)`
- **Riešenie:** Nainštalujte SQLite extension pre PHP:

```bash
# Pre PHP 8.3 (po aktualizácii)
sudo apt install php8.3-sqlite3 -y

# Alebo pre aktuálnu verziu
sudo apt install php8.1-sqlite3 -y

# Overte inštaláciu
php -m | grep sqlite
```

## Problém 4: Feature testy - Databáza

- **Chyba:** Testy sa pokúšajú pripojiť k PostgreSQL, ale databáza nie je dostupná
- **Riešenie:** PHPUnit je už nakonfigurovaný na SQLite in-memory databázu v `phpunit.xml`:
  ```xml
  <env name="DB_CONNECTION" value="sqlite"/>
  <env name="DB_DATABASE" value=":memory:"/>
  ```

## Problém 5: Chýbajúce migrácie

- **Riešenie:** TestCase automaticky spúšťa migrácie pomocou `RefreshDatabase` trait

## Postup opravy

1. **Aktualizujte PHP na 8.3:**

   ```bash
   sudo add-apt-repository ppa:ondrej/php -y
   sudo apt update
   sudo apt install php8.3 php8.3-cli php8.3-sqlite3 -y
   sudo update-alternatives --set php /usr/bin/php8.3
   ```

2. **Nainštalujte SQLite extension:**

   ```bash
   sudo apt install php8.3-sqlite3 -y
   ```

3. **Spustite testy:**
   ```bash
   php artisan test
   ```

## Alternatívne riešenie - Použiť PostgreSQL pre testy

Ak chcete použiť PostgreSQL namiesto SQLite (napr. kvôli špecifickým PostgreSQL funkciám):

1. **Zmeňte phpunit.xml:**

   ```xml
   <env name="DB_CONNECTION" value="pgsql"/>
   <env name="DB_HOST" value="127.0.0.1"/>
   <env name="DB_PORT" value="5432"/>
   <env name="DB_DATABASE" value="satflux.io_testing"/>
   <env name="DB_USERNAME" value="satflux.io"/>
   <env name="DB_PASSWORD" value="password"/>
   ```

2. **Vytvorte testovaciu databázu:**
   ```bash
   sudo -u postgres psql
   CREATE DATABASE satflux.io_testing;
   GRANT ALL PRIVILEGES ON DATABASE satflux.io_testing TO satflux.io;
   \q
   ```

## Verifikácia

Po oprave by ste mali vidieť:

```bash
$ php artisan test

PASS  Tests\Unit\Services\WalletConnectionValidatorTest
✓ valid blink connection string
✓ invalid blink connection string missing parts
✓ invalid blink connection string wrong format
✓ valid boltz descriptor
✓ validation rejects empty string
✓ validation rejects unsupported type

PASS  Tests\Feature\AuthTest
✓ user can register
✓ user can login

...

Tests: XX passed
```
