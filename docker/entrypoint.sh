#!/bin/sh
set -e

# Make sure the directories exist for the socket/log, etc.
mkdir -p /run/nginx

# Clear cache for prod
php bin/console cache:clear --env=prod || true

exec "$@"
