<?php

declare(strict_types=1);

namespace Xkhm\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Xkhm\Config;
use Xkhm\Database\Connection;
use Xkhm\Repository\ActivityLogRepositoryPDO;
use Xkhm\Repository\AdminPanelRepositoryPDO;
use Xkhm\Repository\ClientRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryPDO;
use Xkhm\Repository\ServiceRepositoryPDO;
use Xkhm\Repository\SettingsRepositoryPDO;
use Xkhm\Service\CronService;
use Xkhm\Service\InvoiceNoticeService;
use Xkhm\Service\InvoiceNumberService;
use Xkhm\Service\MailPolicy;
use Xkhm\Service\NoticeQueue;
use Xkhm\Service\PaymentNoticePdf;
use Xkhm\Service\SettingsService;

/**
 * The billing cycle — the WHMCS's `cron.php`.
 *
 * The tests use their **own** service and their own invoices, and
 * clean them up: the installation's own data is never touched.
 */
class CronServiceTest extends TestCase
{
    private ?\PDO $pdo = null;
    private ?ServiceRepositoryPDO $services = null;
    private ?InvoiceRepositoryPDO $invoices = null;
    private ?SettingsRepositoryPDO $settingsRepository = null;
    private ?CronService $cron = null;

    /** @var list<int> */
    private array $serviceIds = [];

    /** @var list<int> */
    private array $invoiceIds = [];

    /** @var list<string> */
    private array $queuedFiles = [];

    /** @var list<int> the invoices that existed before the test */
    private array $invoiceIdsBefore = [];

    /** @var int the last entry of the activity log before the test */
    private int $logIdBefore = 0;

    /** @var array<string, string> */
    private array $savedSettings = [];

    protected function setUp(): void
    {
        try {
            $config = new Config();
            $this->pdo = Connection::get($config->getDbConfig());
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }

        $this->services = new ServiceRepositoryPDO($this->pdo);
        $this->invoices = new InvoiceRepositoryPDO($this->pdo);
        $this->settingsRepository = new SettingsRepositoryPDO($this->pdo);

        $settings = new SettingsService($this->settingsRepository);

        $noticeService = new InvoiceNoticeService(
            new AdminPanelRepositoryPDO(),
            new ClientRepositoryPDO(),
            $settings
        );

        // As faturas que ja existiam: o ciclo cria outras, e o
        // teste tem de limpar **todas** as que ele criou — as suas e
        // as do ciclo.
        $stmt = $this->pdo->prepare('SELECT id FROM tblinvoices');
        $stmt->execute();
        $this->invoiceIdsBefore = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));

        $log = $this->pdo->prepare('SELECT MAX(id) FROM tblactivitylog');
        $log->execute();
        $this->logIdBefore = (int)($log->fetchColumn() ?: 0);

        $this->cron = new CronService(
            $this->services,
            $this->invoices,
            $settings,
            new NoticeQueue($noticeService, new PaymentNoticePdf()),
            new InvoiceNumberService($this->settingsRepository, $this->pdo),
            new ActivityLogRepositoryPDO($this->pdo)
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->serviceIds as $id) {
            $this->pdo?->prepare('DELETE FROM tblhosting WHERE id = ?')->execute([$id]);
        }

        // As faturas que o ciclo criou durante o teste, e as do
        // teste.
        $created = array_diff($this->invoiceIds(), $this->invoiceIdsBefore);

        foreach (array_merge($created, $this->invoiceIds) as $id) {
            $this->pdo?->prepare('DELETE FROM tblinvoiceitems WHERE invoiceid = ?')->execute([$id]);
            $this->pdo?->prepare('DELETE FROM tblinvoices WHERE id = ?')->execute([$id]);
        }

        foreach ($this->queuedFiles as $file) {
            @unlink($file);
        }

        foreach ($this->savedSettings as $setting => $value) {
            $this->settingsRepository?->set($setting, $value);
        }

        // O registo das entradas escritas durante o teste — o ciclo
        // escreve nelas, e sem isto a base enche de ruido.
        $this->pdo?->prepare('DELETE FROM tblactivitylog WHERE id > ?')->execute([$this->logIdBefore]);
    }

    /**
     * The ids of the invoices that exist now.
     *
     * @return list<int>
     */
    private function invoiceIds(): array
    {
        $stmt = $this->pdo?->prepare('SELECT id FROM tblinvoices');
        $stmt?->execute();

        return array_map('intval', $stmt?->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * A service that is due for renewal.
     */
    private function dueService(int $userId = 135, float $amount = 42.00, string $cycle = 'Annually'): int
    {
        $id = $this->services?->create([
            'userid' => $userId,
            'orderid' => 0,
            'packageid' => 1,
            'server' => 1,
            'regdate' => '2025-01-01',
            'domain' => 'cron-teste.example.com',
            'paymentmethod' => '',
            'firstpaymentamount' => $amount,
            'amount' => $amount,
            'billingcycle' => $cycle,
            'nextduedate' => '2026-01-01',
            'nextinvoicedate' => '2026-01-01',
            'domainstatus' => 'Active',
            'username' => '',
            'password' => '',
            'notes' => 'Serviço de teste do ciclo',
            'subscriptionid' => '',
            'promoid' => 0,
            'suspendreason' => '',
            'overideautosuspend' => '',
            'overidesuspenduntil' => '0000-00-00',
            'dedicatedip' => '',
            'assignedips' => '',
            'ns1' => '',
            'ns2' => '',
            'diskusage' => 0,
            'disklimit' => 0,
            'bwusage' => 0,
            'bwlimit' => 0,
        ]);

        $this->serviceIds[] = (int)$id;

        return (int)$id;
    }

    /**
     * An unpaid invoice.
     */
    private function unpaidInvoice(int $userId = 135, float $amount = 30.00, string $dueDate = '2026-01-01'): int
    {
        $id = $this->invoices?->create([
            'userid' => $userId,
            'invoicenum' => 'TESTE-' . uniqid(),
            'date' => '2025-12-01',
            'duedate' => $dueDate,
            'datepaid' => '0000-00-00 00:00:00',
            'subtotal' => $amount,
            'credit' => 0.0,
            'tax' => 0.0,
            'tax2' => 0.0,
            'total' => $amount,
            'taxrate' => 0.0,
            'taxrate2' => 0.0,
            'status' => 'Unpaid',
            'paymentmethod' => '',
        ]);

        $this->invoiceIds[] = (int)$id;

        return (int)$id;
    }

    public function testTheCycleRaisesTheRenewalInvoice(): void
    {
        $serviceId = $this->dueService();

        $result = $this->cron?->run('2026-06-01');

        $this->assertSame(1, $result['invoices'], 'the renewal invoice must be raised');

        // A fatura existe, com o valor do **serviço**.
        $stmt = $this->pdo?->prepare(
            "SELECT v.total, v.status FROM tblinvoices v "
            . 'INNER JOIN tblinvoiceitems i ON i.invoiceid = v.id '
            . 'WHERE i.type = ? AND i.relid = ?'
        );
        $stmt?->execute(['Hosting', $serviceId]);
        $row = $stmt?->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, 'the invoice must exist');
        $this->assertSame(42.0, (float)$row['total']);
        $this->assertSame('Unpaid', $row['status']);

        // E as datas do serviço avançaram um ano.
        $service = $this->services?->findById($serviceId);

        $this->assertSame('2027-01-01', $service['nextduedate']);
        $this->assertSame('2027-01-01', $service['nextinvoicedate']);
    }

    public function testTheCycleIsIdempotent(): void
    {
        $this->dueService();

        $this->cron?->run('2026-06-01');
        $second = $this->cron?->run('2026-06-02');

        $this->assertSame(0, $second['invoices'], 'a second run must not raise a second invoice');

        // Uma só fatura para o serviço.
        $stmt = $this->pdo?->prepare(
            "SELECT COUNT(*) FROM tblinvoiceitems WHERE type = 'Hosting' AND relid = ?"
        );
        $stmt?->execute([$this->serviceIds[0]]);

        $this->assertSame(1, (int)$stmt?->fetchColumn());
    }

    public function testTheCycleAppliesTheLateFee(): void
    {
        $invoiceId = $this->unpaidInvoice(135, 30.00, '2026-05-01');

        // A multa está desligada nesta instalação — o teste liga-a e
        // volta a desligá-la.
        $this->savedSettings['InvoiceLateFeeAmount'] = (string)$this->settingsRepository?->get('InvoiceLateFeeAmount');
        $this->settingsRepository?->set('InvoiceLateFeeAmount', '5.00');

        $result = $this->cron?->run('2026-06-01');

        $this->assertSame(1, $result['lateFees']);

        $stmt = $this->pdo?->prepare(
            "SELECT amount FROM tblinvoiceitems WHERE invoiceid = ? AND type = 'LateFee'"
        );
        $stmt?->execute([$invoiceId]);

        $this->assertSame(5.0, (float)$stmt?->fetchColumn());

        // E a fatura reflecte a multa.
        $invoice = $this->invoices?->findById($invoiceId);
        $this->assertSame(35.0, (float)$invoice['total']);

        // Daqui a uma volta, a multa não é cobrada duas vezes.
        $second = $this->cron?->run('2026-06-02');
        $this->assertSame(0, $second['lateFees']);
    }

    public function testTheCycleQueuesTheNoticeToTheOperatorAndNotToTheClient(): void
    {
        $invoiceId = $this->unpaidInvoice(135, 30.00, date('Y-m-d', strtotime('+3 days')));

        $result = $this->cron?->run(date('Y-m-d'));

        $this->assertGreaterThanOrEqual(1, $result['notices']);

        // A mensagem na fila vai para o operador.
        $pending = dirname(__DIR__, 2) . '/storage/reset-tokens/pending';
        $files = glob($pending . '/aviso-' . $invoiceId . '-*.txt') ?: [];

        $this->assertNotEmpty($files, 'the notice must be queued');

        foreach ($files as $file) {
            $this->queuedFiles[] = $file;

            $payload = (string)file_get_contents($file);

            $this->assertStringContainsString('recipient: ' . MailPolicy::allowed()[0], $payload);
            $this->assertStringNotContainsString('recipient: xkhm-test@example.com', $payload);
            // E o cliente viaja com a mensagem, para o operador saber
            // de quem é.
            $this->assertStringContainsString('client: ', $payload);
        }
    }

    public function testTheNoticeIsSentOnlyOnce(): void
    {
        $this->unpaidInvoice(135, 30.00, date('Y-m-d', strtotime('+3 days')));

        $this->cron?->run(date('Y-m-d'));
        $second = $this->cron?->run(date('Y-m-d', strtotime('+1 day')));

        // A segunda volta não manda outro aviso para a mesma fase.
        $this->assertSame(0, $second['notices']);
    }

    public function testTheSuspensionIsOffUntilItIsEnabled(): void
    {
        $serviceId = $this->dueService();
        $invoiceId = $this->unpaidInvoice(135, 30.00, '2026-05-01');

        $this->pdo?->prepare(
            "INSERT INTO tblinvoiceitems (invoiceid, userid, type, relid, description, amount, taxed, duedate, paymentmethod, notes) "
            . "VALUES (?, ?, 'Hosting', ?, 'Serviço', 30.00, 0, '2026-05-01', '', '')"
        )->execute([$invoiceId, 135, $serviceId]);

        // A política: o serviço **não** é suspenso.
        $result = $this->cron?->run('2026-06-01');

        $this->assertSame(0, $result['suspensions']);

        $service = $this->services?->findById($serviceId);
        $this->assertSame('Active', $service['domainstatus']);

        // Com a permissão explícita, é suspenso.
        putenv('XKHM_CRON_ALLOW_SUSPENSION=1');

        try {
            $second = $this->cron?->run('2026-06-01');

            $this->assertSame(1, $second['suspensions']);

            $service = $this->services?->findById($serviceId);
            $this->assertSame('Suspended', $service['domainstatus']);
        } finally {
            putenv('XKHM_CRON_ALLOW_SUSPENSION');
        }
    }

    public function testTheCycleOnTheRealDataDoesNothing(): void
    {
        /**
         * The installation's own services are **not** due: the cycle
         * must not touch the clients' data by accident.
         */
        $result = $this->cron?->run(date('Y-m-d'));

        $this->assertIsArray($result);
        $this->assertArrayHasKey('invoices', $result);
        $this->assertArrayHasKey('notices', $result);
    }
}
