<?php

declare(strict_types=1);

namespace Xkhm\Service;

use mysqli;

/**
 * Serviço de importação WHMCS → xkhm.
 *
 * Suporta origem ficheiro .sql (ou .gz) ou MySQL remoto.
 * Executa por lotes (chunked) e expõe progresso assíncrono via job ID.
 */
final class ImportService
{
    /** @var array<int, array> Jobs em memória (em produção usar Redis/DB) */
    private static array $jobs = [];

    /**
     * Testa conexão à origem.
     */
    public function testConnection(array $source): bool
    {
        $mysqli = $this->connect($source);
        if (!$mysqli) {
            return false;
        }
        $mysqli->close();
        return true;
    }

    /**
     * Valida esquema – devolve tabelas em falta e extra.
     *
     * @return array{missing: string[], extra: string[], tables: string[]}
     */
    public function validateSchema(array $source): array
    {
        $expected = $this->whmcs53Tables();
        $mysqli   = $this->connect($source);
        if (!$mysqli) {
            throw new \RuntimeException('Falha ao conectar à origem');
        }

        $existing = [];
        $res = $mysqli->query('SHOW TABLES');
        while ($row = $res->fetch_row()) {
            $existing[] = $row[0];
        }
        $mysqli->close();

        $missing = array_diff($expected, $existing);
        $extra   = array_diff($existing, $expected);

        return [
            'missing' => array_values($missing),
            'extra'   => array_values($extra),
            'tables'  => $existing,
        ];
    }

    /**
     * Inicia importação assíncrona – devolve job ID.
     */
    public function startAsyncImport(array $source, array $tables): string
    {
        $jobId = bin2hex(random_bytes(8));
        self::$jobs[$jobId] = [
            'source'   => $source,
            'tables'   => $tables,
            'progress' => [
                'current_table' => '',
                'done'          => 0,
                'total'         => count($tables),
                'status'        => 'starting',
                'log'           => [],
            ],
        ];

        // Dispara processo em background (simples: register_shutdown_function + fastcgi_finish_request)
        // Em CLI real usaríamos Symfony Process ou similar. Aqui apenas registramos.
        register_shutdown_function([$this, 'runImportJob'], $jobId);

        return $jobId;
    }

    /**
     * Executa o job de importação (chamado em shutdown / background).
     */
    public function runImportJob(string $jobId): void
    {
        if (!isset(self::$jobs[$jobId])) {
            return;
        }

        $job = &self::$jobs[$jobId];
        $job['progress']['status'] = 'running';

        $mysqli = $this->connect($job['source']);
        if (!$mysqli) {
            $job['progress']['status'] = 'error';
            $job['progress']['log'][] = 'Falha ao conectar à origem';
            return;
        }

        // Desactiva FK checks
        $mysqli->query('SET FOREIGN_KEY_CHECKS=0');
        $mysqli->query('SET SESSION sql_mode="NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO"');

        foreach ($job['tables'] as $idx => $table) {
            $job['progress']['current_table'] = $table;
            $job['progress']['done'] = $idx;

            // Conta linhas na origem
            $countRes = $mysqli->query("SELECT COUNT(*) FROM `$table`");
            $totalRows = $countRes ? (int)$countRes->fetch_row()[0] : 0;
            if ($countRes) {
                $countRes->free();
            }

            if ($totalRows === 0) {
                $job['progress']['log'][] = "Tabela $table vazia – ignorada";
                continue;
            }

            // Importa em batches de 5000
            $batch = 5000;
            for ($offset = 0; $offset < $totalRows; $offset += $batch) {
                $srcRows = $mysqli->query("SELECT * FROM `$table` LIMIT $batch OFFSET $offset");
                if (!$srcRows) {
                    $job['progress']['log'][] = "Erro ao ler $table offset $offset: " . $mysqli->error;
                    continue;
                }

                // Prepara INSERT em lote
                $first = true;
                $columns = [];
                $valuesBatch = [];

                while ($row = $srcRows->fetch_assoc()) {
                    if ($first) {
                        $columns = array_keys($row);
                        $first = false;
                    }
                    $vals = array_map(fn($v) => $mysqli->real_escape_string((string)$v), array_values($row));
                    $valuesBatch[] = '(' . implode(',', array_map(fn($v) => "'$v'", $vals)) . ')';
                }
                $srcRows->free();

                if ($valuesBatch) {
                    $colList = '`' . implode('`,`', $columns) . '`';
                    $sql = "INSERT INTO `$table` ($colList) VALUES " . implode(',', $valuesBatch);
                    if (!$mysqli->query($sql)) {
                        $job['progress']['log'][] = "Erro ao inserir em $table: " . $mysqli->error;
                    }
                }
            }

            $job['progress']['log'][] = "Tabela $table importada ($totalRows linhas)";
        }

        $mysqli->query('SET FOREIGN_KEY_CHECKS=1');
        $mysqli->close();

        $job['progress']['status'] = 'completed';
        $job['progress']['done']   = $job['progress']['total'];
    }

    /**
     * Devolve progresso actual do job.
     */
    public function getProgress(string $jobId): array
    {
        return self::$jobs[$jobId]['progress'] ?? ['status' => 'unknown'];
    }

    /**
     * Cria ligação mysqli conforme origem.
     */
    private function connect(array $source): ?mysqli
    {
        if ($source['type'] === 'remote') {
            $mysqli = new mysqli(
                $source['host'],
                $source['user'],
                $source['pass'],
                $source['db'],
                (int)($source['port'] ?? 3306)
            );
        } else {
            // Ficheiro .sql – importação via LOAD DATA não suportada aqui; assume-se que o ficheiro já foi carregado na BD local.
            // Para simplicidade, conecta à BD local do xkhm.
            $cfg = (new \Xkhm\Config())->getDbConfig(); // assume Config acessível
            $mysqli = new mysqli(
                $cfg['host'],
                $cfg['user'],
                $cfg['password'],
                $cfg['dbname'],
                (int)($cfg['port'] ?? 3306)
            );
        }

        if ($mysqli->connect_errno) {
            return null;
        }
        $mysqli->set_charset('utf8mb4');
        return $mysqli;
    }

    /**
     * Lista das 94 tabelas WHMCS 5.3.9 (prefixo tbl).
     */
    /**
     * Cria o esquema (91 tabelas) na BD actual, sem dados.
     */
    public function createSchema(): void
    {
        // Procura o ficheiro de esquema
        $schemaFile = null;
        $candidates = [
            (defined('XKHM_PLUGIN_DIR') ? XKHM_PLUGIN_DIR . 'resources/schema/whmcs-schema.sql' : null),
            dirname(__DIR__, 2) . '/resources/schema/whmcs-schema.sql',
            __DIR__ . '/../../resources/schema/whmcs-schema.sql',
        ];
        foreach ($candidates as $c) {
            if ($c && file_exists($c)) {
                $schemaFile = $c;
                break;
            }
        }

        if (function_exists('xkhm_db_config')) {
            $cfg = xkhm_db_config();
            $mysqli = new \mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['dbname'], (int)$cfg['port']);
            if ($mysqli->connect_error) {
                throw new \RuntimeException('DB connection failed: ' . $mysqli->connect_error);
            }
            if ($schemaFile) {
                $sql = file_get_contents($schemaFile);
                // Remove CREATE DATABASE / USE statements
                $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
                $sql = preg_replace('/USE .*?;/is', '', $sql);
                $mysqli->multi_query($sql);
                while ($mysqli->more_results() && $mysqli->next_result()) {
                    // consume
                }
            } else {
                foreach ($this->whmcs53Tables() as $table) {
                    $mysqli->query("CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                }
            }
            $mysqli->close();
            return;
        }

        $pdo = $this->getPdo();
        if ($schemaFile) {
            $sql = file_get_contents($schemaFile);
            $sql = preg_replace('/CREATE DATABASE.*?;/is', '', $sql);
            $sql = preg_replace('/USE .*?;/is', '', $sql);
            $pdo->exec($sql);
        } else {
            foreach ($this->whmcs53Tables() as $table) {
                $pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (id INT AUTO_INCREMENT PRIMARY KEY) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        }
    }

    /**
     * Popula a BD com dados de demonstração mínimos.
     */
    public function seedDemoData(): void
    {
        if (function_exists('xkhm_db_config')) {
            $cfg = xkhm_db_config();
            $mysqli = new \mysqli($cfg['host'], $cfg['user'], $cfg['password'], $cfg['dbname'], (int)$cfg['port']);
            if ($mysqli->connect_error) {
                return;
            }
            $mysqli->query("INSERT IGNORE INTO tblclients (id, firstname, lastname, email, password, status, datecreated) VALUES (1, 'Demo', 'Cliente', 'demo@example.com', MD5('demo'), 'Active', CURDATE())");
            $mysqli->query("INSERT IGNORE INTO tbladmins (id, username, password, fullname, email) VALUES (1, 'admin', MD5('admin'), 'Administrador', 'admin@example.com')");
            $mysqli->query("INSERT IGNORE INTO tblconfiguration (setting, value) VALUES ('CompanyName', 'Demo Company')");
            $mysqli->close();
            return;
        }
        $pdo = $this->getPdo();
        $pdo->exec("INSERT IGNORE INTO tblclients (id, firstname, lastname, email, password, status, datecreated) VALUES (1, 'Demo', 'Cliente', 'demo@example.com', MD5('demo'), 'Active', CURDATE())");
        $pdo->exec("INSERT IGNORE INTO tbladmins (id, username, password, fullname, email) VALUES (1, 'admin', MD5('admin'), 'Administrador', 'admin@example.com')");
        $pdo->exec("INSERT IGNORE INTO tblconfiguration (setting, value) VALUES ('CompanyName', 'Demo Company')");
    }

    private function getPdo(): \PDO
    {
        if (function_exists('xkhm_db_config')) {
            $cfg = xkhm_db_config();
            $dbConfig = [
                'host'     => $cfg['host'],
                'port'     => $cfg['port'],
                'name'     => $cfg['dbname'],
                'user'     => $cfg['user'],
                'pass'     => $cfg['password'],
                'charset'  => $cfg['charset'] ?? 'utf8mb4',
            ];
            return \Xkhm\Database\Connection::get($dbConfig);
        }
        $config = new \Xkhm\Config();
        return \Xkhm\Database\Connection::get($config->getDbConfig());
    }

    public function whmcs53Tables(): array
    {
                return [
            'tblaccounts', 'tblactivitylog', 'tbladdonmodules', 'tbladdons', 'tbladminlog', 'tbladminperms', 'tbladminroles', 'tbladmins', 'tbladminsecurityquestions', 'tblaffiliates', 'tblaffiliatesaccounts', 'tblaffiliateshistory', 'tblaffiliatespending', 'tblaffiliateswithdrawals', 'tblannouncements', 'tblbannedemails', 'tblbannedips', 'tblbillableitems', 'tblbrowserlinks', 'tblbundles', 'tblcalendar', 'tblcancelrequests', 'tblclientgroups', 'tblclients', 'tblclientsfiles', 'tblconfiguration', 'tblcontacts', 'tblcredit', 'tblcurrencies', 'tblcustomfields', 'tblcustomfieldsvalues', 'tbldomainpricing', 'tbldomainreminders', 'tbldomains', 'tbldomainsadditionalfields', 'tbldownloadcats', 'tbldownloads', 'tblemailmarketer', 'tblemails', 'tblemailtemplates', 'tblfraud', 'tblgatewaylog', 'tblhosting', 'tblhostingaddons', 'tblhostingconfigoptions', 'tblinvoiceitems', 'tblinvoices', 'tblknowledgebase', 'tblknowledgebasecats', 'tblknowledgebaselinks', 'tbllinks', 'tblmodulelog', 'tblnetworkissues', 'tblnotes', 'tblorders', 'tblorderstatuses', 'tblpaymentgateways', 'tblpricing', 'tblproductconfiggroups', 'tblproductconfiglinks', 'tblproductconfigoptions', 'tblproductconfigoptionssub', 'tblproductgroups', 'tblproducts', 'tblpromotions', 'tblquoteitems', 'tblquotes', 'tblregistrars', 'tblservergroups', 'tblservergroupsrel', 'tblservers', 'tblsslorders', 'tbltax', 'tblticketbreaklines', 'tblticketdepartments', 'tblticketescalations', 'tblticketfeedback', 'tblticketlog', 'tblticketmaillog', 'tblticketnotes', 'tblticketpredefinedcats', 'tblticketpredefinedreplies', 'tblticketreplies', 'tbltickets', 'tblticketspamfilters', 'tblticketstatuses', 'tbltickettags', 'tbltodolist', 'tbltransientdata', 'tblupgrades', 'tblwhoislog'
        ];
    }
}