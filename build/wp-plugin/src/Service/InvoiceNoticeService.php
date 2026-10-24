<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\AdminPanelRepositoryInterface;
use Xkhm\Repository\ClientRepositoryInterface;

/**
 * The payment notice of an invoice.
 *
 * ## What it is
 *
 * A **payment notice**: a commercial document that says what is owed,
 * by whom, by when and how to pay it. It is **not** a fiscal document,
 * and it says so on its face — in Portugal an invoice may only be
 * issued by certified invoicing software.
 *
 * ## Where the data comes from
 *
 * The issuer is the **configuration** (`tblconfiguration`), the customer
 * is the `tblclients` row, and the amounts are the ones the invoice
 * already has. The notice does **not** recalculate anything: the tax
 * table of this installation is empty, and inventing a tax rate is
 * worse than showing the totals the invoice has.
 */
class InvoiceNoticeService
{
    public function __construct(
        private AdminPanelRepositoryInterface $invoices,
        private ClientRepositoryInterface $clients,
        private SettingsService $settings
    ) {
    }

    /**
     * The notice of an invoice, or null when the invoice does not
     * exist.
     */
    public function build(int $invoiceId): ?InvoiceNotice
    {
        $invoice = $this->invoices->findInvoice($invoiceId);

        if ($invoice === null) {
            return null;
        }

        $clientId = (int)($invoice['userid'] ?? 0);
        $client = $this->clients->findById($clientId) ?? [];

        $items = [];
        foreach ($this->invoices->invoiceItems($invoiceId) as $item) {
            $items[] = [
                'description' => (string)($item['description'] ?? ''),
                'amount' => (float)($item['amount'] ?? 0),
            ];
        }

        $company = $this->settings->company();

        return new InvoiceNotice(
            id: $invoiceId,
            number: (string)($invoice['invoicenum'] ?? (string)$invoiceId),
            issuedAt: $this->formatDate((string)($invoice['datecreated'] ?? '')),
            dueAt: $this->formatDate((string)($invoice['duedate'] ?? '')),
            items: $items,
            subtotal: (float)($invoice['subtotal'] ?? 0),
            tax: (float)($invoice['tax'] ?? 0) + (float)($invoice['tax2'] ?? 0),
            credit: (float)($invoice['credit'] ?? 0),
            total: (float)($invoice['total'] ?? 0),
            status: (string)($invoice['status'] ?? ''),
            client: $this->clientBlock($client),
            issuer: [
                'name' => $company['name'],
                'address' => trim(implode(', ', array_filter([
                    $company['address'],
                    $company['address2'],
                    $company['postcode'] . ' ' . $company['city'],
                ]))),
                'city' => $company['city'],
                'postcode' => $company['postcode'],
                'country' => $company['country'],
                'email' => $company['email'],
                'phone' => $company['phone'],
                'nif' => $company['nif'],
            ],
            paymentMethods: $this->paymentMethods($company),
            note: (string)($invoice['notes'] ?? ''),
        );
    }

    /**
     * The customer, as the document shows them.
     *
     * @param array<string, mixed> $client
     *
     * @return array<string, string>
     */
    private function clientBlock(array $client): array
    {
        $name = trim(sprintf(
            '%s %s',
            (string)($client['firstname'] ?? ''),
            (string)($client['lastname'] ?? '')
        ));

        $address = trim(implode(', ', array_filter([
            (string)($client['address1'] ?? ''),
            (string)($client['address2'] ?? ''),
            trim(sprintf(
                '%s %s',
                (string)($client['postcode'] ?? ''),
                (string)($client['city'] ?? '')
            )),
            (string)($client['country'] ?? ''),
        ])));

        return [
            'name' => $name !== '' ? $name : (string)($client['email'] ?? ''),
            'email' => (string)($client['email'] ?? ''),
            'address' => $address,
        ];
    }

    /**
     * The ways of paying, as the configuration describes them.
     *
     * @param array<string, string> $company
     *
     * @return list<array{label: string, detail: string}>
     */
    private function paymentMethods(array $company): array
    {
        $methods = [];

        if (trim($company['bankDetails']) !== '') {
            $methods[] = [
                'label' => 'Transferência bancária',
                'detail' => $company['bankDetails'],
            ];
        }

        if (trim($company['paymentInfo']) !== '') {
            $methods[] = [
                'label' => 'Outros meios',
                'detail' => $company['paymentInfo'],
            ];
        }

        if (trim($company['phone']) !== '') {
            $methods[] = [
                'label' => 'MB Way',
                'detail' => $company['phone'],
            ];
        }

        return $methods;
    }

    private function formatDate(string $value): string
    {
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return '—';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('d/m/Y', $timestamp);
    }
}
