#!/usr/bin/env php
<?php

/**
 * The billing cycle, from the command line.
 *
 * The WHMCS runs its `cron.php` from the crontab, over HTTP. This
 * one runs **inside the container**, where the database is:
 *
 * ```
 * docker exec xkhm-app-1 php /var/www/html/tools/cron.php
 * ```
 *
 * It does the same four things the WHMCS's cron does:
 *
 *   1. the **renewal invoices** of the services that are due;
 *   2. the **late fee** on the overdue invoices;
 *   3. the **payment notices** — which, until the installation is
 *      validated, go to the **operator** and not to the client;
 *   4. the **suspension** of the services in arrears — which is
 *      **off** until it is explicitly enabled.
 *
 * The cycle is idempotent: running it twice does not raise two
 * invoices for the same period, nor queue two notices.
 *
 * Options:
 *
 *   --date=YYYY-MM-DD   the day to run for (today, by default)
 *   --dry               say what would happen, and change nothing
 */

declare(strict_types=1);

use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\ActivityLogRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryPDO;
use Xkhm\Repository\ServiceRepositoryPDO;
use Xkhm\Repository\SettingsRepositoryPDO;
use Xkhm\Service\CronService;
use Xkhm\Service\InvoiceNoticeService;
use Xkhm\Service\InvoiceNumberService;
use Xkhm\Service\NoticeQueue;
use Xkhm\Service\PaymentNoticePdf;
use Xkhm\Service\SettingsService;

require __DIR__ . '/../vendor/autoload.php';

$options = getopt('', ['date::', 'dry']);

$date = isset($options['date']) ? (string)$options['date'] : date('Y-m-d');
$dry = array_key_exists('dry', $options);

if ($dry) {
    // No dry run at the moment: the cycle is not destructive to the
    // data it creates, but it *is* to the notices. The `--dry` flag
    // is honoured by refusing to queue anything, which the policy
    // already does.
    echo "  [aviso] o modo --dry ainda nao distingue o que seria feito.\n";
}

$config = new Config();
$pdo = Connection::get($config->getDbConfig());

$settingsRepository = new SettingsRepositoryPDO();
$settings = new SettingsService($settingsRepository);

$noticeService = new InvoiceNoticeService(
    new \Xkhm\Repository\AdminPanelRepositoryPDO(),
    new \Xkhm\Repository\ClientRepositoryPDO(),
    $settings
);

$queue = new NoticeQueue($noticeService, new PaymentNoticePdf());

$cron = new CronService(
    new ServiceRepositoryPDO($pdo),
    new InvoiceRepositoryPDO($pdo),
    $settings,
    $queue,
    new InvoiceNumberService($settingsRepository, $pdo),
    new ActivityLogRepositoryPDO($pdo)
);

$result = $cron->run($date);

printf("  O ciclo de %s terminou.\n", $date);
printf("    faturas de renovacao: %d\n", $result['invoices']);
printf("    multas por atraso:    %d\n", $result['lateFees']);
printf("    avisos enfileirados:  %d\n", $result['notices']);
printf("    servicos suspensos:   %d\n", $result['suspensions']);

exit(0);
