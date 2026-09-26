#!/bin/sh
# 容器启动入口：后台拉起 php-fpm，前台运行 nginx
set -e

php-fpm -D
exec nginx -g 'daemon off;'
