<?php
/**
 * Tests unitarios básicos para Grid Bot
 */

namespace App\Tests;

use PHPUnit\Framework\TestCase;

class BotTest extends TestCase
{
    public function testConstantsLoaded(): void
    {
        $this->assertTrue(defined('App\\Utils\\GridConstants::SYM'));
        $this->assertEquals('ETHUSDT', \App\Utils\GridConstants::SYM);
    }
    
    public function testConfigSingleton(): void
    {
        $config1 = \App\Core\Config::getInstance();
        $config2 = \App\Core\Config::getInstance();
        $this->assertSame($config1, $config2);
    }
    
    public function testLoggerSingleton(): void
    {
        $logger1 = \App\Core\Logger::getInstance();
        $logger2 = \App\Core\Logger::getInstance();
        $this->assertSame($logger1, $logger2);
    }
    
    public function testDatabaseSingleton(): void
    {
        $db1 = \App\Core\Database::getInstance();
        $db2 = \App\Core\Database::getInstance();
        $this->assertSame($db1, $db2);
    }
}
