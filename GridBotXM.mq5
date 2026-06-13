//+------------------------------------------------------------------+
//|                                           GridBotXM.mq5          |
//|                        Grid Trading Bot for XM Global (ETHUSD#)  |
//|                                                                  |
//+------------------------------------------------------------------+
#property copyright "Grid Bot XM"
#property link      ""
#property version   "1.00"
#property strict

//--- Inputs de Configuración
input group "Configuración de Cuenta"
input int    InpMagicNumber      = 101809995; // ID único para identificar órdenes de este bot
input double InpLotSize          = 0.01;       // Tamaño inicial del lote
input int    InpGridStepPoints   = 500;        // Distancia entre grids (en puntos del broker)
input int    InpMaxGridLevels    = 10;         // Número máximo de niveles de grid
input int    InpTakeProfitPoints = 300;        // Take Profit por operación (en puntos)
input double InpStopLossTotal    = 100.0;      // Stop Loss total en dinero de la cuenta ($)

input group "Configuración de Tiempo"
input bool   InpUseTimeFilter    = false;      // Usar filtro de tiempo
input int    InpStartHour        = 8;          // Hora inicio (servidor)
input int    InpEndHour          = 20;         // Hora fin (servidor)

//--- Variables Globales
int    m_gridHandleBuy = -1;
int    m_gridHandleSell = -1;
double m_lastBuyPrice = 0;
double m_lastSellPrice = 0;
int    m_buyCount = 0;
int    m_sellCount = 0;
string m_symbolName = "";

//+------------------------------------------------------------------+
//| Expert initialization function                                   |
//+------------------------------------------------------------------+
int OnInit()
  {
   m_symbolName = Symbol();
   
   // Validar símbolo (debería ser ETHUSD# o similar en XM)
   if(StringFind(m_symbolName, "ETH") < 0)
     {
      Print("ADVERTENCIA: Este bot está diseñado para pares ETH. Símbolo actual: ", m_symbolName);
     }

   // Normalizar valores
   InpGridStepPoints = (int)(InpGridStepPoints / Point());
   InpTakeProfitPoints = (int)(InpTakeProfitPoints / Point());

   Print("Grid Bot Iniciado en ", m_symbolName, " | Magic: ", InpMagicNumber);
   Print("Grid Step: ", InpGridStepPoints * Point(), " | TP: ", InpTakeProfitPoints * Point());
   
   return(INIT_SUCCEEDED);
  }

//+------------------------------------------------------------------+
//| Expert deinitialization function                                 |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
  {
   Comment("");
   Print("Grid Bot Detenido. Razón: ", reason);
  }

//+------------------------------------------------------------------+
//| Expert tick function                                             |
//+------------------------------------------------------------------+
void OnTick()
  {
   // 1. Verificar Filtro de Tiempo
   if(InpUseTimeFilter && !IsTimeToTrade()) return;

   // 2. Verificar Stop Loss Global en Dinero
   if(CheckGlobalStopLoss()) 
     {
      CloseAllOrders();
      return;
     }

   // 3. Obtener precios actuales
   MqlTick lastTick;
   if(!SymbolInfoTick(m_symbolName, lastTick)) return;
   
   double ask = lastTick.ask;
   double bid = lastTick.bid;
   double point = SymbolInfoDouble(m_symbolName, SYMBOL_POINT);
   double stepPrice = InpGridStepPoints * point;

   // 4. Lógica de Grid de Compra (Buy)
   // Si no hay compras o el precio ha bajado lo suficiente desde la última compra
   if(m_buyCount == 0 || bid <= m_lastBuyPrice - stepPrice)
     {
      if(m_buyCount < InpMaxGridLevels)
        {
         OpenOrder(ORDER_TYPE_BUY, ask);
        }
     }
   
   // Tomar ganancias en compras si el precio subió
   if(m_buyCount > 0 && bid >= m_lastBuyPrice + (InpTakeProfitPoints * point))
     {
      CloseBuyOrders();
      m_lastBuyPrice = 0; // Resetear para reiniciar grid
     }

   // 5. Lógica de Grid de Venta (Sell)
   // Si no hay ventas o el precio ha subido lo suficiente desde la última venta
   if(m_sellCount == 0 || ask >= m_lastSellPrice + stepPrice)
     {
      if(m_sellCount < InpMaxGridLevels)
        {
         OpenOrder(ORDER_TYPE_SELL, bid);
        }
     }

   // Tomar ganancias en ventas si el precio bajó
   if(m_sellCount > 0 && ask <= m_lastSellPrice - (InpTakeProfitPoints * point))
     {
      CloseSellOrders();
      m_lastSellPrice = 0; // Resetear para reiniciar grid
     }
     
   UpdateComment();
  }

//+------------------------------------------------------------------+
//| Abrir Orden                                                      |
//+------------------------------------------------------------------+
void OpenOrder(ENUM_ORDER_TYPE type, double price)
  {
   MqlTradeRequest request = {};
   MqlTradeResult result = {};
   
   request.action = TRADE_ACTION_DEAL;
   request.symbol = m_symbolName;
   request.volume = InpLotSize;
   request.type = type;
   request.price = price;
   request.deviation = 10; // Slippage permitido
   request.magic = InpMagicNumber;
   request.comment = "GridBot XM";
   
   // Ajustar tipo de llenado según el broker
   request.type_filling = ORDER_FILLING_FOK;
   if(!OrderSend(request, result))
     {
      // Intentar con filling alternativo si FOK falla
      request.type_filling = ORDER_FILLING_IOC;
      if(!OrderSend(request, result))
        {
         Print("Error abriendo orden: ", result.retcode, " ", result.comment);
         return;
        }
     }
   
   if(result.retcode == TRADE_RETCODE_DONE)
     {
      if(type == ORDER_TYPE_BUY) 
        {
         m_lastBuyPrice = price;
         m_buyCount++;
        }
      else 
        {
         m_lastSellPrice = price;
         m_sellCount++;
        }
      Print("Orden ", type == ORDER_TYPE_BUY ? "COMPRA" : "VENTA", " abierta en ", price);
     }
  }

//+------------------------------------------------------------------+
//| Cerrar todas las compras                                         |
//+------------------------------------------------------------------+
void CloseBuyOrders()
  {
   CloseOrdersByType(POSITION_TYPE_BUY);
   m_buyCount = 0;
  }

//+------------------------------------------------------------------+
//| Cerrar todas las ventas                                          |
//+------------------------------------------------------------------+
void CloseSellOrders()
  {
   CloseOrdersByType(POSITION_TYPE_SELL);
   m_sellCount = 0;
  }

//+------------------------------------------------------------------+
//| Cerrar órdenes por tipo                                          |
//+------------------------------------------------------------------+
void CloseOrdersByType(ENUM_POSITION_TYPE posType)
  {
   for(int i = PositionsTotal() - 1; i >= 0; i--)
     {
      ulong ticket = PositionGetTicket(i);
      if(PositionGetString(POSITION_SYMBOL) == m_symbolName && 
         PositionGetInteger(POSITION_MAGIC) == InpMagicNumber &&
         PositionGetInteger(POSITION_TYPE) == posType)
        {
         MqlTradeRequest request = {};
         MqlTradeResult result = {};
         
         request.action = TRADE_ACTION_DEAL;
         request.position = ticket;
         request.symbol = m_symbolName;
         request.volume = PositionGetDouble(POSITION_VOLUME);
         request.type = (posType == POSITION_TYPE_BUY) ? ORDER_TYPE_SELL : ORDER_TYPE_BUY;
         request.price = (posType == POSITION_TYPE_BUY) ? SymbolInfoDouble(m_symbolName, SYMBOL_BID) : SymbolInfoDouble(m_symbolName, SYMBOL_ASK);
         request.deviation = 10;
         request.magic = InpMagicNumber;
         
         if(!OrderSend(request, result))
           Print("Error cerrando posición ", ticket, ": ", result.retcode);
        }
     }
  }

//+------------------------------------------------------------------+
//| Cerrar todo (Emergencia)                                         |
//+------------------------------------------------------------------+
void CloseAllOrders()
  {
   Print("STOP LOSS GLOBAL ALCANZADO. Cerrando todo.");
   CloseOrdersByType(POSITION_TYPE_BUY);
   CloseOrdersByType(POSITION_TYPE_SELL);
   m_buyCount = 0;
   m_sellCount = 0;
  }

//+------------------------------------------------------------------+
//| Verificar Stop Loss Global en Dinero                             |
//+------------------------------------------------------------------+
bool CheckGlobalStopLoss()
  {
   if(InpStopLossTotal <= 0) return false;
   
   double totalProfit = 0;
   for(int i = PositionsTotal() - 1; i >= 0; i--)
     {
      if(PositionGetTicket(i) > 0)
        {
         if(PositionGetString(POSITION_SYMBOL) == m_symbolName && 
            PositionGetInteger(POSITION_MAGIC) == InpMagicNumber)
           {
            totalProfit += PositionGetDouble(POSITION_PROFIT) + PositionGetDouble(POSITION_SWAP);
           }
        }
     }
   
   // Si la pérdida supera el límite (el profit es negativo grande)
   if(totalProfit < -InpStopLossTotal) return true;
   
   return false;
  }

//+------------------------------------------------------------------+
//| Filtro de Tiempo                                                 |
//+------------------------------------------------------------------+
bool IsTimeToTrade()
  {
   MqlDateTime dt;
   TimeToStruct(TimeCurrent(), dt);
   
   if(dt.hour >= InpStartHour && dt.hour < InpEndHour) return true;
   return false;
  }

//+------------------------------------------------------------------+
//| Actualizar información en pantalla                               |
//+------------------------------------------------------------------+
void UpdateComment()
  {
   double profit = 0;
   int totalPos = 0;
   
   for(int i = PositionsTotal() - 1; i >= 0; i--)
     {
      if(PositionGetTicket(i) > 0 && 
         PositionGetString(POSITION_SYMBOL) == m_symbolName && 
         PositionGetInteger(POSITION_MAGIC) == InpMagicNumber)
        {
         profit += PositionGetDouble(POSITION_PROFIT) + PositionGetDouble(POSITION_SWAP);
         totalPos++;
        }
     }
     
   string comment = "=== GRID BOT XM (ETHUSD#) ===\n";
   comment += "Usuario: " + IntegerToString(InpMagicNumber) + "\n";
   comment += "Posiciones Activas: " + IntegerToString(totalPos) + "\n";
   comment += "Beneficio Actual: $" + DoubleToString(profit, 2) + "\n";
   comment += "Nivel Compra: " + (m_buyCount > 0 ? DoubleToString(m_lastBuyPrice, _Digits) : "Esperando") + "\n";
   comment += "Nivel Venta: " + (m_sellCount > 0 ? DoubleToString(m_lastSellPrice, _Digits) : "Esperando") + "\n";
   
   Comment(comment);
  }
//+------------------------------------------------------------------+
