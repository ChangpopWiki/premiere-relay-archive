#!/bin/sh
chown -R www-data:www-data /app/logs /app/data
exec "$@"