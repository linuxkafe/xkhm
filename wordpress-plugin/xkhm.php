<?php
/**
 * Plugin Name:       XKHM – WHMCS Reimplementation
 * Plugin URI:        https://linuxkafe.com/xkhm
 * Description:       XKHM - Hosting Manager Plugin based on WHMCS features. Independent reimplementation of WHMCS.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            LINUXKAFE
 * License:           GPL-3.0-or-later
 * Text Domain:       xkhm
 */

defined('ABSPATH') || exit;

// Indica que o plugin está activo para outras partes da aplicação (ex.: public/index.php)
defined('XKHM_WP_PLUGIN_ACTIVE') || define('XKHM_WP_PLUGIN_ACTIVE', true);

// Constantes de caminho
define('XKHM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('XKHM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('XKHM_FLUTTER_BASE', XKHM_PLUGIN_URL . 'assets/flutter/');

// Autoload Composer (src/ do projeto principal)
$vendor = XKHM_PLUGIN_DIR . 'vendor/autoload.php';
if (file_exists($vendor)) {
    require_once $vendor;
} else {
    // Fallback: aponta para o autoload do projeto principal (duas pastas acima)
    $fallback = dirname(XKHM_PLUGIN_DIR, 2) . '/vendor/autoload.php';
    if (file_exists($fallback)) {
        require_once $fallback;
    }
}

// Bootstrap do plugin
require_once XKHM_PLUGIN_DIR . 'includes/bootstrap.php';

// Shortcode da área de cliente
require_once XKHM_PLUGIN_DIR . 'includes/shortcodes.php';

// Rewrite rules para APIs
require_once XKHM_PLUGIN_DIR . 'includes/rewrite.php';

// Menu de admin + wizard
require_once XKHM_PLUGIN_DIR . 'includes/admin-menu.php';


// Painel de administração xkhm no wp-admin
require_once XKHM_PLUGIN_DIR . 'includes/admin-panel.php';

// Comando WP-CLI (carregado apenas se WP_CLI)
if (defined('WP_CLI') && WP_CLI) {
    require_once XKHM_PLUGIN_DIR . 'includes/cli-import.php';
}

/**
 * Ativação: cria o esquema da BD e flush rewrite rules.
 */
register_activation_hook(__FILE__, function () {
    // Cria o esquema (91 tabelas tbl*) se não existirem
    if (class_exists('Xkhm\\Service\\ImportService')) {
        try {
            $service = new \Xkhm\Service\ImportService();
            $service->createSchema();
        } catch (\Throwable $e) {
            // Regista o erro mas não impede a activação
            error_log('XKHM activation: erro ao criar esquema: ' . $e->getMessage());
        }
    }
    flush_rewrite_rules();
});

/**
 * Desactivação: flush rewrite rules.
 */
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});