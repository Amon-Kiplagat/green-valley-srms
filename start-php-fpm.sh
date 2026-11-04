#!/bin/sh
# Pass all environment variables to PHP-FPM via php.ini
echo "variables_order = \"EGPCS\"" > /usr/local/etc/php/conf.d/env-vars.ini
echo "clear_env = no" >> /usr/local/etc/php-fpm.d/www.conf

# Start PHP-FPM
exec php-fpm --nodaemon -c /usr/local/etc/php -y /usr/local/etc/php-fpm.d/www.conf
