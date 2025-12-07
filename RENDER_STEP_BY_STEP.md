# 🚀 GUÍA PASO A PASO: SUBIR A RENDER

## PASO 1: Preparación Local (5 minutos)

### 1.1 Verificar que todo esté en orden
```powershell
# En PowerShell, desde la carpeta del proyecto
ls -Force  # Verificar que todos los archivos existen

# Archivos críticos que deben estar presentes:
# - render.yaml
# - composer.json
# - Procfile
# - build.sh
# - database.sql
# - .gitignore
# - bootstrap.php
# - public/index.php
```

### 1.2 Asegurar que build.sh es ejecutable (importante para Linux en Render)
```powershell
# Desde bash/gitbash:
bash -c "chmod +x build.sh"
```

### 1.3 Verificar configuración de .env
```powershell
# Asegurar que .env local está en .gitignore
cat .gitignore | findstr ".env"

# Resultado esperado: .env (pero NO .env.production)
```

### 1.4 Configurar Git (primera vez solamente)
```powershell
git config user.email "tu@email.com"
git config user.name "Tu Nombre"
git init  # Si aún no está inicializado
```

## PASO 2: Hacer Push a GitHub (5 minutos)

### 2.1 Agregar todos los archivos a Git
```powershell
git add .
git status  # Verificar que NO incluye .env (local)
```

### 2.2 Crear commit
```powershell
git commit -m "Preparación para despliegue en Render - Sistema de Ventas"
```

### 2.3 Crear repositorio en GitHub
1. Ir a https://github.com/new
2. Nombre: `SistemaVentas`
3. Descripción: `Sistema integral de ventas, inventario y usuarios`
4. Privado o Público (recomendado: Privado)
5. Click en "Create repository"

### 2.4 Agregar origen remoto y hacer push
```powershell
# Copiar la URL del repositorio (HTTPS)
# Luego ejecutar:
git remote add origin https://github.com/TU_USUARIO/SistemaVentas.git
git branch -M main
git push -u origin main

# Verificar en GitHub que los archivos aparecen
```

## PASO 3: Preparar Base de Datos en Render (10 minutos)

### 3.1 Crear servicio MySQL en Render
1. Ir a https://dashboard.render.com
2. Click en "New +" → "Database" → "MySQL"
3. Configurar:
   - **Name**: `sistema-ventas-db`
   - **Database Name**: `SistemaVentas`
   - **Username**: `ventas_user`
   - **Region**: Elegir según tu ubicación
4. Click en "Create Database"
5. Esperar 2-3 minutos a que se cree

### 3.2 Obtener credenciales de conexión
1. Una vez creado, ir a "Connections"
2. Copiar:
   - External Database URL (para herramientas externas)
   - Host, Database, Username, Password (notar son diferentes)

### 3.3 Crear la estructura de BD (schema)
**Opción A: Usar MySQL CLI (recomendado)**
1. Instalar MySQL client si no lo tienes:
   ```powershell
   # En Windows, descargar de https://dev.mysql.com/downloads/mysql/
   # O usar WSL: wsl sudo apt-get install mysql-client
   ```

2. Conectarse a BD de Render:
   ```powershell
   mysql -h <HOST_DE_RENDER> -u <USERNAME> -p -D SistemaVentas
   # Pedir contraseña (PASSWORD_DE_RENDER)
   ```

3. Ejecutar schema:
   ```sql
   # Copiar contenido completo de database.sql
   # Pegarrlo en MySQL client
   # O:
   source C:\xampp\htdocs\SistemaVentas\database.sql
   ```

**Opción B: Usar herramienta visual (alternativa)**
1. Descargar MySQL Workbench o DBeaver
2. Crear conexión SSH tunnel a BD de Render
3. Abrir database.sql e ejecutar

## PASO 4: Crear Web Service en Render (10 minutos)

### 4.1 Acceder a Render Dashboard
1. Ir a https://dashboard.render.com
2. Hacer login con cuenta de Render

### 4.2 Crear servicio web
1. Click en "New +" → "Web Service"
2. Elegir repositorio (conectar GitHub si es primera vez)
3. Seleccionar `SistemaVentas`
4. Configurar:
   - **Name**: `sistema-ventas`
   - **Environment**: `PHP`
   - **Region**: Misma que la BD
   - **Branch**: `main`
   - **Build Command**: `composer install`
   - **Start Command**: `php -S 0.0.0.0:$PORT -t public`
   - **Plan**: Free (o pagado según necesidad)

### 4.3 Agregar variables de entorno
Antes de hacer click en "Create Web Service", ir a "Advanced":

1. Click en "Add Environment Variable"
2. Agregar cada una de estas:

```
APP_NAME = Sistema de Ventas
APP_DEBUG = false
APP_TIMEZONE = America/Mexico_City
APP_URL = /
DB_HOST = <HOST_DE_RENDER_BD>
DB_PORT = 3306
DB_DATABASE = SistemaVentas
DB_USERNAME = <USERNAME_DE_RENDER>
DB_PASSWORD = <PASSWORD_DE_RENDER>
DB_CHARSET = utf8mb4
```

⚠️ **IMPORTANTE**: Las credenciales de DB deben ser exactas (copiar del panel de conexión de la BD)

### 4.4 Crear servicio
1. Verificar que todo está correcto
2. Click en "Create Web Service"
3. Esperar 3-5 minutos a que se construya

## PASO 5: Monitorear Construcción (5 minutos)

### 5.1 Ver logs de construcción
1. En el panel de Render, ir a "Logs"
2. Esperar a ver:
   ```
   ✓ Build successful
   ✓ Server started on port XXX
   ```

### 5.2 Si hay errores
Errores comunes:

**Error: "composer install: not found"**
- Verificar que `composer.json` está en raíz del proyecto
- Verificar que autoload PSR-4 está correcto

**Error: "Cannot connect to database"**
- Verificar credenciales de BD en env vars
- Verificar que BD de Render está accesible
- Ejecutar `database.sql` nuevamente en BD

**Error: "build.sh: Permission denied"**
- Ejecutar en local: `bash -c "chmod +x build.sh"`
- Hacer commit y push

## PASO 6: Acceder a la Aplicación (2 minutos)

### 6.1 Obtener URL
1. En panel de Render, copiar URL del servicio
   (Algo como: `https://sistema-ventas.onrender.com`)

### 6.2 Acceder en navegador
1. Ir a `https://sistema-ventas.onrender.com`
2. Debería ver página de login

### 6.3 Login inicial
1. Email: `admin@test.com`
2. Contraseña: `admin123`
3. Click "Entrar"

## PASO 7: Validación Post-Despliegue (10 minutos)

### Checklist de validación:
- [ ] Página de login carga correctamente
- [ ] Login con admin@test.com / admin123 funciona
- [ ] Dashboard carga sin errores
- [ ] Menú de navegación muestra vistas permitidas
- [ ] Inventario: Crear, Editar, Eliminar productos
- [ ] Ventas: Ver y crear ventas
- [ ] Usuarios: Gestionar usuarios
- [ ] Reportes: Descargar Excel
- [ ] Permisos: Gestionar roles
- [ ] Logout funciona
- [ ] Timeout de sesión (esperar 1 hora de inactividad)

## PASO 8: Problemas y Soluciones

### Problema: Página blanca
**Solución**:
1. Ir a logs en Render
2. Buscar error PHP
3. Verificar que bootstrap.php incluye autoload

### Problema: 500 Server Error
**Solución**:
1. Ver logs en Render dashboard
2. Revisar variables de entorno
3. Verificar que database.sql fue ejecutado

### Problema: Login no funciona
**Solución**:
1. Verificar que usuario `admin@test.com` existe en BD
2. Ejecutar script de seed en BD:
```sql
INSERT INTO Usuarios (nombre, email, contraseña, rol_id, activo) 
VALUES ('Admin', 'admin@test.com', SHA2('admin123', 256), 1, 1);
```

### Problema: Página es lenta
**Solución**:
1. Plan free de Render es lento, considerar plan pagado
2. Optimizar queries en Models
3. Agregar índices en BD

## PASO 9: Monitoreo Continuo

### En Render Dashboard:
1. **Logs**: Ver en tiempo real
2. **Metrics**: CPU, Memory, Network
3. **Alerts**: Configurar notificaciones
4. **Auto-Deploy**: Activar para deploy automático en cada push

### Comandos útiles:
```powershell
# Ver logs del servicio
curl https://sistema-ventas.onrender.com/

# Si tienes ssh en Render (plan pagado):
ssh render@sistema-ventas.onrender.com
```

## ✅ ¡LISTO!

Tu aplicación está desplegada en Render. 

**URL de acceso**: `https://sistema-ventas.onrender.com`

Para cambios futuros:
1. Modificar código localmente
2. `git add .` y `git commit -m "descripción"`
3. `git push origin main`
4. Render auto-despliega automáticamente (si auto-deploy está activado)

---

**Problemas?** Revisar `DEPLOYMENT.md` o `README.md` para más detalles.
