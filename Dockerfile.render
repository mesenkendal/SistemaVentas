# Imagen base con PHP y Apache
FROM php:8.2-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    unzip \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install mysqli pdo pdo_mysql zip intl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# Intentar habilitar módulos adicionales (ignorar errores)
RUN a2enmod expires || true \
    && a2enmod headers || true \
    && a2enmod deflate || true

# Configurar PHP para producción
RUN echo "date.timezone = America/Mexico_City" >> /usr/local/etc/php/conf.d/timezone.ini \
    && echo "upload_max_filesize = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 10M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/limits.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/limits.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/production.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/production.ini

# Configurar Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Crear directorios necesarios
RUN mkdir -p /var/www/html/public \
    && mkdir -p /var/www/html/tmp \
    && chown -R www-data:www-data /var/www/html

# Copiar el código al contenedor
COPY . /var/www/html/

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/tmp

# Usar configuración simple de Apache apuntando a /public
COPY docker/apache-config-simple.conf /etc/apache2/sites-available/000-default.conf

# Verificar configuración
RUN apache2ctl configtest

# Variables de entorno para producción
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV APACHE_RUN_USER=www-data
ENV APACHE_RUN_GROUP=www-data
ENV APACHE_LOG_DIR=/var/log/apache2
ENV APACHE_PID_FILE=/var/run/apache2.pid
ENV APACHE_RUN_DIR=/var/run/apache2
ENV APACHE_LOCK_DIR=/var/lock/apache2

# Exponer el puerto
EXPOSE 80

# Comando de inicio
CMD ["apache2-foreground"]
