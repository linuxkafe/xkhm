<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\BillingService;

class OrderController
{
    /**
     * GET /clientarea.php?action=orders
     */
    public function list(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        // TODO: Fetch orders from repository
        $rows = '<tr><td colspan="5" class="text-center">No orders found</td></tr>';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Meus Pedidos - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>My Orders</h2>
        <table class="table table-striped">
            <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th><th>Actions</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <a href="/clientarea.php" class="btn btn-default">Back</a>
    </div>
</body>
</html>
HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
