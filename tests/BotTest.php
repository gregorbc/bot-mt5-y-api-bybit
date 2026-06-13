<?php
/**
 * Tests unitarios básicos para Grid Bot
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Utils\GridConstants;
use App\Models\GridConfig;
use App\Models\GridOrder;

class BotTest extends TestCase
{
    /**
     * Test que verifica que las constantes estén cargadas correctamente
     */
    public function testConstantsLoaded(): void
    {
        $this->assertTrue(defined('App\\Utils\\GridConstants::SYM'));
        $this->assertEquals('ETHUSDT', GridConstants::SYM);
    }
    
    /**
     * Test para verificar constantes básicas de configuración
     */
    public function testBasicConstants(): void
    {
        $this->assertEquals(30.0, GridConstants::CAPITAL);
        $this->assertEquals(100, GridConstants::LEVERAGE);
        $this->assertEquals('5', GridConstants::TF);
        $this->assertEquals(16, GridConstants::FIXED_LEVELS);
    }
    
    /**
     * Test para verificar constantes de spacing
     */
    public function testSpacingConstants(): void
    {
        $this->assertEquals(0.0004, GridConstants::MIN_SPACING);
        $this->assertEquals(0.0012, GridConstants::MAX_SPACING);
        $this->assertEquals(0.0004, GridConstants::BASE_SPACING);
    }
    
    /**
     * Test para verificar direcciones válidas
     */
    public function testValidDirections(): void
    {
        $this->assertTrue(GridConstants::isValidDirection('LONG'));
        $this->assertTrue(GridConstants::isValidDirection('SHORT'));
        $this->assertTrue(GridConstants::isValidDirection('SIDEWAYS'));
        $this->assertTrue(GridConstants::isValidDirection('NEUTRAL'));
        $this->assertFalse(GridConstants::isValidDirection('INVALID'));
    }
    
    /**
     * Test para cálculo de cantidad mínima
     */
    public function testCalcMinQty(): void
    {
        $price = 3000.0;
        $expected = GridConstants::MIN_NOTIONAL / $price;
        $this->assertEquals($expected, GridConstants::calcMinQty($price));
    }
    
    /**
     * Test para cálculo de fees
     */
    public function testCalcFee(): void
    {
        $notional = 100.0;
        
        // Fee maker
        $expectedMaker = $notional * GridConstants::MAKER_FEE;
        $this->assertEquals($expectedMaker, GridConstants::calcFee($notional, true));
        
        // Fee taker
        $expectedTaker = $notional * GridConstants::TAKER_FEE;
        $this->assertEquals($expectedTaker, GridConstants::calcFee($notional, false));
    }
    
    /**
     * Test para obtener todas las constantes
     */
    public function testAllConstants(): void
    {
        $all = GridConstants::all();
        $this->assertIsArray($all);
        $this->assertNotEmpty($all);
        $this->assertArrayHasKey('SYM', $all);
        $this->assertArrayHasKey('CAPITAL', $all);
    }
}
