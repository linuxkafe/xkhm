<?php
/**
 * Página de definições do xkhm no wp-admin.
 *
 * Permite configurar a ligação à base de dados (alvo) onde o xkhm
 * armazena os seus dados. As credenciais do importador (origem) são
 * pedidas no próprio assistente de importação.
 */
defined('ABSPATH') || exit;

/**
 * Regista a página de definições no menu xkhm.
 */
add_action('admin_menu', function () {
    add_submenu_page(
        'xkhm-panel',
        'Definições',
        'Definições',
        'manage_options',
        'xkhm-settings',
        'xkhm_settings_page'
    );
}, 10);

/**
 * Renderiza a página de definições.
 */
function xkhm_settings_page(): void
{
    if (isset($_POST['xkhm_settings_nonce']) && wp_verify_nonce($_POST['xkhm_settings_nonce'], 'xkhm_settings')) {
        update_option('xkhm_db_host', sanitize_text_field($_POST['xkhm_db_host'] ?? ''));
        update_option('xkhm_db_port', (int)($_POST['xkhm_db_port'] ?? 3306));
        update_option('xkhm_db_name', sanitize_text_field($_POST['xkhm_db_name'] ?? ''));
        update_option('xkhm_db_user', sanitize_text_field($_POST['xkhm_db_user'] ?? ''));
        if (!empty($_POST['xkhm_db_password'])) {
            update_option('xkhm_db_password', sanitize_text_field($_POST['xkhm_db_password']));
        }
        echo '<div class="notice notice-success"><p>Definições guardadas.</p></div>';
    }

    $host = get_option('xkhm_db_host', '');
    $port = (int)get_option('xkhm_db_port', 3306);
    $name = get_option('xkhm_db_name', 'linuxkonect');
    $user = get_option('xkhm_db_user', 'linuxkonect');
    ?>
    <div class="wrap">
        <h1>Definições xkhm</h1>
        <form method="post">
            <?php wp_nonce_field('xkhm_settings', 'xkhm_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="xkhm_db_host">Host da base de dados</label></th>
                    <td><input type="text" id="xkhm_db_host" name="xkhm_db_host" value="<?php echo esc_attr($host); ?>" class="regular-text" placeholder="localhost"></td>
                </tr>
                <tr>
                    <th><label for="xkhm_db_port">Porta</label></th>
                    <td><input type="number" id="xkhm_db_port" name="xkhm_db_port" value="<?php echo esc_attr($port); ?>" class="small-text"></td>
                </tr>
                <tr>
                    <th><label for="xkhm_db_name">Nome da base de dados</label></th>
                    <td><input type="text" id="xkhm_db_name" name="xkhm_db_name" value="<?php echo esc_attr($name); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="xkhm_db_user">Utilizador</label></th>
                    <td><input type="text" id="xkhm_db_user" name="xkhm_db_user" value="<?php echo esc_attr($user); ?>" class="regular-text"></td>
                </tr>
                <tr>
                    <th><label for="xkhm_db_password">Senha</label></th>
                    <td><input type="password" id="xkhm_db_password" name="xkhm_db_password" value="" class="regular-text" placeholder="Deixe vazio para manter a actual"></td>
                </tr>
            </table>
            <?php submit_button('Guardar definições'); ?>
        </form>
    </div>
    <?php
}
