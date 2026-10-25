<?php
/**
 * Integra o painel de administração xkhm no wp-admin.
 */
defined('ABSPATH') || exit;

define('XKHM_NO_RUN', true);

function xkhm_plugin_public_dir(): string
{
    return XKHM_PLUGIN_DIR . 'public';
}

function xkhm_render_xkhm_panel(): void
{
    if (!isset($_SESSION)) {
        @session_start();
    }
    $wpUser = wp_get_current_user();
    $_SESSION['admin_id'] = $wpUser->ID;
    $_SESSION['admin_name'] = $wpUser->display_name ?: $wpUser->user_login;
    $_SESSION['admin_email'] = $wpUser->user_email;

    $section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : '';
    if ($section !== '' && $section !== 'dashboard') {
        $path = '/admin/' . $section . '.php';
    } else {
        $path = '/admin';
    }

    $qs = $_GET;
    unset($qs['page'], $qs['section']);
    if (!empty($qs)) {
        $path .= '?' . http_build_query($qs);
    }

    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['REQUEST_METHOD'] = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // Propaga credenciais de BD (usa a BD do WordPress por defeito)
    if (function_exists('xkhm_db_config')) {
        $cfg = xkhm_db_config();
        $pw = $cfg['password'] ?? '';
        putenv('DB_PASSWORD=' . $pw);
        putenv('XKHM_DB_PASS=' . $pw);
        $_ENV['DB_PASSWORD'] = $pw;
        $_ENV['XKHM_DB_PASS'] = $pw;
        $_SERVER['DB_PASSWORD'] = $pw;
        $_SERVER['XKHM_DB_PASS'] = $pw;
        putenv('XKHM_DB_HOST=' . ($cfg['host'] ?? 'localhost'));
        putenv('XKHM_DB_NAME=' . ($cfg['dbname'] ?? 'linuxkonect'));
        putenv('XKHM_DB_USER=' . ($cfg['user'] ?? 'linuxkonect'));
        putenv('XKHM_DB_PORT=' . ($cfg['port'] ?? 3306));
        $_ENV['XKHM_DB_HOST'] = $cfg['host'] ?? 'localhost';
        $_ENV['XKHM_DB_NAME'] = $cfg['dbname'] ?? 'linuxkonect';
        $_ENV['XKHM_DB_USER'] = $cfg['user'] ?? 'linuxkonect';
        $_ENV['XKHM_DB_PORT'] = $cfg['port'] ?? 3306;
    }

    $publicIndex = xkhm_plugin_public_dir() . '/index.php';
    if (!file_exists($publicIndex)) {
        echo '<div class="wrap"><h1>xkhm</h1><p>Ficheiro public/index.php não encontrado.</p></div>';
        return;
    }

    include $publicIndex;

    if (!isset($app) || !($app instanceof \Slim\App)) {
        echo '<div class="wrap"><h1>xkhm</h1><p>App Slim não inicializada.</p></div>';
        return;
    }

    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
    $creator = new \Nyholm\Psr7Server\ServerRequestCreator($factory, $factory, $factory, $factory);
    $request = $creator->fromGlobals();
    $uri = $factory->createUri($_SERVER['REQUEST_URI'] ?? '/admin');
    $request = $request->withUri($uri);

    try {
        $response = $app->handle($request);
        $body = (string)$response->getBody();
    } catch (\Throwable $e) {
        echo '<div class="wrap"><h1>xkhm</h1><p>Erro: ' . esc_html($e->getMessage()) . '</p></div>';
        return;
    }

    if (trim($body) === '') {
        echo '<div class="wrap"><h1>xkhm</h1><p>O painel não produziu saída.</p></div>';
        return;
    }

    // Reescreve links /admin/xxx.php para o WP admin
    $body = preg_replace_callback(
        '/href="\/admin\/([a-z]+)\.php(?:\?([^"]*))?"/',
        function ($m) {
            $section = $m[1];
            $qs = isset($m[2]) ? $m[2] : '';
            $url = 'admin.php?page=xkhm-panel&section=' . $section;
            if ($qs !== '') {
                $url .= '&' . $qs;
            }
            return 'href="' . $url . '"';
        },
        $body
    );

    // Enqueue CSS do xkhm
    echo '<link rel="stylesheet" href="' . esc_url(XKHM_PLUGIN_URL . 'public/templates/default/css/whmcs.css') . '" />';
    echo '<link rel="stylesheet" href="' . esc_url(XKHM_PLUGIN_URL . 'public/templates/default/css/tokens.css') . '" />';

    echo $body;
}

add_action('admin_menu', function () {
    $sections = [
        'dashboard' => 'Painel',
        'clients'   => 'Clientes',
        'invoices'  => 'Faturas',
        'orders'    => 'Pedidos',
        'tickets'   => 'Tickets',
        'domains'   => 'Domínios',
        'services'  => 'Serviços',
        'catalogue' => 'Catálogo',
        'servers'   => 'Servidores',
    ];

    add_menu_page(
        'xkhm',
        'xkhm',
        'manage_options',
        'xkhm-panel',
        'xkhm_render_xkhm_panel',
        'dashicons-admin-generic',
        30
    );

    foreach ($sections as $slug => $label) {
        add_submenu_page(
            'xkhm-panel',
            $label,
            $label,
            'manage_options',
            'xkhm-panel&section=' . $slug,
            'xkhm_render_xkhm_panel'
        );
    }

    // Definições
    add_submenu_page(
        'xkhm-panel',
        'Definições',
        'Definições',
        'manage_options',
        'xkhm-settings',
        'xkhm_settings_page'
    );

    // Importação WHMCS
    add_submenu_page(
        'xkhm-panel',
        'Importar WHMCS',
        'Importar WHMCS',
        'manage_options',
        'xkhm-import-whmcs',
        'xkhm_render_import_wizard_page'
    );
}, 9);