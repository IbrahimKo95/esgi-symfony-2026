#!/bin/sh
set -e

mkdir -p var/cache var/log var/sessions

# Doctrine DBAL needs serverVersion in the URL — Fly.io doesn't inject it
if [ -n "$DATABASE_URL" ] && ! echo "$DATABASE_URL" | grep -q "serverVersion"; then
    if echo "$DATABASE_URL" | grep -q "?"; then
        export DATABASE_URL="${DATABASE_URL}&serverVersion=16"
    else
        export DATABASE_URL="${DATABASE_URL}?serverVersion=16"
    fi
fi

echo "==> Build Tailwind CSS..."
php bin/console tailwind:build --minify --env=prod

echo "==> Warm-up du cache Symfony..."
php bin/console cache:warmup --env=prod

echo "==> Compilation des assets statiques..."
php bin/console asset-map:compile

echo "==> Exécution des migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod

echo "==> Démarrage via supervisord..."
exec supervisord -c /etc/supervisord.conf
