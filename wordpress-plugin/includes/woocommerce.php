<?php
/**
 * Integração com WooCommerce.
 *
 * Permite vender produtos xkhm (hosting, domínios) através do WooCommerce.
 * Quando uma encomenda WooCommerce é paga, o serviço é aprovisionado
 * automaticamente no xkhm.
 */
defined('ABSPATH') || exit;

// Só carrega se WooCommerce estiver activo
if (!class_exists('WooCommerce')) {
    return;
}

/**
 * Adiciona campo de ligação ao produto xkhm na edição de produto WooCommerce.
 */
add_action('woocommerce_product_options_general_product_data', function () {
    echo '<div class="options_group">';
    woocommerce_wp_text_input([
        'id'          => '_xkhm_product_id',
        'label'       => __('ID do produto xkhm', 'xkhm'),
        'desc_tip'    => true,
        'description' => __('ID do produto no catálogo xkhm (tblproducts.id)', 'xkhm'),
        'type'        => 'number',
    ]);
    woocommerce_wp_text_input([
        'id'          => '_xkhm_billing_cycle',
        'label'       => __('Ciclo de facturação', 'xkhm'),
        'desc_tip'    => true,
        'description' => __('Monthly, Quarterly, Semi Annually, Annually, Biennially, Triennially', 'xkhm'),
        'default'     => 'Monthly',
    ]);
    echo '</div>';
});

/**
 * Guarda o campo de ligação ao produto xkhm.
 */
add_action('woocommerce_process_product_meta', function ($postId) {
    $productId = isset($_POST['_xkhm_product_id']) ? (int)$_POST['_xkhm_product_id'] : 0;
    $cycle = isset($_POST['_xkhm_billing_cycle']) ? sanitize_text_field($_POST['_xkhm_billing_cycle']) : 'Monthly';
    update_post_meta($postId, '_xkhm_product_id', $productId);
    update_post_meta($postId, '_xkhm_billing_cycle', $cycle);
});

/**
 * Aprovisiona o serviço xkhm quando a encomenda WooCommerce é paga.
 */
add_action('woocommerce_order_status_completed', function ($orderId) {
    $order = wc_get_order($orderId);
    if (!$order) {
        return;
    }

    $pdo = xkhm_admin_pdo();
    if ($pdo === null) {
        return;
    }

    $repo = new \Xkhm\Repository\AdminPanelRepositoryPDO($pdo);
    $catalogue = new \Xkhm\Repository\CatalogueRepositoryPDO();
    $settingsRepo = new \Xkhm\Repository\SettingsRepositoryPDO($pdo);
    $settingsService = new \Xkhm\Service\SettingsService($settingsRepo);
    $invoiceNumberService = new \Xkhm\Service\InvoiceNumberService($settingsRepo);
    $orderService = new \Xkhm\Service\OrderService(
        new \Xkhm\Repository\OrderRepositoryPDO($pdo),
        $invoiceNumberService
    );

    foreach ($order->get_items() as $item) {
        $productId = (int)get_post_meta($item->get_product_id(), '_xkhm_product_id', true);
        if ($productId <= 0) {
            continue;
        }

        // Obtém ou cria o cliente xkhm
        $email = $order->get_billing_email();
        $clientRepo = new \Xkhm\Repository\ClientRepositoryPDO($pdo);
        $client = $clientRepo->findByEmail($email);

        if ($client === null) {
            // Cria o cliente no xkhm
            $clientData = [
                'firstname'   => $order->get_billing_first_name(),
                'lastname'    => $order->get_billing_last_name(),
                'email'       => $email,
                'companyname' => $order->get_billing_company(),
                'phonenumber' => $order->get_billing_phone(),
                'address1'    => $order->get_billing_address_1(),
                'address2'    => $order->get_billing_address_2(),
                'city'        => $order->get_billing_city(),
                'state'       => $order->get_billing_state(),
                'postcode'    => $order->get_billing_postcode(),
                'country'     => $order->get_billing_country(),
                'password'    => wp_generate_password(12, true),
                'status'      => 'Active',
            ];
            $clientRepo->create($clientData);
            $client = $clientRepo->findByEmail($email);
        }

        if ($client === null) {
            continue;
        }

        $clientId = (int)$client['id'];
        $cycle = (string)(get_post_meta($item->get_product_id(), '_xkhm_billing_cycle', true) ?: 'Monthly');

        // Cria a encomenda e factura no xkhm
        $result = $orderService->place($clientId, [
            'productId' => $productId,
            'cycle'     => $cycle,
            'domain'    => '',
        ]);

        if (!empty($result['ok']) && !empty($result['invoiceId'])) {
            // Marca a fatura como paga e aprovisiona o serviço
            $orderService->markPaid((int)$result['invoiceId'], 'woocommerce');
        }
    }
}, 10, 1);

/**
 * Adiciona coluna "Produto xkhm" na lista de produtos WooCommerce.
 */
add_filter('manage_product_posts_columns', function ($columns) {
    $columns['xkhm_product'] = __('Produto xkhm', 'xkhm');
    return $columns;
});

add_action('manage_product_posts_custom_column', function ($column, $postId) {
    if ($column === 'xkhm_product') {
        $xkhmId = (int)get_post_meta($postId, '_xkhm_product_id', true);
        if ($xkhmId > 0) {
            echo '#' . $xkhmId;
        } else {
            echo '—';
        }
    }
}, 10, 2);