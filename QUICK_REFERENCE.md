# 🚀 REFERENCIA RÁPIDA - RENDER DEPLOYMENT

## En 5 Minutos

```powershell
# 1. Validar
bash validate_before_deploy.sh

# 2. Commit
git add .
git commit -m "Listo para Render"

# 3. Push
git push origin main

# 4. Ir a https://dashboard.render.com
# 5. New Web Service
# 6. Conectar GitHub
# 7. Agregar env vars (ver .env.production)
# 8. Deploy ✓
```

## Variables de Entorno Requeridas

```
APP_NAME              = Sistema de Ventas
APP_DEBUG             = false
APP_TIMEZONE          = America/Mexico_City
APP_URL               = /
DB_HOST               = (de Render MySQL)
DB_PORT               = 3306
DB_DATABASE           = SistemaVentas
DB_USERNAME           = (de Render MySQL)
DB_PASSWORD           = (de Render MySQL)
DB_CHARSET            = utf8mb4
```

## Checklist Final

- [ ] `render.yaml` existe
- [ ] `composer.json` existe
- [ ] `build.sh` es ejecutable (`chmod +x build.sh`)
- [ ] `.gitignore` excluye `.env`
- [ ] `database.sql` existe
- [ ] Bootstrap.php incluye autoload
- [ ] Todos los Models existen
- [ ] Todas las vistas existen
- [ ] Handlers existen
- [ ] `.env.production` tiene APP_DEBUG=false
- [ ] Git push completado
- [ ] BD MySQL creada en Render
- [ ] `database.sql` ejecutado en BD

## URL de Referencia

- **Render Dashboard**: https://dashboard.render.com
- **Render Documentation**: https://render.com/docs
- **GitHub (tu repo)**: https://github.com/tu_usuario/SistemaVentas

## Credenciales de Prueba

```
Email: admin@test.com
Password: admin123
```

## Archivos Importantes

| Archivo | Propósito |
|---------|-----------|
| `RENDER_STEP_BY_STEP.md` | 👈 Lee esto PRIMERO |
| `DEPLOYMENT.md` | Detalles técnicos |
| `validate_before_deploy.sh` | Script de validación |
| `CHECKLIST.md` | Verificación |
| `README.md` | Documentación general |

## SOS - Errores Rápidos

**"Cannot connect to database"**
→ Verificar DB credenciales en Render env vars

**"500 error"**
→ Ver logs en Render dashboard

**"Permission denied build.sh"**
→ Ejecutar: `bash -c "chmod +x build.sh"` y push

**"Class not found"**
→ Verificar autoload PSR-4 en composer.json

## Post-Despliegue

1. ✅ Acceder a: `https://sistema-ventas.onrender.com` (o tu URL)
2. ✅ Login: `admin@test.com` / `admin123`
3. ✅ Probar: Inventario, Ventas, Usuarios, Reportes
4. ✅ Exportar: Descargar Excel desde Reportes
5. ✅ Timeout: Esperar 1 hora de inactividad o editar `session_guard.php`

## Deploy Automático (Futuro)

Después del primer despliegue:
- Git push automáticamente redeploya
- Ver en Render dashboard → Activity

## Monitoreo

```
Render Dashboard
├── Logs (tiempo real)
├── Metrics (CPU, Memory)
├── Environment (vars)
└── Settings (config)
```

---

**¿Listo?** 👉 Abre `RENDER_STEP_BY_STEP.md` y sigue cada paso.

**¿Ayuda?** 👉 Ver `DEPLOYMENT.md` para troubleshooting.
