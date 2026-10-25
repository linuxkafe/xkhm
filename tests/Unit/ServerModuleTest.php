<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Module\Server\ServerModuleFactory;
use Xkhm\Module\Server\Cpanel;
use Xkhm\Module\Server\Virtualmin;

class ServerModuleTest extends TestCase
{
    public function testFactoryCreatesCpanel(): void
    {
        $module = ServerModuleFactory::create('cpanel', [
            'hostname' => 'server.example.com',
            'username' => 'root',
            'password' => 'secret',
        ]);
        $this->assertInstanceOf(Cpanel::class, $module);
    }

    public function testFactoryCreatesVirtualmin(): void
    {
        $module = ServerModuleFactory::create('virtualmin', [
            'hostname' => 'server.example.com',
            'username' => 'root',
            'password' => 'secret',
        ]);
        $this->assertInstanceOf(Virtualmin::class, $module);
    }

    public function testFactoryReturnsNullForUnknown(): void
    {
        $module = ServerModuleFactory::create('unknown', []);
        $this->assertNull($module);
    }

    public function testSupportedList(): void
    {
        $supported = ServerModuleFactory::supported();
        $this->assertContains('cpanel', $supported);
        $this->assertContains('virtualmin', $supported);
    }

    public function testCpanelCreateAccountRequiresParams(): void
    {
        $module = new Cpanel(['hostname' => 'localhost']);
        $result = $module->createAccount([]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', strtolower($result['message']));
    }

    public function testVirtualminCreateAccountRequiresDomain(): void
    {
        $module = new Virtualmin(['hostname' => 'localhost']);
        $result = $module->createAccount([]);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('required', strtolower($result['message']));
    }
}