<?php

namespace App\Core;

/**
 * Clase de configuración centralizada
 * Carga y valida config.json desde múltiples ubicaciones
 */
class Config
{
    private static ?Config $instance = null;
    private array $config = [];
    private string $configPath = '';
    
    private function __construct()
    {
        $this->load();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function load(): void
    {
        $cfgPaths = [
            dirname(__DIR__, 2) . '/config/config.json',
            dirname(__DIR__, 2) . '/private/config.json',
            __DIR__ . '/../../config/config.json',
            '/home/erika/config/config.json',
        ];
        
        $cfgFile = null;
        foreach ($cfgPaths as $path) {
            if (@file_exists($path)) {
                $cfgFile = $path;
                break;
            }
        }
        
        if (!$cfgFile) {
            throw new \RuntimeException(
                "ERROR: config.json no encontrado.\nBuscado en:\n  " . implode("\n  ", $cfgPaths)
            );
        }
        
        $this->configPath = $cfgFile;
        $content = file_get_contents($cfgFile);
        $decoded = json_decode($content, true);
        
        if (!is_array($decoded)) {
            throw new \RuntimeException("ERROR: config.json inválido en {$cfgFile}");
        }
        
        $this->config = trimRecursive($decoded);
    }
    
    public function get(array $keys, $default = null)
    {
        $value = $this->config;
        foreach ($keys as $key) {
            if (!isset($value[$key])) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }
    
    public function all(): array
    {
        return $this->config;
    }
    
    public function getConfigPath(): string
    {
        return $this->configPath;
    }
    
    public function getConfigDir(): string
    {
        return dirname($this->configPath);
    }
    
    // Métodos específicos para secciones comunes
    public function getBybitKey(): string
    {
        return trim($this->get(['bybit', 'api_key'], ''));
    }
    
    public function getBybitSecret(): string
    {
        return trim($this->get(['bybit', 'api_secret'], ''));
    }
    
    public function isTestnet(): bool
    {
        return (bool)$this->get(['bybit', 'testnet'], false);
    }
    
    public function getDbConfig(): array
    {
        return [
            'host' => $this->get(['mysql', 'host'], 'localhost'),
            'dbname' => $this->get(['mysql', 'dbname'], ''),
            'user' => $this->get(['mysql', 'user'], ''),
            'password' => $this->get(['mysql', 'password'], ''),
        ];
    }
    
    public function getPaths(): array
    {
        $botDir = dirname(__DIR__, 2);
        $configDir = $this->getConfigDir();
        
        return [
            'log' => $this->get(['paths', 'log'], "$botDir/bot.log"),
            'status' => $this->get(['paths', 'status'], "$configDir/grid_status.json"),
            'ctrl' => $this->get(['paths', 'ctrl'], "$configDir/grid_control.json"),
            'conf_hist' => $this->get(['paths', 'conf_hist'], "$configDir/grid_confidence.json"),
            'pid' => $this->get(['paths', 'pid'], "$botDir/grid_bot.pid"),
            'web_dir' => $this->get(['paths', 'web_dir'], $botDir),
        ];
    }
}
