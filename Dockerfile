FROM php:8.2-apache

# แอพใช้ PDO(MySQL) และฟังก์ชัน mb_* -> ต้องมี pdo_mysql + mbstring
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev \
 && docker-php-ext-install pdo_mysql mbstring \
 && apt-get clean \
 && rm -rf /var/lib/apt/lists/*

# ใช้ php.ini แบบ production และเปิด mod_rewrite
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html

# storage/logs ต้องเขียนได้โดย apache (www-data)
RUN mkdir -p storage/logs \
 && chown -R www-data:www-data storage

EXPOSE 80
