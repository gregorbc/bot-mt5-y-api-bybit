//+------------------------------------------------------------------+
//|                                                ETHUSD_Grid_ML.mq5|
//|                                    Adaptado de PHP Grid Bot v13.9|
//|                                    Capital: 30 USD (por defecto) |
//|                                    XM / MT5                       |
//+------------------------------------------------------------------+
#property copyright "Adaptado a MQL5 - Grid Bot ML v2.0"
#property version   "2.00"
#property description "Grid scalping adaptativo con ML para ETHUSD"

#include <Trade\Trade.mqh>
#include <Trade\PositionInfo.mqh>
#include <Trade\OrderInfo.mqh>
#include <Trade\SymbolInfo.mqh>

//--- Entradas configurables
input group "=== CAPITAL Y RIESGO ==="
input double InpCapital         = 30.0;   // Capital inicial (USD) - AJUSTADO
input int    InpLeverage        = 100;        // Apalancamiento (referencia)
input double InpMaxDailyLossPct = 12.0;       // Pérdida máxima diaria (% del capital)
input double InpHardStopPct     = 3.0;        // Drawdown no realizado que fuerza cierre (%)
input bool   InpUseRecovery     = true;       // Activar modo recovery (duplica spacing al 3% pérdida)

input group "=== CONFIGURACIÓN GRID ==="
input int    InpMaxLevels       = 16;         // Niveles máximos (suma de long+short)
input double InpBaseSpacingPct  = 0.03;       // Espaciado base (%)
input double InpMaxSpacingPct   = 0.12;       // Espaciado máximo (%)
input double InpAtrMult         = 0.28;       // Multiplicador ATR para spacing
input double InpMinNotional     = 1.5;        // Nocional mínimo por orden (USD)
input double InpFixedLot        = 0.0;        // Lote fijo (0 = automático por capital)

input group "=== IA Y ML ==="
input int    InpAiIntervalSec   = 120;        // Intervalo de evaluación IA (segundos)
input int    InpDirectionConfirm= 2;          // Ciclos consecutivos para cambiar dirección

input group "=== AVANZADO ==="
input ulong  InpMagicNumber     = 139001;     // Número mágico del EA
input int    InpMinBuildInterval= 90;         // Segundos mínimos entre reconstrucciones
input bool   InpLogEvents       = false;      // Mostrar logs detallados

//--- Variables globales
datetime last_ai_check = 0;
datetime last_grid_build = 0;
string   current_direction = "SIDEWAYS";
int      current_confidence = 50;
bool     grid_built = false;
double   peak_daily_pnl = 0.0;
bool     recovery_mode = false;

//--- Persistencia de dirección
string   last_direction = "";
int      direction_change_count = 0;

//--- Handles de indicadores
int h_rsi, h_stoch, h_macd, h_macd_signal;
int h_ema9, h_ema21, h_atr, h_bb;

//--- Pesos del modelo ML (extraídos de ml_weights_v2.json)
double ml_weights[3][10] = {
    { 0.00718086, -0.00180493, -0.12701010,  0.20639896, -0.14482576, -0.04831269,  0.17346799,  0.29865688,  0.03660412, -0.08451731},
    { 0.01540875, -0.10296610,  0.16343295, -0.02986060,  0.14902969, -0.17075546, -0.33481619, -0.06175510, -0.20504740,  0.18282112},
    {-0.02258961,  0.10477103, -0.03642285, -0.17653836, -0.00420393,  0.21906815,  0.16134820, -0.23690177,  0.16844328, -0.09830381}
};
double ml_intercepts[3] = {-0.83846603, 2.69877997, -1.86031394};
double ml_scaler_mean[10] = {
    48.18356546, 48.69262104, -0.00679844, -0.00017170, 0.64987694,
    0.00323380, 0.16819710, 0.99739408, 0.14209753, -0.01036993
};
double ml_scaler_scale[10] = {
    21.64078795, 48.47666712, 0.78905702, 0.00135457, 0.86372621,
    0.00275694, 0.12423370, 0.00828162, 0.12828935, 0.27953102
};
string ml_classes[3] = {"DOWN", "SIDEWAYS", "UP"};

//--- Objetos para trading
CTrade trade;
CPositionInfo posInfo;
COrderInfo orderInfo;
CSymbolInfo symInfo;

//+------------------------------------------------------------------+
//| Inicialización                                                   |
//+------------------------------------------------------------------+
int OnInit() {
   symInfo.Name(_Symbol);
   symInfo.Refresh();
   
   // Crear indicadores
   h_rsi        = iRSI(_Symbol, PERIOD_M5, 14, PRICE_CLOSE);
   h_stoch      = iStochastic(_Symbol, PERIOD_M5, 14, 3, 3, MODE_SMA, STO_LOWHIGH);
   h_macd       = iMACD(_Symbol, PERIOD_M5, 12, 26, 9, PRICE_CLOSE);
   h_macd_signal= iMACD(_Symbol, PERIOD_M5, 12, 26, 9, PRICE_CLOSE);
   h_ema9       = iMA(_Symbol, PERIOD_M5, 9, 0, MODE_EMA, PRICE_CLOSE);
   h_ema21      = iMA(_Symbol, PERIOD_M5, 21, 0, MODE_EMA, PRICE_CLOSE);
   h_atr        = iATR(_Symbol, PERIOD_M5, 14);
   h_bb         = iBands(_Symbol, PERIOD_M5, 20, 0, 2.0, PRICE_CLOSE);
   
   if(h_rsi==INVALID_HANDLE || h_stoch==INVALID_HANDLE || h_macd==INVALID_HANDLE ||
      h_ema9==INVALID_HANDLE || h_ema21==INVALID_HANDLE || h_atr==INVALID_HANDLE || h_bb==INVALID_HANDLE) {
      Print("Error al crear indicadores. Código: ", GetLastError());
      return INIT_FAILED;
   }
   
   trade.SetExpertMagicNumber(InpMagicNumber);
   trade.SetDeviationInPoints(10);
   trade.SetTypeFilling(ORDER_FILLING_FOK);
   
   Print("✅ Grid Bot ML MQL5 iniciado en ", _Symbol, " | Capital: ", DoubleToString(InpCapital,2), " USD");
   return INIT_SUCCEEDED;
}

//+------------------------------------------------------------------+
//| Deinicialización                                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason) {
   IndicatorRelease(h_rsi);
   IndicatorRelease(h_stoch);
   IndicatorRelease(h_macd);
   IndicatorRelease(h_macd_signal);
   IndicatorRelease(h_ema9);
   IndicatorRelease(h_ema21);
   IndicatorRelease(h_atr);
   IndicatorRelease(h_bb);
   Print("EA detenido. Razón: ", reason);
}

//+------------------------------------------------------------------+
//| Ciclo principal                                                  |
//+------------------------------------------------------------------+
void OnTick() {
   static datetime last_tick_time = 0;
   if(TimeCurrent() == last_tick_time) return;
   last_tick_time = TimeCurrent();
   
   double price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   if(price <= 0) return;
   
   EvaluateAI();
   CheckRisk();
   BreakoutCheck();
   
   if(!grid_built) {
      BuildGrid();
   }
}

//+------------------------------------------------------------------+
//| Evaluación del modelo ML y persistencia de dirección            |
//+------------------------------------------------------------------+
void EvaluateAI() {
   if(TimeCurrent() - last_ai_check < InpAiIntervalSec) return;
   last_ai_check = TimeCurrent();
   
   double price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   if(price <= 0) return;
   
   double features[10];
   double buffer[1];
   
   // RSI
   CopyBuffer(h_rsi, 0, 0, 1, buffer);
   features[0] = buffer[0];
   
   // Estocástico %K
   CopyBuffer(h_stoch, 0, 0, 1, buffer);
   features[1] = buffer[0];
   
   // MACD Hist
   double macd_main, macd_sig;
   CopyBuffer(h_macd, 0, 0, 1, buffer);
   macd_main = buffer[0];
   CopyBuffer(h_macd_signal, 1, 0, 1, buffer);
   macd_sig = buffer[0];
   features[2] = macd_main - macd_sig;
   
   // EMA diff (9-21)/price
   CopyBuffer(h_ema9, 0, 0, 1, buffer);
   double ema9 = buffer[0];
   CopyBuffer(h_ema21, 0, 0, 1, buffer);
   double ema21 = buffer[0];
   features[3] = (ema9 - ema21) / price;
   
   // Volumen ratio
   long vol_now = iVolume(_Symbol, PERIOD_M5, 0);
   double vol_sum = 0;
   for(int i=1; i<=20; i++) vol_sum += iVolume(_Symbol, PERIOD_M5, i);
   double vol_avg = (vol_sum > 0) ? vol_sum / 20.0 : 1.0;
   features[4] = (vol_avg > 0) ? (double)vol_now / vol_avg : 1.0;
   
   // Bollinger width
   double bb_up, bb_low;
   CopyBuffer(h_bb, 1, 0, 1, buffer);
   bb_up = buffer[0];
   CopyBuffer(h_bb, 2, 0, 1, buffer);
   bb_low = buffer[0];
   features[5] = ((bb_up - bb_low) / price) * 100.0;
   
   // ATR%
   CopyBuffer(h_atr, 0, 0, 1, buffer);
   features[6] = (buffer[0] / price) * 100.0;
   
   // VWAP ratio
   double cumTV = 0, cumV = 0;
   for(int i=0; i<150; i++) {
      double high = iHigh(_Symbol, PERIOD_M5, i);
      double low  = iLow(_Symbol, PERIOD_M5, i);
      double close= iClose(_Symbol, PERIOD_M5, i);
      double typical = (high + low + close) / 3.0;
      double vol = iVolume(_Symbol, PERIOD_M5, i);
      cumTV += typical * vol;
      cumV  += vol;
   }
   double vwap = (cumV > 0) ? cumTV / cumV : price;
   features[7] = (vwap > 0) ? price / vwap : 1.0;
   
   // Spread %
   double high0 = iHigh(_Symbol, PERIOD_M5, 0);
   double low0  = iLow(_Symbol, PERIOD_M5, 0);
   double close0= iClose(_Symbol, PERIOD_M5, 0);
   features[8] = ((high0 - low0) / close0) * 100.0;
   
   // Momentum 5
   double close5 = iClose(_Symbol, PERIOD_M5, 5);
   features[9] = (close5 > 0) ? ((close0 - close5) / close5) * 100.0 : 0.0;
   
   // Escalado robusto y clipping
   double scaled[10];
   for(int i=0; i<10; i++) {
      double s = (features[i] - ml_scaler_mean[i]) / ml_scaler_scale[i];
      scaled[i] = MathMax(-3.0, MathMin(3.0, s));
   }
   
   // Softmax
   double scores[3], max_score = -DBL_MAX;
   for(int c=0; c<3; c++) {
      scores[c] = ml_intercepts[c];
      for(int i=0; i<10; i++) scores[c] += scaled[i] * ml_weights[c][i];
      if(scores[c] > max_score) max_score = scores[c];
   }
   double exps[3], sum_exp = 0;
   for(int c=0; c<3; c++) {
      exps[c] = MathExp(scores[c] - max_score);
      sum_exp += exps[c];
   }
   double probs[3];
   int best = 0;
   double best_prob = 0;
   for(int c=0; c<3; c++) {
      probs[c] = exps[c] / sum_exp;
      if(probs[c] > best_prob) {
         best_prob = probs[c];
         best = c;
      }
   }
   string new_dir = ml_classes[best];
   int new_conf = (int)MathRound(best_prob * 100);
   
   // Persistencia de dirección
   if(new_dir != current_direction) {
      if(new_dir == last_direction) {
         direction_change_count++;
         if(direction_change_count >= InpDirectionConfirm) {
            current_direction = new_dir;
            current_confidence = new_conf;
            direction_change_count = 0;
            Print("🔁 Dirección cambiada a: ", current_direction, " (confianza ", current_confidence, "%)");
            if(InpLogEvents) Print("[IA] Nueva dirección: ", current_direction);
            grid_built = false;
         } else {
            if(InpLogEvents) Print("[IA] Posible cambio a ", new_dir, " pendiente confirmación (", direction_change_count, "/", InpDirectionConfirm, ")");
         }
      } else {
         last_direction = new_dir;
         direction_change_count = 1;
         if(InpLogEvents) Print("[IA] Primer aviso de cambio a ", new_dir);
      }
   } else {
      last_direction = new_dir;
      direction_change_count = 0;
      current_confidence = new_conf;
      if(InpLogEvents) Print("[IA] Dirección ", current_direction, " confirmada, confianza ", current_confidence, "%");
   }
}

//+------------------------------------------------------------------+
//| Cálculo del lote                                                 |
//+------------------------------------------------------------------+
double CalcLotSize(double price, int levels) {
   double contract_size = symInfo.ContractSize();
   double min_lot = symInfo.LotsMin();
   double max_lot = symInfo.LotsMax();
   double step    = symInfo.LotsStep();
   
   if(contract_size <= 0) contract_size = 1.0;
   
   if(InpFixedLot > 0) {
      double lot = InpFixedLot;
      if(step > 0) lot = MathFloor(lot / step) * step;
      lot = MathMax(min_lot, MathMin(max_lot, lot));
      return lot;
   }
   
   // CÁLCULO PROPORCIONAL AL CAPITAL (Base 30 USD)
   double capital = MathMin(AccountInfoDouble(ACCOUNT_EQUITY), InpCapital);
   double base_lot = (capital / 30.0) * 0.02; // Si capital=30, factor=1, lote=0.02
   double level_factor = 16.0 / MathMax(1, levels);
   double lot = base_lot * level_factor;
   
   // Nocional mínimo
   double min_units = InpMinNotional / price;
   double min_lot_by_notional = min_units / contract_size;
   lot = MathMax(lot, min_lot_by_notional);
   
   if(step > 0) lot = MathFloor(lot / step) * step;
   lot = MathMax(min_lot, MathMin(max_lot, lot));
   lot = MathMin(lot, 0.50);   // seguridad
   
   return lot;
}

//+------------------------------------------------------------------+
//| Obtener spacing actual                                           |
//+------------------------------------------------------------------+
double GetSpacing() {
   double price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   if(price <= 0) return InpBaseSpacingPct / 100.0;
   double atr_buffer[1];
   CopyBuffer(h_atr, 0, 0, 1, atr_buffer);
   double atr_pct = (atr_buffer[0] / price) * 100.0;
   double spacing = (InpBaseSpacingPct / 100.0) + (atr_pct * InpAtrMult / 100.0);
   spacing = MathMax(InpBaseSpacingPct / 100.0, MathMin(InpMaxSpacingPct / 100.0, spacing));
   if(current_direction == "SIDEWAYS") spacing *= 0.90;
   if(recovery_mode) spacing = MathMin(InpMaxSpacingPct / 100.0, spacing * 1.8);
   return spacing;
}

//+------------------------------------------------------------------+
//| Construcción de la grilla                                        |
//+------------------------------------------------------------------+
void BuildGrid() {
   if(grid_built && (TimeCurrent() - last_grid_build) < InpMinBuildInterval) return;
   
   DeleteAllPendingOrders();
   
   double price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   if(price <= 0) return;
   
   int levels = InpMaxLevels;
   double spacing = GetSpacing();
   int digits = (int)symInfo.Digits();
   
   int long_lev = (int)(levels * 0.5);
   int short_lev = levels - long_lev;
   if(current_direction == "UP") {
      long_lev = (int)MathRound(levels * 0.625);
      short_lev = levels - long_lev;
   } else if(current_direction == "DOWN") {
      short_lev = (int)MathRound(levels * 0.625);
      long_lev = levels - short_lev;
   }
   
   double lot = CalcLotSize(price, levels);
   if(lot <= 0) {
      Print("ERROR: lote calculado es cero. Abortando grid.");
      return;
   }
   
   int placed = 0;
   // Buy Limits
   for(int i=1; i<=long_lev; i++) {
      double p = NormalizeDouble(price * (1.0 - spacing * i), digits);
      if(p <= 0) continue;
      if(PlaceOrder(ORDER_TYPE_BUY_LIMIT, lot, p)) placed++;
   }
   // Sell Limits
   for(int i=1; i<=short_lev; i++) {
      double p = NormalizeDouble(price * (1.0 + spacing * i), digits);
      if(p <= 0) continue;
      if(PlaceOrder(ORDER_TYPE_SELL_LIMIT, lot, p)) placed++;
   }
   
   if(placed > 0) {
      grid_built = true;
      last_grid_build = TimeCurrent();
      if(InpLogEvents) Print("✅ Grilla construida: ", long_lev, " BUY, ", short_lev, " SELL | Spacing=", DoubleToString(spacing*100,3), "% | Lote=", DoubleToString(lot,2));
   } else {
      grid_built = false;
      Print("❌ No se pudo colocar ninguna orden. Reintentando más tarde.");
   }
}

//+------------------------------------------------------------------+
//| Colocar orden pendiente con Take Profit                         |
//+------------------------------------------------------------------+
bool PlaceOrder(ENUM_ORDER_TYPE type, double lot, double price) {
   double spacing = GetSpacing();
   double tp = 0;
   int digits = (int)symInfo.Digits();
   
   if(type == ORDER_TYPE_BUY_LIMIT) {
      tp = NormalizeDouble(price * (1.0 + spacing), digits);
   } else {
      tp = NormalizeDouble(price * (1.0 - spacing), digits);
   }
   
   trade.PositionOpen(_Symbol, type, lot, price, 0, tp, "GridML");
   if(trade.ResultRetcode() == TRADE_RETCODE_DONE) {
      return true;
   } else {
      if(InpLogEvents) Print("Error orden ", type, " a ", price, " : ", trade.ResultRetcode(), " - ", trade.ResultComment());
      return false;
   }
}

//+------------------------------------------------------------------+
//| Eliminar todas las órdenes pendientes                            |
//+------------------------------------------------------------------+
void DeleteAllPendingOrders() {
   for(int i=OrdersTotal()-1; i>=0; i--) {
      if(orderInfo.SelectByIndex(i)) {
         if(orderInfo.Symbol() == _Symbol && orderInfo.Magic() == InpMagicNumber && orderInfo.Type() > ORDER_TYPE_SELL) {
            trade.OrderDelete(orderInfo.Ticket());
         }
      }
   }
}

//+------------------------------------------------------------------+
//| Cerrar todas las posiciones                                      |
//+------------------------------------------------------------------+
void CloseAllPositions() {
   for(int i=PositionsTotal()-1; i>=0; i--) {
      if(posInfo.SelectByIndex(i)) {
         if(posInfo.Symbol() == _Symbol && posInfo.Magic() == InpMagicNumber) {
            trade.PositionClose(posInfo.Ticket());
         }
      }
   }
}

//+------------------------------------------------------------------+
//| Obtener PnL diario                                               |
//+------------------------------------------------------------------+
double GetDailyPnL() {
   double pnl = 0.0;
   datetime today_start = iTime(_Symbol, PERIOD_D1, 0);
   HistorySelect(today_start, TimeCurrent());
   int deals = HistoryDealsTotal();
   for(int i=0; i<deals; i++) {
      ulong ticket = HistoryDealGetTicket(i);
      if(HistoryDealGetInteger(ticket, DEAL_MAGIC) == InpMagicNumber && 
         HistoryDealGetString(ticket, DEAL_SYMBOL) == _Symbol &&
         HistoryDealGetInteger(ticket, DEAL_ENTRY) == DEAL_ENTRY_OUT) {
         pnl += HistoryDealGetDouble(ticket, DEAL_PROFIT) + 
                HistoryDealGetDouble(ticket, DEAL_SWAP) + 
                HistoryDealGetDouble(ticket, DEAL_COMMISSION);
      }
   }
   return pnl;
}

//+------------------------------------------------------------------+
//| Gestión de riesgos                                               |
//+------------------------------------------------------------------+
void CheckRisk() {
   double daily_pnl = GetDailyPnL();
   if(daily_pnl > peak_daily_pnl) peak_daily_pnl = daily_pnl;
   
   double loss_pct = (daily_pnl < 0) ? (MathAbs(daily_pnl) / InpCapital * 100.0) : 0.0;
   
   if(loss_pct >= InpMaxDailyLossPct) {
      Print("🚨 PÉRDIDA DIARIA MÁXIMA (", DoubleToString(loss_pct,1), "%) alcanzada. Deteniendo EA.");
      CloseAllPositions();
      DeleteAllPendingOrders();
      grid_built = false;
      recovery_mode = false;
      ExpertRemove();
      return;
   }
   
   double contract_size = symInfo.ContractSize();
   if(contract_size <= 0) contract_size = 1.0;
   
   for(int i=PositionsTotal()-1; i>=0; i--) {
      if(posInfo.SelectByIndex(i)) {
         if(posInfo.Symbol() == _Symbol && posInfo.Magic() == InpMagicNumber) {
            double entry = posInfo.PriceOpen();
            double vol = posInfo.Volume();
            double upnl = posInfo.Profit() + posInfo.Swap() + posInfo.Commission();
            double notional = vol * entry * contract_size;
            if(notional > 0 && (MathAbs(upnl) / notional * 100.0) >= InpHardStopPct && upnl < 0) {
               Print("🛑 HARD STOP: drawdown ", DoubleToString((MathAbs(upnl)/notional*100.0),1), "%. Cerrando posición.");
               trade.PositionClose(posInfo.Ticket());
            }
         }
      }
   }
   
   if(InpUseRecovery && loss_pct >= 3.0 && !recovery_mode) {
      Print("🔄 Activando modo recovery (pérdida ", DoubleToString(loss_pct,1), "%). Espaciado x1.8.");
      recovery_mode = true;
      grid_built = false;
   } else if(recovery_mode && daily_pnl >= 0) {
      Print("✅ Recuperación completada. Volviendo a modo normal.");
      recovery_mode = false;
      grid_built = false;
   }
}

//+------------------------------------------------------------------+
//| Detección de breakout                                            |
//+------------------------------------------------------------------+
void BreakoutCheck() {
   if(!grid_built) return;
   
   double min_price = DBL_MAX, max_price = 0;
   for(int i=OrdersTotal()-1; i>=0; i--) {
      if(orderInfo.SelectByIndex(i)) {
         if(orderInfo.Symbol() == _Symbol && orderInfo.Magic() == InpMagicNumber && orderInfo.Type() > ORDER_TYPE_SELL) {
            double p = orderInfo.PriceOpen();
            if(p < min_price) min_price = p;
            if(p > max_price) max_price = p;
         }
      }
   }
   if(min_price == DBL_MAX) return;
   
   double range = max_price - min_price;
   double margin = range * 0.30;
   double cur_price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   
   if(cur_price < (min_price - margin) || cur_price > (max_price + margin)) {
      Print("📈 Breakout detectado. Reconstruyendo grilla.");
      grid_built = false;
   }
}
//+------------------------------------------------------------------+
