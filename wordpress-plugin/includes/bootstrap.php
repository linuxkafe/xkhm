<?php
/**
 * Bootstrap do plugin xkhm – carrega configuração, define constantes de BD
 * e inicializa o container de serviços partilhado com a aplicação principal.
 */
defined('ABSPATH') || exit;

// Constantes de BD – podem ser sobrescritas em wp-config.php
defined('XKHM_DB_HOST')     || define('XKHM_DB_HOST',     getenv('XKHM_DB_HOST')     ?: 'localhost');
defined('XKHM_DB_NAME')     || define('XKHM_DB_NAME',     getenv('XKHM_DB_NAME')     ?: 'linuxkonect');
defined('XKHM_DB_USER')     || define('XKHM_DB_USER',     getenv('XKHM_DB_USER')     ?: 'linuxkonect');
defined('XKHM_DB_PASSWORD') || define('XKHM_DB_PASSWORD', getenv('XKHM_DB_PASSWORD') ?: '');
defined('XKHM_DB_PREFIX')   || define('XKHM_DB_PREFIX',   'tbl'); // prefixo WHMCS

// Configuração partilhada (reutiliza Xkhm\Config)
if (!class_exists('Xkhm\Config')) {
    // Se o autoload do projeto principal não carregou, tentamos incluir manualmente
    $cfgFile = dirname(XKHM_PLUGIN_DIR, 2) . '/src/Config.php';
    if (file_exists($cfgFile)) {
        require_once $cfgFile;
    }
}

/**
 * Retorna array de configuração PDO para o xkhm.
 */
function xkhm_db_config(): array
{
    return [
        'driver'   => 'mysql',
        'host'     => XKHM_DB_HOST,
        'dbname'   => XKHM_DB_NAME,
        'user'     => XKHM_DB_USER,
        'password' => XKHM_DB_PASSWORD,
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

    public function get(string $id, callable $factory)
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