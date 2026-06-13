# 🤖 Grid Bot con Machine Learning - Actualización

## 📋 Resumen de Cambios

Se ha integrado un **sistema de predicción ML Ensemble** que combina múltiples modelos para mejorar la precisión de las señales de trading.

---

## 🧠 Nuevas Características ML

### Ensemble de Modelos

El bot ahora utiliza **3 modelos** combinados:

1. **Linear Regression v2** (`ml_weights_v2.json`)
   - Peso: 40%
   - R²: 0.8673
   - MAE: 0.0188
   - Accuracy: 78.45%

2. **Ridge Regression** (`volatility_weights_ridge.json`)
   - Peso: 35%
   - R²: 0.8631
   - MAE: 0.0192
   - Incluye clipping de predicciones

3. **Linear Volatility** (`volatility_weights.json`)
   - Peso: 25%
   - R²: 0.8673
   - MAE: 0.0188

### Features Utilizadas

Los modelos procesan **10 features** técnicas:

| Feature | Descripción |
|---------|-------------|
| `rsi_14` | Relative Strength Index (14 períodos) |
| `stoch_14` | Estocástico (14 períodos) |
| `macd_hist` | Histograma MACD |
| `ema_diff_9_21` | Diferencia entre EMA 9 y 21 |
| `vol_ratio` | Ratio de volumen (20 períodos) |
| `bb_width` | Ancho de Bandas de Bollinger |
| `atr_pct` | ATR como porcentaje del precio |
| `vwap_ratio` | Ratio Precio/VWAP |
| `spread_pct` | Spread como porcentaje |
| `momentum_5` | Momentum (5 períodos) |

---

## 📁 Archivos Modificados/Creados

### Nuevos Archivos

1. **`/workspace/src/Services/MlPredictor.php`**
   - Servicio completo de predicción ML
   - Cálculo de features técnicas
   - Soporte para múltiples modelos
   - Sistema de ensemble ponderado

2. **`/workspace/data/ml_weights_v2.json`**
   - Pesos del modelo Linear Regression v2
   - Estadísticas completas (accuracy, precision, recall, F1)

### Archivos Modificados

1. **`/workspace/config/config.json`**
   ```json
   {
     "ml": {
       "weights_file": "/workspace/data/ml_weights_v2.json",
       "volatility_weights": "/workspace/data/volatility_weights.json",
       "volatility_weights_ridge": "/workspace/data/volatility_weights_ridge.json",
       "min_confidence": 45,
       "use_ridge": true,
       "ensemble_enabled": true
     }
   }
   ```

2. **`/workspace/private/bot.php`**
   - Integración de `MlPredictor`
   - Función `calculateDirection()` mejorada
   - Fallback a análisis técnico tradicional
   - Logging de predicciones ML

---

## 🚀 Cómo Funciona

### Flujo de Predicción

```
┌─────────────────┐
│   Velas (150)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Calcular        │
│ 10 Features     │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Normalizar      │
│ (scaler_mean,   │
│  scaler_scale)  │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────┐
│  Predicción Ensemble        │
│  ┌───────────────────────┐  │
│  │ Linear v2 (40%)       │  │
│  │ Ridge (35%)           │  │
│  │ Linear Vol (25%)      │  │
│  └───────────────────────┘  │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────┐
│ Dirección +     │
│ Confianza       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Si confianza    │
│ >= 60% → USAR   │
│ Si no → Fallback│
│ a TA clásico    │
└─────────────────┘
```

### Lógica de Decisión

```php
// El bot usa ML si la confianza es >= 60%
if ($mlPrediction['confidence'] >= 60) {
    $direction = $mlPrediction['direction']; // LONG, SHORT, NEUTRAL
    $confidence = $mlPrediction['confidence']; // 60-95%
} else {
    // Fallback a indicadores técnicos tradicionales
    // (RSI, MACD, EMAs)
}
```

---

## 📊 Ejemplo de Salida

```
[ML] Modelo cargado: /workspace/data/ml_weights_v2.json
[ML] Volatility weights cargados: /workspace/data/volatility_weights.json
[ML] Volatility weights Ridge cargados: /workspace/data/volatility_weights_ridge.json
[AI] LONG, Conf: 72%, Spacing: 0.45%
[Bot] ML Ensemble: LONG (conf: 72%, pred: 0.6234)
```

---

## ⚙️ Configuración

### Habilitar/Deshabilitar Modelos

En `config/config.json`:

```json
{
  "ml": {
    "use_ridge": true,          // Usar modelo Ridge (35% peso)
    "ensemble_enabled": true,    // Usar ensemble completo
    "min_confidence": 45         // Mínima confianza para operar
  }
}
```

### Solo Modelo Principal

```json
{
  "ml": {
    "use_ridge": false,
    "ensemble_enabled": false,
    "min_confidence": 45
  }
}
```

---

## 🧪 Pruebas Unitarias

Para probar el nuevo servicio ML:

```bash
./vendor/bin/phpunit tests/MlPredictorTest.php
```

Tests incluidos:
- ✅ Carga de modelos
- ✅ Cálculo de features
- ✅ Predicción individual
- ✅ Ensemble ponderado
- ✅ Fallback a NEUTRAL

---

## 📈 Métricas de Rendimiento

### Model Performance

| Modelo | R² | MAE | Accuracy | Precision | Recall | F1 |
|--------|----|-----|----------|-----------|--------|-----|
| Linear v2 | 0.8673 | 0.0188 | 78.45% | 76.21% | 81.03% | 78.55% |
| Ridge | 0.8631 | 0.0192 | - | - | - | - |
| Linear Vol | 0.8673 | 0.0188 | - | - | - | - |

### Datos de Entrenamiento

- **Training samples**: 15,000
- **Validation samples**: 3,000
- **Última actualización**: 2026-05-28

---

## 🔧 Solución de Problemas

### Error: "Modelo no cargado"

Verifica que los archivos existan:
```bash
ls -la /workspace/data/*.json
```

### Error: "Feature faltante"

Asegúrate de tener suficientes velas (mínimo 50):
```php
if (count($candles) < 50) {
    // Error: insuficientes datos
}
```

### Recargar Modelos

Después de re-entrenar:
```php
$mlPredictor->reloadModels();
```

---

## 🎯 Mejoras Futuras

- [ ] Agregar más modelos al ensemble (XGBoost, Random Forest)
- [ ] Implementar validación cruzada en tiempo real
- [ ] Guardar historial de predicciones para backtesting
- [ ] Ajuste dinámico de pesos según performance reciente
- [ ] Integración con NVIDIA API para inferencia GPU

---

## 📞 Soporte

Para problemas o preguntas sobre la integración ML:

1. Revisa los logs: `/workspace/logs/bot.log`
2. Verifica estadísticas del modelo en el dashboard
3. Consulta la documentación de cada modelo en los JSON

---

**⚠️ Advertencia**: El trading conlleva riesgos. Usa siempre stop loss y nunca operes con dinero que no puedas permitirte perder.
