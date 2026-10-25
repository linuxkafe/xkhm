<?php
/**
 * Bootstrap do plugin xkhm – carrega configuração, define constantes de BD
 * e inicializa o container de serviços partilhado com a aplicação principal.
 */
defined('ABSPATH') || exit;

// Constantes de BD – podem ser sobrescritas em wp-config.php (opcional)
defined('XKHM_DB_HOST')     || define('XKHM_DB_HOST',     getenv('XKHM_DB_HOST')     ?: 'localhost');
defined('XKHM_DB_NAME')     || define('XKHM_DB_NAME',     getenv('XKHM_DB_NAME')     ?: 'linuxkonect');
defined('XKHM_DB_USER')     || define('XKHM_DB_USER',     getenv('XKHM_DB_USER')     ?: 'linuxkonect');
defined('XKHM_DB_PASSWORD') || define('XKHM_DB_PASSWORD', getenv('XKHM_DB_PASSWORD') ?: '');
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
 * Lê das opções do WP (definições), constantes ou env.
 */
function xkhm_db_config(): array
{
    $host = '';
    $port = 0;
    $name = '';
    $user = '';
    $pass = '';

    if (function_exists('get_option')) {
        $host = get_option('xkhm_db_host', '');
        $port = (int)get_option('xkhm_db_port', 0);
        $name = get_option('xkhm_db_name', '');
        $user = get_option('xkhm_db_user', '');
        $pass = get_option('xkhm_db_password', '');
    }

    if ($host === '' && defined('XKHM_DB_HOST')) $host = XKHM_DB_HOST;
    if ($port === 0 && defined('XKHM_DB_PORT')) $port = (int)XKHM_DB_PORT;
    if ($name === '' && defined('XKHM_DB_NAME')) $name = XKHM_DB_NAME;
    if ($user === '' && defined('XKHM_DB_USER')) $user = XKHM_DB_USER;
    if ($pass === '' && defined('XKHM_DB_PASSWORD')) $pass = XKHM_DB_PASSWORD;

    if ($host === '') $host = getenv('XKHM_DB_HOST') ?: 'localhost';
    if ($port === 0) $port = (int)(getenv('XKHM_DB_PORT') ?: 3306);
    if ($name === '') $name = getenv('XKHM_DB_NAME') ?: 'linuxkonect';
    if ($user === '') $user = getenv('XKHM_DB_USER') ?: 'linuxkonect';
    if ($pass === '') $pass = getenv('XKHM_DB_PASS') ?: getenv('XKHM_DB_PASSWORD') ?: '';

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