# 🏢 Sistema de Ventas - SistemaVentas

Sistema integral de gestión de inventario, ventas y usuarios con auditoría completa, permisos basados en roles y despliegue en Render.

## 📋 Características Principales

✅ **Autenticación y Autorización**
- Login seguro con sesiones
- Sistema de roles y permisos granular
- Timeout automático de sesión (1 hora inactividad)
- Dashboard personalizado por rol

✅ **Gestión de Inventario**
- CRUD completo de productos
- Búsqueda y filtros avanzados
- Paginación de resultados
- Eliminación suave (soft delete)

✅ **Gestión de Ventas**
- Registro de ventas con detalles de línea
- Historial completo
- Filtros por fecha, producto, usuario
- Paginación eficiente

✅ **Gestión de Usuarios**
- CRUD de usuarios del sistema
- Asignación de roles
- Estado activo/inactivo
- Auditoría de cambios

✅ **Reportes y Exportación**
- Visualización de reportes filtrados
- Exportación a Excel (CSV/TSV)
- Reportes de auditoría

✅ **Sistema de Auditoría**
- Bitácora automática de cambios
- Triggers en todas las tablas CRUD
- Registro de usuario, acción y timestamp
- Protección de datos con soft delete

✅ **Seguridad**
- PDO prepared statements contra SQL injection
- Validación de permisos en cada vista
- Hashing SHA256 de contraseñas
- Variables de entorno para secretos

## 🏗️ Arquitectura Técnica

```
SistemaVentas/
├── src/
│   └── Models/
│       ├── BaseModel.php (modelo abstracto con auditoría)
│       ├── UserModel.php
│       ├── InventoryModel.php
│       ├── SaleModel.php
│       ├── SaleDetailModel.php
│       ├── BitacoraModel.php
│       ├── RoleModel.php
│       └── PermissionModel.php
├── public/
│   ├── index.php (router principal)
│   ├── views/ (vistas PHP)
│   ├── handlers/ (controladores POST)
│   ├── assets/
│   │   ├── css/ (estilos responsivos)
│   │   └── js/ (scripts del cliente)
│   └── reportes_export.php (exportación a Excel)
├── includes/
│   ├── permissions.php (helpers de autorización)
│   └── session_guard.php (manejo de sesiones)
├── config/
│   └── config.php (configuración global)
├── bootstrap.php (inicialización y autoload)
├── database.sql (schema y triggers)
├── composer.json (dependencias PHP)
├── render.yaml (configuración de servicios para Render)
├── Procfile (buildpack para Render)
├── build.sh (script de construcción)
├── .env.example (variables de entorno ejemplo)
├── .env.production (variables para producción)
└── DEPLOYMENT.md (guía de despliegue)
```

## 📊 Base de Datos

**Motor**: MySQL 8.0, UTF8MB4

**Tablas**:
- `Roles`: Roles del sistema (Admin, Supervisor, Vendedor, etc.)
- `Usuarios`: Usuarios del sistema con contraseña hasheada
- `Inventario`: Productos disponibles
- `Ventas`: Encabezado de ventas
- `DetallesVenta`: Línea de detalles de cada venta
- `Vistas`: Vistas del sistema (Dashboard, Inventario, etc.)
- `RolVistas`: Asignación de vistas permitidas por rol
- `Bitacora`: Registro de auditoría automático

**Características de BD**:
- Foreign keys con cascada
- Triggers automáticos en INSERT/UPDATE/DELETE
- Stored procedure `sp_log_bitacora` para auditoría
- Soft delete (campo `eliminado`)

## 🚀 Inicio Rápido - Desarrollo Local

### Requisitos
- PHP 8.1 o superior
- MySQL 8.0
- Composer (opcional, para gestionar dependencias)

### Pasos

1. **Clonar o descargar el proyecto**
```bash
cd /xampp/htdocs
git clone <tu-repo> SistemaVentas
cd SistemaVentas
```

2. **Configurar variables de entorno**
```bash
cp .env.example .env
# Editar .env con tu configuración local
```

3. **Crear base de datos**
```bash
mysql -u root -p < database.sql
```

4. **Iniciar servidor PHP**
```bash
php -S localhost:8000 -t public
```

5. **Acceder a la aplicación**
```
http://localhost:8000
```

6. **Credenciales de prueba**
- Email: `admin@test.com`
- Contraseña: `admin123`

## 🌐 Despliegue en Render

### Requisitos previos
- Repositorio Git (GitHub, GitLab, etc.)
- Cuenta en [Render](https://render.com)

### Pasos de despliegue

1. **Preparar el repositorio**
```bash
git add .
git commit -m "Preparación para Render"
git push origin main
```

2. **En Render Dashboard**
   - Click en "New" → "Web Service"
   - Conectar tu repositorio Git
   - Render detectará automáticamente `render.yaml`

3. **Configurar variables de entorno**
   
   En el dashboard de Render, agregar:
   ```
   APP_NAME=Sistema de Ventas
   APP_DEBUG=false
   APP_TIMEZONE=America/Mexico_City
   DB_HOST=tu-db.render.com
   DB_PORT=3306
   DB_DATABASE=SistemaVentas
   DB_USERNAME=usuario
   DB_PASSWORD=contraseña
   DB_CHARSET=utf8mb4
   ```

4. **Inicializar base de datos**
   - Crear servicio MySQL en Render o usar proveedor externo
   - Ejecutar `database.sql` en la nueva base de datos
   - Crear usuario admin de prueba

5. **Monitorear despliegue**
   - Ver logs en Render dashboard
   - Acceder a la URL del servicio
   - Validar que login funciona

### Troubleshooting Render

**Error: "Cannot connect to database"**
- Verificar credenciales en variables de entorno
- Confirmar que BD está accesible desde Render
- Revisar firewall/whitelist de IP

**Error: "500 Internal Server"**
- Ver logs en Render dashboard
- Verificar que `bootstrap.php` está presente
- Confirmar que autoload PSR-4 en `composer.json` es correcto

**Error: "Permission denied"**
- Ejecutar `chmod +x build.sh` en local
- Hacer commit y push

Ver `DEPLOYMENT.md` para más detalles.

## 🔐 Seguridad

### Autenticación
- Contraseñas hasheadas con SHA256
- Sesiones con ID único y timeout
- Validación de credenciales en cada login

### Autorización
- Permisos granulares basados en roles
- Verificación en cada vista y handler
- Navbar dinámico según permisos

### Auditoría
- Triggers automáticos en todas las tablas CRUD
- Registro de usuario, acción, timestamp
- Soft delete para preservar datos históricos

### Protección de datos
- PDO prepared statements contra SQL injection
- Validación de entrada en handlers
- Variables de entorno para secretos

## 📱 Frontend

**Tema**: Oscuro (dark mode)
- Colores principales: Oro (#f5b942), Cian (#4ce3f7), Verde (#3dd598), Magenta (#ff006e)
- Responsive design (mobile-first)
- Paginación JavaScript integrada

**Vistas disponibles**:
- 🏠 Dashboard: Resumen y accesos rápidos
- 📦 Inventario: CRUD de productos
- 🛒 Ventas: CRUD de ventas
- 👥 Usuarios: Gestión de usuarios
- 📊 Reportes: Visualización y exportación
- 🔐 Permisos: Gestión de roles y vistas

## 📝 API de Handlers

```
POST /public/handlers/auth_handler.php
  ├── action=login (POST: email, password)
  ├── action=logout
  └── action=register (POST: nombre, email, password)

POST /public/handlers/inventory_handler.php
  ├── action=create (POST: nombre, descripcion, precio, cantidad)
  ├── action=edit (POST: id, nombre, descripcion, precio, cantidad)
  └── action=delete (POST: id)

POST /public/handlers/sales_handler.php
  ├── action=create_sale (POST: productos[], cantidades[])
  ├── action=cancel_sale (POST: id)
  └── action=get_details (GET: id)

POST /public/handlers/users_handler.php
  ├── action=create (POST: nombre, email, rol_id)
  ├── action=edit (POST: id, nombre, email, rol_id, activo)
  └── action=delete (POST: id)

POST /public/handlers/permissions_handler.php
  ├── action=sync (POST: role_id, vista_ids[])
  └── action=get_role_views (GET: role_id)
```

## 🧪 Testing

Ejecutar en local para validar:
```bash
# 1. Login con admin
# 2. Acceder a cada vista (Inventario, Ventas, Usuarios, Reportes, Permisos)
# 3. Crear/Editar/Eliminar elementos en cada CRUD
# 4. Descargar reporte en Excel
# 5. Esperar 1 hora (o modificar timeout en session_guard.php) y verificar logout automático
# 6. Probar cambio de rol y permisos en otra ventana
```

## 📚 Archivos de Referencia

- `CHECKLIST.md` - Verificación pre-despliegue
- `DEPLOYMENT.md` - Guía completa de despliegue
- `verify_deployment.sh` - Script de verificación automática
- `.env.example` - Variables de entorno disponibles
- `database.sql` - Schema completo con triggers

## 🤝 Contribuir

1. Clonar repositorio
2. Crear rama de feature: `git checkout -b feature/nueva-funcionalidad`
3. Hacer cambios y tests
4. Commit: `git commit -am 'Agrega nueva funcionalidad'`
5. Push: `git push origin feature/nueva-funcionalidad`
6. Abrir Pull Request

## 📞 Soporte

Para problemas:
1. Revisar logs en Render dashboard
2. Ejecutar `verify_deployment.sh` para diagnosticar
3. Consultar `DEPLOYMENT.md` para troubleshooting
4. Revisar archivo `.log` en directorio `logs/`

## 📄 Licencia

Este proyecto está bajo licencia privada.

---

**Versión**: 1.0.0  
**Estado**: Listo para producción ✅  
**Última actualización**: 2024
