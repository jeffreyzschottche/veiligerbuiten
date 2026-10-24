#!/bin/sh
# Bij elke (her)start: database bijwerken, caches opbouwen, en dan alle processen via supervisor.
set -e
# --isolated: starten er twee containers tegelijk, dan migreert er maar één.
php artisan migrate --force --isolated
# Config, routes, events en views gecachet: minder werk per verzoek.
php artisan optimize
exec supervisord -c /etc/supervisord.conf
