#!/bin/bash

# SistemaVentas - Pre-deployment Local Validation
# Este script valida que todo funciona correctamente antes de subir a Render

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║     VALIDACIÓN PRE-DESPLIEGUE SISTEMA DE VENTAS               ║"
echo "║              Render Deployment Validation                     ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Variables de color
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Contadores
CHECKS_PASSED=0
CHECKS_FAILED=0

# Función auxiliar
check_result() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✓${NC} $2"
        ((CHECKS_PASSED++))
    else
        echo -e "${RED}✗${NC} $2"
        ((CHECKS_FAILED++))
    fi
}

# Función para verificar archivo existe
file_exists() {
    if [ -f "$1" ]; then
        return 0
    else
        return 1
    fi
}

# Función para verificar directorio existe
dir_exists() {
    if [ -d "$1" ]; then
        return 0
    else
        return 1
    fi
}

# Función para buscar en archivo
contains_text() {
    if grep -q "$2" "$1" 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

echo -e "${BLUE}1. Validando estructura de directorios...${NC}"
echo ""

dir_exists "src"
check_result $? "Directorio src/ existe"

dir_exists "src/Models"
check_result $? "Directorio src/Models/ existe"

dir_exists "public"
check_result $? "Directorio public/ existe"

dir_exists "public/views"
check_result $? "Directorio public/views/ existe"

dir_exists "public/handlers"
check_result $? "Directorio public/handlers/ existe"

dir_exists "public/assets"
check_result $? "Directorio public/assets/ existe"

dir_exists "includes"
check_result $? "Directorio includes/ existe"

dir_exists "config"
check_result $? "Directorio config/ existe"

echo ""
echo -e "${BLUE}2. Validando archivos de configuración...${NC}"
echo ""

file_exists "render.yaml"
check_result $? "render.yaml existe"

file_exists "composer.json"
check_result $? "composer.json existe"

file_exists "Procfile"
check_result $? "Procfile existe"

file_exists "build.sh"
check_result $? "build.sh existe"

file_exists ".gitignore"
check_result $? ".gitignore existe"

file_exists ".env.production"
check_result $? ".env.production existe"

file_exists "database.sql"
check_result $? "database.sql existe"

echo ""
echo -e "${BLUE}3. Validando archivos PHP principales...${NC}"
echo ""

file_exists "bootstrap.php"
check_result $? "bootstrap.php existe"

file_exists "public/index.php"
check_result $? "public/index.php existe"

file_exists "config/config.php"
check_result $? "config/config.php existe"

echo ""
echo -e "${BLUE}4. Validando Models...${NC}"
echo ""

file_exists "src/Models/BaseModel.php"
check_result $? "BaseModel.php existe"

file_exists "src/Models/UserModel.php"
check_result $? "UserModel.php existe"

file_exists "src/Models/InventoryModel.php"
check_result $? "InventoryModel.php existe"

file_exists "src/Models/SaleModel.php"
check_result $? "SaleModel.php existe"

file_exists "src/Models/PermissionModel.php"
check_result $? "PermissionModel.php existe"

echo ""
echo -e "${BLUE}5. Validando vistas...${NC}"
echo ""

file_exists "public/views/index.php"
check_result $? "Dashboard (index.php) existe"

file_exists "public/views/inventario.php"
check_result $? "Inventario (inventario.php) existe"

file_exists "public/views/ventas.php"
check_result $? "Ventas (ventas.php) existe"

file_exists "public/views/usuarios.php"
check_result $? "Usuarios (usuarios.php) existe"

file_exists "public/views/reportes.php"
check_result $? "Reportes (reportes.php) existe"

file_exists "public/views/permisos.php"
check_result $? "Permisos (permisos.php) existe"

file_exists "public/views/login.php"
check_result $? "Login (login.php) existe"

echo ""
echo -e "${BLUE}6. Validando handlers...${NC}"
echo ""

file_exists "public/handlers/auth_handler.php"
check_result $? "auth_handler.php existe"

file_exists "public/handlers/inventory_handler.php"
check_result $? "inventory_handler.php existe"

file_exists "public/handlers/sales_handler.php"
check_result $? "sales_handler.php existe"

file_exists "public/handlers/users_handler.php"
check_result $? "users_handler.php existe"

echo ""
echo -e "${BLUE}7. Validando includes de seguridad...${NC}"
echo ""

file_exists "includes/permissions.php"
check_result $? "permissions.php existe"

file_exists "includes/session_guard.php"
check_result $? "session_guard.php existe"

echo ""
echo -e "${BLUE}8. Validando contenido de archivos críticos...${NC}"
echo ""

contains_text "render.yaml" "services:"
check_result $? "render.yaml tiene sección de servicios"

contains_text "render.yaml" "mysql"
check_result $? "render.yaml define servicio MySQL"

contains_text "composer.json" "psr-4\|PSR-4"
check_result $? "composer.json tiene autoload PSR-4"

contains_text "bootstrap.php" "session_start"
check_result $? "bootstrap.php inicia sesión"

contains_text "bootstrap.php" "require.*vendor/autoload\|spl_autoload_register"
check_result $? "bootstrap.php tiene autoload"

contains_text "public/index.php" "bootstrap\|autoload"
check_result $? "public/index.php incluye bootstrap"

contains_text "database.sql" "CREATE TABLE"
check_result $? "database.sql tiene definiciones de tablas"

contains_text "database.sql" "TRIGGER"
check_result $? "database.sql tiene triggers de auditoría"

echo ""
echo -e "${BLUE}9. Validando seguridad...${NC}"
echo ""

contains_text ".gitignore" "\.env"
check_result $? ".gitignore excluye .env"

file_exists ".env.production"
check_result $? ".env.production existe (para referencia)"

contains_text ".env.production" "APP_DEBUG=false"
check_result $? ".env.production tiene APP_DEBUG=false para producción"

echo ""
echo -e "${BLUE}10. Validando permisos de scripts...${NC}"
echo ""

if [ -x "build.sh" ]; then
    echo -e "${GREEN}✓${NC} build.sh es ejecutable"
    ((CHECKS_PASSED++))
else
    echo -e "${YELLOW}⚠${NC} build.sh NO es ejecutable (ejecutar: chmod +x build.sh)"
fi

echo ""
echo "╔════════════════════════════════════════════════════════════════╗"
echo "║                      RESUMEN DE VALIDACIÓN                    ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""
echo -e "Validaciones exitosas:  ${GREEN}$CHECKS_PASSED${NC}"
echo -e "Validaciones fallidas:  ${RED}$CHECKS_FAILED${NC}"
echo ""

if [ $CHECKS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ Todas las validaciones pasaron correctamente${NC}"
    echo -e "${GREEN}✓ El proyecto está listo para desplegar en Render${NC}"
    echo ""
    echo "Próximos pasos:"
    echo "  1. Hacer commit: git add . && git commit -m 'Preparado para Render'"
    echo "  2. Push a GitHub: git push origin main"
    echo "  3. Ir a https://dashboard.render.com"
    echo "  4. Crear nuevo Web Service conectando tu repositorio"
    echo ""
    echo "Ver RENDER_STEP_BY_STEP.md para instrucciones detalladas"
    exit 0
else
    echo -e "${RED}✗ Hay ${CHECKS_FAILED} error(es) que corregir antes de desplegar${NC}"
    echo ""
    echo "Por favor:"
    echo "  1. Revisar los errores marcados arriba"
    echo "  2. Crear directorios o archivos faltantes"
    echo "  3. Ejecutar nuevamente este script"
    exit 1
fi
