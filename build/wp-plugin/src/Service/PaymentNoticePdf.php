<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The payment notice, as a PDF.
 *
 * The document is built as **HTML** and converted — the application is
 * HTML, with a stylesheet of tokens, and the notice comes out with the
 * appearance of the product instead of a second language of styles.
 *
 * The PDF is a **commercial document**: it says what is owed and how to
 * pay it, and it says, on its face, that it is not a fiscal document.
 */
class PaymentNoticePdf
{
    /**
     * The notice, as a PDF.
     */
    public function render(InvoiceNotice $notice): string
    {
        $dompdf = new Dompdf($this->options());

        $dompdf->loadHtml($this->html($notice));
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $output = (string)$dompdf->output();

        if ($output === '') {
            throw new \RuntimeException('O gerador de PDF não devolveu nada.');
        }

        return $output;
    }

    /**
     * The options of the generator.
     *
     * The `chroot` is the public directory: the notice refers to the
     * stylesheet, and nothing else may be read from the disk.
     */
    private function options(): Options
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsHtml5ParserEnabled(true);
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsFontSubsettingEnabled(true);
        $options->setChroot(dirname(__DIR__, 2) . '/public');

        return $options;
    }

    /**
     * The HTML of the notice.
     */
    private function html(InvoiceNotice $notice): string
    {
        $issuer = $notice->issuer;
        $client = $notice->client;

        $rows = '';
        foreach ($notice->items as $item) {
            $rows .= sprintf(
                '<tr><td class="desc">%s</td><td class="amount">%s</td></tr>',
                $this->e($item['description']),
                $this->money($item['amount'])
            );
        }

        if ($rows === '') {
            $rows = '<tr><td class="desc">—</td><td class="amount">—</td></tr>';
        }

        $methods = '';
        foreach ($notice->paymentMethods as $method) {
            $methods .= sprintf(
                '<li><strong>%s</strong> %s</li>',
                $this->e($method['label']),
                $this->e($method['detail'])
            );
        }

        $note = '';
        if ($notice->note !== '') {
            $note = sprintf(
                '<p class="note">%s</p>',
                nl2br($this->e($notice->note))
            );
        }

        $css = $this->css();

        return <<<HTML
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
        <meta charset="utf-8">
        <title>Aviso de pagamento {$this->e($notice->number)}</title>
        <style>{$css}</style>
        </head>
        <body>
            <header class="head">
                <div class="issuer">
                    <img src="templates/default/images/linuxkafe.png" alt="LINUXKAFE" style="height: 40px; margin-bottom: 8px;">
                    <h1>{$this->e($issuer['name'] ?? '')}</h1>
                    <p>{$this->e($issuer['address'] ?? '')}</p>
                    <p>{$this->e($issuer['email'] ?? '')}</p>
                    <p>NIF: {$this->e($issuer['nif'] ?? '')}</p>
                </div>
                <div class="document">
                    <h2>Aviso de pagamento</h2>
                    <p class="number">{$this->e($notice->number)}</p>
                    <p>Emitido em {$this->e($notice->issuedAt)}</p>
                    <p>Vence em {$this->e($notice->dueAt)}</p>
                    <p class="status">{$this->e($notice->statusLabel())}</p>
                </div>
            </header>

            <section class="client">
                <h3>Destinatário</h3>
                <p>{$this->e($client['name'] ?? '')}</p>
                <p>{$this->e($client['email'] ?? '')}</p>
                <p>{$this->e($client['address'] ?? '')}</p>
            </section>

            <table class="items">
                <thead>
                    <tr><th>Descrição</th><th class="amount">Valor</th></tr>
                </thead>
                <tbody>{$rows}</tbody>
            </table>

            <section class="totals">
                <p><span>Subtotal</span><span>{$this->money($notice->subtotal)}</span></p>
                <p><span>Imposto</span><span>{$this->money($notice->tax)}</span></p>
                <p><span>Crédito</span><span>{$this->money($notice->credit)}</span></p>
                <p class="total"><span>Total</span><span>{$this->money($notice->total)}</span></p>
            </section>

            <section class="methods">
                <h3>Como pagar</h3>
                <ul>{$methods}</ul>
            </section>

            {$note}

            <footer class="foot">
                <p class="disclaimer">
                    Este documento é um <strong>aviso de pagamento</strong>. Não é
                    uma fatura, nem um documento fiscal, e não serve de título
                    para efeitos fiscais. A fatura certificada é emitida
                    separadamente, pelo programa de faturação certificado.
                </p>
                <p class="small">
                    {$this->e($issuer['name'] ?? '')} · NIF {$this->e($issuer['nif'] ?? '')}
                    · {$this->e($issuer['email'] ?? '')}
                </p>
            </footer>
        </body>
        </html>
        HTML;
    }

    /**
     * The stylesheet of the document.
     *
     * A folha é do próprio documento: o `dompdf` não executa
     * JavaScript, e a folha da aplicação tem media queries que o
     * gerador não sabe aplicar.
     */
    private function css(): string
    {
        return <<<'CSS'
        * { box-sizing: border-box; }
        body {
            font-family: "DejaVu Sans", sans-serif;
            font-size: 10.5pt;
            color: #212121;
            margin: 0;
            line-height: 1.45;
        }
        h1 { font-size: 15pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 0 0 4px; }
        h3 { font-size: 10pt; margin: 0 0 4px; text-transform: uppercase;
             letter-spacing: 0.5px; color: #555555; }
        p { margin: 0 0 2px; }
        .head { display: table; width: 100%; margin-bottom: 18px; }
        .head > div { display: table-cell; vertical-align: top; }
        .issuer { width: 58%; }
        .document { width: 42%; text-align: right; }
        .document .number { font-size: 13pt; font-weight: bold; }
        .document .status { display: inline-block; margin-top: 6px;
            padding: 2px 9px; border-radius: 10px; background: #f3f4f6;
            font-size: 9pt; font-weight: bold; }
        .client { margin-bottom: 14px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.items th {
            background: #2d2d2d; color: #ffffff; text-align: left;
            padding: 6px 8px; font-size: 9pt;
        }
        table.items td { padding: 6px 8px; border-bottom: 1px solid #e0e0e0; }
        td.amount, th.amount { text-align: right; white-space: nowrap; }
        .totals { width: 46%; margin-left: 54%; margin-bottom: 16px; }
        .totals p { display: table; width: 100%; margin: 0; }
        .totals span { display: table-cell; }
        .totals span:last-child { text-align: right; }
        .totals .total { font-weight: bold; border-top: 1px solid #212121;
            padding-top: 5px; margin-top: 5px; font-size: 12pt; }
        .methods ul { margin: 4px 0 0 16px; padding: 0; }
        .methods li { margin-bottom: 3px; }
        .note { margin: 12px 0; font-size: 9.5pt; color: #555555; }
        .foot { margin-top: 18px; border-top: 1px solid #e0e0e0; padding-top: 10px; }
        .disclaimer { font-size: 9pt; color: #555555; }
        .small { font-size: 8.5pt; color: #71717a; margin-top: 6px; }
        CSS;
    }

    private function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES);
    }

    private function money(float $value): string
    {
        return number_format($value, 2, ',', ' ') . ' €';
    }
}
