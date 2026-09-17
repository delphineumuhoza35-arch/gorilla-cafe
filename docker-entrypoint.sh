#!/bin/sh
set -e

PORT="${PORT:-80}"
echo "Listening on port: $PORT"

echo "Listen $PORT" > /etc/apache2/ports.conf

cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:$PORT>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html
    <Directory /var/www/html>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOF

exec docker-php-entrypoint apache2-foreground