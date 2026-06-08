<?php

namespace App\Utils;

/**
 * Constantes estratégicas para el Grid Bot ETH/USDT
 * Versión 13.7 - Scalping Adaptativo Mejorado
 */
class GridConstants
{
    // Símbolo y configuración básica
    public const SYM = 'ETHUSDT';
    public const CAPITAL = 30.0;
    public const LEVERAGE = 100;
    
    // Timing
    public const CYCLE_SEC = 8;
    public const AI_INTERVAL = 120;
    public const TF = '5'; // Timeframe 5 minutos
    public const CANDLES = 150;
    
    // Niveles de grid
    public const MIN_LEVELS = 8;
    public const MAX_LEVELS = 20;
    public const FIXED_LEVELS = 16;
    
    // Spacing (escalado adaptativo)
    public const MIN_SPACING = 0.0004;   // 0.04% - mínimo para scalping 5m
    public const MAX_SPACING = 0.0012;   // 0.12% - máximo efectivo con ATR ~0.5%
    public const BASE_SPACING = 0.0004;  // 0.04% base para scalping
    public const SPACING_ATR_MULT = 0.20; // spacing ≈ ATR% * 0.20 / 100
    
    // Márgenes y fees
    public const MARGIN_SAFETY = 0.85;
    public const MAKER_FEE = 0.0001;
    public const TAKER_FEE = 0.0006;
    public const MIN_NOTIONAL = 3.0;
    
    // Gestión de riesgo
    public const MAX_DAILY_LOSS = 12.0;
    public const HARD_STOP_PCT = 3.0;  // 3% drawdown no realizado antes de cierre forzoso
    public const RECOVERY_THR = 1.0;
    public const COMPOUND_THR = 1.5;
    public const COMPOUND_MULT = 1.05;
    public const COMPOUND_CD = 300;
    
    // Recovery mode
    public const RECOVERY_LOSS_PCT = 3.0;
    public const MIN_BUILD_INTERVAL = 60;
    
    // Machine Learning
    public const ML_BLEND_WEIGHT = 0.90;
    public const ML_RELOAD_CYCLES = 120;  // reload check cada 120 ciclos (~16min)
    public const VL_BLEND_WEIGHT = 0.10;
    public const VOL_RELOAD_CYCLES = 120;
    
    // Direcciones
    public const DIR_LONG = 'LONG';
    public const DIR_SHORT = 'SHORT';
    public const DIR_SIDEWAYS = 'SIDEWAYS';
    public const DIR_NEUTRAL = 'NEUTRAL';
    
    // Roles de orden
    public const ROLE_ENTRY = 'ENTRY';
    public const ROLE_EXIT = 'EXIT';
    
    // Estados de orden
    public const STATUS_OPEN = 'OPEN';
    public const STATUS_FILLED = 'FILLED';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_REJECTED = 'REJECTED';
    
    // Modos de operación
    public const MODE_NORMAL = 'NORMAL';
    public const MODE_RECOVERY = 'RECOVERY';
    public const MODE_PAUSED = 'PAUSED';
    
    /**
     * Obtener todas las constantes como array
     */
    public static function all(): array
    {
        $reflection = new \ReflectionClass(self::class);
        return $reflection->getConstants();
    }
    
    /**
     * Verificar si una dirección es válida
     */
    public static function isValidDirection(string $direction): bool
    {
        return in_array($direction, [
            self::DIR_LONG,
            self::DIR_SHORT,
            self::DIR_SIDEWAYS,
            self::DIR_NEUTRAL,
        ]);
    }
    
    /**
     * Calcular cantidad mínima según precio actual
     */
    public static function calcMinQty(float $price): float
    {
        return self::MIN_NOTIONAL / $price;
    }
    
    /**
     * Calcular fee estimado
     */
    public static function calcFee(float $notional, bool $isMaker = true): float
    {
        return $notional * ($isMaker ? self::MAKER_FEE : self::TAKER_FEE);
    }
}
