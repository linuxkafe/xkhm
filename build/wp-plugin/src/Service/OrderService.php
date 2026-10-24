<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\CatalogueRepositoryInterface;
use Xkhm\Repository\CatalogueRepositoryPDO;
use Xkhm\Repository\InvoiceRepositoryInterface;
use Xkhm\Repository\OrderRepositoryInterface;
use Xkhm\Repository\ServiceRepositoryInterface;

/**
 * The order: from the catalogue to the service.
 *
 * The WHMCS's path is:
 *
 * ```
 * order.php → the order (tblorders) → the invoice (tblinvoices)
 *            → the payment → the service (tblhosting)
 * ```
 *
 * and this service walks the same path, in the same tables, with the
 * same conventions:
 *
 *   * the order's number is a **random** bigint — the WHMCS's
 *     convention, so that the customer can quote it;
 *   * the invoice's item of a **new** order points at the **product**
 *     (`type = 'Product'`); the item of a **renewal** points at the
 *     **service** (`type = 'Hosting'`) — as the billing cycle does;
 *   * the service is created when the invoice is **paid**, with the
 *     price that was **paid** (not the product's price: the two can
 *     differ — see T040).
 */
class OrderService
{
    /**
     * The billing cycles, and the length of each.
     *
     * @var array<string, string>
     */
    public const CYCLES = [
        'Monthly' => '+1 month',
        'Quarterly' => '+3 months',
        'Semi Annually' => '+6 months',
        'Annually' => '+1 year',
        'Biennially' => '+2 years',
        'Triennially' => '+3 years',
    ];

    public function __construct(
        private CatalogueRepositoryInterface $catalogue,
        private OrderRepositoryInterface $orders,
        private InvoiceRepositoryInterface $invoices,
        private ServiceRepositoryInterface $services,
        private InvoiceNumberService $numbers,
        private SettingsService $settings
    ) {
    }

    /**
     * The catalogue, as the client sees it.
     *
     * The products that are **not** hidden, with their group, their
     * type and the price of every cycle they have.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(): array
    {
        $catalogue = [];

        foreach ($this->catalogue->products() as $product) {
            if ((string)($product['hidden'] ?? '') === 'on') {
                continue;
            }

            $prices = [];

            foreach (CatalogueRepositoryPDO::CYCLES as $column => $label) {
                $price = (float)($product['prices'][$column] ?? -1);

                // Um ciclo que o produto não tem não entra: o
                // WHMCS marca-os com `-1.00`.
                if ($price >= 0) {
                    $prices[] = [
                        'cycle' => $label,
                        'label' => $label,
                        'price' => $price,
                    ];
                }
            }

            if ($prices === []) {
                // Um produto sem preço nenhum não se vende.
                continue;
            }

            $catalogue[] = [
                'id' => (int)($product['id'] ?? 0),
                'name' => (string)($product['name'] ?? ''),
                'type' => (string)($product['type'] ?? ''),
                'group' => (string)($product['groupname'] ?? ''),
                'description' => (string)($product['description'] ?? ''),
                'setup' => (float)($product['prices']['setup'] ?? 0),
                'cycles' => $prices,
                'needsDomain' => (string)($product['type'] ?? '') === 'hostingaccount',
            ];
        }

        return $catalogue;
    }

    /**
     * Place an order.
     *
     * @param array<string, mixed> $data
     *
     * @return array{ok: bool, orderId?: int, invoiceId?: int, error?: string}
     */
    public function place(int $clientId, array $data): array
    {
        if ($clientId <= 0) {
            return ['ok' => false, 'error' => 'Sessão inválida.'];
        }

        $productId = (int)($data['productId'] ?? 0);
        $cycle = (string)($data['cycle'] ?? 'Annually');
        $domain = trim((string)($data['domain'] ?? ''));

        $product = $this->catalogue->product($productId);

        if ($product === null) {
            return ['ok' => false, 'error' => 'O produto não existe.'];
        }

        if ((string)($product['hidden'] ?? '') === 'on') {
            return ['ok' => false, 'error' => 'O produto não está disponível.'];
        }

        // O preço vem do **produto**, para o ciclo escolhido. O ciclo
        // do WHMCS (`Annually`) e a coluna do `tblpricing`
        // (`annually`) não são o mesmo nome.
        $prices = $product['prices'] ?? [];
        $column = CatalogueRepositoryPDO::CYCLE_COLUMNS[$cycle] ?? '';
        $price = (float)($prices[$column] ?? -1);

        if ($price < 0) {
            return ['ok' => false, 'error' => 'O produto não tem esse ciclo de faturação.'];
        }

        $setup = (float)($prices['setup'] ?? 0);

        // O alojamento web pede um domínio.
        if ((string)($product['type'] ?? '') === 'hostingaccount') {
            if ($domain === '') {
                return ['ok' => false, 'error' => 'Indique o domínio do serviço.'];
            }

            if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,253}[a-z0-9]$/i', $domain)) {
                return ['ok' => false, 'error' => 'O domínio não é válido.'];
            }
        }

        $amount = round($price + $setup, 2);
        $today = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime($today . ' +7 days'));
        $paymentMethod = (string)($this->settings->get('GatewayModule', 'banktransfer') ?: 'banktransfer');

        // O pedido.
        $orderId = $this->orders->create([
            'ordernum' => $this->orderNumber(),
            'userid' => $clientId,
            'contactid' => 0,
            'date' => date('Y-m-d H:i:s'),
            'amount' => $amount,
            'paymentmethod' => $paymentMethod,
            'status' => 'Pending',
            'invoiceid' => 0,
            'nameservers' => $this->nameservers(),
            'orderdata' => 'a:0:{}',
            'notes' => '',
        ]);

        // A fatura.
        $invoiceId = $this->invoices->create([
            'userid' => $clientId,
            'invoicenum' => $this->numbers->next(),
            'date' => $today,
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
            'paymentmethod' => $paymentMethod,
        ]);

        $this->invoices->addItem($invoiceId, [
            'userid' => $clientId,
            'type' => 'Product',
            'relid' => $productId,
            'description' => $this->description($product, $cycle, $domain),
            'amount' => $amount,
            'taxed' => 0,
            'duedate' => $dueDate,
            'paymentmethod' => $paymentMethod,
        ]);

        // E o pedido aponta para a fatura.
        $this->orders->update($orderId, ['invoiceid' => $invoiceId]);

        return ['ok' => true, 'orderId' => $orderId, 'invoiceId' => $invoiceId];
    }

    /**
     * Mark an invoice as paid — and provision the service.
     *
     * This is the moment the WHMCS creates the service: the order
     * was placed, the invoice was raised, the client paid.
     */
    public function markPaid(int $invoiceId, string $paymentMethod = ''): bool
    {
        $invoice = $this->invoices->findById($invoiceId);

        if ($invoice === null) {
            return false;
        }

        if ((string)($invoice['status'] ?? '') === 'Paid') {
            // Já está paga: o serviço já foi criado (ou não há nada a
            // criar).
            return true;
        }

        $clientId = (int)($invoice['userid'] ?? 0);

        $this->invoices->update($invoiceId, [
            'status' => 'Paid',
            'datepaid' => date('Y-m-d H:i:s'),
            'paymentmethod' => $paymentMethod !== ''
                ? $paymentMethod
                : (string)($invoice['paymentmethod'] ?? ''),
        ]);

        return $this->provision($invoiceId, $clientId);
    }

    /**
     * Create the service of a paid order.
     */
    private function provision(int $invoiceId, int $clientId): bool
    {
        $order = $this->orderOfInvoice($invoiceId);

        if ($order === null) {
            return false;
        }

        $orderId = (int)($order['id'] ?? 0);

        // O serviço já existe? Então não se cria outro.
        foreach ($this->services->findByUserId($clientId) as $service) {
            if ((int)($service['orderid'] ?? 0) === $orderId) {
                return true;
            }
        }

        $item = $this->orderItem($invoiceId);

        if ($item === null) {
            return false;
        }

        $productId = (int)($item['relid'] ?? 0);
        $product = $this->catalogue->product($productId);

        if ($product === null) {
            return false;
        }

        $cycle = $this->cycleOf($item);
        $amount = (float)($item['amount'] ?? 0);
        $today = date('Y-m-d');
        $next = date('Y-m-d', strtotime($today . ' ' . (self::CYCLES[$cycle] ?? '+1 year')));

        $this->services->create([
            'userid' => $clientId,
            'orderid' => $orderId,
            'packageid' => $productId,
            'server' => $this->serverOf($product),
            'regdate' => $today,
            'domain' => $this->domainOf($item),
            'paymentmethod' => (string)($order['paymentmethod'] ?? ''),
            'firstpaymentamount' => $amount,
            'amount' => $amount,
            'billingcycle' => $cycle,
            'nextduedate' => $next,
            'nextinvoicedate' => $next,
            'domainstatus' => 'Active',
            'username' => '',
            'password' => '',
            'notes' => '',
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

        $this->orders->update($orderId, ['status' => 'Active']);

        return true;
    }

    // ─── The parts ───────────────────────────────────────────────────────────

    /**
     * The order of an invoice.
     *
     * @return array<string, mixed>|null
     */
    private function orderOfInvoice(int $invoiceId): ?array
    {
        // O pedido e' procurado pela fatura, e por isso a consulta e'
        // directa.
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT * FROM tblorders WHERE invoiceid = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$invoiceId]);

        $order = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $order === false ? null : $order;
    }

    /**
     * The item of the invoice that describes the product.
     *
     * @return array<string, mixed>|null
     */
    private function orderItem(int $invoiceId): ?array
    {
        $pdo = $this->pdo();
        $stmt = $pdo->prepare(
            "SELECT * FROM tblinvoiceitems WHERE invoiceid = ? AND type = 'Product' ORDER BY id LIMIT 1"
        );
        $stmt->execute([$invoiceId]);

        $item = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $item === false ? null : $item;
    }

    /**
     * The billing cycle of an item.
     */
    private function cycleOf(array $item): string
    {
        $description = (string)($item['description'] ?? '');

        foreach (array_keys(self::CYCLES) as $cycle) {
            if (str_contains($description, '[' . $cycle . ']')) {
                return $cycle;
            }
        }

        return 'Annually';
    }

    /**
     * The domain of an item.
     */
    private function domainOf(array $item): string
    {
        $description = (string)($item['description'] ?? '');

        // O dominio vem no parêntesis: «produto (dominio)».
        if (preg_match('/\(([^()]*)\)/', $description, $matches) === 1) {
            return trim($matches[1]);
        }

        return '';
    }

    /**
     * The description of the item of the invoice.
     */
    private function description(array $product, string $cycle, string $domain): string
    {
        $description = sprintf(
            '%s [%s]',
            (string)($product['name'] ?? ''),
            $cycle
        );

        if ($domain !== '') {
            $description .= sprintf(' (%s)', $domain);
        }

        return $description;
    }

    /**
     * The server of a product: the first of its server group.
     */
    private function serverOf(array $product): int
    {
        $groupId = (int)($product['servergroup'] ?? 0);

        if ($groupId <= 0) {
            return 0;
        }

        $servers = $this->catalogue->serversOfGroup($groupId);

        return (int)($servers[0]['id'] ?? 0);
    }

    /**
     * The nameservers of the installation.
     */
    private function nameservers(): string
    {
        $nameservers = [];

        for ($i = 1; $i <= 5; $i++) {
            $name = trim((string)$this->settings->get('DefaultNameserver' . $i, ''));

            if ($name !== '') {
                $nameservers[] = $name;
            }
        }

        return implode(',', $nameservers);
    }

    /**
     * The order's number: a random bigint, como o WHMCS.
     */
    private function orderNumber(): int
    {
        return random_int(1000000000, 9999999999);
    }

    private function pdo(): \PDO
    {
        $config = new \Xkhm\Config();

        return \Xkhm\Database\Connection::get($config->getDbConfig());
    }
}
