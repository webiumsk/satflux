# PHP Upgrade Guide - Ubuntu 22.04

## Current Situation
- **Current PHP version:** 8.1.2
- **Required PHP version:** >= 8.3.0
- **Ubuntu version:** 22.04

## Solution: Install PHP 8.3

### Option 1: Using ondrej/php PPA (Recommended)

```bash
# Add PPA repository
sudo add-apt-repository ppa:ondrej/php -y

# Update package list
sudo apt update

# Install PHP 8.3 and common extensions
sudo apt install php8.3 php8.3-cli php8.3-fpm php8.3-common php8.3-mysql php8.3-zip php8.3-gd php8.3-mbstring php8.3-curl php8.3-xml php8.3-bcmath php8.3-pgsql php8.3-opcache php8.3-fileinfo -y

# Install Composer dependencies
composer install
```

### Option 2: Using Docker (If you're using docker-compose)

If you're using Docker, you can update the PHP version in `docker/php/Dockerfile`:

```dockerfile
FROM php:8.3-fpm
# ... rest of Dockerfile
```

Then rebuild:
```bash
docker-compose build php
docker-compose up -d
```

### Option 3: Switch PHP Version (If multiple versions installed)

If you have multiple PHP versions installed:

```bash
# List available PHP versions
sudo update-alternatives --list php

# Set PHP 8.3 as default
sudo update-alternatives --set php /usr/bin/php8.3

# Verify
php -v
```

## Verify Installation

```bash
# Check PHP version
php -v

# Should output: PHP 8.3.x

# Check required extensions
php -m | grep -E "pdo_pgsql|pgsql|mbstring|openssl|json|bcmath|gd|fileinfo"
```

## After Installation

1. **Update Composer:**
   ```bash
   composer self-update
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Clear Laravel cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Troubleshooting

### If you get "Package not found" errors:
```bash
# Make sure PPA is added
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
```

### If you need to remove old PHP version:
```bash
# List installed PHP packages
dpkg -l | grep php8.1

# Remove if needed (be careful!)
sudo apt remove php8.1-* --purge
```

### If using Apache/Nginx:
Don't forget to restart your web server:
```bash
# Apache
sudo systemctl restart apache2

# Nginx + PHP-FPM
sudo systemctl restart php8.3-fpm
sudo systemctl restart nginx
```

