<?php

declare(strict_types=1);

namespace Xkhm\Service;

/**
 * The payment notice, put in the queue of the host's mailer.
 *
 * The mailer runs on the **host**, not in the container — and it
 * cannot generate the PDF (it has no application). So the application
 * generates it, **saves** it, and the queue carries the path.
 *
 * The invoice itself is not touched: a notice that is sent twice is the
 * same notice, and re-sending it must not change anything.
 */
class NoticeQueue
{
    /** Where the generated PDFs wait. */
    public const DIRECTORY = 'storage/notice-pdfs';

    public function __construct(
        private InvoiceNoticeService $notices,
        private PaymentNoticePdf $pdf
    ) {
    }

    /**
     * Queue the notice of an invoice.
     */
    public function queue(int $invoiceId, int $clientId): bool
    {
        $notice = $this->notices->build($invoiceId);

        if ($notice === null) {
            return false;
        }

        $client = $this->clientEmail($clientId);

        if ($client === null) {
            return false;
        }

        $pdf = $this->pdf->render($notice);

        $directory = $this->directory();

        if ($directory === null) {
            return false;
        }

        $file = sprintf(
            '%d-%s.pdf',
            $invoiceId,
            date('Ymd-His')
        );

        $path = $directory . '/' . $file;

        if (@file_put_contents($path, $pdf) === false) {
            return false;
        }

        @chmod($path, 0644);

        $payload = sprintf(
            "purpose: aviso\nrecipient: %s\naccount: %d\ntoken: %d\n"
            . "subject: %s\nattachment: %s\ncreated: %s\n",
            $client,
            $clientId,
            $invoiceId,
            $this->subject($notice),
            // The path is **relative** to the storage root: the queue is
            // read by the host's mailer, and the host's path is not the
            // container's.
            'notice-pdfs/' . $file,
            date('c')
        );

        $pending = sprintf(
            '%s/aviso-%d-%s.txt',
            $this->pendingDirectory(),
            $invoiceId,
            date('Ymd-His')
        );

        return @file_put_contents($pending, $payload, LOCK_EX) !== false;
    }

    /**
     * The subject of the message.
     */
    private function subject(InvoiceNotice $notice): string
    {
        return sprintf(
            'Aviso de pagamento — fatura %s',
            $notice->number
        );
    }

    private function clientEmail(int $clientId): ?string
    {
        $client = $this->clients()->findById($clientId);

        if ($client === null) {
            return null;
        }

        $email = trim((string)($client['email'] ?? ''));

        return $email === '' ? null : $email;
    }

    private function clients(): \Xkhm\Repository\ClientRepositoryInterface
    {
        return new \Xkhm\Repository\ClientRepositoryPDO();
    }

    /**
     * Where the PDFs wait.
     */
    private function directory(): ?string
    {
        $directory = dirname(__DIR__, 2) . '/' . self::DIRECTORY;

        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        return $directory;
    }

    /**
     * Where the queue of the mailer is.
     */
    private function pendingDirectory(): string
    {
        $directory = dirname(__DIR__, 2) . '/storage/reset-tokens/pending';

        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        return $directory;
    }
}
