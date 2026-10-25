<?php
/**
 * Menu de admin “xkhm → Importar WHMCS” e página do wizard AJAX.
 */
defined('ABSPATH') || exit;

/**
 * Regista submenu em “Ferramentas”.
 */
add_action('admin_menu', function () {
    add_management_page(
        'Importar WHMCS – XKHM',
        'Importar WHMCS',
        'manage_options',
        'xkhm-import-whmcs',
        'xkhm_render_import_wizard_page'
    );
});

/**
 * Enfileira scripts/estilos apenas na página do wizard.
 */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'tools_page_xkhm-import-whmcs') {
        return;
    }
    wp_enqueue_script(
        'xkhm-import-wizard',
        XKHM_PLUGIN_URL . 'assets/js/import-wizard.js',
        ['jquery'],
        null,
        true
    );
    wp_localize_script('xkhm-import-wizard', 'xkhmImportWizard', [
        'ajaxurl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('xkhm_import_wizard'),
        'steps'        => ['source', 'schema', 'import', 'report'],
        'i18n'         => [
            'selectSource' => __('Selecionar origem', 'xkhm'),
            'fileOrRemote' => __('Ficheiro .sql ou MySQL remoto', 'xkhm'),
            'next'         => __('Seguinte', 'xkhm'),
            'prev'         => __('Anterior', 'xkhm'),
            'startImport'  => __('Iniciar importação', 'xkhm'),
            'finished'     => __('Importação concluída', 'xkhm'),
        ],
    ]);
    wp_enqueue_style(
        'xkhm-import-wizard-css',
        XKHM_PLUGIN_URL . 'assets/css/import-wizard.css'
    );
});

/**
 * Render da página do wizard (HTML base – JS preenche steps).
 */
function xkhm_render_import_wizard_page(): void
{
    ?>
    <div class="wrap xkhm-import-wizard">
        <h1><?php esc_html_e('Importar WHMCS', 'xkhm'); ?></h1>
        <p><?php esc_html_e('Assistente de 4 passos para importar uma base de dados WHMCS 5.3.x para o xkhm.', 'xkhm'); ?></p>

        <div id="xkhm-wizard-steps" class="xkhm-wizard-steps">
            <!-- Step 1: Origem -->
            <section class="xkhm-step active" data-step="source">
                <h2><?php esc_html_e('1. Origem dos dados', 'xkhm'); ?></h2>
                <fieldset>
                    <legend><?php esc_html_e('Escolha a origem', 'xkhm'); ?></legend>
                    <label>
                        <input type="radio" name="source_type" value="file" checked>
                        <?php esc_html_e('Ficheiro .sql (upload)', 'xkhm'); ?>
                    </label><br>
                    <label>
                        <input type="radio" name="source_type" value="remote">
                        <?php esc_html_e('MySQL remoto', 'xkhm'); ?>
                    </label>
                </fieldset>
                <div id="source-file" class="xkhm-source-option">
                    <input type="file" name="sql_file" accept=".sql,.gz" required>
                    <p class="description"><?php esc_html_e('Máx. 500 MB. Ficheiros .gz são aceites.', 'xkhm'); ?></p>
                </div>
                <div id="source-remote" class="xkhm-source-option" style="display:none;">
                    <table class="form-table">
                        <tr><th><label for="remote_host"><?php esc_html_e('Host', 'xkhm'); ?></label></th><td><input type="text" id="remote_host" name="remote_host" value="localhost" required></td></tr>
                        <tr><th><label for="remote_port"><?php esc_html_e('Porta', 'xkhm'); ?></label></th><td><input type="number" id="remote_port" name="remote_port" value="3306" required></td></tr>
                        <tr><th><label for="remote_db"><?php esc_html_e('Base de dados', 'xkhm'); ?></label></th><td><input type="text" id="remote_db" name="remote_db" required></td></tr>
                        <tr><th><label for="remote_user"><?php esc_html_e('Utilizador', 'xkhm'); ?></label></th><td><input type="text" id="remote_user" name="remote_user" required></td></tr>
                        <tr><th><label for="remote_pass"><?php esc_html_e('Senha', 'xkhm'); ?></label></th><td><input type="password" id="remote_pass" name="remote_pass" autocomplete="new-password"></td></tr>
                    </table>
                </div>
                <button type="button" class="button button-primary xkhm-wizard-next" data-next="schema"><?php esc_html_e('Seguinte', 'xkhm'); ?></button>
            </section>

            <!-- Step 2: Validação de esquema -->
            <section class="xkhm-step" data-step="schema">
                <h2><?php esc_html_e('2. Validação do esquema', 'xkhm'); ?></h2>
                <div id="schema-result" class="xkhm-schema-result">
                    <p><?php esc_html_e('A validar tabelas…', 'xkhm'); ?></p>
                </div>
                <button type="button" class="button xkhm-wizard-prev" data-prev="source"><?php esc_html_e('Anterior', 'xkhm'); ?></button>
                <button type="button" class="button button-primary xkhm-wizard-next" data-next="import" disabled><?php esc_html_e('Seguinte', 'xkhm'); ?></button>
            </section>

            <!-- Step 3: Importação -->
            <section class="xkhm-step" data-step="import">
                <h2><?php esc_html_e('3. Importação', 'xkhm'); ?></h2>
                <div class="xkhm-progress">
                    <progress id="import-progress" max="100" value="0"></progress>
                    <span id="import-status"><?php esc_html_e('A preparar…', 'xkhm'); ?></span>
                </div>
                <div id="import-log" class="xkhm-log"></div>
                <button type="button" class="button xkhm-wizard-prev" data-prev="schema"><?php esc_html_e('Anterior', 'xkhm'); ?></button>
                <button type="button" class="button button-primary xkhm-wizard-start-import" disabled><?php esc_html_e('Iniciar importação', 'xkhm'); ?></button>
            </section>

            <!-- Step 4: Relatório -->
            <section class="xkhm-step" data-step="report">
                <h2><?php esc_html_e('4. Relatório', 'xkhm'); ?></h2>
                <table class="widefat striped" id="report-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Tabela', 'xkhm'); ?></th>
                            <th><?php esc_html_e('Linhas origem', 'xkhm'); ?></th>
                            <th><?php esc_html_e('Linhas importadas', 'xkhm'); ?></th>
                            <th><?php esc_html_e('Avisos', 'xkhm'); ?></th>
                            <th><?php esc_html_e('Erros', 'xkhm'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
                <button type="button" class="button xkhm-wizard-prev" data-prev="import"><?php esc_html_e('Anterior', 'xkhm'); ?></button>
                <button type="button" class="button button-primary" id="xkhm-wizard-finish"><?php esc_html_e('Finalizar', 'xkhm'); ?></button>
            </section>
        </div>
    </div>
    <?php
}

/**
 * AJAX handlers para cada passo.
 */
add_action('wp_ajax_xkhm_upload_sql', 'xkhm_ajax_upload_sql');
function xkhm_ajax_upload_sql(): void
{
    check_ajax_referer('xkhm_import_wizard', 'nonce');

    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        wp_send_json(['success' => false, 'error' => __('Erro no upload do ficheiro', 'xkhm')]);
        return;
    }

    $file = $_FILES['sql_file'];
    $allowed = ['sql', 'gz'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        wp_send_json(['success' => false, 'error' => __('Apenas ficheiros .sql ou .gz', 'xkhm')]);
        return;
    }

    $uploadDir = wp_upload_dir();
    $tmpPath = $uploadDir['basedir'] . '/xkhm-import-' . wp_generate_password(12, false) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
        wp_send_json(['success' => false, 'error' => __('Não foi possível guardar o ficheiro', 'xkhm')]);
        return;
    }

    set_transient('xkhm_import_sql_tmp', $tmpPath, HOUR_IN_SECONDS);
    wp_send_json(['success' => true]);
}

add_action('wp_ajax_xkhm_import_step', 'xkhm_ajax_import_step');
function xkhm_ajax_import_step(): void
{
    check_ajax_referer('xkhm_import_wizard', 'nonce');

    $step = $_POST['step'] ?? '';
    $response = ['success' => false];

    try {
        switch ($step) {
            case 'validate_source':
                $response = xkhm_ajax_validate_source($_POST);
                break;
            case 'validate_schema':
                $response = xkhm_ajax_validate_schema($_POST);
                break;
            case 'start_import':
                $response = xkhm_ajax_start_import($_POST);
                break;
            case 'import_progress':
                $response = xkhm_ajax_import_progress($_POST);
                break;
            default:
                $response['error'] = __('Passo desconhecido', 'xkhm');
        }
    } catch (\Throwable $e) {
        $response['error'] = $e->getMessage();
    }

    wp_send_json($response);
}

/**
 * Valida origem (ficheiro ou credenciais remotas) e guarda em transient.
 */
function xkhm_ajax_validate_source(array $data): array
{
    $type = $data['source_type'] ?? 'file';

    if ($type === 'file') {
        // Em AJAX não há $_FILES – o upload é feito via formulário normal.
        // Aqui apenas confirmamos que o ficheiro foi enviado anteriormente.
        // Para simplificar, assumimos que o ficheiro já está em upload temporário.
        $tmp = get_transient('xkhm_import_sql_tmp');
        if (!$tmp) {
            return ['success' => false, 'error' => __('Nenhum ficheiro enviado', 'xkhm')];
        }
        set_transient('xkhm_import_source', ['type' => 'file', 'path' => $tmp], HOUR_IN_SECONDS);
    } else {
        $required = ['remote_host','remote_port','remote_db','remote_user'];
        foreach ($required as $f) {
            if (empty($data[$f])) {
                return ['success' => false, 'error' => sprintf(__('Campo %s obrigatório', 'xkhm'), $f)];
            }
        }
        set_transient('xkhm_import_source', [
            'type'   => 'remote',
            'host'   => $data['remote_host'],
            'port'   => (int)$data['remote_port'],
            'db'     => $data['remote_db'],
            'user'   => $data['remote_user'],
            'pass'   => $data['remote_pass'] ?? '',
        ], HOUR_IN_SECONDS);
    }
    return ['success' => true, 'next_step' => 'schema'];
}

/**
 * Valida esquema – usa ImportService.
 */
function xkhm_ajax_validate_schema(array $data): array
{
    $source = get_transient('xkhm_import_source');
    if (!$source) {
        return ['success' => false, 'error' => __('Origem não definida', 'xkhm')];
    }

    $service = new \Xkhm\Service\ImportService();
    $result  = $service->validateSchema($source);

    set_transient('xkhm_import_schema', $result, HOUR_IN_SECONDS);

    $missing = $result['missing'] ?? [];
    $extra   = $result['extra'] ?? [];

    $html = '<ul>';
    if ($missing) {
        $html .= '<li><strong>' . esc_html__('Tabelas em falta:', 'xkhm') . '</strong> ' . esc_html(implode(', ', $missing)) . '</li>';
    }
    if ($extra) {
        $html .= '<li><strong>' . esc_html__('Tabelas extra (ignoradas):', 'xkhm') . '</strong> ' . esc_html(implode(', ', $extra)) . '</li>';
    }
    if (!$missing && !$extra) {
        $html .= '<li>' . esc_html__('Esquema compatível – todas as 94 tabelas presentes.', 'xkhm') . '</li>';
    }
    $html .= '</ul>';

    return [
        'success'      => empty($missing),
        'schema_html'  => $html,
        'can_continue' => empty($missing),
    ];
}

/**
 * Inicia importação – devolve job ID para polling.
 */
function xkhm_ajax_start_import(array $data): array
{
    $source = get_transient('xkhm_import_source');
    $schema  = get_transient('xkhm_import_schema');
    if (!$source || !$schema) {
        return ['success' => false, 'error' => __('Dados de importação incompletos', 'xkhm')];
    }

    $service = new \Xkhm\Service\ImportService();
    $jobId   = $service->startAsyncImport($source, $schema['tables'] ?? []);

    set_transient('xkhm_import_job', $jobId, HOUR_IN_SECONDS);

    return ['success' => true, 'job_id' => $jobId];
}

/**
 * Polling de progresso.
 */
function xkhm_ajax_import_progress(array $data): array
{
    $jobId = $data['job_id'] ?? get_transient('xkhm_import_job');
    if (!$jobId) {
        return ['success' => false, 'error' => __('Job não encontrado', 'xkhm')];
    }

    $service = new \Xkhm\Service\ImportService();
    $progress = $service->getProgress($jobId);

    return ['success' => true, 'progress' => $progress];
}