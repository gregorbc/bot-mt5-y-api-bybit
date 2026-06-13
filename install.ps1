# ============================================================
# Grid Bot v14.0 - Instalador para Windows (PowerShell)
# ============================================================
# Este script instala y configura el Grid Bot en Windows
# Requisitos: PHP 8.0+, MySQL/MariaDB, Git (opcional)
# ============================================================

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Grid Bot v14.0 - Instalador para Windows" -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Verificar permisos de administrador
if (!([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole] "Administrator")) {
    Write-Host "[ERROR] Este script requiere privilegios de administrador." -ForegroundColor Red
    Write-Host "Ejecuta PowerShell como administrador." -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir"
    exit 1
}

# Variables de instalación
$INSTALL_DIR = Get-Location
$PHP_EXE = "php"
$MYSQL_EXE = "mysql"
$CONFIG_FILE = Join-Path $INSTALL_DIR "config\config.json"

Write-Host "[INFO] Directorio de instalación: $INSTALL_DIR" -ForegroundColor Green
Write-Host ""

# Paso 1: Verificar PHP
Write-Host "[PASO 1/6] Verificando PHP..." -ForegroundColor Cyan
try {
    $phpPath = Get-Command $PHP_EXE -ErrorAction Stop
    $phpVersion = & $PHP_EXE -v | Select-String "PHP" | ForEach-Object { $_.Line.Split()[1] }
    Write-Host "[OK] PHP version: $phpVersion" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] PHP no encontrado en el PATH." -ForegroundColor Red
    Write-Host "Descarga PHP de https://windows.php.net/download/" -ForegroundColor Yellow
    Write-Host "O instala XAMPP/WAMP de https://www.apachefriends.org/" -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir"
    exit 1
}
Write-Host ""

# Paso 2: Verificar extensiones PHP requeridas
Write-Host "[PASO 2/6] Verificando extensiones PHP..." -ForegroundColor Cyan
$requiredExtensions = @("pdo", "pdo_mysql", "curl", "json", "mbstring", "openssl")
$missingExtensions = @()

foreach ($ext in $requiredExtensions) {
    if (!(Get-Module -ListAvailable -Name $ext)) {
        $check = & $PHP_EXE -m | Select-String -Pattern $ext
        if (!$check) {
            $missingExtensions += $ext
        }
    }
}

if ($missingExtensions.Count -gt 0) {
    Write-Host "[ADVERTENCIA] Extensiones faltantes: $($missingExtensions -join ', ')" -ForegroundColor Yellow
    Write-Host "Asegúrate de tener habilitadas en php.ini:" -ForegroundColor Yellow
    foreach ($ext in $missingExtensions) {
        Write-Host "  - extension=$ext" -ForegroundColor Yellow
    }
    $continue = Read-Host "¿Continuar de todos modos? (S/N)"
    if ($continue -ne "S" -and $continue -ne "s") {
        exit 1
    }
} else {
    Write-Host "[OK] Extensiones requeridas disponibles" -ForegroundColor Green
}
Write-Host ""

# Paso 3: Verificar MySQL
Write-Host "[PASO 3/6] Verificando MySQL..." -ForegroundColor Cyan
$MYSQL_HOST = Read-Host "Host MySQL [localhost]"
if ([string]::IsNullOrWhiteSpace($MYSQL_HOST)) { $MYSQL_HOST = "localhost" }

$MYSQL_PORT = Read-Host "Puerto MySQL [3306]"
if ([string]::IsNullOrWhiteSpace($MYSQL_PORT)) { $MYSQL_PORT = "3306" }

$MYSQL_USER = Read-Host "Usuario MySQL [root]"
if ([string]::IsNullOrWhiteSpace($MYSQL_USER)) { $MYSQL_USER = "root" }

$MYSQL_PASS = Read-Host "Contraseña MySQL" -AsSecureString
$MYSQL_PASS_PLAIN = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($MYSQL_PASS)
)
Write-Host ""

try {
    $mysqlTest = & $MYSQL_EXE --host=$MYSQL_HOST --port=$MYSQL_PORT --user=$MYSQL_USER --password=$MYSQL_PASS_PLAIN -e "SELECT 1" 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[OK] Conexión a MySQL exitosa" -ForegroundColor Green
    } else {
        throw "MySQL connection failed"
    }
} catch {
    Write-Host "[ERROR] No se pudo conectar a MySQL." -ForegroundColor Red
    Write-Host "Verifica las credenciales y que MySQL esté ejecutándose." -ForegroundColor Yellow
    Read-Host "Presiona Enter para salir"
    exit 1
}
Write-Host ""

# Paso 4: Crear base de datos
Write-Host "[PASO 4/6] Configurando base de datos..." -ForegroundColor Cyan
$DB_NAME = Read-Host "Nombre de la base de datos [gridbot_db]"
if ([string]::IsNullOrWhiteSpace($DB_NAME)) { $DB_NAME = "gridbot_db" }

$DB_USER = Read-Host "Usuario de la base de datos [gridbot_user]"
if ([string]::IsNullOrWhiteSpace($DB_USER)) { $DB_USER = "gridbot_user" }

$DB_PASS = Read-Host "Contraseña de la base de datos" -AsSecureString
$DB_PASS_PLAIN = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($DB_PASS)
)

try {
    & $MYSQL_EXE --host=$MYSQL_HOST --port=$MYSQL_PORT --user=$MYSQL_USER --password=$MYSQL_PASS_PLAIN `
        -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    
    & $MYSQL_EXE --host=$MYSQL_HOST --port=$MYSQL_PORT --user=$MYSQL_USER --password=$MYSQL_PASS_PLAIN `
        -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS_PLAIN'; GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
    
    Write-Host "[OK] Base de datos '$DB_NAME' creada" -ForegroundColor Green
    Write-Host "[OK] Usuario '$DB_USER' creado" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] No se pudo configurar la base de datos." -ForegroundColor Red
    Read-Host "Presiona Enter para salir"
    exit 1
}
Write-Host ""

# Paso 5: Configurar config.json
Write-Host "[PASO 5/6] Configurando config.json..." -ForegroundColor Cyan

if (Test-Path $CONFIG_FILE) {
    Write-Host "[INFO] Respaldando config.json existente..." -ForegroundColor Yellow
    Copy-Item $CONFIG_FILE "$CONFIG_FILE.backup" -Force
}

$BYBIT_API_KEY = Read-Host "API Key de Bybit"
$BYBIT_API_SECRET = Read-Host "API Secret de Bybit" -AsSecureString
$BYBIT_API_SECRET_PLAIN = [Runtime.InteropServices.Marshal]::PtrToStringAuto(
    [Runtime.InteropServices.Marshal]::SecureStringToBSTR($BYBIT_API_SECRET)
)

$TRADING_PAIR = Read-Host "Par de trading [BTCUSDT]"
if ([string]::IsNullOrWhiteSpace($TRADING_PAIR)) { $TRADING_PAIR = "BTCUSDT" }

$GRID_LEVELS = Read-Host "Niveles Grid [10]"
if ([string]::IsNullOrWhiteSpace($GRID_LEVELS)) { $GRID_LEVELS = "10" }

$configJson = @"
{
  "database": {
    "host": "$MYSQL_HOST",
    "port": $MYSQL_PORT,
    "name": "$DB_NAME",
    "user": "$DB_USER",
    "password": "$DB_PASS_PLAIN"
  },
  "bybit": {
    "api_key": "$BYBIT_API_KEY",
    "api_secret": "$BYBIT_API_SECRET_PLAIN",
    "testnet": false
  },
  "trading": {
    "pair": "$TRADING_PAIR",
    "grid_levels": $GRID_LEVELS,
    "investment_per_grid": 100,
    "stop_loss_pct": 5.0,
    "take_profit_pct": 2.0
  },
  "bot": {
    "check_interval_seconds": 30,
    "log_level": "INFO",
    "max_concurrent_orders": 5
  }
}
"@

Set-Content -Path $CONFIG_FILE -Value $configJson -Encoding UTF8
Write-Host "[OK] config.json configurado" -ForegroundColor Green
Write-Host ""

# Paso 6: Ejecutar tests
Write-Host "[PASO 6/6] Ejecutando verificaciones..." -ForegroundColor Cyan

try {
    $syntaxCheck = & $PHP_EXE -l src/autoload.php 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "[OK] Autoloader válido" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Error en el autoloader" -ForegroundColor Red
    }
} catch {
    Write-Host "[ERROR] No se pudo verificar el autoloader" -ForegroundColor Red
}

if (Test-Path "tests\BotTest.php") {
    Write-Host "[INFO] Tests unitarios disponibles en tests/BotTest.php" -ForegroundColor Cyan
}

Write-Host ""
Write-Host "============================================" -ForegroundColor Green
Write-Host "  ¡Instalación completada exitosamente!" -ForegroundColor Green
Write-Host "============================================" -ForegroundColor Green
Write-Host ""
Write-Host "Siguientes pasos:" -ForegroundColor Cyan
Write-Host "1. Revisa config/config.json y ajusta parámetros si es necesario"
Write-Host "2. Para iniciar el bot: cd private; php bot.php"
Write-Host "3. Para el dashboard web: apunta tu servidor web a public/"
Write-Host "4. Para el servidor WebSocket: cd private; php websocket_server.php"
Write-Host ""
Read-Host "Presiona Enter para finalizar"
