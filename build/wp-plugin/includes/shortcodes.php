<?php
/**
 * Shortcode [xkhm_client_area] – renderiza o bootstrap da aplicação Flutter.
 */
defined('ABSPATH') || exit;

add_shortcode('xkhm_client_area', function () {
    $base = rtrim(XKHM_FLUTTER_BASE, '/') . '/';

    // Enfileira o script de bootstrap apenas uma vez
    static $enqueued = false;
    if (!$enqueued) {
        wp_enqueue_script(
            'xkhm-flutter-bootstrap',
            $base . 'flutter_bootstrap.js',
            [],
            null,
            true
        );
        wp_add_inline_script('xkhm-flutter-bootstrap', "window.xkhmBase = '{$base}';", 'before');
        $enqueued = true;
    }

    // Container onde o Flutter vai montar a app
    return '<div id="xkhm-app" data-base="' . esc_url($base) . '"></div>';
});

/**
 * Garante que os assets Flutter (index.html, manifest, icons) são servidos
 * directamente pelo WordPress quando acedidos via URL do plugin.
 * O Flutter build usa <base href="/app/"> – mapeamos /app/ para a pasta assets/flutter/.
 */
add_action('init', function () {
    add_rewrite_rule(
        '^app/(.*)$',
        XKHM_PLUGIN_DIR . 'assets/flutter/$1',
        'top'
    );
}, 10, 0);