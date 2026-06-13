<?php

namespace App\Models;

/**
 * Modelo para configuración de Grid
 */
class GridConfig
{
    public int $id = 0;
    public string $symbol = 'ETHUSDT';
    public string $direction = 'NEUTRAL';
    public int $confidence = 50;
    public string $aiReason = '';
    public ?\DateTime $lastAiCheck = null;
    public float $capitalUsd = 30.0;
    public int $leverage = 100;
    public int $levels = 16;
    public float $spacingPct = 0.0008;
    public int $longLevels = 8;
    public int $shortLevels = 8;
    public float $qtyPerLevel = 0.0;
    public int $pp = 2;
    public int $qp = 3;
    public string $mode = 'NORMAL';
    public bool $recoveryActive = false;
    public float $peakPnlToday = 0.0;
    public string $status = 'ACTIVE';
    public ?string $pausedReason = null;
    public float $mlAccuracy = 0.0;
    public float $atrPredicted = 0.0;
    public float $vlUsed = 0.0;
    public string $vlDirection = 'SIDEWAYS';
    public int $vlConfidence = 50;
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
            'id', 'confidence', 'leverage', 'levels', 'longLevels', 'shortLevels', 
            'pp', 'qp', 'vlConfidence' => (int)$value,
            'capitalUsd', 'spacingPct', 'qtyPerLevel', 'peakPnlToday', 
            'mlAccuracy', 'atrPredicted', 'vlUsed' => (float)$value,
            'recoveryActive' => (bool)$value,
            'lastAiCheck', 'createdAt', 'updatedAt' => $value ? new \DateTime($value) : null,
            default => (string)$value,
        };
    }
    
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'symbol' => $this->symbol,
            'direction' => $this->direction,
            'confidence' => $this->confidence,
            'ai_reason' => $this->aiReason,
            'last_ai_check' => $this->lastAiCheck?->format('Y-m-d H:i:s'),
            'capital_usd' => $this->capitalUsd,
            'leverage' => $this->leverage,
            'levels' => $this->levels,
            'spacing_pct' => $this->spacingPct,
            'long_levels' => $this->longLevels,
            'short_levels' => $this->shortLevels,
            'qty_per_level' => $this->qtyPerLevel,
            'pp' => $this->pp,
            'qp' => $this->qp,
            'mode' => $this->mode,
            'recovery_active' => $this->recoveryActive,
            'peak_pnl_today' => $this->peakPnlToday,
            'status' => $this->status,
            'paused_reason' => $this->pausedReason,
            'ml_accuracy' => $this->mlAccuracy,
            'atr_predicted' => $this->atrPredicted,
            'vl_used' => $this->vlUsed,
            'vl_direction' => $this->vlDirection,
            'vl_confidence' => $this->vlConfidence,
            'created_at' => $this->createdAt?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
        ];
    }
    
    public function isActive(): bool
    {
        return $this->status === 'ACTIVE' && $this->pausedReason === null;
    }
    
    public function isRecoveryMode(): bool
    {
        return $this->recoveryActive;
    }
}
