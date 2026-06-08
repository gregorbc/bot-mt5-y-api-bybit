<?php

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;

/**
 * Cliente HTTP para la API de Bybit
 * Maneja autenticación y requests firmados
 */
class BybitApi
{
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private string $publicUrl;
    private bool $testnet;
    
    public function __construct()
    {
        $config = Config::getInstance();
        
        $this->apiKey = $config->getBybitKey();
        $this->apiSecret = $config->getBybitSecret();
        $this->testnet = $config->isTestnet();
        
        $this->baseUrl = $this->testnet 
            ? 'https://api-demo.bybit.com' 
            : 'https://api.bybit.com';
        
        // Para datos de mercado SIEMPRE mainnet (testnet no tiene klines históricas)
        $this->publicUrl = 'https://api.bybit.com';
    }
    
    /**
     * Request público GET (sin autenticación)
     */
    public function publicGet(string $path, array $params = []): ?array
    {
        $url = $this->publicUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        return ($data['retCode'] ?? -1) === 0 ? ($data['result'] ?? null) : null;
    }
    
    /**
     * Request privado GET (con autenticación)
     */
    public function privateGet(string $path, array $params = []): ?array
    {
        return $this->signedRequest('GET', $path, $params);
    }
    
    /**
     * Request privado POST (con autenticación)
     */
    public function privatePost(string $path, array $params = []): ?array
    {
        return $this->signedRequest('POST', $path, $params);
    }
    
    /**
     * Realiza un request firmado a la API de Bybit
     */
    private function signedRequest(string $method, string $path, array $params = []): ?array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            return null;
        }
        
        $timestamp = (string)(intval(microtime(true) * 1000));
        $recvWindow = '8000';
        
        // Preparar query string o body
        $queryString = '';
        if (!empty($params)) {
            ksort($params);
            $queryString = http_build_query($params);
        }
        
        // Crear string para firmar
        $signStr = $timestamp . $this->apiKey . $recvWindow . $queryString;
        $signature = hash_hmac('sha256', $signStr, $this->apiSecret);
        
        // Headers
        $headers = [
            "X-BAPI-API-KEY: {$this->apiKey}",
            "X-BAPI-TIMESTAMP: $timestamp",
            "X-BAPI-RECV-WINDOW: $recvWindow",
            "X-BAPI-SIGN: $signature",
            "Content-Type: application/json",
        ];
        
        $url = $this->baseUrl . $path;
        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . $queryString;
        }
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        
        if ($method === 'POST' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            return null;
        }
        
        $data = json_decode($response, true);
        return ($data['retCode'] ?? -1) === 0 ? ($data['result'] ?? null) : null;
    }
    
    /**
     * Obtener velas (klines) históricas
     */
    public function getKlines(string $symbol, string $interval, int $limit = 200, ?int $endTime = null): array
    {
        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'interval' => $interval,
            'limit' => min($limit, 1000),
        ];
        
        if ($endTime !== null) {
            $params['endTime'] = $endTime;
        }
        
        $result = $this->publicGet('/v5/market/kline', $params);
        return $result['list'] ?? [];
    }
    
    /**
     * Obtener todas las velas hasta alcanzar el límite deseado
     */
    public function getAllKlines(string $symbol, string $interval, int $maxCandles = 5000): array
    {
        $allKlines = [];
        $endTime = null;
        
        while (count($allKlines) < $maxCandles) {
            $klines = $this->getKlines($symbol, $interval, 1000, $endTime);
            
            if (empty($klines)) {
                break;
            }
            
            $allKlines = array_merge($allKlines, $klines);
            
            // Actualizar endTime para la siguiente iteración
            $endTime = (int)end($klines)[0] - 1;
            
            if (count($klines) < 1000) {
                break;
            }
            
            usleep(300000); // Rate limit
        }
        
        return $allKlines;
    }
    
    /**
     * Obtener precio actual (ticker)
     */
    public function getTicker(string $symbol): ?array
    {
        $result = $this->publicGet('/v5/market/tickers', [
            'category' => 'linear',
            'symbol' => $symbol,
        ]);
        
        if ($result && !empty($result['list'])) {
            return $result['list'][0];
        }
        
        return null;
    }
    
    /**
     * Obtener balance de la cuenta
     */
    public function getBalance(): float
    {
        $result = $this->privateGet('/v5/account/wallet-balance', [
            'accountType' => 'UNIFIED',
        ]);
        
        if (!$result || empty($result['list'])) {
            return 0.0;
        }
        
        foreach ($result['list'] as $account) {
            $avail = (float)($account['totalAvailableBalance'] ?? 0);
            if ($avail > 0) {
                return $avail;
            }
            
            foreach ($account['coin'] ?? [] as $coin) {
                if (($coin['coin'] ?? '') !== 'USDT') {
                    continue;
                }
                
                foreach (['availableToWithdraw', 'availableBalance', 'walletBalance', 'equity'] as $field) {
                    $value = (float)($coin[$field] ?? 0);
                    if ($value > 0) {
                        return $value;
                    }
                }
            }
            
            $eq = (float)($account['totalEquity'] ?? 0);
            if ($eq > 0) {
                return $eq;
            }
        }
        
        return 0.0;
    }
    
    /**
     * Obtener posiciones abiertas
     */
    public function getPositions(string $symbol): array
    {
        $result = $this->privateGet('/v5/position/list', [
            'category' => 'linear',
            'symbol' => $symbol,
        ]);
        
        return $result['list'] ?? [];
    }
    
    /**
     * Colocar orden
     */
    public function placeOrder(string $symbol, string $side, string $orderType, 
                               float $qty, ?float $price = null, array $extraParams = []): ?array
    {
        $params = [
            'category' => 'linear',
            'symbol' => $symbol,
            'side' => strtoupper($side),
            'orderType' => strtoupper($orderType),
            'qty' => (string)$qty,
            'timeInForce' => 'GTC',
        ];
        
        if ($price !== null && in_array(strtoupper($orderType), ['LIMIT', 'LIMITMAKER'])) {
            $params['price'] = (string)$price;
        }
        
        $params = array_merge($params, $extraParams);
        
        return $this->privatePost('/v5/order/create', $params);
    }
    
    /**
     * Cancelar orden
     */
    public function cancelOrder(string $symbol, string $orderId): ?array
    {
        return $this->privatePost('/v5/order/cancel', [
            'category' => 'linear',
            'symbol' => $symbol,
            'orderId' => $orderId,
        ]);
    }
    
    /**
     * Obtener historial de órdenes
     */
    public function getOrderHistory(string $symbol, int $limit = 100): array
    {
        $result = $this->privateGet('/v5/order/realtime', [
            'category' => 'linear',
            'symbol' => $symbol,
            'limit' => $limit,
        ]);
        
        return $result['list'] ?? [];
    }
}
