# Inštalácia SQLite extensionu pre PHP 8.3

## Problém
Všetky Feature testy zlyhávajú s chybou:
```
could not find driver (Connection: sqlite, SQL: PRAGMA foreign_keys = ON;)
```

## Riešenie: Nainštalujte SQLite extension

### 1. Inštalácia SQLite extensionu

```bash
# Pre PHP 8.3
sudo apt install php8.3-sqlite3 -y

# Alebo ak máte inú verziu PHP, použite:
# sudo apt install php8.1-sqlite3 -y
# sudo apt install php8.2-sqlite3 -y
```

### 2. Overenie inštalácie

```bash
# Skontrolujte, či je extension nainštalovaný
php -m | grep sqlite

# Malo by zobraziť:
# sqlite3
# pdo_sqlite
```

### 3. Reštartovanie PHP-FPM (ak používate)

```bash
sudo systemctl restart php8.3-fpm
# alebo
sudo systemctl restart php-fpm
```

### 4. Spustenie testov

```bash
php artisan test
```

## Alternatívne riešenie: Použiť PostgreSQL pre testy

Ak nechcete inštalovať SQLite, môžete upraviť `phpunit.xml` aby používal PostgreSQL:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="BCRYPT_ROUNDS" value="4"/>
    <env name="CACHE_DRIVER" value="array"/>
    <env name="DB_CONNECTION" value="pgsql"/>
    <env name="DB_HOST" value="127.0.0.1"/>
    <env name="DB_PORT" value="5432"/>
    <env name="DB_DATABASE" value="uzol21_testing"/>
    <env name="DB_USERNAME" value="uzol21"/>
    <env name="DB_PASSWORD" value="your_password"/>
    <env name="MAIL_MAILER" value="array"/>
    <env name="QUEUE_CONNECTION" value="sync"/>
    <env name="SESSION_DRIVER" value="array"/>
</php>
```

Potom vytvorte testovaciu databázu:
```bash
sudo -u postgres psql
CREATE DATABASE uzol21_testing;
GRANT ALL PRIVILEGES ON DATABASE uzol21_testing TO uzol21;
\q
```

**Poznámka:** SQLite je odporúčané pre testy, pretože:
- Je rýchlejší (in-memory databáza)
- Nevyžaduje externý server
- Automaticky sa vyčistí po každom teste

