@echo off
setlocal EnableDelayedExpansion

:: ============================================================
:: Grid Bot v14.0 - Instalador para Windows
:: ============================================================
:: Este script instala y configura el Grid Bot en Windows
:: Requisitos: PHP 8.0+, MySQL/MariaDB, Git (opcional)
:: ============================================================

title Grid Bot v14.0 - Instalador para Windows
color 0A

echo.
echo ============================================
echo   Grid Bot v14.0 - Instalador para Windows
echo ============================================
echo.

:: Verificar permisos de administrador
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] Este script requiere privilegios de administrador.
    echo Haz clic derecho y ejecuta como administrador.
    pause
    exit /b 1
)

:: Variables de instalación
set INSTALL_DIR=%CD%
set PHP_EXE=php
set MYSQL_EXE=mysql
set CONFIG_FILE=%INSTALL_DIR%\config\config.json

echo [INFO] Directorio de instalación: %INSTALL_DIR%
echo.

:: Paso 1: Verificar PHP
echo [PASO 1/6] Verificando PHP...
where %PHP_EXE% >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] PHP no encontrado en el PATH.
    echo Descarga PHP de https://windows.php.net/download/
    echo O instala XAMPP/WAMP de https://www.apachefriends.org/
    pause
    exit /b 1
)

for /f "tokens=3" %%i in ('%PHP_EXE% -v ^| findstr /i "PHP"') do set PHP_VERSION=%%i
echo [OK] PHP version: %PHP_VERSION%
echo.

:: Paso 2: Verificar extensiones PHP requeridas
echo [PASO 2/6] Verificando extensiones PHP...
%PHP_EXE% -m | findstr /i "pdo pdo_mysql curl json mbstring openssl" >nul 2>&1
if %errorLevel% neq 0 (
    echo [ADVERTENCIA] Algunas extensiones PHP pueden faltar.
    echo Asegúrate de tener habilitadas en php.ini:
    echo   - extension=pdo_mysql
    echo   - extension=curl
    echo   - extension=json
    echo   - extension=mbstring
    echo   - extension=openssl
    echo.
    set /p CONTINUE="¿Continuar de todos modos? (S/N): "
    if /i not "!CONTINUE!"=="S" exit /b 1
) else (
    echo [OK] Extensiones requeridas disponibles
)
echo.

:: Paso 3: Verificar MySQL
echo [PASO 3/6] Verificando MySQL...
set /p MYSQL_HOST="Host MySQL [localhost]: " || set MYSQL_HOST=localhost
set /p MYSQL_PORT="Puerto MySQL [3306]: " || set MYSQL_PORT=3306
set /p MYSQL_USER="Usuario MySQL [root]: " || set MYSQL_USER=root
set /p MYSQL_PASS="Contraseña MySQL: "
echo.

%MYSQL_EXE% --host=%MYSQL_HOST% --port=%MYSQL_PORT% --user=%MYSQL_USER% --password=%MYSQL_PASS% -e "SELECT 1" >nul 2>&1
if %errorLevel% neq 0 (
    echo [ERROR] No se pudo conectar a MySQL.
    echo Verifica las credenciales y que MySQL esté ejecutándose.
    pause
    exit /b 1
)
echo [OK] Conexión a MySQL exitosa
echo.

:: Paso 4: Crear base de datos
echo [PASO 4/6] Configurando base de datos...
set /p DB_NAME="Nombre de la base de datos [gridbot_db]: " || set DB_NAME=gridbot_db
set /p DB_USER="Usuario de la base de datos [gridbot_user]: " || set DB_USER=gridbot_user
set /p DB_PASS="Contraseña de la base de datos: "

%MYSQL_EXE% --host=%MYSQL_HOST% --port=%MYSQL_PORT% --user=%MYSQL_USER% --password=%MYSQL_PASS% -e "CREATE DATABASE IF NOT EXISTS %DB_NAME% CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if %errorLevel% neq 0 (
    echo [ERROR] No se pudo crear la base de datos.
    pause
    exit /b 1
)

%MYSQL_EXE% --host=%MYSQL_HOST% --port=%MYSQL_PORT% --user=%MYSQL_USER% --password=%MYSQL_PASS% -e "CREATE USER IF NOT EXISTS '%DB_USER%'@'localhost' IDENTIFIED BY '%DB_PASS%'; GRANT ALL PRIVILEGES ON %DB_NAME%.* TO '%DB_USER%'@'localhost'; FLUSH PRIVILEGES;"
if %errorLevel% neq 0 (
    echo [ERROR] No se pudo crear el usuario de la base de datos.
    pause
    exit /b 1
)
echo [OK] Base de datos '%DB_NAME%' creada
echo [OK] Usuario '%DB_USER%' creado
echo.

:: Paso 5: Configurar config.json
echo [PASO 5/6] Configurando config.json...
if exist "%CONFIG_FILE%" (
    echo [INFO] Respaldando config.json existente...
    copy "%CONFIG_FILE%" "%CONFIG_FILE%.backup" >nul
)

set /p BYBIT_API_KEY="API Key de Bybit: "
set /p BYBIT_API_SECRET="API Secret de Bybit: "
set /p TRADING_PAIR="Par de trading [BTCUSDT]: " || set TRADING_PAIR=BTCUSDT
set /p GRID_LEVELS="Niveles Grid [10]: " || set GRID_LEVELS=10

(
    echo {
    echo   "database": {
    echo     "host": "%MYSQL_HOST%",
    echo     "port": %MYSQL_PORT%,
    echo     "name": "%DB_NAME%",
    echo     "user": "%DB_USER%",
    echo     "password": "%DB_PASS%"
    echo   },
    echo   "bybit": {
    echo     "api_key": "%BYBIT_API_KEY%",
    echo     "api_secret": "%BYBIT_API_SECRET%",
    echo     "testnet": false
    echo   },
    echo   "trading": {
    echo     "pair": "%TRADING_PAIR%",
    echo     "grid_levels": %GRID_LEVELS%,
    echo     "investment_per_grid": 100,
    echo     "stop_loss_pct": 5.0,
    echo     "take_profit_pct": 2.0
    echo   },
    echo   "bot": {
    echo     "check_interval_seconds": 30,
    echo     "log_level": "INFO",
    echo     "max_concurrent_orders": 5
    echo   }
    echo }
) > "%CONFIG_FILE%"

echo [OK] config.json configurado
echo.

:: Paso 6: Ejecutar tests
echo [PASO 6/6] Ejecutando verificaciones...
cd /d "%INSTALL_DIR%"
%PHP_EXE% -l src/autoload.php >nul 2>&1
if %errorLevel% equ 0 (
    echo [OK] Autoloader válido
) else (
    echo [ERROR] Error en el autoloader
)

if exist "tests\BotTest.php" (
    echo [INFO] Tests unitarios disponibles en tests/BotTest.php
)

echo.
echo ============================================
echo   ¡Instalación completada exitosamente!
echo ============================================
echo.
echo Siguientes pasos:
echo 1. Revisa config/config.json y ajusta parámetros si es necesario
echo 2. Para iniciar el bot: cd private ^&^& php bot.php
echo 3. Para el dashboard web: apunta tu servidor web a public/
echo 4. Para el servidor WebSocket: cd private ^&^& php websocket_server.php
echo.
pause
