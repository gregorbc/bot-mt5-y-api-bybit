# Grid Bot v14.0 - Guía de Instalación para Windows

## Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

1. **PHP 8.0 o superior**
   - Descarga desde: https://windows.php.net/download/
   - O instala XAMPP/WAMP: https://www.apachefriends.org/
   - Asegúrate de agregar PHP al PATH del sistema

2. **MySQL o MariaDB**
   - Descarga MySQL: https://dev.mysql.com/downloads/mysql/
   - O usa el incluido en XAMPP/WAMP

3. **Extensiones PHP requeridas** (habilitar en php.ini):
   ```ini
   extension=pdo_mysql
   extension=curl
   extension=json
   extension=mbstring
   extension=openssl
   ```

## Métodos de Instalación

### Método 1: Usando PowerShell (Recomendado)

1. **Abre PowerShell como Administrador**:
   - Click derecho en Inicio → Windows PowerShell (Admin)

2. **Navega al directorio del proyecto**:
   ```powershell
   cd C:\ruta\a\grid-bot
   ```

3. **Ejecuta el instalador**:
   ```powershell
   .\install.ps1
   ```

   Si recibes un error de ejecución de scripts:
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

### Método 2: Usando CMD (Batch)

1. **Abre CMD como Administrador**:
   - Click derecho en Símbolo del sistema → Ejecutar como administrador

2. **Navega al directorio del proyecto**:
   ```cmd
   cd C:\ruta\a\grid-bot
   ```

3. **Ejecuta el instalador**:
   ```cmd
   install.bat
   ```

## Proceso de Instalación

El instalador realizará los siguientes pasos automáticamente:

1. ✅ Verificación de PHP y versión
2. ✅ Verificación de extensiones PHP requeridas
3. ✅ Conexión a MySQL
4. ✅ Creación de base de datos y usuario
5. ✅ Configuración de `config/config.json`
6. ✅ Verificación de archivos del proyecto

## Configuración Manual (Opcional)

Si prefieres configurar manualmente, edita `config/config.json`:

```json
{
  "database": {
    "host": "localhost",
    "port": 3306,
    "name": "gridbot_db",
    "user": "gridbot_user",
    "password": "tu_contraseña"
  },
  "bybit": {
    "api_key": "tu_api_key",
    "api_secret": "tu_api_secret",
    "testnet": false
  },
  "trading": {
    "pair": "BTCUSDT",
    "grid_levels": 10,
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
```

## Uso del Bot

### Iniciar el Bot de Trading

```cmd
cd private
php bot.php
```

### Iniciar el Servidor WebSocket

```cmd
cd private
php websocket_server.php
```

### Acceder al Dashboard Web

1. Configura tu servidor web (Apache/Nginx/IIS) para apuntar al directorio `public/`
2. Abre tu navegador en `http://localhost`

**Ejemplo con PHP built-in server** (solo desarrollo):
```cmd
cd public
php -S localhost:8000
```

## Solución de Problemas

### Error: "PHP no encontrado"

- Agrega PHP al PATH de Windows:
  1. Panel de Control → Sistema → Configuración avanzada
  2. Variables de entorno → Path → Editar
  3. Agrega la ruta a PHP (ej: `C:\php`)

### Error: "extension pdo_mysql not found"

- Edita `php.ini` y descomenta:
  ```ini
  extension=pdo_mysql
  ```
- Reinicia el servidor web

### Error: "No se pudo conectar a MySQL"

- Verifica que MySQL esté ejecutándose:
  ```cmd
  net start MySQL
  ```
- Verifica las credenciales en `config/config.json`

### Error: "Permission denied" en Windows

- Ejecuta PowerShell/CMD como Administrador
- Verifica permisos en carpetas `data/` y `config/`

## Estructura de Directorios

```
grid-bot/
├── config/                 # Configuración (¡no compartir!)
│   └── config.json         # Tu configuración
├── private/                # Scripts privados (CLI)
│   ├── bot.php             # Bot principal
│   └── websocket_server.php
├── public/                 # Accesible vía web
│   ├── index.php           # Dashboard
│   ├── grid_ajax.php       # API
│   └── ...
├── src/                    # Código fuente PHP
│   ├── Core/               # Clases base
│   ├── Models/             # Modelos de datos
│   ├── Services/           # Servicios externos
│   └── Utils/              # Utilidades
├── data/                   # Datos y logs
├── tests/                  # Tests unitarios
├── install.bat             # Instalador CMD
└── install.ps1             # Instalador PowerShell
```

## Seguridad

⚠️ **Importante**:

- Nunca compartas `config/config.json` (contiene credenciales)
- El directorio `private/` NO debe ser accesible vía web
- Solo el directorio `public/` debe estar expuesto
- Usa HTTPS en producción
- Rota tus API keys regularmente

## Actualización

Para actualizar desde una versión anterior:

1. Haz backup de `config/config.json`
2. Extrae la nueva versión
3. Restaura `config/config.json`
4. Ejecuta: `php tests/BotTest.php` para verificar

## Soporte

Para reportar errores o solicitar ayuda:

1. Revisa los logs en `data/logs/`
2. Ejecuta tests: `php tests/BotTest.php`
3. Verifica la configuración en `config/config.json`

---

**Grid Bot v14.0** - Trading automatizado con estrategia Grid
