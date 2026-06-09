# Reestructuración del Grid Bot - v14.0

## Estructura de Directorios

```
/workspace/
├── config/                 # Configuración (acceso restringido)
│   └── config.json         # Configuración principal
├── private/                # Scripts privados (no accesibles vía web)
│   ├── bot.php             # Bot principal refactorizado
│   └── websocket_server.php # Servidor WebSocket
├── public/                 # Archivos accesibles vía web
│   ├── index.php           # Dashboard principal
│   ├── grid_ajax.php       # API AJAX para el frontend
│   ├── trainer.php         # Interfaz de entrenamiento ML
│   └── save_chart.php      # Guardado de gráficos
├── data/                   # Datos y archivos temporales
│   ├── volatility_weights.json
│   └── volatility_weights_ridge.json
├── scripts/                # Scripts de utilidad
│   └── train_ml_weights.py  # Entrenamiento de modelos ML
├── src/                    # Código fuente PHP (PSR-4)
│   ├── autoload.php        # Autoloader PSR-4 + helpers
│   ├── Core/               # Clases base
│   │   ├── Config.php      # Gestión de configuración
│   │   ├── Database.php    # Conexión MySQL singleton
│   │   └── Logger.php      # Logger centralizado
│   ├── Models/             # Modelos de datos
│   │   ├── GridConfig.php  # Modelo configuración Grid
│   │   └── GridOrder.php   # Modelo órdenes Grid
│   ├── Services/           # Servicios externos
│   │   └── BybitApi.php    # Cliente API Bybit
│   └── Utils/              # Utilidades
│       └── GridConstants.php # Constantes estratégicas
├── tests/                  # Tests unitarios
│   └── BotTest.php         # Tests básicos
└── README_RESTRUCTURACION.md # Este archivo
```

## Cambios Principales

### 1. Separación de Responsabilidades
- **`config/`**: Configuración sensible separada del código
- **`private/`**: Scripts CLI no accesibles vía web
- **`public/`**: Únicos archivos expuestos al servidor web
- **`data/`**: Datos dinámicos y pesos ML
- **`src/`**: Código fuente organizado por namespaces PSR-4

### 2. Clases Refactorizadas

#### Core
- `Config`: Singleton con carga multi-ubicación
- `Database`: Singleton con reconexión automática
- `Logger`: Logger centralizado con niveles y buffer

#### Models
- `GridConfig`: Modelo tipado para configuración
- `GridOrder`: Modelo tipado para órdenes

#### Services
- `BybitApi`: Cliente HTTP con autenticación HMAC

#### Utils
- `GridConstants`: Constantes estratégicas centralizadas

### 3. Bot Refactorizado (v14.0)
El nuevo `private/bot.php` usa:
- Autoloader PSR-4
- Inyección de dependencias vía Singletons
- Modelo `GridConfig` para estado
- Clase `BybitApi` para llamadas HTTP
- Logger centralizado
- Constantes de `GridConstants`

### 4. Helpers Globales
El autoloader incluye:
- `cv($array, $keys, $default)`: Acceso anidado a arrays
- `trimRecursive($array)`: Trim recursivo de strings

## Uso

### Ejecutar Bot
```bash
cd /workspace/private
php bot.php
```

### Tests Unitarios
```bash
cd /workspace
phpunit tests/BotTest.php
```

### Configurar Nginx/Apache
Apuntar document_root a `/workspace/public/`

## Ventajas

1. **Seguridad**: Credenciales fuera del document_root
2. **Mantenibilidad**: Código organizado por responsabilidades
3. **Testabilidad**: Tests unitarios aislados
4. **Escalabilidad**: PSR-4 permite añadir clases fácilmente
5. **Reutilización**: Clases base utilizables en otros proyectos
