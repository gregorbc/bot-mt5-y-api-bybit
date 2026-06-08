# PAQUETE SCALPER COMPLETO — MT4 + MT5
## Capital $100 | XAUUSD + Multi-Par | Scalping Inteligente

---

## ARCHIVOS INCLUIDOS

```
📁 MT4/
   ├── XAUUSD_GoldScalper.mq4     ← EA XAUUSD optimizado $100 / 100:1
   ├── MultiPar_Scalper.mq4       ← EA multi-par adaptativo (EURUSD, GBPUSD, USDJPY...)
   └── FTMO_ETH_Scalper.mq4       ← EA ETHUSD para FTMO Challenge

📁 MT5/
   ├── XAUUSD_GoldScalper.mq5     ← Mismo EA Gold pero MQL5 nativo
   └── MultiPar_Scalper.mq5       ← Multi-par MQL5 con perfiles automáticos

📁 Python/
   └── eth_scalper.py             ← Bot Python MT5 ETHUSD $30 / 100x
```

---

## INSTALACIÓN MT4

1. `File → Open Data Folder → MQL4 → Experts`
2. Copiar los `.mq4`
3. `MetaEditor (F4) → F5 compilar → 0 errors`
4. Arrastrar al gráfico **M1** del par
5. Habilitar **"Allow live trading"**

## INSTALACIÓN MT5

1. `File → Open Data Folder → MQL5 → Experts`
2. Copiar los `.mq5`
3. `MetaEditor (F4) → F5 compilar → 0 errors`
4. Arrastrar al gráfico **M1** del par
5. Habilitar **"Allow algo trading"** (botón toolbar)

---

## CONFIGURACIÓN RÁPIDA — $100 / 100:1

| Parámetro          | Valor  | Motivo                          |
|--------------------|--------|---------------------------------|
| RiskPercent        | 1.0    | $1 por trade — conservador      |
| MaxDailyLossPct    | 4.0    | Máx $4/día antes de parar       |
| MaxTotalLossPct    | 8.0    | Máx $8 total antes de parar     |
| MaxLot XAUUSD      | 0.10   | Límite seguro con $100          |
| MaxLot Forex       | 0.20   | Más líquido, menor margen       |
| ServerUTCOffset    | 2      | XM / FTMO / ICMarkets / Pepperstone |
| MinScore           | 60-65  | Umbral mínimo de calidad señal  |

---

## MEJORES PARES SCALPING — APALANCAMIENTO MÁXIMO

| Par     | Leverage máx | Spread | Dificultad | Score |
|---------|-------------|--------|------------|-------|
| EURUSD  | 1:500       | 0.5-1  | Baja       | ⭐⭐⭐⭐⭐ |
| USDJPY  | 1:500       | 0.5-1  | Baja       | ⭐⭐⭐⭐⭐ |
| XAUUSD  | 1:500       | 20-50  | Alta       | ⭐⭐⭐⭐⭐ |
| GBPUSD  | 1:500       | 1-2    | Media      | ⭐⭐⭐⭐  |
| USDCHF  | 1:500       | 1-2    | Baja       | ⭐⭐⭐⭐  |
| EURJPY  | 1:500       | 1.5-3  | Media      | ⭐⭐⭐⭐  |
| AUDUSD  | 1:500       | 0.8-1.5| Baja       | ⭐⭐⭐⭐  |
| GBPJPY  | 1:500       | 2-4    | Alta       | ⭐⭐⭐   |
| US30    | 1:100       | 1-3    | Media      | ⭐⭐⭐   |
| BTCUSD  | 1:50        | 10-50  | Muy Alta   | ⭐⭐    |

---

## SESIONES RECOMENDADAS

| Sesión        | UTC         | Mejor para            |
|---------------|-------------|------------------------|
| London Open   | 08:00-10:00 | XAUUSD, GBPUSD, EURUSD|
| London        | 08:00-17:00 | Todos los pares        |
| **OVERLAP**   | **13:00-17:00** | **Mejor volumen — ORO** |
| New York      | 13:00-20:00 | USD pairs, Oro         |
| Tokyo         | 00:00-08:00 | USDJPY, AUDUSD         |

---

## INDICADORES USADOS (8 en total)

```
M15 → EMA 8/21/50 Stack (tendencia)
M5  → EMA 8/21 (confirmación)
M1  → RSI 14 / MACD / Bollinger Bands / Stochastic / CCI / Momentum
```

**Score mínimo 60/100 para abrir trade.**
**Trailing Stop + Breakeven automático activados.**

---

## DIFERENCIAS MQL4 vs MQL5

| Característica | MQL4 | MQL5 |
|----------------|------|------|
| Indicadores    | Llamadas directas | Handles + CopyBuffer |
| Órdenes        | OrderSend()       | CTrade.Buy/Sell() |
| Posiciones     | OrderSelect()     | PositionsTotal() / CPositionInfo |
| Librerías      | Manual            | #include Trade.mqh |
| Netting        | No                | Sí (1 pos por símbolo en netting) |
| Velocidad      | Más rápido init   | Más moderno y robusto |

> **MT5 Netting:** en cuentas netting (ej. FTMO) solo existe 1 posición
> por símbolo. El EA lo maneja correctamente.

---

## AJUSTE ZONA HORARIA

El parámetro `ServerUTCOffset` debe coincidir con la hora del servidor:
- Ver en MT4/MT5: esquina inferior derecha del gráfico
- Si servidor dice `22:00` y son las `20:00 UTC` → offset = 2
- Brokers comunes: XM=2, FTMO=2, ICMarkets=2, Pepperstone=2, Exness=2

---

## PYTHON BOT (MT5)

Solo para Windows con MT5 instalado:
```bash
pip install MetaTrader5 numpy pandas
python eth_scalper.py
```
Editar `CONFIG` con login/password/server antes de ejecutar.
