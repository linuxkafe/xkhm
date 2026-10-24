<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Service\ImportService;
use Xkhm\Config;
use Xkhm\Database\Connection;

/**
 * Unit tests for ImportService – uses the same test DB as the main suite.
 */
final class ImportServiceTest extends TestCase
{
    private ImportService $service;
    private array $source;

    protected function setUp(): void
    {
        $this->service = new ImportService();

        // Use the same DB config as the main test suite
        $config = new Config();
        $pdo = Connection::get($config->getDbConfig());

        $dbConfig = $config->getDbConfig();

        $this->source = [
            'type' => 'remote',
            'host' => $dbConfig['host'],
            'port' => (int)($dbConfig['port'] ?? 3306),
            'db'   => $dbConfig['name'],
            'user' => $dbConfig['user'],
            'pass' => $dbConfig['pass'],
        ];
    }

    public function testTestConnection(): void
    {
        $ok = $this->service->testConnection($this->source);
        $this->assertTrue($ok, 'Should connect to the test database');
    }

    public function testValidateSchema(): void
    {
        $result = $this->service->validateSchema($this->source);
        $this->assertArrayHasKey('missing', $result);
        $this->assertArrayHasKey('extra', $result);
        $this->assertArrayHasKey('tables', $result);
        // At least the core tables should exist
        $core = ['tblclients', 'tblinvoices', 'tblhosting', 'tbldomains', 'tblconfiguration', 'tbladmins'];
        foreach ($core as $table) {
            $this->assertContains($table, $result['tables'], "Core table $table missing");
        }
        // Missing tables are expected in a minimal test DB; just ensure core not missing
        foreach ($core as $table) {
            $this->assertNotContains($table, $result['missing'], "Core table $table should not be missing");
        }
    }

    public function testWhmcs53TablesList(): void
    {
        $tables = $this->service->whmcs53Tables();
        $this->assertCount(91, $tables, 'WHMCS 5.3.9 should have 91 tables');
        $this->assertContains('tblclients', $tables);
        $this->assertContains('tblinvoices', $tables);
    }
}