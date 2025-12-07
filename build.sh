#!/bin/bash
set -e

# Instalar dependencias
composer install --no-dev --optimize-autoloader

# Crear directorio de logs si no existe
mkdir -p storage/logs

# Ejecutar migraciones de base de datos (si las hay)
php database.sql 2>/dev/null || true

# Iniciar servidor PHP
php -S 0.0.0.0:${PORT:-8000} -t public
