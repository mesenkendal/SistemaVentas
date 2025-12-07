# SistemaVentas - Guía de Despliegue en Render

## Prerequisitos
- Cuenta en [Render](https://render.com)
- Repositorio Git (GitHub, GitLab, etc.)
- Base de datos MySQL configurada

## Pasos para desplegar

### 1. Preparar el repositorio
```bash
# Verificar que tengas todos los archivos
- render.yaml
- composer.json
- Procfile
- .env.production
- build.sh
- database.sql
```

### 2. Variables de entorno en Render
En el dashboard de Render, configura estas variables:
- `APP_NAME`: Sistema de Ventas
- `APP_URL`: /
- `APP_DEBUG`: false
- `APP_TIMEZONE`: America/Mexico_City
- `DB_HOST`: URL de tu base de datos
- `DB_PORT`: 3306
- `DB_DATABASE`: SistemaVentas
- `DB_USERNAME`: usuario_db
- `DB_PASSWORD`: contraseña_db

### 3. Base de datos
1. Crea una base de datos MySQL en Render o un proveedor externo
2. Ejecuta `database.sql` para crear tablas e inserts iniciales
3. Crea un usuario administrativo de prueba en la tabla `Usuarios`

### 4. Desplegar
- Conecta tu repositorio Git a Render
- Selecciona la rama `main` o `production`
- Render ejecutará automáticamente:
  - `composer install`
  - `build.sh`
  - Iniciará el servidor PHP

### 5. Problemas comunes

**Error: "Cannot connect to database"**
- Verifica las credenciales en variables de entorno
- Comprueba que la BD está accesible desde Render

**Error: "Permission denied"**
- Asegúrate que `build.sh` tiene permisos de ejecución:
  ```bash
  chmod +x build.sh
  ```

**Error: "PDO Connection Failed"**
- Verifica que `DB_HOST` incluya el puerto si es necesario
- Comprueba que el usuario de DB tiene permisos globales

### 6. Configuración en Render Dashboard
1. Ve a **Services** → **New Web Service**
2. Conecta tu repositorio
3. Selecciona **Docker** o **PHP**
4. En **Build Command**: `composer install`
5. En **Start Command**: `php -S 0.0.0.0:8000 -t public`
6. Agrega las variables de entorno
7. Haz clic en **Create Web Service**

## Seguridad recomendada
- Cambiar contraseñas por defecto
- Usar HTTPS (Render lo proporciona automáticamente)
- Limitar acceso a la BD desde IP de Render
- Hacer backup regularmente de la BD
