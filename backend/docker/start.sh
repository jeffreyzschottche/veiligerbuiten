#!/bin/sh
# Bij elke (her)start: database bijwerken, config cachen, en dan alle processen via supervisor.
set -e
php artisan migrate --force
php artisan config:cache
exec supervisord -c /etc/supervisord.conf
