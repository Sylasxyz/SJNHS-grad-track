#!/bin/sh
set -e

PORT="${PORT:-8080}"
DOCROOT="/var/www/html"

# Railway injects MYSQL*, SMTP*, etc. into the process env — no Apache PassEnv needed.
exec php -S "0.0.0.0:${PORT}" -t "${DOCROOT}" "${DOCROOT}/router.php"
