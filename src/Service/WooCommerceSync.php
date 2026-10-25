<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * Sincronização bidireccional entre XKHM e WooCommerce.
 *
 * - Produtos: xkhm → WooCommerce (criação/atualização)
 * - Clientes: xkhm → WooCommerce (criação/atualização)
 * - Encomendas: WooCommerce → xkhm (aprovisionamento, já implementado em woocommerce.php)
 */
class WooCommerceSync
{
    public function __construct(
        private \Xkhm\Repository\CatalogueRepositoryInterface $catalogue,
        private \Xkhm\Repository\ClientRepositoryInterface $clients,
        private ?\Xkhm\Repository\AdminPanelRepositoryInterface $adminPanel = null,
    ) {
    }

    /**
     * Sincroniza um produto xkhm para o WooCommerce.
     * Cria ou actualiza o produto WC correspondente.
     *
     * @return int|null ID do produto WC criado/actualizado, ou null em erro.
     */
    public function syncProductToWooCommerce(int $xkhmProductId): ?int
    {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_product')) {
            return null;
        }

        $product = $this->catalogue->product($xkhmProductId);
        if ($product === null) {
            return null;
        }

        $name = (string)($product['name'] ?? '');
        $description = (string)($product['description'] ?? '');
        $prices = $product['prices'] ?? [];
        $price = (float)($prices['monthly'] ?? $prices['annually'] ?? 0);

        // Procura produto WC existente ligado a este xkhm product
        $existingWcId = $this->findWooCommerceProductId($xkhmProductId);

        if ($existingWcId > 0) {
            $wcProduct = wc_get_product($existingWcId);
        } else {
            $wcProduct = new \WC_Product_Simple();
        }

        if (!$wcProduct) {
            return null;
        }

        $wcProduct->set_name($name);
        $wcProduct->set_description($description);
        $wcProduct->set_regular_price((string)$price);
        $wcProduct->set_status('publish');

        // Meta para ligação bidireccional
        $wcProduct->update_meta_data('_xkhm_product_id', $xkhmProductId);

        $wcId = $wcProduct->save();

        // Guarda o ID WC no xkhm (se a tabela suportar)
        return $wcId;
    }

    /**
     * Sincroniza um cliente xkhm para o WooCommerce.
     * Cria ou actualiza o cliente WC correspondente.
     *
     * @return int|null ID do cliente WC, ou null em erro.
     */
    public function syncClientToWooCommerce(int $xkhmClientId): ?int
    {
        if (!class_exists('WooCommerce')) {
            return null;
        }

        $client = $this->clients->findById($xkhmClientId);
        if ($client === null) {
            return null;
        }

        $email = (string)($client['email'] ?? '');
        if ($email === '') {
            return null;
        }

        // Procura utilizador WC existente
        $userId = email_exists($email);
        if ($userId) {
            $user = new \WP_User($userId);
        } else {
            $userId = wp_create_user(
                $email,
                wp_generate_password(16, true),
                $email
            );
            if (is_wp_error($userId)) {
                return null;
            }
            $user = new \WP_User($userId);
            $user->set_role('customer');
        }

        // Actualiza meta do utilizador
        update_user_meta($userId, 'first_name', $client['firstname'] ?? '');
        update_user_meta($userId, 'last_name', $client['lastname'] ?? '');
        update_user_meta($userId, 'billing_first_name', $client['firstname'] ?? '');
        update_user_meta($userId, 'billing_last_name', $client['lastname'] ?? '');
        update_user_meta($userId, 'billing_email', $email);
        update_user_meta($userId, 'billing_company', $client['companyname'] ?? '');
        update_user_meta($userId, 'billing_phone', $client['phonenumber'] ?? '');
        update_user_meta($userId, 'billing_address_1', $client['address1'] ?? '');
        update_user_meta($userId, 'billing_address_2', $client['address2'] ?? '');
        update_user_meta($userId, 'billing_city', $client['city'] ?? '');
        update_user_meta($userId, 'billing_state', $client['state'] ?? '');
        update_user_meta($userId, 'billing_postcode', $client['postcode'] ?? '');
        update_user_meta($userId, 'billing_country', $client['country'] ?? '');
        update_user_meta($userId, '_xkhm_client_id', $xkhmClientId);

        return $userId;
    }

    /**
     * Encontra o ID do produto WC ligado a um produto xkhm.
     */
    private function findWooCommerceProductId(int $xkhmProductId): int
    {
        $posts = get_posts([
            'post_type'  => 'product',
            'meta_key'   => '_xkhm_product_id',
            'meta_value' => $xkhmProductId,
            'posts_per_page' => 1,
            'fields'     => 'ids',
        ]);
        return !empty($posts) ? (int)$posts[0] : 0;
    }

    /**
     * Sincroniza todos os produtos xkhm para o WooCommerce.
     *
     * @return int Número de produtos sincronizados.
     */
    public function syncAllProducts(): int
    {
        $products = $this->catalogue->products();
        $count = 0;
        foreach ($products as $product) {
            if ($this->syncProductToWooCommerce((int)$product['id'])) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Sincroniza todos os clientes xkhm para o WooCommerce.
     *
     * @return int Número de clientes sincronizados.
     */
    public function syncAllClients(): int
    {
        if ($this->adminPanel === null) {
            return 0;
        }
        $clients = $this->adminPanel->listClients(null, 1000, 0);
        $count = 0;
        foreach ($clients as $client) {
            if ($this->syncClientToWooCommerce((int)$client['id'])) {
                $count++;
            }
        }
        return $count;
    }
}