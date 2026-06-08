<?php

namespace App\Core;

/**
 * Logger centralizado para el Grid Bot
 * Soporta logs en archivo y consola con niveles de severidad
 */
class Logger
{
    public const DEBUG = 'DEBUG';
    public const INFO = 'INFO';
    public const WARNING = 'WARNING';
    public const ERROR = 'ERROR';
    public const CRITICAL = 'CRITICAL';
    
    private static ?Logger $instance = null;
    private string $logFile = '';
    private bool $consoleOutput = false;
    private array $buffer = [];
    private int $maxBufferSize = 100;
    
    private function __construct()
    {
        $config = Config::getInstance();
        $paths = $config->getPaths();
        $this->logFile = $paths['log'];
        
        // Verificar si estamos en CLI
        $this->consoleOutput = PHP_SAPI === 'cli';
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function setLogFile(string $path): void
    {
        $this->logFile = $path;
    }
    
    public function enableConsoleOutput(bool $enable = true): void
    {
        $this->consoleOutput = $enable;
    }
    
    public function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = $this->interpolate($message, $context);
        $logLine = "[$timestamp] [$level] $formattedMessage" . PHP_EOL;
        
        // Escribir en archivo
        if (!empty($this->logFile)) {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);
        }
        
        // Escribir en consola si está habilitado
        if ($this->consoleOutput) {
            $color = $this->getColorForLevel($level);
            fwrite(STDOUT, $color . $logLine . "\033[0m");
        }
        
        // Buffer para recuperación reciente
        $this->buffer[] = [
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $formattedMessage,
        ];
        
        if (count($this->buffer) > $this->maxBufferSize) {
            array_shift($this->buffer);
        }
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    public function getRecentLogs(int $limit = 50): array
    {
        return array_slice(array_reverse($this->buffer), 0, $limit);
    }
    
    public function clearBuffer(): void
    {
        $this->buffer = [];
    }
    
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }
            $replacements['{' . $key . '}'] = $value;
        }
        
        return strtr($message, $replacements);
    }
    
    private function getColorForLevel(string $level): string
    {
        return match($level) {
            self::DEBUG => "\033[36m",      // Cyan
            self::INFO => "\033[32m",       // Green
            self::WARNING => "\033[33m",    // Yellow
            self::ERROR => "\033[31m",      // Red
            self::CRITICAL => "\033[35m",   // Magenta
            default => "\033[37m"           // White
        };
    }
}
