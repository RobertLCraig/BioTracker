#!/usr/bin/env bash
# Deploy BioTracker to pmox (ubuntu-web VM 102). Run from the repo root in Git Bash: bin/deploy.sh
#
# Ships the committed HEAD only, after the suite passes on SQLite AND on the server's Postgres
# (database biotracker_test), because the two engines differ and a bug once showed only on Postgres.
# Ends by checking the web app answers and the bot is running. Any failed step stops the deploy.
set -euo pipefail

SERVER=${BOT_SERVER:-rob@192.168.222.134}
APP=/opt/biotracker
PHP=${PHP:-/c/Users/r/.config/herd/bin/php84/php.exe}
URL=https://biotracker.home.craig.ooo

if [ -n "$(git status --porcelain)" ]; then
    echo "Uncommitted changes. Commit first: only HEAD is deployed." >&2
    exit 1
fi

echo "== Tests on SQLite"
"$PHP" vendor/bin/phpunit

echo "== Tests on Postgres (biotracker_test)"
# The password is read from the server's .env and never printed or written to disk here.
DB_PASSWORD=$(ssh "$SERVER" "grep '^DB_PASSWORD=' $APP/.env | cut -d= -f2-" | tr -d '\r"')
DB_CONNECTION=pgsql DB_HOST=192.168.222.225 DB_PORT=5432 DB_DATABASE=biotracker_test \
    DB_USERNAME=biotracker DB_PASSWORD="$DB_PASSWORD" "$PHP" vendor/bin/phpunit
unset DB_PASSWORD

echo "== Build"
npm run build

echo "== Copy $(git rev-parse --short HEAD) to $SERVER"
git archive HEAD | ssh "$SERVER" "tar -x -C $APP"
tar -c public/build | ssh "$SERVER" "tar -x -C $APP"

echo "== Install and migrate"
ssh "$SERVER" "cd $APP && composer install --no-dev -o --no-interaction -q \
    && php artisan migrate --force \
    && php artisan config:cache -q && php artisan route:cache -q \
    && sudo systemctl reload php8.5-fpm && sudo systemctl restart telegram-bot"

echo "== Check"
sleep 3
ssh "$SERVER" "systemctl is-active telegram-bot"
curl -fsS -o /dev/null -w "web app: HTTP %{http_code}\n" "$URL/up"
echo "Deployed $(git rev-parse --short HEAD)."
