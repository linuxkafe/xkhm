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
}
