<?php

declare(strict_types=1);

namespace Xkhm\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;

class ConfigTest extends TestCase
{
    private string $tempConfigPath;

    /**
     * @var array<string, string|false>
     */
    private array $savedEnv = [];

    protected function setUp(): void
    {
        // The env overrides are a deployment feature; the unit test asserts
        // the configuration-file behaviour, so they must be neutralised.
        $this->savedEnv = [];
        foreach (['XKHM_DB_HOST', 'XKHM_DB_NAME', 'XKHM_DB_USER', 'XKHM_DB_PASS'] as $name) {
            $this->savedEnv[$name] = getenv($name);
            putenv($name);
        }

        $this->tempConfigPath = sys_get_temp_dir() . '/test_config_' . uniqid() . '.php';
        // The configuration file uses the WHMCS `$cfg['key']` array pattern.
        file_put_contents($this->tempConfigPath, '<?php
$cfg["db_host"] = "test_host";
$cfg["db_name"] = "test_db";
$cfg["db_username"] = "test_user";
$cfg["db_password"] = "test_pass";
$cfg["cc_encryption_hash"] = "test_hash";
$cfg["templates_compiledir"] = "templates_c/";
$cfg["mysql_charset"] = "utf8";
$cfg["display_errors"] = true;
$cfg["customadminpath"] = "admin";
$cfg["license"] = "test_license";
');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempConfigPath)) {
            unlink($this->tempConfigPath);
        }

        foreach ($this->savedEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
            } else {
                putenv($name . '=' . $value);
            }
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
$cfg["db_host"] = "localhost";
$cfg["db_name"] = "test";
$cfg["db_username"] = "user";
$cfg["db_password"] = "pass";
');

        $config = new Config($minimalConfig);
        $this->assertEquals('templates_c/', $config->getTemplateCompileDir());
        $this->assertEquals('utf8', $config->get('mysql_charset', 'utf8'));
        $this->assertFalse($config->getDisplayErrors());

        unlink($minimalConfig);
    }

    public function testEnvOverrideTakesPrecedence(): void
    {
        $config = new Config($this->tempConfigPath);

        putenv('XKHM_DB_HOST=override-host');
        putenv('XKHM_DB_NAME=override-db');

        $dbConfig = $config->getDbConfig();
        $this->assertEquals('override-host', $dbConfig['host']);
        $this->assertEquals('override-db', $dbConfig['name']);
        // Non-overridden keys still come from the configuration file.
        $this->assertEquals('test_user', $dbConfig['user']);
    }
}
