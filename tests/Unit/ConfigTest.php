<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;

class ConfigTest extends TestCase
{
    private string $tempConfigPath;

    protected function setUp(): void
    {
        $this->tempConfigPath = sys_get_temp_dir() . '/test_config_' . uniqid() . '.php';
        file_put_contents($this->tempConfigPath, '<?php
$db_host = "test_host";
$db_name = "test_db";
$db_username = "test_user";
$db_password = "test_pass";
$cc_encryption_hash = "test_hash";
$templates_compiledir = "templates_c/";
$mysql_charset = "utf8";
$display_errors = true;
$customadminpath = "admin";
$license = "test_license";
');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigPath)) {
            unlink($this->tempConfigPath);
        }
    }

    public function testLoadsConfig(): void
    {
        $config = new Config($this->tempConfigPath);
        $this->assertEquals('test_host', $config->get('db_host'));
        $this->assertEquals('test_db', $config->get('db_name'));
        $this->assertEquals('test_user', $config->get('db_username'));
        $this->assertEquals('test_pass', $config->get('db_password'));
        $this->assertEquals('test_hash', $config->get('cc_encryption_hash'));
    }

    public function testGetDbConfig(): void
    {
        $config = new Config($this->tempConfigPath);
        $dbConfig = $config->getDbConfig();

        $this->assertEquals('test_host', $dbConfig['host']);
        $this->assertEquals('test_db', $dbConfig['name']);
        $this->assertEquals('test_user', $dbConfig['user']);
        $this->assertEquals('test_pass', $dbConfig['pass']);
        $this->assertEquals('utf8', $dbConfig['charset']);
    }

    public function testGetCcEncryptionHash(): void
    {
        $config = new Config($this->tempConfigPath);
        $this->assertEquals('test_hash', $config->getCcEncryptionHash());
    }

    public function testGetCustomAdminPath(): void
    {
        $config = new Config($this->tempConfigPath);
        $this->assertEquals('admin', $config->getCustomAdminPath());
    }

    public function testGetDisplayErrors(): void
    {
        $config = new Config($this->tempConfigPath);
        // The parser reads 'true' as string 'true', which filter_var converts to true
        $this->assertTrue($config->getDisplayErrors());
    }

    public function testGetLicense(): void
    {
        $config = new Config($this->tempConfigPath);
        $this->assertEquals('test_license', $config->getLicense());
    }

    public function testDefaultValues(): void
    {
        // Config with missing values
        $minimalConfig = sys_get_temp_dir() . '/minimal_config_' . uniqid() . '.php';
        file_put_contents($minimalConfig, '<?php
$db_host = "localhost";
$db_name = "test";
$db_username = "user";
$db_password = "pass";
');

        $config = new Config($minimalConfig);
        $this->assertEquals('templates_c/', $config->getTemplateCompileDir());
        $this->assertEquals('utf8', $config->get('mysql_charset', 'utf8'));
        $this->assertFalse($config->getDisplayErrors());

        unlink($minimalConfig);
    }
}
