#!/bin/bash

# SistemaVentas - Pre-deployment Verification Script

echo "================================"
echo "Verificando preparación de Render"
echo "================================"

# Colores para output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Contadores
checks_passed=0
checks_failed=0

# Función para verificar archivos
check_file() {
    if [ -f "$1" ]; then
        echo -e "${GREEN}✓${NC} $1 existe"
        ((checks_passed++))
    else
        echo -e "${RED}✗${NC} $1 NO ENCONTRADO"
        ((checks_failed++))
    fi
}

# Función para verificar directorios
check_dir() {
    if [ -d "$1" ]; then
        echo -e "${GREEN}✓${NC} $1/ existe"
        ((checks_passed++))
    else
        echo -e "${RED}✗${NC} $1/ NO ENCONTRADO"
        ((checks_failed++))
    fi
}

echo ""
echo "Verificando archivos de configuración..."
check_file "render.yaml"
check_file "composer.json"
check_file "Procfile"
check_file ".env.production"
check_file "build.sh"
check_file "database.sql"
check_file ".gitignore"

echo ""
echo "Verificando directorios principales..."
check_dir "src"
check_dir "public"
check_dir "includes"
check_dir "config"

echo ""
echo "Verificando archivos PHP principales..."
check_file "bootstrap.php"
check_file "public/index.php"
check_file "config/config.php"

echo ""
echo "Verificando Models..."
check_file "src/Models/BaseModel.php"
check_file "src/Models/UserModel.php"
check_file "src/Models/InventoryModel.php"

echo ""
echo "Verificando vistas..."
check_file "public/views/index.php"
check_file "public/views/inventario.php"
check_file "public/views/ventas.php"
check_file "public/views/usuarios.php"
check_file "public/views/reportes.php"
check_file "public/views/permisos.php"

echo ""
echo "Verificando archivos de seguridad..."
check_file "includes/permissions.php"
check_file "includes/session_guard.php"

echo ""
echo "Verificando archivos de handlers..."
check_file "public/handlers/auth_handler.php"
check_file "public/handlers/inventory_handler.php"
check_file "public/handlers/sales_handler.php"

echo ""
echo "================================"
echo "Verificando contenido de archivos críticos..."
echo "================================"

# Verificar que render.yaml tiene contenido válido
if grep -q "services:" render.yaml && grep -q "web:" render.yaml; then
    echo -e "${GREEN}✓${NC} render.yaml contiene configuración de servicios"
    ((checks_passed++))
else
    echo -e "${RED}✗${NC} render.yaml NO tiene configuración correcta"
    ((checks_failed++))
fi

# Verificar que composer.json tiene PSR-4
if grep -q "PSR-4\|psr-4" composer.json; then
    echo -e "${GREEN}✓${NC} composer.json tiene autoload PSR-4"
    ((checks_passed++))
else
    echo -e "${RED}✗${NC} composer.json NO tiene autoload PSR-4"
    ((checks_failed++))
fi

# Verificar que build.sh es ejecutable
if [ -x "build.sh" ]; then
    echo -e "${GREEN}✓${NC} build.sh es ejecutable"
    ((checks_passed++))
else
    echo -e "${YELLOW}⚠${NC} build.sh NO es ejecutable (ejecutar: chmod +x build.sh)"
fi

# Verificar que .env.production NO está en .gitignore
if grep -q "\.env$" .gitignore && ! grep -q "\.env\.production" .gitignore; then
    echo -e "${GREEN}✓${NC} .gitignore excluye .env pero permite .env.production"
    ((checks_passed++))
else
    echo -e "${YELLOW}⚠${NC} Verificar que .env está en .gitignore"
fi

echo ""
echo "================================"
echo "Resumen"
echo "================================"
echo -e "${GREEN}Pasadas: $checks_passed${NC}"
if [ $checks_failed -gt 0 ]; then
    echo -e "${RED}Fallidas: $checks_failed${NC}"
else
    echo -e "${GREEN}Fallidas: 0${NC}"
fi

if [ $checks_failed -eq 0 ]; then
    echo ""
    echo -e "${GREEN}✓ Sistema listo para desplegar en Render${NC}"
    exit 0
else
    echo ""
    echo -e "${RED}✗ Por favor corrige los errores antes de desplegar${NC}"
    exit 1
fi
