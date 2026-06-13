<?php
/**
 * Tests unitarios para GridOrder
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use App\Models\GridOrder;

class GridOrderTest extends TestCase
{
    /**
     * Test para crear GridOrder con array vacío
     */
    public function testCreateEmptyOrder(): void
    {
        $order = new GridOrder();
        
        $this->assertEquals(0, $order->id);
        $this->assertEquals(0, $order->configId);
        $this->assertEquals('ETHUSDT', $order->symbol);
        $this->assertEquals('NEUTRAL', $order->direction);
        $this->assertEquals(0, $order->gridLevel);
        $this->assertEquals('', $order->side);
        $this->assertEquals('', $order->gridRole);
        $this->assertEquals('', $order->orderId);
        $this->assertEquals(0.0, $order->price);
        $this->assertEquals(0.0, $order->qty);
        $this->assertEquals('OPEN', $order->status);
        $this->assertFalse($order->isRecovery);
    }
    
    /**
     * Test para crear GridOrder con datos
     */
    public function testCreateOrderWithData(): void
    {
        $data = [
            'id' => 1,
            'config_id' => 5,
            'symbol' => 'BTCUSDT',
            'direction' => 'LONG',
            'grid_level' => 3,
            'side' => 'BUY',
            'grid_role' => 'ENTRY',
            'order_id' => 'ORD123456',
            'price' => 50000.0,
            'exit_price' => 51000.0,
            'qty' => 0.1,
            'status' => 'FILLED',
            'pnl_usd' => 100.0,
            'is_recovery' => false,
        ];
        
        $order = new GridOrder($data);
        
        $this->assertEquals(1, $order->id);
        $this->assertEquals(5, $order->configId);
        $this->assertEquals('BTCUSDT', $order->symbol);
        $this->assertEquals('LONG', $order->direction);
        $this->assertEquals(3, $order->gridLevel);
        $this->assertEquals('BUY', $order->side);
        $this->assertEquals('ENTRY', $order->gridRole);
        $this->assertEquals('ORD123456', $order->orderId);
        $this->assertEquals(50000.0, $order->price);
        $this->assertEquals(51000.0, $order->exitPrice);
        $this->assertEquals(0.1, $order->qty);
        $this->assertEquals('FILLED', $order->status);
        $this->assertEquals(100.0, $order->pnlUsd);
        $this->assertFalse($order->isRecovery);
    }
    
    /**
     * Test para verificar el método isOpen
     */
    public function testIsOpen(): void
    {
        $openOrder = new GridOrder(['status' => 'OPEN']);
        $this->assertTrue($openOrder->isOpen());
        
        $filledOrder = new GridOrder(['status' => 'FILLED']);
        $this->assertFalse($filledOrder->isOpen());
        
        $cancelledOrder = new GridOrder(['status' => 'CANCELLED']);
        $this->assertFalse($cancelledOrder->isOpen());
    }
    
    /**
     * Test para verificar el método isFilled
     */
    public function testIsFilled(): void
    {
        $filledOrder = new GridOrder(['status' => 'FILLED']);
        $this->assertTrue($filledOrder->isFilled());
        
        $openOrder = new GridOrder(['status' => 'OPEN']);
        $this->assertFalse($openOrder->isFilled());
    }
    
    /**
     * Test para verificar métodos de rol (entry/exit)
     */
    public function testEntryExitMethods(): void
    {
        $entryOrder = new GridOrder(['grid_role' => 'ENTRY']);
        $this->assertTrue($entryOrder->isEntry());
        $this->assertFalse($entryOrder->isExit());
        
        $exitOrder = new GridOrder(['grid_role' => 'EXIT']);
        $this->assertFalse($exitOrder->isEntry());
        $this->assertTrue($exitOrder->isExit());
    }
    
    /**
     * Test para verificar métodos de dirección (buy/sell)
     */
    public function testBuySellMethods(): void
    {
        $buyOrder = new GridOrder(['side' => 'BUY']);
        $this->assertTrue($buyOrder->isBuy());
        $this->assertFalse($buyOrder->isSell());
        
        $sellOrder = new GridOrder(['side' => 'SELL']);
        $this->assertFalse($sellOrder->isBuy());
        $this->assertTrue($sellOrder->isSell());
        
        // Test case insensitive
        $buyOrderLower = new GridOrder(['side' => 'buy']);
        $this->assertTrue($buyOrderLower->isBuy());
    }
    
    /**
     * Test para convertir a array
     */
    public function testToArray(): void
    {
        $data = [
            'id' => 1,
            'config_id' => 5,
            'symbol' => 'ETHUSDT',
            'side' => 'BUY',
            'grid_role' => 'ENTRY',
            'price' => 3000.0,
            'qty' => 0.5,
            'status' => 'OPEN',
        ];
        
        $order = new GridOrder($data);
        $array = $order->toArray();
        
        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals(5, $array['config_id']);
        $this->assertEquals('ETHUSDT', $array['symbol']);
        $this->assertEquals('BUY', $array['side']);
        $this->assertEquals('ENTRY', $array['grid_role']);
        $this->assertEquals(3000.0, $array['price']);
        $this->assertEquals(0.5, $array['qty']);
        $this->assertEquals('OPEN', $array['status']);
    }
}
