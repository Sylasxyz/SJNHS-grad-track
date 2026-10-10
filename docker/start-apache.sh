#!/bin/bash
set -e

PORT="${PORT:-8080}"

if [ -f /etc/apache2/ports.conf ]; then
  sed -i "s/^Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf || true
fi

if [ -f /etc/apache2/sites-available/000-default.conf ]; then
  sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf || true
fi

exec apache2-foreground
