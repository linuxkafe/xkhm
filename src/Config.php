<?php

declare(strict_types=1);

namespace Xkhm;

class Config
{
    /**
     * Keys that must be present in `configuration.php`.
     *
     * Without this check a legacy configuration file (the old
     * `$db_host = '...'` form) would silently produce an empty config and
     * the service would fall back to `localhost/whmcs` with no password.
     */
    public const REQUIRED_KEYS = [
        'db_host',
        'db_name',
        'db_username',
        'db_password',
    ];

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

        $this->config = self::validate($cfg, $path);
        $this->loaded = true;
    }

    /**
     * @param array<mixed> $cfg
     * @return array<string, mixed>
     */
    private static function validate(array $cfg, string $path): array
    {
        if ($cfg === []) {
            throw new \RuntimeException(
                "Configuration file $path does not define the \$cfg array. "
                . 'Expected the WHMCS pattern $cfg[\'db_host\'] = \'...\';'
            );
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (!isset($cfg[$key])) {
                throw new \RuntimeException(
                    "Configuration file $path is missing the required key '$key'."
                );
            }
        }

        /** @var array<string, mixed> $cfg */
        return $cfg;
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
        // Environment variables take precedence over configuration.php so the
        // test suite can run on the host against the Docker-published MySQL
        // port (127.0.0.1:3306) while the container keeps using the service
        // name (mysql:3306). The committed configuration.php stays untouched
        // and continues to point at the production database.
        return [
            'host' => $this->env('XKHM_DB_HOST') ?? $this->get('db_host', 'localhost'),
            'port' => (int)($this->env('XKHM_DB_PORT') ?? $this->get('db_port', '3306')),
            'name' => $this->env('XKHM_DB_NAME') ?? $this->get('db_name', 'whmcs'),
            'user' => $this->env('XKHM_DB_USER') ?? $this->get('db_username', 'whmcs'),
            'pass' => $this->env('XKHM_DB_PASS') ?? $this->get('db_password', ''),
            'charset' => $this->get('mysql_charset', 'utf8'),
        ];
    }

    private function env(string $name): ?string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return null;
        }

        return $value;
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