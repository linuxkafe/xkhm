<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\ActivityLogRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Repository\ServiceRepositoryInterface;

/**
 * The billing cycle — the WHMCS's `cron.php`.
 *
 * The WHMCS runs it once a day, at the hour the operator chooses, and
 * it does four things:
 *
 *   1. it raises the **renewal invoice** of the services whose
 *      renewal is due;
 *   2. it applies the **late fee** to the invoices that are overdue;
 *   3. it sends the **payment notices**;
 *   4. it **suspends** the services whose invoice is overdue.
 *
 * Two of those four touch the client, and one of them takes the
 * service down. Until the installation is validated, the notices go
 * to the **operator** ([[MailPolicy]]) and the suspension is **off**
 * (`XKHM_CRON_ALLOW_SUSPENSION`).
 *
 * The cycle is **idempotent**: running it twice must not raise two
 * invoices for the same period, nor queue two notices for the same
 * invoice. The guard is the data itself — the invoice item carries
 * the period it pays for, and the activity log carries the notices
 * already sent.
 */
class CronService
{
    /**
     * The billing cycles of the WHMCS, and their length.
     *
     * @var array<string, string>
     */
    private const CYCLES = [
        'Monthly' => '+1 month',
        'Quarterly' => '+3 months',
        'Semi Annually' => '+6 months',
        'Annually' => '+1 year',
        'Biennially' => '+2 years',
        'Triennially' => '+3 years',
    ];

    public function __construct(
        private ServiceRepositoryInterface $services,
        private InvoiceRepositoryInterface $invoices,
        private SettingsService $settings,
        private NoticeQueue $notices,
        private InvoiceNumberService $numbers,
        private ActivityLogRepositoryPDO $log
    ) {
    }

    /**
     * Run the cycle.
     *
     * @return array<string, int> what was done
     */
    public function run(?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');

        return [
            'invoices' => $this->raiseRenewalInvoices($date),
            'lateFees' => $this->applyLateFees($date),
            'notices' => $this->sendPaymentNotices($date),
            'suspensions' => $this->suspendOverdue($date),
        ];
    }

    /**
     * The renewal invoices.
     *
     * The invoice is raised `CreateInvoiceDaysBefore` days before the
     * renewal — as the WHMCS does — and it carries the **service's**
     * price, not the product's: the two can differ ([[T040]]).
     */
    private function raiseRenewalInvoices(string $date): int
    {
        $daysBefore = (int)$this->settings->get('CreateInvoiceDaysBefore', '30');

        $raised = 0;

        foreach ($this->services->dueForInvoice($date, $daysBefore) as $service) {
            $serviceId = (int)($service['id'] ?? 0);
            $periodEnd = (string)($service['nextduedate'] ?? '');

            if ($serviceId === 0 || $periodEnd === '') {
                continue;
            }

            // **The guard**: an invoice for this service, for this
            // period, already exists.
            if ($this->services->invoicedForPeriod($serviceId, $periodEnd) > 0) {
                continue;
            }

            $amount = round((float)($service['amount'] ?? 0), 2);

            if ($amount <= 0) {
                // A service with no price does not get an invoice — the
                // price is set by the operator (T040).
                continue;
            }

            $cycle = (string)($service['billingcycle'] ?? 'Annually');
            $invoiceId = $this->createInvoice(
                (int)($service['userid'] ?? 0),
                $date,
                $periodEnd,
                $amount
            );

            if ($invoiceId === null) {
                continue;
            }

            $this->invoices->addItem($invoiceId, [
                'userid' => (int)($service['userid'] ?? 0),
                'type' => 'Hosting',
                'relid' => $serviceId,
                'description' => sprintf(
                    '%s — renovação %s',
                    (string)($service['domain'] ?? ('Serviço #' . $serviceId)),
                    $cycle
                ),
                'amount' => $amount,
                'taxed' => 0,
                'duedate' => $periodEnd,
                'paymentmethod' => '',
            ]);

            // As datas avançam — e é isto que torna o ciclo
            // idempotente: da próxima vez o serviço já não está
            // vencido.
            $this->advance($serviceId, $cycle, $periodEnd);

            $this->log->log(sprintf(
                'Fatura de renovação #%d criada para o serviço %d (%s, %s)',
                $invoiceId,
                $serviceId,
                (string)($service['domain'] ?? ''),
                number_format($amount, 2, '.', '') . ' €'
            ));

            $raised++;
        }

        return $raised;
    }

    /**
     * The late fee.
     *
     * The amount comes from `InvoiceLateFeeAmount` (0.00 in this
     * installation), the minimum from `LateFeeMinimum`, and the type
     * from `LateFeeType`.
     */
    private function applyLateFees(string $date): int
    {
        $type = (string)$this->settings->get('LateFeeType', 'Fixed Amount');
        $amount = (float)$this->settings->get('InvoiceLateFeeAmount', '0');
        $minimum = (float)$this->settings->get('LateFeeMinimum', '0');
        $days = (int)$this->settings->get('LateFeeDays', '0');

        if ($amount <= 0) {
            // A multa está desligada — e o WHMCS também não a cobra.
            return 0;
        }

        $limit = date('Y-m-d', strtotime($date . ' -' . max(0, $days) . ' days'));

        $applied = 0;

        foreach ($this->overdueInvoices($limit) as $invoice) {
            $invoiceId = (int)($invoice['id'] ?? 0);

            if ($invoiceId === 0) {
                continue;
            }

            // **The guard**: a multa já foi cobrada nesta fatura.
            if ($this->hasLateFee($invoiceId)) {
                continue;
            }

            $total = (float)($invoice['total'] ?? 0);

            $fee = $type === 'Percentage'
                ? round($total * $amount / 100, 2)
                : round($amount, 2);

            if ($fee < $minimum) {
                $fee = $minimum;
            }

            if ($fee <= 0) {
                continue;
            }

            $this->invoices->addItem($invoiceId, [
                'userid' => (int)($invoice['userid'] ?? 0),
                'type' => 'LateFee',
                'relid' => $invoiceId,
                'description' => 'Multa por atraso',
                'amount' => $fee,
                'taxed' => 0,
                'duedate' => (string)($invoice['duedate'] ?? ''),
                'paymentmethod' => '',
            ]);

            $this->invoices->update($invoiceId, [
                'total' => round($total + $fee, 2),
            ]);

            $this->log->log(sprintf(
                'Multa por atraso de %s € aplicada à fatura #%d',
                number_format($fee, 2, '.', ''),
                $invoiceId
            ));

            $applied++;
        }

        return $applied;
    }

    /**
     * The payment notices.
     *
     * The WHMCS sends one before the due date, and three after it
     * ([[SendInvoiceReminderDays]], [[SendFirstOverdueInvoiceReminder]],
     * and the second and the third). Each stage is sent **once** — the
     * activity log is the guard.
     *
     * The notice goes to the **operator**, not to the client: the
     * installation is not validated ([[MailPolicy]]).
     */
    private function sendPaymentNotices(string $date): int
    {
        $before = (int)$this->settings->get('SendInvoiceReminderDays', '7');

        // As janelas não se sobrepõem, e são **acumulativas**: uma
        // fase que se perdeu (o ciclo não correu nesse dia) é apanhada
        // pela fase seguinte.
        $first = (int)$this->settings->get('SendFirstOverdueInvoiceReminder', '5');
        $second = (int)$this->settings->get('SendSecondOverdueInvoiceReminder', '10');
        $third = (int)$this->settings->get('SendThirdOverdueInvoiceReminder', '15');

        $stages = [
            // A vencer: de hoje a daqui a `SendInvoiceReminderDays`.
            'upcoming' => $this->dateWindow($date, 0, $before),
            // Em atraso: 1 a 5 dias, 6 a 10, 11 a 15.
            'overdue1' => $this->dateWindow($date, -$first, -1),
            'overdue2' => $this->dateWindow($date, -$second, -($first + 1)),
            'overdue3' => $this->dateWindow($date, -$third, -($second + 1)),
        ];

        $sent = 0;

        foreach ($this->unpaidInvoices() as $invoice) {
            $invoiceId = (int)($invoice['id'] ?? 0);
            $due = (string)($invoice['duedate'] ?? '');

            if ($invoiceId === 0 || $due === '') {
                continue;
            }

            $stage = null;

            foreach ($stages as $name => $window) {
                [$from, $to] = $window;

                if ($due >= $from && $due <= $to) {
                    $stage = $name;
                    break;
                }
            }

            if ($stage === null) {
                continue;
            }

            // **The guard**: this stage was already notified. The
            // entry in the log is the *same* string — the guard and
            // the writing have to agree exactly.
            $entry = sprintf('Aviso de pagamento (%s) da fatura #%d', $stage, $invoiceId);

            if ($this->log->hasEntry($entry)) {
                continue;
            }

            $clientId = (int)($invoice['userid'] ?? 0);

            if (!$this->notices->queue($invoiceId, $clientId)) {
                continue;
            }

            $this->log->log($entry);

            $sent++;
        }

        return $sent;
    }

    /**
     * The suspension of the services whose invoice is overdue.
     *
     * **Off by default**: the installation is not validated, and a
     * suspension takes the service down. It is enabled with
     * `XKHM_CRON_ALLOW_SUSPENSION=1`.
     */
    private function suspendOverdue(string $date): int
    {
        if (!$this->suspensionAllowed()) {
            return 0;
        }

        if (!$this->settings->isOn('AutoSuspension')) {
            return 0;
        }

        $days = (int)$this->settings->get('AutoSuspensionDays', '5');

        $suspended = 0;

        foreach ($this->services->overdue($date, $days) as $service) {
            $serviceId = (int)($service['id'] ?? 0);

            if ($serviceId === 0) {
                continue;
            }

            $this->services->update($serviceId, [
                'domainstatus' => 'Suspended',
                'suspendreason' => 'Fatura em atraso',
            ]);

            $this->log->log(sprintf(
                'Serviço %d suspenso por fatura em atraso',
                $serviceId
            ));

            $suspended++;
        }

        return $suspended;
    }

    // ─── The parts ───────────────────────────────────────────────────────────

    /**
     * Whether the suspension may happen.
     */
    private function suspensionAllowed(): bool
    {
        $value = getenv('XKHM_CRON_ALLOW_SUSPENSION');

        if ($value === false) {
            return false;
        }

        return !in_array(strtolower(trim($value)), ['', '0', 'false', 'no'], true);
    }

    /**
     * Create the invoice itself.
     */
    private function createInvoice(int $userId, string $date, string $dueDate, float $amount): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $number = $this->numbers->next();

        return $this->invoices->create([
            'userid' => $userId,
            'invoicenum' => $number,
            'date' => $date,
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
    }

    /**
     * Advance the service's dates by one cycle.
     */
    private function advance(int $serviceId, string $cycle, string $periodEnd): void
    {
        $step = self::CYCLES[$cycle] ?? '+1 year';

        $next = date('Y-m-d', strtotime($periodEnd . ' ' . $step));

        $this->services->update($serviceId, [
            'nextduedate' => $next,
            'nextinvoicedate' => $next,
        ]);
    }

    /**
     * The invoices that are unpaid.
     *
     * @return list<array<string, mixed>>
     */
    private function unpaidInvoices(): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM tblinvoices WHERE status = 'Unpaid' AND duedate IS NOT NULL ORDER BY id"
        );
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * The invoices that are overdue.
     *
     * @return list<array<string, mixed>>
     */
    private function overdueInvoices(string $limit): array
    {
        $stmt = $this->pdo()->prepare(
            "SELECT * FROM tblinvoices WHERE status = 'Unpaid' AND duedate IS NOT NULL "
            . 'AND duedate < :limit ORDER BY id'
        );
        $stmt->execute(['limit' => $limit]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Whether the invoice already carries a late fee.
     */
    private function hasLateFee(int $invoiceId): bool
    {
        $stmt = $this->pdo()->prepare(
            "SELECT COUNT(*) FROM tblinvoiceitems WHERE invoiceid = ? AND type = 'LateFee'"
        );
        $stmt->execute([$invoiceId]);

        return ((int)$stmt->fetchColumn()) > 0;
    }

    /**
     * A window of dates, as a pair.
     *
     * @return array{0: string, 1: string}
     */
    private function dateWindow(string $date, int $fromDays, int $toDays): array
    {
        return [
            date('Y-m-d', strtotime($date . ' ' . ($fromDays >= 0 ? '+' : '') . $fromDays . ' days')),
            date('Y-m-d', strtotime($date . ' ' . ($toDays >= 0 ? '+' : '') . $toDays . ' days')),
        ];
    }

    private function pdo(): \PDO
    {
        $config = new \Xkhm\Config();

        return \Xkhm\Database\Connection::get($config->getDbConfig());
    }
}
