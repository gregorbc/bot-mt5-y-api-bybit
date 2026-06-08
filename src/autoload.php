<?php
/**
 * Autoloader para Grid Bot ETH/USDT
 * 
 * Estructura de namespaces:
 * - Core\     : Clases base (Database, Logger, Config, HttpClient)
 * - Services\ : Servicios externos (BybitAPI, MLService, NotificationService)
 * - Models\   : Modelos de datos (GridConfig, GridOrder, Ticker, Candle)
 * - Utils\    : Utilidades (Helpers, Validators, Constants)
 */

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Funciones helper globales
if (!function_exists('cv')) {
    /**
     * Get nested config value
     */
    function cv($config, array $keys, $default = null) {
        $value = $config;
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
}

if (!function_exists('trimRecursive')) {
    /**
     * Trim all string values in array recursively
     */
    function trimRecursive(array $arr): array {
        $out = [];
        foreach ($arr as $k => $v) {
            $tk = trim($k);
            $out[$tk] = is_array($v) ? trimRecursive($v) : (is_string($v) ? trim($v) : $v);
        }
        return $out;
    }
}
