<?php
// Stubs for WordPress functions used by WooCommerceSync
function email_exists(string $email): int|false { return false; }
function wp_create_user(string $user_login, string $password, string $email = ''): int|\WP_Error { return 0; }
function update_user_meta(int $user_id, string $meta_key, mixed $meta_value): int|bool { return true; }
function get_posts(array $args = []): array { return []; }
function wc_get_product(int $product_id): ?\WC_Product { return null; }
function wc_get_order(int $order_id): ?\WC_Order { return null; }
function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string { return ''; }
function is_wp_error(mixed $thing): bool { return false; }

class WC_Product_Simple {
    public function set_name(string $name): void {}
    public function set_description(string $description): void {}
    public function set_regular_price(string $price): void {}
    public function set_status(string $status): void {}
    public function update_meta_data(string $key, mixed $value): void {}
    public function save(): int { return 0; }
}
class WC_Product extends WC_Product_Simple {}
class WP_User {
    public function __construct(int $id) {}
    public function set_role(string $role): void {}
}
class WP_Error {}