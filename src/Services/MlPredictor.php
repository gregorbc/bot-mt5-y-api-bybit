<?php
/**
 * ML Predictor Service
 * 
 * Servicio para realizar predicciones usando modelos de Machine Learning
 * Soporta múltiples modelos: Linear Regression, Ridge, y Ensemble
 */

namespace App\Services;

use App\Core\Logger;

class MlPredictor
{
    private static ?MlPredictor $instance = null;
    private Logger $logger;
    private array $mlWeights = [];
    private array $volatilityWeights = [];
    private array $volatilityWeightsRidge = [];
    private bool $useRidge = false;
    private bool $ensembleEnabled = false;
    private int $minConfidence = 45;

    private function __construct()
    {
        $this->logger = Logger::getInstance();
        $this->loadModels();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carga todos los modelos de ML desde los archivos JSON
     */
    private function loadModels(): void
    {
        $config = \App\Core\Config::getInstance();
        $mlConfig = $config->get('ml', []);

        $this->minConfidence = $mlConfig['min_confidence'] ?? 45;
        $this->useRidge = $mlConfig['use_ridge'] ?? false;
        $this->ensembleEnabled = $mlConfig['ensemble_enabled'] ?? false;

        // Cargar pesos del modelo principal (v2)
        $weightsFile = $mlConfig['weights_file'] ?? '/workspace/data/ml_weights_v2.json';
        if (file_exists($weightsFile)) {
            $this->mlWeights = json_decode(file_get_contents($weightsFile), true) ?? [];
            $this->logger->info("[ML] Modelo cargado: {$weightsFile}");
        } else {
            $this->logger->warning("[ML] Archivo de pesos no encontrado: {$weightsFile}");
        }

        // Cargar pesos de volatilidad (Linear)
        $volFile = $mlConfig['volatility_weights'] ?? '/workspace/data/volatility_weights.json';
        if (file_exists($volFile)) {
            $this->volatilityWeights = json_decode(file_get_contents($volFile), true) ?? [];
            $this->logger->info("[ML] Volatility weights cargados: {$volFile}");
        }

        // Cargar pesos de volatilidad (Ridge)
        $volRidgeFile = $mlConfig['volatility_weights_ridge'] ?? '/workspace/data/volatility_weights_ridge.json';
        if (file_exists($volRidgeFile)) {
            $this->volatilityWeightsRidge = json_decode(file_get_contents($volRidgeFile), true) ?? [];
            $this->logger->info("[ML] Volatility weights Ridge cargados: {$volRidgeFile}");
        }
    }

    /**
     * Realiza una predicción usando el ensemble de modelos
     * 
     * @param array $features Array asociativo con las features calculadas
     * @return array ['prediction' => float, 'confidence' => int, 'direction' => string, 'details' => array]
     */
    public function predict(array $features): array
    {
        if (empty($this->mlWeights)) {
            return [
                'prediction' => 0.5,
                'confidence' => 50,
                'direction' => 'NEUTRAL',
                'details' => ['error' => 'Modelo no cargado']
            ];
        }

        $predictions = [];
        $weights = [];

        // Predicción con modelo principal (Linear Regression v2)
        if (!empty($this->mlWeights)) {
            $pred = $this->predictWithModel($features, $this->mlWeights);
            if ($pred !== null) {
                $predictions[] = $pred;
                $weights[] = 0.4; // 40% de peso
            }
        }

        // Predicción con Ridge (si está habilitado)
        if ($this->useRidge && !empty($this->volatilityWeightsRidge)) {
            $pred = $this->predictWithModel($features, $this->volatilityWeightsRidge);
            if ($pred !== null) {
                $predictions[] = $pred;
                $weights[] = 0.35; // 35% de peso
            }
        }

        // Predicción con Linear volatility (si está habilitado ensemble)
        if ($this->ensembleEnabled && !empty($this->volatilityWeights)) {
            $pred = $this->predictWithModel($features, $this->volatilityWeights);
            if ($pred !== null) {
                $predictions[] = $pred;
                $weights[] = 0.25; // 25% de peso
            }
        }

        // Calcular predicción final como promedio ponderado
        $finalPrediction = 0.5;
        $totalWeight = array_sum($weights);
        
        if (!empty($predictions) && $totalWeight > 0) {
            $weightedSum = 0;
            for ($i = 0; $i < count($predictions); $i++) {
                $weightedSum += $predictions[$i] * $weights[$i];
            }
            $finalPrediction = $weightedSum / $totalWeight;
        }

        // Clamp entre 0 y 1
        $finalPrediction = max(0.0, min(1.0, $finalPrediction));

        // Determinar dirección y confianza
        $direction = 'NEUTRAL';
        $confidence = 50;

        if ($finalPrediction > 0.55) {
            $direction = 'LONG';
            $confidence = min(95, (int)(50 + ($finalPrediction - 0.5) * 100));
        } elseif ($finalPrediction < 0.45) {
            $direction = 'SHORT';
            $confidence = min(95, (int)(50 + (0.5 - $finalPrediction) * 100));
        } else {
            $confidence = (int)(50 + abs($finalPrediction - 0.5) * 100);
        }

        // Aplicar mínimo de confianza
        if ($confidence < $this->minConfidence) {
            $direction = 'NEUTRAL';
            $confidence = $this->minConfidence;
        }

        return [
            'prediction' => round($finalPrediction, 4),
            'confidence' => $confidence,
            'direction' => $direction,
            'details' => [
                'individual_predictions' => $predictions,
                'weights_used' => $weights,
                'model_count' => count($predictions),
                'use_ridge' => $this->useRidge,
                'ensemble_enabled' => $this->ensembleEnabled
            ]
        ];
    }

    /**
     * Realiza una predicción con un modelo específico
     * 
     * @param array $features Features calculadas
     * @param array $model Modelo con weights, intercept, scaler_mean, scaler_scale, features
     * @return float|null Predicción o null si hay error
     */
    private function predictWithModel(array $features, array $model): ?float
    {
        if (empty($model['weights']) || empty($model['features'])) {
            return null;
        }

        $featureNames = $model['features'];
        $scalerMean = $model['scaler_mean'] ?? [];
        $scalerScale = $model['scaler_scale'] ?? [];
        $intercept = $model['intercept'] ?? 0;
        $weights = $model['weights'];

        // Extraer valores de features en el orden correcto
        $values = [];
        foreach ($featureNames as $name) {
            if (!isset($features[$name])) {
                $this->logger->warning("[ML] Feature faltante: {$name}");
                return null;
            }
            $values[] = $features[$name];
        }

        // Normalizar features
        $normalized = [];
        for ($i = 0; $i < count($values); $i++) {
            $mean = $scalerMean[$i] ?? 0;
            $scale = $scalerScale[$i] ?? 1;
            $normalized[$i] = $scale != 0 ? ($values[$i] - $mean) / $scale : 0;
        }

        // Calcular predicción linear
        $prediction = $intercept;
        for ($i = 0; $i < count($featureNames); $i++) {
            $weight = $weights[$featureNames[$i]] ?? 0;
            $prediction += $weight * $normalized[$i];
        }

        // Si el modelo tiene clipping (Ridge)
        if (isset($model['prediction_clip_lower']) && isset($model['prediction_clip_upper'])) {
            $prediction = max($model['prediction_clip_lower'], min($model['prediction_clip_upper'], $prediction));
        }

        return $prediction;
    }

    /**
     * Calcula todas las features necesarias para el modelo ML
     * 
     * @param array $candles Array de velas [t, o, h, l, c, v]
     * @return array Features calculadas
     */
    public function calculateFeatures(array $candles): array
    {
        if (count($candles) < 50) {
            $this->logger->warning("[ML] Insuficientes velas para calcular features");
            return [];
        }

        $closes = array_column($candles, 'c');
        $highs = array_column($candles, 'h');
        $lows = array_column($candles, 'l');
        $volumes = array_column($candles, 'v');
        $lastClose = end($closes);

        // RSI 14
        $rsi = $this->calculateRSI($closes, 14);

        // Stochastic 14
        $stoch = $this->calculateStochastic($closes, $highs, $lows, 14);

        // MACD Histogram
        $macdHist = $this->calculateMACDHistogram($closes);

        // EMA Diff 9-21
        $ema9 = $this->calculateEMA($closes, 9);
        $ema21 = $this->calculateEMA($closes, 21);
        $emaDiff = count($ema9) > 0 && count($ema21) > 0 ? ($ema9[count($ema9) - 1] - $ema21[count($ema21) - 1]) : 0;

        // Volume Ratio
        $volRatio = $this->calculateVolumeRatio($volumes, 20);

        // Bollinger Bands Width
        $bbWidth = $this->calculateBBWidth($closes, 20);

        // ATR Percentage
        $atrPct = $this->calculateATRPercentage($candles, 14);

        // VWAP Ratio
        $vwapRatio = $this->calculateVWAPRatio($candles);

        // Spread Percentage
        $spreadPct = $this->calculateSpreadPercentage($candles);

        // Momentum 5
        $momentum = $this->calculateMomentum($closes, 5);

        return [
            'rsi_14' => round($rsi, 6),
            'stoch_14' => round($stoch, 6),
            'macd_hist' => round($macdHist, 8),
            'ema_diff_9_21' => round($emaDiff, 8),
            'vol_ratio' => round($volRatio, 6),
            'bb_width' => round($bbWidth, 6),
            'atr_pct' => round($atrPct, 6),
            'vwap_ratio' => round($vwapRatio, 6),
            'spread_pct' => round($spreadPct, 6),
            'momentum_5' => round($momentum, 6)
        ];
    }

    private function calculateRSI(array $closes, int $period): float
    {
        $n = count($closes);
        if ($n <= $period) return 50.0;

        $avgGain = $avgLoss = 0.0;
        for ($i = 1; $i <= $period; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            if ($change > 0) $avgGain += $change;
            else $avgLoss += abs($change);
        }
        $avgGain /= $period;
        $avgLoss /= $period;

        for ($i = $period + 1; $i < $n; $i++) {
            $change = $closes[$i] - $closes[$i - 1];
            $avgGain = ($avgGain * ($period - 1) + max($change, 0)) / $period;
            $avgLoss = ($avgLoss * ($period - 1) + max(-$change, 0)) / $period;
        }

        return $avgLoss == 0 ? 100.0 : 100 - 100 / (1 + $avgGain / $avgLoss);
    }

    private function calculateStochastic(array $closes, array $highs, array $lows, int $period): float
    {
        $n = count($closes);
        if ($n < $period) return 50.0;

        $highestHigh = max(array_slice($highs, -$period));
        $lowestLow = min(array_slice($lows, -$period));
        $lastClose = end($closes);

        if ($highestHigh == $lowestLow) return 50.0;

        return (($lastClose - $lowestLow) / ($highestHigh - $lowestLow)) * 100;
    }

    private function calculateMACDHistogram(array $closes): float
    {
        $ema12 = $this->calculateEMA($closes, 12);
        $ema26 = $this->calculateEMA($closes, 26);

        if (count($ema12) < 9 || count($ema26) < 9) return 0.0;

        $macdLine = [];
        $minLen = min(count($ema12), count($ema26));
        for ($i = 0; $i < $minLen; $i++) {
            if ($ema12[$i] !== null && $ema26[$i] !== null) {
                $macdLine[] = $ema12[$i] - $ema26[$i];
            }
        }

        if (count($macdLine) < 9) return 0.0;

        $signalLine = $this->calculateEMA($macdLine, 9);
        $lastMacd = end($macdLine);
        $lastSignal = end($signalLine) ?: 0;

        return $lastMacd - $lastSignal;
    }

    private function calculateEMA(array $values, int $period): array
    {
        $n = count($values);
        if ($n === 0 || $period <= 0) return [];

        $result = array_fill(0, min($period - 1, $n), null);
        if ($n < $period) return $result;

        $k = 2 / ($period + 1);
        $emaValue = array_sum(array_slice($values, 0, $period)) / $period;
        $result[] = $emaValue;

        for ($i = $period; $i < $n; $i++) {
            $emaValue = $values[$i] * $k + $emaValue * (1 - $k);
            $result[] = $emaValue;
        }

        return $result;
    }

    private function calculateVolumeRatio(array $volumes, int $period): float
    {
        $n = count($volumes);
        if ($n < $period) return 1.0;

        $recentVol = array_sum(array_slice($volumes, -$period)) / $period;
        $prevVol = array_sum(array_slice($volumes, -$period * 2, $period)) / $period;

        return $prevVol > 0 ? $recentVol / $prevVol : 1.0;
    }

    private function calculateBBWidth(array $closes, int $period): float
    {
        $n = count($closes);
        if ($n < $period) return 0.0;

        $slice = array_slice($closes, -$period);
        $sma = array_sum($slice) / $period;

        $sumSquaredDiff = 0;
        foreach ($slice as $price) {
            $sumSquaredDiff += pow($price - $sma, 2);
        }
        $stdDev = sqrt($sumSquaredDiff / $period);

        $upperBand = $sma + 2 * $stdDev;
        $lowerBand = $sma - 2 * $stdDev;

        return $sma > 0 ? ($upperBand - $lowerBand) / $sma : 0.0;
    }

    private function calculateATRPercentage(array $candles, int $period): float
    {
        $n = count($candles);
        if ($n < 2) return 0.0;

        $trueRanges = [];
        for ($i = 1; $i < $n; $i++) {
            $tr = max(
                $candles[$i]['h'] - $candles[$i]['l'],
                abs($candles[$i]['h'] - $candles[$i - 1]['c']),
                abs($candles[$i]['l'] - $candles[$i - 1]['c'])
            );
            $trueRanges[] = $tr;
        }

        $atr = array_sum(array_slice($trueRanges, -$period)) / min($period, count($trueRanges));
        $price = end($candles)['c'];

        return $price > 0 ? ($atr / $price) * 100 : 0.0;
    }

    private function calculateVWAPRatio(array $candles): float
    {
        $n = count($candles);
        if ($n < 20) return 1.0;

        $slice = array_slice($candles, -20);
        $typicalPrices = [];
        $volumes = [];

        foreach ($slice as $candle) {
            $tp = ($candle['h'] + $candle['l'] + $candle['c']) / 3;
            $typicalPrices[] = $tp;
            $volumes[] = $candle['v'];
        }

        $cumulativeTPVol = 0;
        $cumulativeVol = 0;
        for ($i = 0; $i < count($typicalPrices); $i++) {
            $cumulativeTPVol += $typicalPrices[$i] * $volumes[$i];
            $cumulativeVol += $volumes[$i];
        }

        $vwap = $cumulativeVol > 0 ? $cumulativeTPVol / $cumulativeVol : 0;
        $lastClose = end($slice)['c'];

        return $vwap > 0 ? $lastClose / $vwap : 1.0;
    }

    private function calculateSpreadPercentage(array $candles): float
    {
        $lastCandle = end($candles);
        $high = $lastCandle['h'];
        $low = $lastCandle['l'];
        $close = $lastCandle['c'];

        return $close > 0 ? (($high - $low) / $close) * 100 : 0.0;
    }

    private function calculateMomentum(array $closes, int $period): float
    {
        $n = count($closes);
        if ($n <= $period) return 0.0;

        return $closes[$n - 1] - $closes[$n - 1 - $period];
    }

    /**
     * Recarga los modelos desde disco (útil después de re-entrenamiento)
     */
    public function reloadModels(): void
    {
        $this->mlWeights = [];
        $this->volatilityWeights = [];
        $this->volatilityWeightsRidge = [];
        $this->loadModels();
        $this->logger->info("[ML] Modelos recargados");
    }

    /**
     * Obtiene estadísticas del modelo actual
     */
    public function getModelStats(): array
    {
        return [
            'main_model' => !empty($this->mlWeights) ? [
                'type' => $this->mlWeights['model_type'] ?? 'unknown',
                'version' => $this->mlWeights['version'] ?? 'unknown',
                'r2' => $this->mlWeights['r2'] ?? null,
                'mae' => $this->mlWeights['mae'] ?? null,
                'accuracy' => $this->mlWeights['accuracy'] ?? null,
                'updated_at' => $this->mlWeights['updated_at'] ?? null
            ] : null,
            'ridge_model' => !empty($this->volatilityWeightsRidge) ? [
                'r2' => $this->volatilityWeightsRidge['r2'] ?? null,
                'mae' => $this->volatilityWeightsRidge['mae'] ?? null,
                'updated_at' => $this->volatilityWeightsRidge['updated_at'] ?? null
            ] : null,
            'linear_model' => !empty($this->volatilityWeights) ? [
                'r2' => $this->volatilityWeights['r2'] ?? null,
                'mae' => $this->volatilityWeights['mae'] ?? null,
                'updated_at' => $this->volatilityWeights['updated_at'] ?? null
            ] : null,
            'config' => [
                'use_ridge' => $this->useRidge,
                'ensemble_enabled' => $this->ensembleEnabled,
                'min_confidence' => $this->minConfidence
            ]
        ];
    }
}
