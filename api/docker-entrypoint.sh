#!/bin/sh
# Force MySQL when running in Docker so the app uses the mysql service even if
# the mounted api/.env has DB_CONNECTION=sqlite. Re-export DB_* from the environment
# (set by docker-compose from the project root .env) so Laravel sees them.
export DB_CONNECTION=${DB_CONNECTION:-mysql}
export DB_HOST=${DB_HOST:-mysql}
export DB_PORT=${DB_PORT:-3306}
export DB_DATABASE=${DB_DATABASE:-rms}
export DB_USERNAME=${DB_USERNAME:-rms}
export DB_PASSWORD=${DB_PASSWORD:-rms_secret}
exec "$@"
