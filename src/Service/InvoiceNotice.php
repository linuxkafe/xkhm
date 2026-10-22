<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The content of a **payment notice**.
 *
 * A payment notice is not a tax document. It says what is owed, by
 * whom, by when and how — and it says, on its face, that it is not an
 * invoice.
 *
 * Issuing one is not the same as issuing an invoice: in Portugal an
 * invoice is a fiscal document, and it may only be issued by certified
 * invoicing software. Until the software is certified (T039), this is
 * what the customer gets.
 */
final class InvoiceNotice
{
    /**
     * @param list<array{description: string, amount: float}> $items
     * @param array<string, string> $client
     * @param array<string, string> $issuer
     * @param list<array{label: string, detail: string}> $paymentMethods
     */
    public function __construct(
        public int $id,
        public string $number,
        public string $issuedAt,
        public string $dueAt,
        public array $items,
        public float $subtotal,
        public float $tax,
        public float $credit,
        public float $total,
        public string $status,
        public array $client,
        public array $issuer,
        public array $paymentMethods = [],
        public string $note = ''
    ) {
    }

    /**
     * The status, in the words of a person.
     */
    public function statusLabel(): string
    {
        return match ($this->status) {
            'Paid' => 'Pago',
            'Unpaid' => 'Por pagar',
            'Cancelled' => 'Cancelado',
            'Refunded' => 'Reembolsado',
            default => $this->status,
        };
    }

    /**
     * The name of the file, when the notice is downloaded.
     *
     * It carries the invoice's number — which is the reference the
     * customer already knows — and nothing else.
     */
    public function fileName(): string
    {
        $number = preg_replace('/[^A-Za-z0-9._-]+/', '-', $this->number) ?: (string)$this->id;

        return 'aviso-pagamento-' . trim($number, '-') . '.pdf';
    }
}
