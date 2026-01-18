# Súhrn opravy testov

## ✅ VYRIEŠENÉ

### 1. SQLite Extension
- **Problém:** `could not find driver (Connection: sqlite)`
- **Riešenie:** Nainštalovaný `php8.3-sqlite3`
- **Status:** ✅ Opravené

### 2. Migrácia pre SQLite
- **Problém:** `SQLSTATE[HY000]: General error: 1 near "ALTER": syntax error`
- **Príčina:** SQLite nepodporuje PostgreSQL syntax `ALTER TABLE users ALTER COLUMN name DROP NOT NULL`
- **Riešenie:** 
  - Upravená migrácia `2026_01_15_085939_make_name_nullable_in_users_table.php` aby detekovala SQLite a preskočila ALTER COLUMN
  - Upravená pôvodná migrácia `2024_01_01_000000_create_users_table.php` aby `name` bol nullable od začiatku pre SQLite kompatibilitu
- **Status:** ✅ Opravené

## ⚠️ ZOSTÁVAJÚCE PROBLÉMY

### 1. Redis Extension
- **Problém:** `Class "Redis" not found`
- **Riešenie:** 
  ```bash
  sudo apt install php8.3-redis -y
  ```
  Alebo nastaviť v `phpunit.xml`:
  ```xml
  <env name="REDIS_CLIENT" value="array"/>
  ```
- **Status:** ⚠️ Čaká na opravu

### 2. Permissions pre logy (voliteľné)
- **Problém:** `Permission denied` pri zápise do `storage/logs/laravel.log`
- **Riešenie:** 
  ```bash
  sudo chmod 775 storage/logs
  sudo chmod 664 storage/logs/*.log
  ```
  Alebo vypnúť logging pre testy v `phpunit.xml`:
  ```xml
  <env name="LOG_CHANNEL" value="null"/>
  ```
- **Status:** ⚠️ Voliteľné

## Pokrok

- **Unit testy:** ✅ 6/6 passujú
- **Feature testy:** ⚠️ Zlyhávajú kvôli Redis extension

## Ďalšie kroky

1. Nainštalovať Redis extension:
   ```bash
   sudo apt install php8.3-redis -y
   ```

2. Alebo vypnúť Redis pre testy úplne (odporúčané pre testy):
   - V `phpunit.xml` je už nastavené `CACHE_DRIVER=array`
   - Skontrolovať, či aplikácia nepoužíva Redis priamo (nie cez cache driver)

3. Spustiť testy znova:
   ```bash
   php artisan test
   ```

## Zmenené súbory

1. `database/migrations/2024_01_01_000000_create_users_table.php` - name je nullable od začiatku
2. `database/migrations/2026_01_15_085939_make_name_nullable_in_users_table.php` - SQLite detection a skip
3. `phpunit.xml` - SQLite configuration, LOG_CHANNEL=null
4. `tests/Unit/Services/WalletConnectionValidatorTest.php` - opravené testy
5. `app/Services/WalletConnectionValidator.php` - opravený return format

