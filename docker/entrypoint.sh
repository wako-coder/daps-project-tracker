#!/bin/sh
# Container start-up. Runs on every deploy, before the app accepts traffic.
# If any step fails the container exits, the host keeps the previous release
# serving, and the pipeline's "wait for release" step fails.
set -e

# 1. Apply pending database migrations. A no-op when there are none.
php artisan migrate --force

# 2. Optionally create the demo data. The seeder does nothing if it already
#    exists, so leaving RUN_DEMO_SEED=true never resets live data.
if [ "$RUN_DEMO_SEED" = "true" ]; then
    php artisan db:seed --class=DemoSeeder --force
fi

# 3. Cache configuration, routes and views for production.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 4. Serve.
exec frankenphp run --config /app/docker/Caddyfile --adapter caddyfile
