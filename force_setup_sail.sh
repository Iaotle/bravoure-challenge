#!/bin/bash
# start the container with sail up before running this script

# Run one-time setup tasks inside the application container
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan event:cache

./vendor/bin/sail npm run prod
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan db:seed --class=CountrySeeder --force

echo "Bootstrap commands executed. Running tests..."
./vendor/bin/sail artisan test