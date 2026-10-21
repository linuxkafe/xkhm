<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;

class MigrationValidationTest extends TestCase
{
    private Config $config;
    private bool $dbAvailable = false;

    protected function setUp(): void
    {
        try {
            $this->config = new Config();
            $this->dbAvailable = true;
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('Configuration file not found (expected in CI/CD environments)');
        }
    }

    public function testDatabaseConnection(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT 1');
        $result = $stmt->fetchColumn();

        $this->assertEquals(1, $result);
    }

    public function testDatabaseCredentialsMatch(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Config not available');
        }

        // Verify DB config structure
        $dbConfig = $this->config->getDbConfig();

        $this->assertArrayHasKey('host', $dbConfig);
        $this->assertArrayHasKey('name', $dbConfig);
        $this->assertArrayHasKey('user', $dbConfig);
        $this->assertArrayHasKey('pass', $dbConfig);
        $this->assertArrayHasKey('charset', $dbConfig);

        // The production database name must not drift.
        $this->assertSame('linuxkonect', $dbConfig['name']);
    }

    public function testCcEncryptionHashPresent(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('Config not available');
        }

        $hash = $this->config->getCcEncryptionHash();
        $this->assertNotEmpty($hash, 'CC encryption hash should be present');
        // WHMCS stores a 64-character base64 hash, not hex.
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9+\/]{64}$/', $hash);
    }

    public function testTableSchemaIntegrity(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());

        // Check critical tables exist
        $tables = ['tblclients', 'tblinvoices', 'tbldomains', 'tblorders', 'tblticketdepartments'];
        foreach ($tables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            $this->assertNotEmpty($stmt->fetchAll(), "Table $table should exist");
        }
    }

    public function testClientDataIntegrity(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT COUNT(*) FROM tblclients');
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count);
    }

    public function testInvoiceDataIntegrity(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT COUNT(*) FROM tblinvoices');
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count);
    }

    public function testPasswordHashFormat(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT password FROM tblclients WHERE password IS NOT NULL AND password != "" LIMIT 1');
        $hash = $stmt->fetchColumn();

        $this->assertNotFalse($hash);
        // The production clients use the WHMCS 5.x format: a 32-character
        // MD5, a colon, and a 5-character salt — 38 characters in total.
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}:.{5}$/', $hash);
    }

    public function testAdminPasswordFormat(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT password FROM tbladmins WHERE password IS NOT NULL AND password != "" LIMIT 1');
        $hash = $stmt->fetchColumn();

        $this->assertNotFalse($hash);
        // The admin table was upgraded to bcrypt.
        $this->assertMatchesRegularExpression('/^\$2[axy]\$/', $hash);
    }

    public function testDomainPricingAvailable(): void
    {
        if (!$this->dbAvailable) {
            $this->markTestSkipped('DB not available');
        }

        $pdo = Connection::get($this->config->getDbConfig());
        $stmt = $pdo->query('SELECT COUNT(*) FROM tbldomainpricing');
        $count = (int)$stmt->fetchColumn();

        $this->assertGreaterThan(0, $count);
    }
}
