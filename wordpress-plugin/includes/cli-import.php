<?php
/**
 * Comando WP-CLI: wp xkhm import
 *
 * Modos:
 *   --mode=whmcs   Importa de uma base WHMCS existente (padrão)
 *   --mode=schema  Cria apenas o esquema (91 tabelas, sem dados)
 *   --mode=demo    Cria o esquema e popula com dados de demonstração
 */
defined('WP_CLI') && WP_CLI || exit;

WP_CLI::add_command('xkhm import', function (array $args, array $assoc) {
    $mode = $assoc['mode'] ?? 'whmcs';

    if ($mode === 'schema') {
        WP_CLI::line('A criar esquema (91 tabelas)…');
        $service = new \Xkhm\Service\ImportService();
        $service->createSchema();
        WP_CLI::success('Esquema criado com sucesso (91 tabelas, sem dados).');
        return;
    }

    if ($mode === 'demo') {
        WP_CLI::line('A criar esquema + dados de demonstração…');
        $service = new \Xkhm\Service\ImportService();
        $service->createSchema();
        $service->seedDemoData();
        WP_CLI::success('Esquema e dados de demonstração criados com sucesso.');
        return;
    }

    // Modo whmcs (importação de BD WHMCS existente)
    $sourceType = $assoc['source'] ?? 'file';
    $file       = $assoc['file'] ?? '';
    $host       = $assoc['host'] ?? '';
    $port       = $assoc['port'] ?? 3306;
    $db         = $assoc['db'] ?? '';
    $user       = $assoc['user'] ?? '';
    $pass       = $assoc['pass'] ?? '';

    if ($sourceType === 'file') {
        if (!$file || !is_readable($file)) {
            WP_CLI::error('Ficheiro .sql não encontrado ou ilegível: ' . $file);
        }
        $tmp = sys_get_temp_dir() . '/xkhm_import_' . basename($file);
        copy($file, $tmp);
        set_transient('xkhm_import_sql_tmp', $tmp, HOUR_IN_SECONDS);
        $source = ['type' => 'file', 'path' => $tmp];
    } else {
        if (!$host || !$db || !$user) {
            WP_CLI::error('Para origem remota são obrigatórios --host, --db, --user');
        }
        $source = [
            'type' => 'remote',
            'host' => $host,
            'port' => (int)$port,
            'db'   => $db,
            'user' => $user,
            'pass' => $pass,
        ];
    }

    WP_CLI::line('A validar esquema…');
    $service = new \Xkhm\Service\ImportService();
    $schema  = $service->validateSchema($source);

    if ($schema['missing']) {
        WP_CLI::warning('Tabelas em falta: ' . implode(', ', $schema['missing']));
        if (!($assoc['force'] ?? false)) {
            WP_CLI::error('Importação abortada. Use --force para continuar mesmo com tabelas em falta.');
        }
    }

    WP_CLI::line('A iniciar importação…');
    $jobId = $service->startAsyncImport($source, $schema['tables']);

    while (true) {
        $progress = $service->getProgress($jobId);
        $pct = $progress['total'] ? round(($progress['done'] / $progress['total']) * 100) : 0;
        WP_CLI::line(sprintf('[%3d%%] %s – %s', $pct, $progress['current_table'], $progress['status']));
        if ($progress['status'] === 'completed' || $progress['status'] === 'error') {
            break;
        }
        sleep(2);
    }

    if ($progress['status'] === 'completed') {
        WP_CLI::success('Importação concluída com sucesso.');
    } else {
        WP_CLI::error('Importação falhou: ' . implode('; ', $progress['log'] ?? []));
    }
}, [
    'shortdesc' => 'Importa base de dados WHMCS para XKHM',
    'synopsis'  => [
        [
            'type'        => 'assoc',
            'name'        => 'mode',
            'description' => 'Modo: whmcs (importar de WHMCS), schema (apenas esquema vazio), demo (esquema + dados de demonstração)',
            'default'     => 'whmcs',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'source',
            'description' => 'Origem: file ou remote (apenas modo whmcs)',
            'default'     => 'file',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'file',
            'description' => 'Caminho para ficheiro .sql (quando source=file)',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'host',
            'description' => 'Host MySQL remoto',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'port',
            'description' => 'Porta MySQL',
            'default'     => '3306',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'db',
            'description' => 'Nome da base de dados',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'user',
            'description' => 'Utilizador MySQL',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'pass',
            'description' => 'Senha MySQL',
        ],
        [
            'type'        => 'assoc',
            'name'        => 'force',
            'description' => 'Forçar importação mesmo com tabelas em falta',
            'default'     => false,
        ],
    ],
]);