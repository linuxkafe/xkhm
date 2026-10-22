<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;

/**
 * The detail of a **service**.
 *
 * A service is a row of `tblhosting` — the web hosting, the backups,
 * the servers. What tells them apart is the product, and each kind has
 * its own fields: a hosting has a domain and a user, a backup has
 * neither.
 */
class ServiceController extends AdminController
{
    public function __construct(
        \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private \Xkhm\Service\ServiceTermsService $terms
    ) {
        parent::__construct($repository);
    }

    /**
     * GET /admin/services.php?id=N
     */
    public function index(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            if ($this->hasInvalidId($request)) {
                return $this->badRequest($response, 'O serviço', $admin);
            }

            return $this->notFound($response, 'O serviço', $admin);
        }

        $service = $this->repository->findService($id);

        if ($service === null) {
            return $this->notFound($response, 'O serviço', $admin);
        }

        $group = trim((string)($service['groupname'] ?? ''));
        $product = trim((string)($service['productname'] ?? ''));

        $body = AdminLayout::header(
            $product !== '' ? $product : 'Serviço #' . $id,
            ['Clientes' => '/admin/clients.php?id=' . (int)$service['userid']]
        );

        // ── As condicoes ──
        $body .= '<section class="panel"><div class="panel-heading">'
            . 'Preço e prazo de renovação</div><div class="panel-body">';

        $body .= '<dl class="admin-details">';
        foreach ([
            'Preço actual' => $this->money($service['amount'] ?? 0),
            'Ciclo' => $this->e($service['billingcycle'] ?? ''),
            'Próxima renovação' => $this->date($service['nextduedate'] ?? null),
            'Próxima fatura' => $this->date($service['nextinvoicedate'] ?? null),
        ] as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl>';

        $body .= $this->termsForm($service);
        $body .= '</div></section>';

        // ── O que o serviço e ──
        $fields = [
            'Cliente' => sprintf(
                '<a href="/admin/clients.php?id=%d">#%d</a>',
                (int)$service['userid'],
                (int)$service['userid']
            ),
            'Tipo' => $this->e($group !== '' ? $group : (string)($service['producttype'] ?? '')),
            'Estado' => $this->badge((string)($service['domainstatus'] ?? '')),
            'Registo' => $this->date($service['regdate'] ?? null),
            'Valor' => $this->money($service['amount'] ?? 0),
            'Ciclo de faturação' => $this->e($service['billingcycle'] ?? ''),
            'Próxima renovação' => $this->date($service['nextduedate'] ?? null),
            'Próxima fatura' => $this->date($service['nextinvoicedate'] ?? null),
            'Via de pagamento' => $this->e($service['paymentmethod'] ?? ''),
        ];

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        foreach ($fields as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl></div></section>';

        // ── O que o tipo de servico tem ──
        $body .= '<section class="panel"><div class="panel-heading">'
            . $this->e($product !== '' ? $product : 'Alojamento')
            . '</div><div class="panel-body">';

        // Um alojamento tem dominio, utilizador e servidor. Um backup
        // nao tem: mostrar celulas vazias seria pior do que nao as
        // mostrar.
        $specific = [];

        $domain = trim((string)($service['domain'] ?? ''));
        if ($domain !== '') {
            $specific['Domínio'] = $this->e($domain);
        }

        $username = trim((string)($service['username'] ?? ''));
        if ($username !== '') {
            $specific['Utilizador'] = $this->e($username);
        }

        $server = trim((string)($service['server'] ?? ''));
        if ($server !== '') {
            $specific['Servidor'] = $this->e($server);
        }

        $ip = trim((string)($service['dedicatedip'] ?? ''));
        if ($ip !== '') {
            $specific['IP dedicado'] = $this->e($ip);
        }

        $diskLimit = (int)($service['disklimit'] ?? 0);
        if ($diskLimit > 0) {
            $specific['Disco'] = $this->e(
                sprintf(
                    '%d MB de %d MB',
                    (int)($service['diskusage'] ?? 0),
                    $diskLimit
                )
            );
        }

        $bandwidthLimit = (int)($service['bwlimit'] ?? 0);
        if ($bandwidthLimit > 0) {
            $specific['Banda'] = $this->e(
                sprintf(
                    '%d MB de %d MB',
                    (int)($service['bwusage'] ?? 0),
                    $bandwidthLimit
                )
            );
        }

        $nameservers = array_filter([
            trim((string)($service['ns1'] ?? '')),
            trim((string)($service['ns2'] ?? '')),
        ]);

        if ($nameservers !== []) {
            $specific['Servidores de nomes'] = $this->e(implode(', ', $nameservers));
        }

        if ($specific === []) {
            $body .= '<p class="text-muted">Este serviço não tem campos próprios.</p>';
        } else {
            $body .= '<dl class="admin-details">';
            foreach ($specific as $label => $value) {
                $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
            }
            $body .= '</dl>';
        }

        $body .= '</div></section>';

        if (trim((string)($service['notes'] ?? '')) !== '') {
            $body .= '<section class="panel"><div class="panel-heading">Notas</div>'
                . '<div class="panel-body"><p>'
                . nl2br($this->e((string)$service['notes']))
                . '</p></div></section>';
        }

        return $this->render(
            $response,
            $product !== '' ? $product : 'Serviço #' . $id,
            'clients',
            $body,
            $admin
        );
    }

    /**
     * The form that changes the price and the renewal term.
     *
     * @param array<string, mixed> $service
     */
    private function termsForm(array $service): string
    {
        $id = (int)($service['id'] ?? 0);
        $packageId = (int)($service['packageid'] ?? 0);
        $current = (string)($service['billingcycle'] ?? '');

        $cycles = $this->terms->availableCycles($packageId);

        $options = '';
        foreach ($cycles as $cycle => $price) {
            $selected = $cycle === $current ? ' selected' : '';
            $options .= sprintf(
                '<option value="%s"%s>%s — %s</option>',
                $this->e($cycle),
                $selected,
                $this->e($cycle),
                $this->money($price)
            );
        }

        if ($options === '') {
            return '<p class="text-muted">'
                . 'Este produto não tem preços por ciclo: o preço é definido à mão.'
                . '</p>';
        }

        $price = $this->money($service['amount'] ?? 0);

        return <<<HTML
        <form method="post" action="/admin/services/{$id}/terms" class="admin-form">
            <input type="hidden" name="action" value="quote">
            <div class="form-group">
                <label for="service-cycle">Ciclo de renovação</label>
                <select id="service-cycle" name="billingCycle" class="form-control">
                    {$options}
                </select>
            </div>
            <div class="form-group">
                <label for="service-amount">Preço (€) — em branco, o do produto</label>
                <input type="text" id="service-amount" name="amount" class="form-control"
                       inputmode="decimal" placeholder="{$price}">
            </div>
            <button type="submit" class="btn btn-primary">Calcular a alteração</button>
        </form>
        HTML;
    }

    /**
     * The quote of a change, and the action that applies it.
     */
    private function quoteBlock(array $quote): string
    {
        $difference = (float)$quote['difference'];
        $credit = (float)$quote['credit'];

        if ($difference == 0.0) {
            $message = 'A alteração não dá origem a prorrateio.';
        } elseif ($difference < 0) {
            $message = sprintf(
                'A diferença é a favor do cliente: %.2f € viram crédito.',
                $credit
            );
        } else {
            $message = sprintf(
                'A diferença é a favor da empresa: %.2f € serão facturados '
                . 'na próxima renovação.',
                $difference
            );
        }

        $id = (int)($quote['service']['id'] ?? 0);

        return '<div class="alert alert-info">'
            . '<p><strong>Preço:</strong> '
            . $this->money($quote['currentPrice']) . ' (' . $this->e($quote['currentCycle']) . ')'
            . ' → ' . $this->money($quote['newPrice']) . ' (' . $this->e($quote['newCycle']) . ')</p>'
            . '<p><strong>Próxima renovação:</strong> '
            . $this->e($quote['nextDueDate'])
            . ' (' . (int)$quote['daysRemaining'] . ' dias restantes)</p>'
            . '<p>' . $this->e($message) . '</p>'
            . '<form method="post" action="/admin/services/' . $id . '/terms">'
            . '<input type="hidden" name="action" value="apply">'
            . '<input type="hidden" name="billingCycle" value="' . $this->e($quote['newCycle']) . '">'
            . '<input type="hidden" name="amount" value="' . $this->e((string)$quote['newPrice']) . '">'
            . '<button type="submit" class="btn btn-primary">Aplicar a alteração</button>'
            . '</form>'
            . '</div>';
    }

    /**
     * POST /admin/services/{id}/terms
     *
     * The quote, and the change.
     */
    public function terms(Request $request, Response $response, array $args): Response
    {
        $id = (int)($args['id'] ?? 0);
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $cycle = trim((string)($data['billingCycle'] ?? ''));
        $rawAmount = trim((string)($data['amount'] ?? ''));
        $amount = $rawAmount === '' ? null : (float)str_replace(',', '.', $rawAmount);

        $action = (string)($data['action'] ?? 'quote');

        if ($action === 'apply') {
            $applied = $this->terms->apply(
                $id,
                $cycle,
                $amount,
                (int)($_SESSION['admin_id'] ?? 0)
            );

            if ($applied === null) {
                return $response
                    ->withHeader('Location', '/admin/services.php?id=' . $id . '&error=1')
                    ->withStatus(302);
            }

            return $response
                ->withHeader('Location', '/admin/services.php?id=' . $id . '&changed=1')
                ->withStatus(302);
        }

        $quote = $this->terms->quote($id, $cycle, $amount);

        if ($quote === null) {
            return $response
                ->withHeader('Location', '/admin/services.php?id=' . $id . '&error=1')
                ->withStatus(302);
        }

        $service = $this->repository->findService($id);
        $body = AdminLayout::header('Alterar as condições')
            . $this->quoteBlock($quote)
            . '<p><a class="btn" href="/admin/services.php?id=' . $id . '">Voltar</a></p>';

        unset($service);

        $response->getBody()->write(
            AdminLayout::render('Alterar as condições', $body, 'clients', $this->adminName())
        );

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
