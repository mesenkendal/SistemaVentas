# ✅ Checklist de Despliegue en Render - SistemaVentas

## 📋 Archivos de Configuración
- [x] `render.yaml` - Configuración de servicios (Web + MySQL)
- [x] `composer.json` - Dependencias PHP y autoload PSR-4
- [x] `Procfile` - Especificación de buildpack
- [x] `build.sh` - Script de construcción y arranque
- [x] `.env.production` - Variables de entorno para producción
- [x] `apt-packages` - Dependencias del sistema
- [x] `.gitignore` - Exclusión de archivos sensibles
- [x] `database.sql` - Schema MySQL con triggers
- [x] `DEPLOYMENT.md` - Guía de despliegue

## 🏗️ Estructura de Directorios
- [x] `src/Models/` - Modelos PHP con BaseModel y herencia
- [x] `public/` - Archivos públicos (vistas, handlers, assets)
- [x] `public/views/` - Vistas (dashboard, inventario, ventas, usuarios, reportes, permisos)
- [x] `public/handlers/` - Controladores POST (auth, inventario, ventas, usuarios)
- [x] `public/assets/css/` - Estilos CSS (temas oscuros, responsive)
- [x] `public/assets/js/` - Scripts JavaScript (si existen)
- [x] `includes/` - Helpers (permissions.php, session_guard.php)
- [x] `config/` - Configuración (config.php)
- [x] `bootstrap.php` - Autoloader y inicialización

## 🔐 Seguridad
- [x] `.env` local está en `.gitignore`
- [x] `.env.production` tiene APP_DEBUG=false
- [x] Credenciales de base de datos NO hardcodeadas
- [x] Sesiones con timeout implementado
- [x] Permisos basados en roles configurados
- [x] SQL injection protegido con PDO prepared statements

## 🗄️ Base de Datos
- [x] Schema MySQL 8.0 UTF8MB4
- [x] Tablas: Roles, Usuarios, Inventario, Ventas, DetallesVenta, Bitacora, Vistas, RolVistas
- [x] Triggers para auditoría automática
- [x] Stored procedure sp_log_bitacora
- [x] Datos iniciales de Vistas
- [x] Relaciones con foreign keys

## 🔑 Variables de Entorno Requeridas
```
APP_NAME=Sistema de Ventas
APP_URL=/
APP_DEBUG=false
APP_TIMEZONE=America/Mexico_City
DB_HOST=tu-bd.render.com
DB_PORT=3306
DB_DATABASE=SistemaVentas
DB_USERNAME=usuario
DB_PASSWORD=contraseña
DB_CHARSET=utf8mb4
```

## 📝 Pasos Finales Antes de Desplegar

### 1. Verificar en Local
```bash
# Hacer commit de todos los cambios
git add .
git commit -m "Preparación para despliegue en Render"

# Verificar que el app funciona localmente
php -S localhost:8000 -t public
```

### 2. En Render Dashboard
```
1. Ir a https://dashboard.render.com
2. Click en "New" → "Web Service"
3. Conectar repositorio Git
4. Configurar:
   - Build command: composer install
   - Start command: php -S 0.0.0.0:$PORT -t public
   - Root directory: (dejar en blanco o /)
5. Agregar variables de entorno (ver sección anterior)
6. Click "Create Web Service"
```

### 3. Configurar Base de Datos
```
1. Crear base de datos MySQL en Render o servicio externo
2. Usar credenciales en variables de entorno
3. Ejecutar database.sql para crear schema
4. Crear usuario admin de prueba:
   INSERT INTO Usuarios (nombre, email, contraseña, rol_id, activo) 
   VALUES ('Admin', 'admin@test.com', SHA2('admin123', 256), 1, 1);
```

### 4. Después del Despliegue
```
- Acceder a la URL de Render
- Login con credenciales de admin
- Verificar que todas las vistas cargan
- Probar navegación y permisos
- Probar creación/edición/eliminación en inventario
- Verificar que el timeout de sesión funciona
- Descargar reporte en Excel
```

## 🚨 Problemas Comunes y Soluciones

### Problema: "Cannot connect to database"
- ✅ Verificar credenciales en Render env vars
- ✅ Verificar que BD está accesible desde Render (firewall)
- ✅ Revisar que DB_HOST es correcto (con puerto si es necesario)

### Problema: "Permission denied on build.sh"
- ✅ Ejecutar: `chmod +x build.sh` en local
- ✅ Hacer commit y push

### Problema: "404 Not Found"
- ✅ Verificar que public/ es el root directory
- ✅ Verificar que index.php existe en public/

### Problema: "Undefined variable: _SESSION"
- ✅ Verificar que session_start() se llama en bootstrap.php
- ✅ Revisar que public/index.php incluye bootstrap.php

### Problema: "Class not found"
- ✅ Verificar autoload PSR-4 en composer.json
- ✅ Ejecutar `composer install` en local y push vendor (si es necesario)

## 📊 Monitoreo en Render

Una vez desplegado:
1. Ir a Service Dashboard
2. Ver logs en tiempo real
3. Monitorear CPU/Memory
4. Configurar alertas si es necesario

## 🎯 Estado Actual

| Componente | Estado | Nota |
|-----------|--------|------|
| Schema DB | ✅ Completo | 8 tablas + triggers |
| Auth | ✅ Completo | Login/logout con sesiones |
| CRUD Views | ✅ Completo | Inventario, Ventas, Usuarios, Reportes |
| Permisos | ✅ Completo | Basados en roles |
| Auditoría | ✅ Completo | Bitácora con triggers |
| Export | ✅ Completo | Excel con filtros |
| Timeout | ✅ Completo | 1 hora inactividad |
| Despliegue | ✅ Listo | Todos los archivos configurados |

---

**¡El sistema está listo para desplegar! 🚀**

Para más información, ver `DEPLOYMENT.md` o ejecutar:
```bash
bash verify_deployment.sh
```
