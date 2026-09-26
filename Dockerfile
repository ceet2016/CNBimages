# 基于官方 PHP 8.2 FPM + Nginx（Alpine）
# 已包含：GD（图片处理）、PDO_SQLite（用户/图片元数据）、OPcache
FROM php:8.2-fpm-alpine

# 安装系统库与 PHP 扩展
RUN apk add --no-cache nginx curl libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo_sqlite mbstring opcache

WORKDIR /var/www
COPY . /var/www

# 运行时目录与权限
RUN mkdir -p /var/www/uploads /var/www/cache /var/www/data/sessions \
    && chown -R www-data:www-data /var/www/uploads /var/www/cache /var/www/data \
    && cp /var/www/public/nginx.conf /etc/nginx/http.d/default.conf \
    && cp /var/www/docker/zz-app.conf /usr/local/etc/php-fpm.d/zz-app.conf

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80
CMD ["/usr/local/bin/entrypoint.sh"]
