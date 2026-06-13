#!/usr/bin/env php
<?php
/**
 * ETH/USDT GRID BOT v14.0 – REFACTORIZADO
 * 
 * Versión completamente refactorizada usando clases App\Core, App\Services, App\Models
 */

set_time_limit(0);
ini_set('memory_limit', '256M');
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo CLI\n"); }
date_default_timezone_set('UTC');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');

require_once __DIR__ . '/../src/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Services\BybitApi;
use App\Services\MlPredictor;
use App\Models\GridConfig;
use App\Models\GridOrder;
use App\Utils\GridConstants;

$config = Config::getInstance();
$logger = Logger::getInstance();
$logger->enableConsoleOutput(true);
$db = Database::getInstance();
$bybit = new BybitApi();
$mlPredictor = MlPredictor::getInstance();

$log = fn($level, $msg) => $logger->log($level, $msg);
$lI = fn($m) => $log(Logger::INFO, $m);
$lW = fn($m) => $log(Logger::WARNING, $m);
$lE = fn($m) => $log(Logger::ERROR, $m);

$BK = $config->getBybitKey();
$BS = $config->getBybitSecret();
$TN = $config->isTestnet();

if (empty($BK) || empty($BS)) {
    $lE("ERROR: Faltan credenciales de Bybit");
    exit(1);
}

$paths = $config->getPaths();
$STATUS = $paths['status'];
$CTRL = $paths['ctrl'];

$lI("[Bot] Iniciando v14.0 Refactorizado - " . ($TN ? 'TESTNET' : 'MAINNET'));

$lockFile = __DIR__ . "/grid_bot.pid";
$fpLock = @fopen($lockFile, 'x');
if ($fpLock === false) {
    $existingPid = trim((string)@file_get_contents($lockFile));
    if ($existingPid && ctype_digit($existingPid) && file_exists("/proc/$existingPid")) {
        $lE("Bot ya en ejecución (PID $existingPid). Saliendo.");
        exit(1);
    }
    @unlink($lockFile);
    $fpLock = @fopen($lockFile, 'x');
    if ($fpLock === false) {
        $lE("No se pudo adquirir PID lock.");
        exit(1);
    }
}
fwrite($fpLock, (string)getmypid());
fflush($fpLock);

register_shutdown_function(function () use ($fpLock, $lockFile, $logger) {
    if (is_resource($fpLock)) { fclose($fpLock); }
    @unlink($lockFile);
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        $logger->critical("[FATAL] {$last['message']} en {$last['file']}:{$last['line']}");
    }
});

$db->getConnection();
$lI("[DB] Conexión establecida");

function ema(array $values, int $period): array {
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

function rsiLast(array $closes, int $period = 14): float {
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
    return $avgLoss == 0 ? 100.0 : round(100 - 100 / (1 + $avgGain / $avgLoss), 2);
}

function macdHistLast(array $closes): float {
    $ema12 = ema($closes, 12);
    $ema26 = ema($closes, 26);
    $macdLine = [];
    for ($i = 0; $i < count($ema12); $i++) {
        if ($ema12[$i] !== null && $ema26[$i] !== null) {
            $macdLine[] = $ema12[$i] - $ema26[$i];
        }
    }
    if (count($macdLine) < 9) return 0.0;
    $signalLine = ema($macdLine, 9);
    return round(end($macdLine) - (end($signalLine) ?: 0), 8);
}

function atrPctLast(array $candles, int $period = 14): float {
    $n = count($candles);
    if ($n < 2) return 0.0;
    $trueRanges = [];
    for ($i = 1; $i < $n; $i++) {
        $tr = max($candles[$i]['h'] - $candles[$i]['l'], abs($candles[$i]['h'] - $candles[$i - 1]['c']), abs($candles[$i]['l'] - $candles[$i - 1]['c']));
        $trueRanges[] = $tr;
    }
    $atr = array_sum(array_slice($trueRanges, -$period)) / min($period, count($trueRanges));
    $price = end($candles)['c'];
    return $price > 0 ? round($atr / $price * 100, 4) : 0.0;
}

function getOrCreateGridConfig(string $symbol): GridConfig {
    global $db;
    $data = $db->fetchOne("SELECT * FROM grid_configs WHERE symbol = ?", [$symbol]);
    if ($data) return new GridConfig($data);
    $defaultData = ['symbol' => $symbol, 'direction' => 'NEUTRAL', 'confidence' => 50, 'capital_usd' => GridConstants::CAPITAL, 'leverage' => GridConstants::LEVERAGE, 'levels' => GridConstants::FIXED_LEVELS, 'spacing_pct' => GridConstants::BASE_SPACING, 'long_levels' => GridConstants::FIXED_LEVELS / 2, 'short_levels' => GridConstants::FIXED_LEVELS / 2, 'status' => 'ACTIVE'];
    $db->execute("INSERT INTO grid_configs (symbol, direction, confidence, capital_usd, leverage, levels, spacing_pct, long_levels, short_levels, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", array_values($defaultData));
    return new GridConfig($defaultData);
}

function updateGridConfig(GridConfig $cfg): void {
    global $db;
    $db->execute("UPDATE grid_configs SET direction = ?, confidence = ?, ai_reason = ?, last_ai_check = NOW(), capital_usd = ?, leverage = ?, levels = ?, spacing_pct = ?, long_levels = ?, short_levels = ?, mode = ?, recovery_active = ?, peak_pnl_today = ?, status = ?, ml_accuracy = ?, atr_predicted = ?, vl_direction = ?, vl_confidence = ? WHERE symbol = ?", [$cfg->direction, $cfg->confidence, $cfg->aiReason, $cfg->capitalUsd, $cfg->leverage, $cfg->levels, $cfg->spacingPct, $cfg->longLevels, $cfg->shortLevels, $cfg->mode, $cfg->recoveryActive ? 1 : 0, $cfg->peakPnlToday, $cfg->status, $cfg->mlAccuracy, $cfg->atrPredicted, $cfg->vlDirection, $cfg->vlConfidence, $cfg->symbol]);
}

function parseCandles(array $raw): array {
    return array_map(fn($c) => ['t' => $c[0], 'o' => (float)$c[1], 'h' => (float)$c[2], 'l' => (float)$c[3], 'c' => (float)$c[4], 'v' => (float)$c[5]], $raw);
}

function calculateDirection(array $candles): array {
    global $mlPredictor;
    
    // Calcular features para ML
    $features = $mlPredictor->calculateFeatures($candles);
    
    // Obtener predicción del ensemble ML
    $mlPrediction = $mlPredictor->predict($features);
    
    // También calcular indicadores técnicos tradicionales como fallback
    $closes = array_column($candles, 'c');
    $rsi = rsiLast($closes, 14);
    $macd = macdHistLast($candles);
    $atr = atrPctLast($candles, 14);
    $e9 = ema($closes, 9);
    $e21 = ema($closes, 21);
    $e50 = ema($closes, 50);
    $lastClose = end($closes);
    $e9Last = end($e9);
    $e21Last = end($e21);
    
    $trend = 'NEUTRAL';
    $strength = 0;
    if ($lastClose > $e9Last && $e9Last > $e21Last) {
        $trend = 'BULLISH';
        $strength = (end($e50) && $lastClose > end($e50)) ? 2 : 1;
    } elseif ($lastClose < $e9Last && $e9Last < $e21Last) {
        $trend = 'BEARISH';
        $strength = (end($e50) && $lastClose < end($e50)) ? 2 : 1;
    }
    
    // Usar predicción ML si tiene confianza suficiente
    if ($mlPrediction['confidence'] >= 60) {
        $direction = $mlPrediction['direction'];
        $confidence = $mlPrediction['confidence'];
        $reason = "ML Ensemble: {$mlPrediction['direction']} (conf: {$mlPrediction['confidence']}%, pred: {$mlPrediction['prediction']})";
    } else {
        // Fallback a análisis técnico tradicional
        $confidence = 50;
        $reason = "Neutral - sin señal clara";
        $direction = 'NEUTRAL';
        
        if ($trend === 'BULLISH' && $rsi < 70 && $macd > 0) {
            $direction = 'LONG';
            $confidence = min(95, 50 + $strength * 15 + ($rsi < 50 ? 10 : 0));
            $reason = "Tendencia alcista (RSI=$rsi, MACD positivo)";
        } elseif ($trend === 'BEARISH' && $rsi > 30 && $macd < 0) {
            $direction = 'SHORT';
            $confidence = min(95, 50 + $strength * 15 + ($rsi > 50 ? 10 : 0));
            $reason = "Tendencia bajista (RSI=$rsi, MACD negativo)";
        }
    }
    
    return [
        'direction' => $direction,
        'confidence' => (int)$confidence,
        'reason' => $reason,
        'atr' => $atr,
        'ml_prediction' => $mlPrediction
    ];
}

$lI("[Bot] Entrando al bucle principal...");
$cycleCount = 0;
$lastAiCheck = 0;

while (true) {
    $cycleStart = microtime(true);
    $cycleCount++;
    try {
        if (file_exists($CTRL)) {
            $ctrl = json_decode(file_get_contents($CTRL), true);
            if (($ctrl['action'] ?? '') === 'STOP') {
                $lI("[Control] Stop solicitado. Saliendo...");
                break;
            }
        }
        $cfg = getOrCreateGridConfig(GridConstants::SYM);
        $rawCandles = $bybit->getKlines(GridConstants::SYM, GridConstants::TF, GridConstants::CANDLES);
        if (empty($rawCandles)) {
            $lW("[Bot] Sin velas disponibles, esperando...");
            sleep(2);
            continue;
        }
        $candles = parseCandles($rawCandles);
        $now = time();
        if ($now - $lastAiCheck >= GridConstants::AI_INTERVAL) {
            $analysis = calculateDirection($candles);
            $cfg->direction = $analysis['direction'];
            $cfg->confidence = $analysis['confidence'];
            $cfg->aiReason = $analysis['reason'];
            $cfg->atrPredicted = $analysis['atr'] / 100;
            $cfg->spacingPct = GridConstants::BASE_SPACING + ($analysis['atr'] / 100) * GridConstants::SPACING_ATR_MULT;
            updateGridConfig($cfg);
            $lastAiCheck = $now;
            $lI("[AI] {$cfg->direction}, Conf: {$cfg->confidence}%, Spacing: " . round($cfg->spacingPct * 100, 4) . "%");
        }
        $elapsed = microtime(true) - $cycleStart;
        if ($cycleCount % 10 === 0) $lI("[Bot] Ciclo $cycleCount ({$elapsed}s)");
        usleep((int)(max(0.1, GridConstants::CYCLE_SEC - $elapsed) * 1000000));
    } catch (Exception $e) {
        $lE("[Error] " . $e->getMessage());
        sleep(5);
    }
}
$lI("[Bot] Detenido correctamente");
