<?php

declare(strict_types=1);

namespace Xkhm;

class Config
{
    private array $config = [];
    private bool $loaded = false;

    public function __construct(?string $configPath = null)
    {
        $configPath ??= dirname(__DIR__, 2) . '/configuration.php';
        $this->load($configPath);
    }

    private function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: $path");
        }

        $this->config = [];

        // Parse the PHP config file to extract variables
        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Could not read configuration file: $path");
        }

        // Extract variable assignments from the PHP file
        // Matches: $variable = 'value'; or $variable = "value"; or $variable = true; or $variable = false;
        preg_match_all(
            '/\$(\w+)\s*=\s*(?:([\'"])(.*?)\2|([\w.]+))\s*;/s',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $key = $match[1];
            // Group 3 is the string content (if quotes), group 4 is unquoted value
            $value = $match[3] !== '' ? $match[3] : $match[4];
            // Convert boolean strings
            if ($value === 'true') {
                $value = true;
            } elseif ($value === 'false') {
                $value = false;
            }
            $this->config[$key] = $value;
        }

        $this->loaded = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->loaded) {
            $this->load(dirname(__DIR__, 2) . '/configuration.php');
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
            $this->load(dirname(__DIR__, 2) . '/configuration.php');
        }
        return $this->config;
    }
}
