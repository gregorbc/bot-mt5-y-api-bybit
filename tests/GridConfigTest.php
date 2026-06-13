<?php
/**
 * Tests unitarios para GridConfig
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Models\GridConfig;

class GridConfigTest extends TestCase
{
    /**
     * Test para crear GridConfig con array vacío
     */
    public function testCreateEmptyConfig(): void
    {
        $config = new GridConfig();
        
        $this->assertEquals(0, $config->id);
        $this->assertEquals('ETHUSDT', $config->symbol);
        $this->assertEquals('NEUTRAL', $config->direction);
        $this->assertEquals(50, $config->confidence);
        $this->assertEquals(30.0, $config->capitalUsd);
        $this->assertEquals(100, $config->leverage);
        $this->assertEquals(16, $config->levels);
        $this->assertEquals(0.0008, $config->spacingPct);
    }
    
    /**
     * Test para crear GridConfig con datos
     */
    public function testCreateConfigWithData(): void
    {
        $data = [
            'id' => 1,
            'symbol' => 'BTCUSDT',
            'direction' => 'LONG',
            'confidence' => 75,
            'capital_usd' => 50.0,
            'leverage' => 50,
            'levels' => 20,
            'spacing_pct' => 0.001,
            'long_levels' => 10,
            'short_levels' => 10,
            'status' => 'ACTIVE',
        ];
        
        $config = new GridConfig($data);
        
        $this->assertEquals(1, $config->id);
        $this->assertEquals('BTCUSDT', $config->symbol);
        $this->assertEquals('LONG', $config->direction);
        $this->assertEquals(75, $config->confidence);
        $this->assertEquals(50.0, $config->capitalUsd);
        $this->assertEquals(50, $config->leverage);
        $this->assertEquals(20, $config->levels);
        $this->assertEquals(0.001, $config->spacingPct);
        $this->assertEquals(10, $config->longLevels);
        $this->assertEquals(10, $config->shortLevels);
        $this->assertEquals('ACTIVE', $config->status);
    }
    
    /**
     * Test para verificar el método isActive
     */
    public function testIsActive(): void
    {
        // Config activa
        $activeConfig = new GridConfig(['status' => 'ACTIVE']);
        $this->assertTrue($activeConfig->isActive());
        
        // Config pausada
        $pausedConfig = new GridConfig(['status' => 'PAUSED', 'paused_reason' => 'Testing']);
        $this->assertFalse($pausedConfig->isActive());
        
        // Config con paused_reason pero status ACTIVE
        $pausedReasonConfig = new GridConfig(['status' => 'ACTIVE', 'paused_reason' => 'Testing']);
        $this->assertFalse($pausedReasonConfig->isActive());
    }
    
    /**
     * Test para verificar el método isRecoveryMode
     */
    public function testIsRecoveryMode(): void
    {
        // Recovery activo
        $recoveryConfig = new GridConfig(['recovery_active' => true]);
        $this->assertTrue($recoveryConfig->isRecoveryMode());
        
        // Recovery inactivo
        $normalConfig = new GridConfig(['recovery_active' => false]);
        $this->assertFalse($normalConfig->isRecoveryMode());
    }
    
    /**
     * Test para convertir a array
     */
    public function testToArray(): void
    {
        $data = [
            'id' => 1,
            'symbol' => 'ETHUSDT',
            'direction' => 'SHORT',
            'confidence' => 60,
            'ml_accuracy' => 0.85,
            'atr_predicted' => 0.5,
        ];
        
        $config = new GridConfig($data);
        $array = $config->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('ETHUSDT', $array['symbol']);
        $this->assertEquals('SHORT', $array['direction']);
        $this->assertEquals(60, $array['confidence']);
        $this->assertEquals(0.85, $array['ml_accuracy']);
        $this->assertEquals(0.5, $array['atr_predicted']);
    }
}
