#!/bin/sh
set -e

cd /app
RUNTIME_ENV="${APP_ENV:-prod}"
COMPOSER_FLAGS="--no-interaction --no-progress --prefer-dist --optimize-autoloader"

echo "➕  Installation des dépendances Composer"
if [ "$RUNTIME_ENV" = "prod" ]; then
    /usr/local/bin/composer install \
        --no-dev \
        --classmap-authoritative \
        --no-scripts \
        $COMPOSER_FLAGS
else
    /usr/local/bin/composer install \
        --no-scripts \
        $COMPOSER_FLAGS
fi


DB_HOST="${DATABASE_HOST:-db}"
DB_PORT="${DATABASE_PORT:-5432}"

echo "⏳  Attente de PostgreSQL sur ${DB_HOST}:${DB_PORT}..."
until nc -z "$DB_HOST" "$DB_PORT"; do
    printf "."
    sleep 2
done
echo ""
echo "✅  PostgreSQL disponible"

DB_USER="${DATABASE_USER:-runner}"
DB_PASS="${DATABASE_PASSWORD:-runner}"
DB_NAME="${DATABASE_NAME:-postgres}"

if [ -z "${DATABASE_VERSION:-}" ]; then
    export DATABASE_VERSION="15.0.0"
fi

echo "🧱  Vérification base ${DB_NAME}..."
if ! PGPASSWORD="$DB_PASS" psql \
    -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" -d postgres \
    -tAc "SELECT 1 FROM pg_database WHERE datname='${DB_NAME}'" | grep -q 1; then
    echo "➕  Création base ${DB_NAME}"
    PGPASSWORD="$DB_PASS" createdb \
        -h "$DB_HOST" -p "$DB_PORT" -U "$DB_USER" \
        --template=template0 --encoding=UTF8 --locale=C \
        "$DB_NAME"
fi

echo "🗄️   Migrations..."
# Apply only pending migrations. Existing data is untouched unless migration SQL says otherwise.
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

echo "📦  Installation des assets..."
php bin/console assets:install public --symlink --relative --env="$RUNTIME_ENV" || \
php bin/console assets:install public --env="$RUNTIME_ENV"

echo "🧹  Préparation du cache Symfony..."
mkdir -p var/cache var/log
rm -rf "var/cache/$RUNTIME_ENV"
mkdir -p "var/cache/$RUNTIME_ENV/twig" "var/cache/$RUNTIME_ENV/pools"
chown -R www-data:www-data var/
chmod -R ug+rwX var/

echo "🔥  Cache warmup..."
php bin/console cache:warmup --env="$RUNTIME_ENV"
chown -R www-data:www-data var/
chmod -R ug+rwX var/


echo "🚀  Démarrage PHP-FPM..."
php-fpm -D

echo "⏳  Attente du socket PHP-FPM..."
until [ -S /run/php-fpm.sock ]; do sleep 0.2; done
echo "✅  Socket PHP-FPM prêt"

echo "🌐  Démarrage Nginx..."
exec nginx -g "daemon off;"