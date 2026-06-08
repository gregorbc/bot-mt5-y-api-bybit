<?php

namespace App\Models;

/**
 * Modelo para Órdenes de Grid
 */
class GridOrder
{
    public int $id = 0;
    public int $configId = 0;
    public string $symbol = 'ETHUSDT';
    public string $direction = 'NEUTRAL';
    public int $gridLevel = 0;
    public string $side = ''; // BUY o SELL
    public string $gridRole = ''; // ENTRY o EXIT
    public string $orderId = '';
    public float $price = 0.0;
    public ?float $exitPrice = null;
    public float $qty = 0.0;
    public string $status = 'OPEN'; // OPEN, FILLED, CANCELLED, REJECTED
    public ?int $linkedOrder = null;
    public ?float $pnlUsd = null;
    public bool $isRecovery = false;
    public ?\DateTime $filledAt = null;
    public ?\DateTime $createdAt = null;
    public ?\DateTime $updatedAt = null;
    
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            $property = $this->snakeToCamel($key);
            if (property_exists($this, $property)) {
                $this->$property = $this->castValue($property, $value);
            }
        }
    }
    
    private function snakeToCamel(string $str): string
    {
        return lcfirst(str_replace('_', '', ucwords($str, '_')));
    }
    
    private function castValue(string $property, $value)
    {
        if ($value === null) {
            return null;
        }
        
        return match($property) {
            'id', 'configId', 'gridLevel', 'linkedOrder' => (int)$value,
            'price', 'exitPrice', 'qty', 'pnlUsd' => (float)$value,
            'isRecovery' => (bool)$value,
            'filledAt', 'createdAt', 'updatedAt' => $value ? new \DateTime($value) : null,
            default => (string)$value,
        };
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'config_id' => $this->configId,
            'symbol' => $this->symbol,
            'direction' => $this->direction,
            'grid_level' => $this->gridLevel,
            'side' => $this->side,
            'grid_role' => $this->gridRole,
            'order_id' => $this->orderId,
            'price' => $this->price,
            'exit_price' => $this->exitPrice,
            'qty' => $this->qty,
            'status' => $this->status,
            'linked_order' => $this->linkedOrder,
            'pnl_usd' => $this->pnlUsd,
            'is_recovery' => $this->isRecovery,
            'filled_at' => $this->filledAt?->format('Y-m-d H:i:s'),
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    public function isOpen(): bool
    {
        return $this->status === 'OPEN';
    }
    
    public function isFilled(): bool
    {
        return $this->status === 'FILLED';
    }
    
    public function isEntry(): bool
    {
        return $this->gridRole === 'ENTRY';
    }
    
    public function isExit(): bool
    {
        return $this->gridRole === 'EXIT';
    }
    
    public function isBuy(): bool
    {
        return strtoupper($this->side) === 'BUY';
    }
    
    public function isSell(): bool
    {
        return strtoupper($this->side) === 'SELL';
    }
}
