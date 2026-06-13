<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Gestor de conexión a base de datos MySQL
 * Singleton con reconexión automática y creación de tablas
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $connection = null;
    private array $config;
    private int $timeout = 3;
    
    private function __construct()
    {
        $config = Config::getInstance();
        $this->config = $config->getDbConfig();
    }
    
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection(): ?PDO
    {
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }
    
    private function connect(): void
    {
        if (empty($this->config['host']) || empty($this->config['dbname'])) {
            return;
        }
        
        $hosts = array_unique([$this->config['host'], '127.0.0.1', 'localhost']);
        
        foreach ($hosts as $host) {
            try {
                $dsn = "mysql:host=$host;dbname={$this->config['dbname']};charset=utf8mb4";
                $this->connection = new PDO(
                    $dsn,
                    $this->config['user'],
                    $this->config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => $this->timeout,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]
                );
                
                $this->connection->exec("SET time_zone = '+00:00'");
                $this->connection->query('SELECT 1');
                
                // Inicializar tablas
                $this->initTables();
                
                return;
            } catch (PDOException $e) {
                continue;
            }
        }
        
        $this->connection = null;
    }
    
    public function initTables(): void
    {
        if ($this->connection === null) {
            return;
        }
        
        try {
            // Tabla grid_configs
            $this->connection->exec("CREATE TABLE IF NOT EXISTS grid_configs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                symbol VARCHAR(20) NOT NULL,
                direction VARCHAR(20) DEFAULT 'NEUTRAL',
                confidence INT DEFAULT 50,
                ai_reason VARCHAR(400) DEFAULT '',
                last_ai_check DATETIME,
                capital_usd DECIMAL(12,4),
                leverage INT DEFAULT 100,
                levels INT DEFAULT 10,
                spacing_pct DECIMAL(10,6) DEFAULT 0.000800,
                long_levels INT DEFAULT 5,
                short_levels INT DEFAULT 5,
                qty_per_level DECIMAL(20,8) DEFAULT 0,
                pp INT DEFAULT 2,
                qp INT DEFAULT 3,
                mode VARCHAR(20) DEFAULT 'NORMAL',
                recovery_active TINYINT(1) DEFAULT 0,
                peak_pnl_today DECIMAL(14,6) DEFAULT 0,
                status VARCHAR(10) DEFAULT 'ACTIVE',
                paused_reason VARCHAR(100) DEFAULT NULL,
                ml_accuracy DECIMAL(6,4) DEFAULT 0,
                atr_predicted DECIMAL(10,8) DEFAULT 0,
                vl_used DECIMAL(10,8) DEFAULT 0,
                vl_direction VARCHAR(20) DEFAULT 'SIDEWAYS',
                vl_confidence INT DEFAULT 50,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sym (symbol)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabla grid_orders
            $this->connection->exec("CREATE TABLE IF NOT EXISTS grid_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                config_id INT,
                symbol VARCHAR(20),
                direction VARCHAR(20),
                grid_level INT,
                side VARCHAR(5),
                grid_role VARCHAR(5),
                order_id VARCHAR(80),
                price DECIMAL(20,8),
                exit_price DECIMAL(20,8),
                qty DECIMAL(20,8),
                status VARCHAR(12) DEFAULT 'OPEN',
                linked_order INT DEFAULT NULL,
                pnl_usd DECIMAL(14,8),
                is_recovery TINYINT(1) DEFAULT 0,
                filled_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_symbol (symbol),
                INDEX idx_status (status),
                INDEX idx_filled (filled_at),
                INDEX idx_config (config_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
            // Tabla grid_pnl_daily
            $this->connection->exec("CREATE TABLE IF NOT EXISTS grid_pnl_daily (
                id INT AUTO_INCREMENT PRIMARY KEY,
                symbol VARCHAR(20) NOT NULL,
                date DATE NOT NULL,
                total_pnl DECIMAL(14,8) DEFAULT 0,
                trades_count INT DEFAULT 0,
                wins INT DEFAULT 0,
                losses INT DEFAULT 0,
                win_rate DECIMAL(6,4) DEFAULT 0,
                avg_pnl DECIMAL(14,8) DEFAULT 0,
                max_pnl DECIMAL(14,8) DEFAULT 0,
                min_pnl DECIMAL(14,8) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_sym_date (symbol, date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            
        } catch (PDOException $e) {
            // Silencioso - las tablas pueden no ser necesarias en todos los contextos
        }
    }
    
    public function query(string $sql, array $params = []): array
    {
        $pdo = $this->getConnection();
        if ($pdo === null) {
            return [];
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            return [];
        }
    }
    
    public function execute(string $sql, array $params = []): int
    {
        $pdo = $this->getConnection();
        if ($pdo === null) {
            return 0;
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
    
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $results = $this->query($sql, $params);
        return $results[0] ?? null;
    }
    
    public function fetchColumn(string $sql, array $params = [])
    {
        $result = $this->fetchOne($sql, $params);
        if ($result === null) {
            return null;
        }
        return reset($result);
    }
}
