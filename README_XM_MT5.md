# Grid Bot para XM Global (MT5) - ETHUSD#

[![MT5](https://img.shields.io/badge/Plataforma-MT5-blue)](https://www.metatrader5.com/)
[![Broker](https://img.shields.io/badge/Broker-XM%20Global-green)](https://www.xm.com/)
[![Símbolo](https://img.shields.io/badge/Símbolo-ETHUSD%23-orange)]()
[![Licencia](https://img.shields.io/badge/Licencia-MIT-yellow)]()

## 📋 Descripción

Expert Advisor (EA) de **Grid Trading** diseñado específicamente para operar el par **ETHUSD#** en el broker **XM Global** mediante la plataforma **MetaTrader 5**.

Este bot implementa una estrategia de grid que coloca órdenes de compra y venta a intervalos regulares, aprovechando la volatilidad del mercado de Ethereum para generar beneficios en movimientos laterales.

---

## ⚠️ ADVERTENCIA DE SEGURIDAD IMPORTANTE

> **NUNCA compartas tus credenciales de trading:**
> - Número de cuenta: `101809995`
> - Servidor: `XMGlobalSC-MT5`
> - Contraseña
> 
> **Acciones inmediatas recomendadas:**
> 1. Cambia tu contraseña de trading inmediatamente
> 2. Habilita autenticación de dos factores (2FA) en tu cuenta XM
> 3. Nunca compartas estos datos en foros públicos o chats
> 
> Este código usa el número de cuenta como "Magic Number" para identificar operaciones, **NO** como credencial de acceso.

---

## ✨ Características Principales

### 🎯 Estrategia Grid
- **Doble Dirección**: Opera simultáneamente en compras (BUY) y ventas (SELL)
- **Grid Dinámico**: Coloca órdenes cada X puntos de distancia
- **Take Profit Automático**: Cierra grupos de órdenes al alcanzar beneficio
- **Reinicio Inteligente**: Reinicia el grid después de tomar ganancias

### 🛡 Gestión de Riesgo
- **Stop Loss Global**: Cierra todo si las pérdidas superan $X USD
- **Límite de Niveles**: Máximo de órdenes abiertas simultáneas configurable
- **Filtro de Tiempo**: Opera solo en horarios específicos (opcional)
- **Magic Number Único**: Identifica solo las operaciones de este bot

### 📊 Información en Tiempo Real
- Panel informativo en el gráfico
- Beneficio actual acumulado
- Número de posiciones activas
- Precios de entrada actuales

---

## 🛠 Requisitos

| Requisito | Detalle |
|-----------|---------|
| **Plataforma** | MetaTrader 5 (MT5) |
| **Broker** | XM Global (u otro que ofrezca ETHUSD) |
| **Símbolo** | ETHUSD# (o par ETH similar) |
| **Cuenta** | Cuenta real o demo en MT5 |
| **Conexión** | Internet estable 24/7 (recomendado VPS) |

---

## 📥 Instalación

### Paso 1: Descargar el Archivo
Copia el archivo `GridBotXM.mq5` a la carpeta de Expert Advisors de MT5:

```
1. En MT5: Archivo → Abrir Carpeta de Datos
2. Navega a: MQL5 → Experts
3. Copia GridBotXM.mq5 en esta carpeta
```

### Paso 2: Compilar el EA
```
1. En MT5 presiona F4 (abre MetaEditor)
2. Busca GridBotXM.mq5 en el navegador
3. Haz doble clic para abrirlo
4. Presiona F7 para compilar
5. Verifica que no haya errores en la pestaña "Errores"
```

### Paso 3: Configurar en el Gráfico
```
1. Abre un gráfico de ETHUSD# (temporalidad M1 o M5 recomendado)
2. En el panel "Navegador", busca "GridBotXM" bajo Expert Advisors
3. Arrastra el EA al gráfico
4. Configura los parámetros (ver sección siguiente)
5. Activa "Allow Algo Trading" (botón superior)
6. Marca "Allow Live Trading" en la configuración
```

---

## ⚙️ Parámetros de Configuración

### Configuración de Cuenta

| Parámetro | Valor por Defecto | Descripción |
|-----------|------------------|-------------|
| `InpMagicNumber` | `101809995` | ID único para identificar órdenes (usa tu número de cuenta) |
| `InpLotSize` | `0.01` | Tamaño del lote inicial (**ajustar según capital**) |
| `InpGridStepPoints` | `500` | Distancia entre grids en puntos del broker |
| `InpMaxGridLevels` | `10` | Máximo número de niveles de grid simultáneos |
| `InpTakeProfitPoints` | `300` | Take Profit por operación en puntos |
| `InpStopLossTotal` | `100.0` | Stop Loss global en dólares ($) |

### Configuración de Tiempo

| Parámetro | Valor por Defecto | Descripción |
|-----------|------------------|-------------|
| `InpUseTimeFilter` | `false` | Activar/desactivar filtro de tiempo |
| `InpStartHour` | `8` | Hora de inicio (hora del servidor) |
| `InpEndHour` | `20` | Hora de fin (hora del servidor) |

---

## 📊 Ejemplo de Configuración para ETHUSD# en XM

### Configuración Conservadora
```
InpLotSize          = 0.01
InpGridStepPoints   = 500    // ~$5.00 USD en ETH
InpMaxGridLevels    = 5      // Máximo 5 órdenes por lado
InpTakeProfitPoints = 300    // ~$3.00 USD
InpStopLossTotal    = 50.0   // Máximo $50 de pérdida
```

### Configuración Moderada
```
InpLotSize          = 0.02
InpGridStepPoints   = 300    // ~$3.00 USD en ETH
InpMaxGridLevels    = 10     // Máximo 10 órdenes por lado
InpTakeProfitPoints = 200    // ~$2.00 USD
InpStopLossTotal    = 100.0  // Máximo $100 de pérdida
```

### Configuración Agresiva ⚠️
```
InpLotSize          = 0.05
InpGridStepPoints   = 200    // ~$2.00 USD en ETH
InpMaxGridLevels    = 15     // Máximo 15 órdenes por lado
InpTakeProfitPoints = 150    // ~$1.50 USD
InpStopLossTotal    = 200.0  // Máximo $200 de pérdida
```

> **NOTA**: Los valores en puntos dependen del broker. En XM, 1 punto en ETHUSD# suele ser 0.01. Ajusta según las especificaciones del símbolo.

---

## 🧠 Cómo Funciona la Estrategia

### 1. Grid de Compras (BUY)
```
Precio actual: $3500

1. Compra inicial en $3500
2. Si precio baja a $3495 → Nueva compra
3. Si precio baja a $3490 → Nueva compra
4. ... hasta MaxGridLevels

Cuando el precio sube $3 (Take Profit):
→ Cierra TODAS las compras con beneficio
→ Reinicia el grid
```

### 2. Grid de Ventas (SELL)
```
Precio actual: $3500

1. Venta inicial en $3500
2. Si precio sube a $3505 → Nueva venta
3. Si precio sube a $3510 → Nueva venta
4. ... hasta MaxGridLevels

Cuando el precio baja $3 (Take Profit):
→ Cierra TODAS las ventas con beneficio
→ Reinicia el grid
```

### 3. Gestión de Riesgo
```
Beneficio total de posiciones = -$120
Stop Loss Global configurado = $100

Resultado: -$120 < -$100 → ¡STOP LOSS ACTIVADO!
→ Cierra TODAS las posiciones inmediatamente
```

---

## 🔧 Solución de Problemas

### El EA no abre órdenes
- ✅ Verifica que "Allow Algo Trading" esté activado (icono verde arriba)
- ✅ Verifica que "Allow Live Trading" esté marcado en configuración
- ✅ Comprueba que tienes margen suficiente en la cuenta
- ✅ Verifica que el símbolo sea correcto (ETHUSD#)

### Las órdenes se cierran inmediatamente
- ✅ Revisa el `type_filling` del broker (el EA intenta FOK luego IOC)
- ✅ Reduce el tamaño del lote si hay problemas de margen
- ✅ Verifica que el broker permita hedging (compra y venta simultánea)

### El Stop Loss no funciona
- ✅ Verifica que `InpStopLossTotal` sea mayor a 0
- ✅ Revisa el log de expertos para ver mensajes de cierre
- ✅ Asegúrate de que las órdenes tengan el Magic Number correcto

### Errores de compilación
```
Error común: 'Point' - undeclared identifier
Solución: Usa SymbolInfoDouble(Symbol(), SYMBOL_POINT)

Error común: 'OrderSend' - wrong parameters count
Solución: Verifica que usas la estructura MqlTradeRequest correctamente
```

---

## 📈 Recomendaciones de Uso

### ✅ Mejores Prácticas
1. **Backtesting**: Prueba en cuenta demo antes de usar dinero real
2. **VPS**: Usa un VPS para operación 24/7 sin interrupciones
3. **Monitoreo**: Revisa el bot regularmente, especialmente al inicio
4. **Ajuste**: Modifica parámetros según la volatilidad del mercado
5. **Capital**: Nunca arriesgues más del 2-5% de tu capital total

### ❌ Qué Evitar
1. No uses lotes demasiado grandes para tu capital
2. No desactives el Stop Loss global
3. No operes en múltiples pares sin suficiente margen
4. No ignores noticias económicas importantes (pueden causar gaps)
5. No compartas tus credenciales nunca

---

## 📞 Soporte XM Global

| Contacto | Información |
|----------|-------------|
| **Sitio Web** | [www.xm.com](https://www.xm.com/) |
| **Soporte** | Disponible 24/5 en múltiples idiomas |
| **Servidor** | XMGlobalSC-MT5 (para cuentas Standard) |
| **Horario Trading** | Lunes a Viernes, 24 horas (criptomonedas) |

---

## ⚠️ Descargo de Responsabilidad

**ADVERTENCIA DE RIESGO**: El trading de CFDs y criptomonedas conlleva un **alto nivel de riesgo** y puede no ser adecuado para todos los inversores. 

- Puedes perder parte o **todo tu capital invertido**
- El apalancamiento puede amplificar tanto ganancias como pérdidas
- El rendimiento pasado **no garantiza** resultados futuros
- Este software se proporciona "TAL CUAL" sin garantías de ningún tipo

**Responsabilidad del Usuario**:
- Eres el único responsable de tus decisiones de trading
- Debes entender completamente los riesgos antes de operar
- Se recomienda comenzar con una cuenta demo
- Consulta con un asesor financiero profesional si es necesario

---

## 📄 Licencia

Este proyecto está bajo la licencia MIT. Eres libre de:
- ✅ Usar el software para trading personal
- ✅ Modificar el código según tus necesidades
- ✅ Distribuir copias del software

**Prohibido**:
- ❌ Vender el software como propio
- ❌ Usar para actividades ilegales o fraudulentas

---

## 🤝 Contribuciones

Las mejoras son bienvenidas. Algunas ideas:
- [ ] Implementar trailing stop dinámico
- [ ] Añadir notificaciones por email/Telegram
- [ ] Integrar indicadores técnicos para filtrar entradas
- [ ] Sistema de martingala opcional con límites estrictos
- [ ] Backtesting optimizado con datos históricos

---

## 📞 Contacto

Para preguntas o soporte técnico relacionado con este EA:

> **Recordatorio**: Nunca compartas tu número de cuenta, contraseña o datos personales en foros públicos.

---

**Desarrollado para la comunidad de traders de XM Global**  
*Versión 1.0 - 2024*
