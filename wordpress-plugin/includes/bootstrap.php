<?php
/**
 * Bootstrap do plugin xkhm – carrega configuração, define constantes de BD
 * e inicializa o container de serviços partilhado com a aplicação principal.
 */
defined('ABSPATH') || exit;

// Constantes de BD – por defeito usa a BD do WordPress
defined('XKHM_DB_HOST')     || define('XKHM_DB_HOST',     defined('DB_HOST') ? DB_HOST : (getenv('XKHM_DB_HOST') ?: 'localhost'));
defined('XKHM_DB_NAME')     || define('XKHM_DB_NAME',     defined('DB_NAME') ? DB_NAME : (getenv('XKHM_DB_NAME') ?: 'wordpress'));
defined('XKHM_DB_USER')     || define('XKHM_DB_USER',     defined('DB_USER') ? DB_USER : (getenv('XKHM_DB_USER') ?: 'root'));
defined('XKHM_DB_PASSWORD') || define('XKHM_DB_PASSWORD', defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('XKHM_DB_PASSWORD') ?: ''));
defined('XKHM_DB_PREFIX')   || define('XKHM_DB_PREFIX',   'tbl'); // prefixo WHMCS

// Configuração partilhada (reutiliza Xkhm\Config)
if (!class_exists('Xkhm\Config')) {
    $candidates = [
        XKHM_PLUGIN_DIR . 'src/Config.php',
        dirname(XKHM_PLUGIN_DIR, 2) . '/src/Config.php',
    ];
    foreach ($candidates as $cfgFile) {
        if (file_exists($cfgFile)) {
            require_once $cfgFile;
            break;
        }
    }
}

/**
 * Retorna array de configuração PDO para o xkhm.
 * Usa a base de dados do WordPress (DB_HOST, DB_NAME, etc.).
 */
function xkhm_db_config(): array
{
    $host = defined('DB_HOST') ? DB_HOST : (getenv('XKHM_DB_HOST') ?: (defined('XKHM_DB_HOST') ? XKHM_DB_HOST : 'localhost'));
    $name = defined('DB_NAME') ? DB_NAME : (getenv('XKHM_DB_NAME') ?: (defined('XKHM_DB_NAME') ? XKHM_DB_NAME : 'wordpress'));
    $user = defined('DB_USER') ? DB_USER : (getenv('XKHM_DB_USER') ?: (defined('XKHM_DB_USER') ? XKHM_DB_USER : 'root'));
    $pass = defined('DB_PASSWORD') ? DB_PASSWORD : (getenv('XKHM_DB_PASSWORD') ?: (defined('XKHM_DB_PASSWORD') ? XKHM_DB_PASSWORD : ''));
    $port = (int)(getenv('XKHM_DB_PORT') ?: (defined('XKHM_DB_PORT') ? XKHM_DB_PORT : 3306));

    return [
        'host'     => $host,
        'port'     => $port,
        'dbname'   => $name,
        'user'     => $user,
        'password' => $pass,
        'charset'  => 'utf8mb4',
        'prefix'   => XKHM_DB_PREFIX,
        'options'  => [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET SESSION sql_mode='NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
        ],
    ];
}

/**
 * Container simples de serviços (singleton) para reutilizar repositórios/serviços
 * entre shortcode, APIs e wizard.
 */
class Xkhm_Container
{
    private static ?self $instance = null;
    private array $services = [];

    private function __construct() {}

    public static function get(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function make(string $id, callable $factory)
    {
        if (!isset($this->services[$id])) {
            $this->services[$id] = $factory($this);
        }
        return $this->services[$id];
    }
}

/**
 * Helper para obter o container.
 */
function xkhm_container(): Xkhm_Container
{
    return Xkhm_Container::get();
}