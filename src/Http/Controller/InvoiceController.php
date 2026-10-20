<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\BillingService;
use Xkhm\Service\CreditService;
use Xkhm\Service\GatewayService;

class InvoiceController
{
    private BillingService $billingService;
    private GatewayService $gatewayService;

    public function __construct(BillingService $billingService, GatewayService $gatewayService)
    {
        $this->billingService = $billingService;
        $this->gatewayService = $gatewayService;
    }

    /**
     * GET /viewinvoice.php?id=123
     */
    public function view(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $invoiceId = (int)($queryParams['id'] ?? 0);

        if (!$invoiceId) {
            return $this->errorResponse($response, 'Invalid invoice ID');
        }

        $invoice = $this->billingService->getInvoiceWithItems($invoiceId);
        if (!$invoice) {
            return $this->errorResponse($response, 'Invoice not found');
        }

        // Check ownership
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;
        if ($invoice['userid'] !== $clientId) {
            return $this->errorResponse($response, 'Unauthorized');
        }

        $html = $this->renderInvoiceView($invoice, $clientId);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /viewinvoice.php?action=pay
     */
    public function pay(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $invoiceId = (int)($data['invid'] ?? 0);
        $gateway = $data['gateway'] ?? 'paypal';

        if (!$invoiceId) {
            return $this->errorResponse($response, 'Invalid invoice ID');
        }

        $invoice = $this->billingService->getInvoiceWithItems($invoiceId);
        if (!$invoice || $invoice['status'] !== 'Unpaid') {
            return $this->errorResponse($response, 'Invoice not found or already paid');
        }

        $result = $this->gatewayService->processPayment($invoiceId, $gateway, $data);

        if ($result[0]) {
            $response->getBody()->write($this->renderPaymentSuccess($result[2]));
        } else {
            $response->getBody()->write($this->renderPaymentError($result[1]));
        }

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * GET /clientarea.php?action=invoices
     */
    public function list(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $summary = $this->billingService->getInvoiceSummary($clientId);
        $invoices = $this->billingService->getClientInvoices($clientId);

        $html = $this->renderInvoiceList($invoices, $summary, $clientId);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderInvoiceView(array $invoice, int $clientId): string
    {
        $total = $this->billingService->calculateTotal((int)$invoice['id']);
        $itemsHtml = '';

        foreach (($invoice['items'] ?? []) as $item) {
            $itemsHtml .= sprintf(
                '<tr><td>%s</td><td class="text-right">%s</td></tr>',
                htmlspecialchars($item['description'] ?? ''),
                number_format((float)($item['amount'] ?? 0), 2)
            );
        }

        $isOverdue = $this->billingService->isOverdue((int)$invoice['id']);
        $payButton = '';
        if ($invoice['status'] === 'Unpaid' && $total > 0) {
            $payButton = '<a href="/viewinvoice.php?id=' . (int)$invoice['id'] . '&action=pay" class="btn btn-success">Pay Now</a>';
        }

        $note = $invoice['note'] ?? 'Invoice';
        $rows = sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td class="text-right">%s</td></tr>',
            htmlspecialchars($note),
            $invoice['duedate'] ?? '',
            $invoice['status'] ?? '',
            $invoice['total'] ?? 0
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Aviso Nº{$invoice['id']} - LINUXKAFE</title>
    <link href="/templates/default/css/bootstrap.css" rel="stylesheet">
    <link href="/templates/default/css/whmcs.css" rel="stylesheet">
</head>
<body>
    <div class="whmcscontainer">
        <div class="container">
            <h2>Invoice #{$invoice['id']}</h2>
            <table class="table table-striped">
                <tr><th width="25%">Description</th><th width="15%">Due Date</th><th width="15%">Status</th><th width="15%">Amount</th></tr>
                <tr>{$rows}</tr>
            </table>
            <table class="table table-striped">
                <thead><tr><th>Description</th><th class="text-right">Amount</th></tr></thead>
                <tbody>{$itemsHtml}</tbody>
                <tfoot>
                    <tr><td class="text-right"><strong>Total</strong></td><td class="text-right"><strong>{$total}</strong></td></tr>
                </tfoot>
            </table>
            {$payButton}
            <p><a href="/clientarea.php?action=invoices" class="btn btn-default">Back to Invoices</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderInvoiceList(array $invoices, array $summary, int $clientId): string
    {
        $rows = '';
        foreach ($invoices as $inv) {
            $statusClass = match ($inv['status']) {
                'Paid' => 'text-success',
                'Unpaid' => 'text-warning',
                default => '',
            };
            $rows .= sprintf(
                '<tr>
                    <td><a href="/viewinvoice.php?id=%d">Invoice #%d</a></td>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="%s">%s</td>
                    <td>%s</td>
                </tr>',
                $inv['id'],
                $inv['id'],
                $inv['datecreated'],
                $inv['duedate'],
                $statusClass,
                $inv['status'],
                number_format((float)$inv['total'], 2)
            );
        }

        $creditClass = $summary['credit_balance'] > 0 ? 'text-success' : '';
        $unpaidClass = $summary['unpaid_total'] > 0 ? 'text-danger' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Minhas Faturas - LINUXKAFE</title>
    <link href="/templates/default/css/bootstrap.css" rel="stylesheet">
    <link href="/templates/default/css/whmcs.css" rel="stylesheet">
</head>
<body>
    <div class="whmcscontainer">
        <div class="container">
            <h2>My Invoices</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Credit Balance</div>
                        <div class="panel-body text-center {$creditClass}">
                            <h3>\${$summary['credit_balance']}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Unpaid Invoices</div>
                        <div class="panel-body text-center {$unpaidClass}">
                            <h3>\${$summary['unpaid_total']}</h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="panel panel-default">
                        <div class="panel-heading">Total Paid</div>
                        <div class="panel-body text-center">
                            <h3>\${$summary['paid_total']}</h3>
                        </div>
                    </div>
                </div>
            </div>
            <table class="table table-striped">
                <thead><tr><th>Invoice</th><th>Created</th><th>Due Date</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>{$rows}</tbody>
            </table>
            <p><a href="/clientarea.php" class="btn btn-default">Back</a></p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderPaymentSuccess(string $transactionId): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Pagamento Concluído</title></head>
<body>
    <div class="container">
        <div class="alert alert-success">
            <h3>Pagamento processado com sucesso!</h3>
            <p>Transaction ID: {$transactionId}</p>
        </div>
        <a href="/clientarea.php?action=invoices" class="btn btn-default">Back to Invoices</a>
    </div>
</body>
</html>
HTML;
    }

    private function renderPaymentError(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Erro no Pagamento</title></head>
<body>
    <div class="container">
        <div class="alert alert-danger">
            <h3>Erro no processamento</h3>
            <p>{$message}</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-default">Go Back</a>
    </div>
</body>
</html>
HTML;
    }

    private function errorResponse(Response $response, string $message): Response
    {
        $response->getBody()->write('<html><body><h3>Error: ' . htmlspecialchars($message) . '</h3><a href="/">Home</a></body></html>');
        return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
