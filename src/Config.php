<?php

declare(strict_types=1);

namespace Xkhm;

class Config
{
    private array $config = [];
    private bool $loaded = false;

    public function __construct(?string $configPath = null)
    {
        $configPath ??= dirname(__DIR__) . '/configuration.php';
        $this->load($configPath);
    }

    private function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: $path");
        }

        $this->config = [];

        // Use require to execute the config file (safe since we control it)
        // The config file uses $cfg['key'] = 'value' pattern
        $cfg = [];
        require $path;

        $this->config = $cfg;
        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load(dirname(__DIR__) . '/configuration.php');
        }

        return $this->config[$key] ?? $default;
    }

    public function getDbConfig(): array
    {
        return [
            'host' => $this->get('db_host', 'localhost'),
            'name' => $this->get('db_name', 'whmcs'),
            'user' => $this->get('db_username', 'whmcs'),
            'pass' => $this->get('db_password', ''),
            'charset' => $this->get('mysql_charset', 'utf8'),
        ];
    }

    public function getCcEncryptionHash(): string
    {
        return $this->get('cc_encryption_hash', '');
    }

    public function getTemplateCompileDir(): string
    {
        return $this->get('templates_compiledir', 'templates_c/');
    }

    public function getCustomAdminPath(): string
    {
        return $this->get('customadminpath', 'admin');
    }

    public function getLicense(): string
    {
        return $this->get('license', '');
    }

    public function getDisplayErrors(): bool
    {
        $val = $this->get('display_errors', 'false');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function all(): array
    {
        if (!$this->loaded) {
            $this->load(dirname(__DIR__) . '/configuration.php');
        }
        return $this->config;
    }
}