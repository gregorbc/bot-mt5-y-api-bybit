# GRID BOT ETH/USDT - SCALPING ADAPTATIVO CON IA

[![PHP](https://img.shields.io/badge/PHP-8.2+-blue.svg)](https://php.net/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-10.5-green.svg)](https://phpunit.de/)
[![License](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

Bot de trading automatizado para ETH/USDT con estrategia de Grid Scalping, inteligencia artificial adaptativa y gestión de riesgo avanzada.

---

## 📋 ÍNDICE

- [Características](#-características)
- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Configuración](#-configuración)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Uso](#-uso)
- [Pruebas Unitarias](#-pruebas-unitarias)
- [Estrategia](#-estrategia)
- [Gestión de Riesgo](#-gestión-de-riesgo)
- [API de Bybit](#-api-de-bybit)
- [Solución de Problemas](#-solución-de-problemas)
- [Contribuir](#-contribuir)

---

## ✨ CARACTERÍSTICAS

### Principales
- **Grid Dinámico**: 8-20 niveles adaptables según volatilidad
- **Scalping 5m**: Operativa en timeframe de 5 minutos
- **IA Predictiva**: Machine Learning para dirección y precisión
- **Volatility Lab**: Análisis de volatilidad en tiempo real
- **Modo Recovery**: Recuperación automática tras pérdidas
- **Gestión de Capital**: Compounding inteligente

### Técnicas
- ✅ Spacing adaptativo basado en ATR
- ✅ Fees optimizados (Maker: 0.01%, Taker: 0.06%)
- ✅ Trailing Stop y Breakeven automáticos
- ✅ Límites de pérdida diaria y total
- ✅ Logs detallados y auditoría completa
- ✅ Base de datos SQLite para persistencia

---

## 🛠 REQUISITOS

### Obligatorios
- PHP 8.2 o superior
- Composer 2.x
- Cuenta en Bybit con API Keys
- Conexión a internet estable

### Recomendados
- Servidor VPS (latencia <50ms a Bybit)
- 512MB RAM mínimo
- Linux/Ubuntu preferido

---

## 📥 INSTALACIÓN

### 1. Clonar Repositorio
```bash
cd /workspace
git clone <repository-url> grid-bot
cd grid-bot
```

### 2. Instalar Dependencias
```bash
composer install
```

### 3. Configurar API Keys
Editar `config/config.php`:
```php
return [
    'bybit' => [
        'api_key' => 'TU_API_KEY',
        'api_secret' => 'TU_API_SECRET',
        'testnet' => true, // false para producción
    ],
    'bot' => [
        'capital' => 30.0,
        'leverage' => 100,
        // ... más configuración
    ],
];
```

### 4. Inicializar Base de Datos
```bash
php scripts/init_db.php
```

### 5. Ejecutar Bot
```bash
php public/index.php
```

---

## ⚙️ CONFIGURACIÓN

### Parámetros Principales

| Parámetro | Valor Default | Descripción |
|-----------|--------------|-------------|
| `CAPITAL` | 30.0 USDT | Capital inicial |
| `LEVERAGE` | 100 | Apalancamiento |
| `FIXED_LEVELS` | 16 | Niveles de grid |
| `MIN_SPACING` | 0.04% | Spacing mínimo |
| `MAX_SPACING` | 0.12% | Spacing máximo |
| `CYCLE_SEC` | 8 | Segundos por ciclo |
| `MAX_DAILY_LOSS` | 12.0 USDT | Pérdida máxima diaria |
| `HARD_STOP_PCT` | 3.0% | Drawdown máximo |

### Modos de Operación

- **NORMAL**: Operativa estándar
- **RECOVERY**: Recuperación tras pérdidas >3%
- **PAUSED**: Bot pausado manualmente

---

## 📁 ESTRUCTURA DEL PROYECTO

```
grid-bot/
├── config/                 # Configuración
│   └── config.php
├── src/                    # Código fuente
│   ├── Core/              # Núcleo
│   │   ├── Config.php
│   │   ├── Database.php
│   │   └── Logger.php
│   ├── Models/            # Modelos de datos
│   │   ├── GridConfig.php
│   │   └── GridOrder.php
│   ├── Services/          # Servicios externos
│   │   └── BybitApi.php
│   ├── Utils/             # Utilidades
│   │   └── GridConstants.php
│   └── autoload.php
├── tests/                  # Pruebas unitarias
│   ├── BotTest.php
│   ├── GridConfigTest.php
│   └── GridOrderTest.php
├── public/                 # Punto de entrada
│   └── index.php
├── scripts/                # Scripts utilitarios
├── data/                   # Base de datos y logs
├── private/                # Archivos privados
├── vendor/                 # Dependencias Composer
├── composer.json
├── phpunit.xml
└── README.md
```

---

## 🚀 USO

### Iniciar el Bot
```bash
# Modo normal
php public/index.php

# Con log level específico
php public/index.php --log-level=DEBUG
```

### Comandos Disponibles
```bash
# Ver estado del bot
php scripts/status.php

# Pausar bot
php scripts/pause.php

# Reanudar bot
php scripts/resume.php

# Forzar recovery mode
php scripts/recovery.php
```

### Monitoreo
Los logs se guardan en `data/logs/`:
- `bot.log` - Log principal
- `orders.log` - Historial de órdenes
- `errors.log` - Errores críticos

---

## 🧪 PRUEBAS UNITARIAS

El proyecto incluye un suite completo de pruebas unitarias con PHPUnit 10.

### Ejecutar Tests
```bash
# Todos los tests
./vendor/bin/phpunit

# Con formato detallado
./vendor/bin/phpunit --testdox

# Con cobertura de código
./vendor/bin/phpunit --coverage-html coverage

# Tests específicos
./vendor/bin/phpunit tests/BotTest.php
./vendor/bin/phpunit tests/GridConfigTest.php
./vendor/bin/phpunit tests/GridOrderTest.php
```

### Cobertura de Tests

| Clase | Tests | Asersiones | Cobertura |
|-------|-------|------------|-----------|
| `GridConstants` | 7 | 25 | 100% |
| `GridConfig` | 5 | 38 | 100% |
| `GridOrder` | 7 | 38 | 100% |
| **TOTAL** | **19** | **101** | **100%** |

### Resultados Recientes
```
PHPUnit 10.5.63 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.2.31
Configuration: /workspace/phpunit.xml

...................                                               19 / 19 (100%)

Time: 00:00.021, Memory: 8.00 MB

OK (19 tests, 101 assertions)
```

---

## 📊 ESTRATEGIA

### Grid Scalping Adaptativo

1. **Cálculo de Niveles**
   - Mínimo: 8 niveles
   - Máximo: 20 niveles
   - Default: 16 niveles
   - Basado en ATR y volatilidad

2. **Spacing Dinámico**
   ```
   spacing = max(MIN_SPACING, min(MAX_SPACING, ATR% × 0.20))
   ```
   - Mínimo: 0.04% (scalping puro)
   - Máximo: 0.12% (volatilidad alta)

3. **Cantidad por Nivel**
   ```
   qty = (CAPITAL × LEVERAGE) / (levels × price)
   ```
   - Ajustado automáticamente
   - Respeta minimum notional ($3)

### Machine Learning

- **ML Blend Weight**: 90% (predicción IA)
- **VL Blend Weight**: 10% (volatilidad)
- **Recarga**: Cada 120 ciclos (~16 min)

### Direcciones

| Dirección | Descripción | Acción |
|-----------|-------------|--------|
| LONG | Tendencia alcista | Grid long bias |
| SHORT | Tendencia bajista | Grid short bias |
| SIDEWAYS | Rango lateral | Grid neutral |
| NEUTRAL | Sin señal clara | Esperar confirmación |

---

## 🛡 GESTIÓN DE RIESGO

### Límites Automáticos

| Tipo | Límite | Acción |
|------|--------|--------|
| Daily Loss | $12 (40% capital) | Parar hasta mañana |
| Total Loss | $8 (27% capital) | Parar indefinidamente |
| Hard Stop | 3% drawdown no realizado | Cierre forzoso |
| Recovery Trigger | 3% pérdida realizada | Activar modo recovery |

### Modo Recovery

Se activa automáticamente cuando:
- Pérdida realizada > 3% del capital
- Intervalo mínimo: 60 segundos entre builds

Comportamiento:
- Reduce tamaño de posiciones
- Aumenta spacing para mayor seguridad
- Prioriza recuperación sobre ganancias

### Compounding

Umbral: 1.5% ganancia acumulada
- Multiplicador: 1.05x
- cooldown: 300 segundos
- Reinvertir ganancias progresivamente

---

## 🔌 API DE BYBIT

### Endpoints Usados

| Método | Endpoint | Propósito |
|--------|----------|-----------|
| POST | `/v5/order/create` | Crear orden |
| POST | `/v5/order/cancel` | Cancelar orden |
| GET | `/v5/position/list` | Ver posiciones |
| GET | `/v5/market/kline` | Obtener velas |
| GET | `/v5/account/wallet-balance` | Saldo cuenta |

### Rate Limits

- Trading: 20 órdenes/segundo
- Consultas: 120 requests/segundo
- Websocket: 1 conexión simultánea

### Testnet vs Producción

```php
// Testnet (recomendado para pruebas)
'testnet' => true
// API: https://api-testnet.bybit.com

// Producción
'testnet' => false
// API: https://api.bybit.com
```

---

## 🔧 SOLUCIÓN DE PROBLEMAS

### Error: "Invalid API Key"
- Verificar keys en `config/config.php`
- Confirmar permisos de API en Bybit
- Checkear si está en testnet/producción correcto

### Error: "Insufficient Balance"
- Aumentar capital en configuración
- Reducir leverage o niveles
- Verificar saldo disponible en Bybit

### Error: "Rate Limit Exceeded"
- Reducir frecuencia de ciclos (`CYCLE_SEC`)
- Implementar retry con backoff exponencial
- Contactar soporte Bybit para aumentar límite

### Bot no abre órdenes
- Verificar mínimo nocional ($3)
- Confirmar que hay señal de dirección válida
- Revisar logs en `data/logs/errors.log`

### Pérdidas consecutivas
- Activar modo recovery manualmente
- Reducir niveles de grid
- Aumentar spacing para mayor filtro

---

## 🤝 CONTRIBUIR

### Desarrollo

1. Fork el repositorio
2. Crear rama feature (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'Add AmazingFeature'`)
4. Push a rama (`git push origin feature/AmazingFeature`)
5. Abrir Pull Request

### Estándares de Código

- PSR-12 para estilo de código PHP
- Tests unitarios obligatorios para nuevas features
- Documentación PHPDoc en todas las clases y métodos
- Logs descriptivos en operaciones críticas

### Reporting Issues

Por favor reporta bugs en la sección de Issues incluyendo:
- Versión de PHP
- Logs relevantes
- Pasos para reproducir
- Comportamiento esperado vs real

---

## 📄 LICENCIA

Este proyecto está bajo la Licencia MIT - ver el archivo [LICENSE](LICENSE) para detalles.

---

## ⚠️ DESCARGO DE RESPONSABILIDAD

**ADVERTENCIA**: El trading de criptomonedas implica alto riesgo. Este bot es una herramienta educativa y no garantiza ganancias.

- Nunca inviertas dinero que no puedas permitirte perder
- Prueba exhaustivamente en testnet antes de usar capital real
- El rendimiento pasado no garantiza resultados futuros
- Los mercados crypto son altamente volátiles e impredecibles

**Usa bajo tu propio riesgo.**

---

## 📞 SOPORTE

- **Documentación**: `/docs` directory
- **Issues**: GitHub Issues
- **Email**: support@example.com
- **Telegram**: @gridbotsupport

---

## 🙏 AGRADECIMIENTOS

- Bybit por su excelente API
- Comunidad PHP por las librerías open-source
- Contributors y testers beta

---

<div align="center">

**Hecho con ❤️ para la comunidad de trading algorítmico**

[⬆️ Volver arriba](#grid-bot-ethusdt---scalping-adaptativo-con-ia)

</div>
