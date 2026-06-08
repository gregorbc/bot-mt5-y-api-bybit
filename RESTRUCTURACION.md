# Grid Bot ETH/USDT - Código Reestructurado

## Nueva Estructura del Proyecto

```
/workspace/
├── src/
│   ├── autoload.php          # Autoloader PSR-4 y funciones helper
│   ├── Core/
│   │   ├── Config.php        # Gestión centralizada de configuración
│   │   ├── Database.php      # Conexión MySQL con singleton
│   │   └── Logger.php        # Logger con niveles y colores
│   ├── Services/
│   │   └── BybitApi.php      # Cliente API Bybit autenticado
│   ├── Models/
│   │   ├── GridConfig.php    # Modelo de configuración de grid
│   │   └── GridOrder.php     # Modelo de órdenes de grid
│   └── Utils/
│       └── GridConstants.php # Constantes estratégicas del bot
├── public/                    # Archivos accesibles vía web
├── private/                   # Archivos sensibles (fuera de HTTP)
├── config/
│   └── config.json           # Configuración principal
├── scripts/                   # Scripts de mantenimiento
├── data/
│   ├── logs/                 # Logs rotados
│   └── models/               # Modelos ML guardados
├── tests/                     # Tests unitarios
├── bot.php                    # Script principal del bot (CLI)
├── index.php                  # Dashboard web
├── grid_ajax.php             # Backend AJAX para el dashboard
├── trainer.php               # Interfaz de entrenamiento ML
├── train_ml_weights.py       # Script Python de entrenamiento
├── websocket_server.php      # Servidor WebSocket
└── save_chart.php            # Endpoint para guardar gráficos
```

## Uso del Autoloader

```php
<?php
require_once __DIR__ . '/src/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\BybitApi;
use App\Models\GridConfig;
use App\Models\GridOrder;
use App\Utils\GridConstants;

// Obtener instancia de configuración
$config = Config::getInstance();
$apiKey = $config->getBybitKey();

// Conectar a base de datos
$db = Database::getInstance();
$rows = $db->query("SELECT * FROM grid_configs WHERE symbol = ?", ['ETHUSDT']);

// Logging
$logger = Logger::getInstance();
$logger->info('Bot iniciado', ['symbol' => GridConstants::SYM]);

// API Bybit
$bybit = new BybitApi();
$ticker = $bybit->getTicker('ETHUSDT');
$klines = $bybit->getAllKlines('ETHUSDT', '5', 1000);
```

## Constantes Disponibles

```php
use App\Utils\GridConstants;

GridConstants::SYM           // 'ETHUSDT'
GridConstants::CAPITAL       // 30.0
GridConstants::LEVERAGE      // 100
GridConstants::MIN_SPACING   // 0.0004 (0.04%)
GridConstants::MAX_SPACING   // 0.0012 (0.12%)
GridConstants::MAKER_FEE     // 0.0001
GridConstants::TAKER_FEE     // 0.0006
```

## Migración desde la Versión Anterior

El código existente en `bot.php`, `grid_ajax.php`, etc. puede mantenerse 
funcional mientras se migra gradualmente a la nueva estructura orientada 
a objetos. Las funciones helper `cv()` y `trimRecursive()` están disponibles 
globalmente mediante el autoloader.

## Próximos Pasos

1. **Refactorizar bot.php** para usar las nuevas clases
2. **Refactorizar grid_ajax.php** para usar los modelos
3. **Crear tests unitarios** en la carpeta tests/
4. **Añadir más servicios**: MLService, NotificationService
5. **Documentar endpoints** de la API

## Requisitos

- PHP 8.0+
- MySQL 5.7+
- cURL extension
- PDO MySQL extension
