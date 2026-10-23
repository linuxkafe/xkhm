<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;

/**
 * The domains and the affiliates.
 *
 * They are grouped because they are the two small tables of the panel:
 * the same list, the same pager, the same shape.
 */
class DomainController extends AdminController
{
    /**
     * GET /admin/domains.php
     */
    public function index(Request $request, Response $response): Response
    {
        // Com `id` na URL é o detalhe, não a lista.
        if ($this->id($request) !== null) {
            return $this->detail($request, $response);
        }

        $search = $this->searchTerm($request);
        $page = $this->page($request);

        $total = $this->repository->countDomains($search);
        $domains = $this->repository->listDomains(
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($domains as $domain) {
            $rows[] = [
                'domain' => sprintf(
                    '<a href="/admin/domains.php?id=%d">%s</a>',
                    (int)$domain['id'],
                    $this->e((string)$domain['domain'])
                ),
                'client' => sprintf(
                    '<a href="/admin/clients.php?id=%d">#%d</a>',
                    (int)$domain['userid'],
                    (int)$domain['userid']
                ),
                'registrar' => $this->e($domain['registrar'] ?? ''),
                'period' => $this->e($domain['period'] ?? ''),
                'expiry' => $this->date($domain['expirydate'] ?? null),
                'amount' => $this->money($domain['recurringamount'] ?? 0),
                'status' => $this->badge((string)$domain['status']),
                'actions' => sprintf(
                    '<a class="btn btn-sm" href="/admin/domains.php?id=%d">Editar</a>',
                    (int)$domain['id']
                ),
            ];
        }

        $body = AdminLayout::header('Domínios')
            . $this->filters('/admin/domains.php', $search, null, [], 'Domínio ou registar')
            . $this->table(
                [
                    ['label' => 'Domínio', 'value' => 'domain'],
                    ['label' => 'Cliente', 'value' => 'client'],
                    ['label' => 'Registar', 'value' => 'registrar'],
                    ['label' => 'Período', 'value' => 'period'],
                    ['label' => 'Expira', 'value' => 'expiry'],
                    ['label' => 'Valor', 'value' => 'amount', 'numeric' => true],
                    ['label' => 'Estado', 'value' => 'status'],
                    ['label' => 'Ações', 'value' => 'actions'],
                ],
                $rows,
                'Nenhum domínio encontrado.'
            )
            . $this->pager($total, $page, array_filter([
                'q' => $search,
            ]));

        return $this->render($response, 'Domínios', 'domains', $body, $this->adminName());
    }

    /**
     * GET /admin/domains.php?id=N
     */
    public function detail(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O domínio', $admin);
        }

        $domain = $this->repository->findDomain($id);

        if ($domain === null) {
            return $this->notFound($response, 'O domínio', $admin);
        }

        $body = AdminLayout::header(
            'Domínio ' . (string)$domain['domain'],
            ['Domínios' => '/admin/domains.php']
        );

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">'
            . '<dl class="admin-details">'
            . '<div><dt>Domínio</dt><dd>' . $this->e((string)$domain['domain']) . '</dd></div>'
            . '<div><dt>Cliente</dt><dd><a href="/admin/clients.php?id=' . (int)$domain['userid'] . '">#' . (int)$domain['userid'] . '</a></dd></div>'
            . '<div><dt>Registar</dt><dd>' . $this->e((string)$domain['registrar']) . '</dd></div>'
            . '<div><dt>Expira</dt><dd>' . $this->date($domain['expirydate'] ?? null) . '</dd></div>'
            . '<div><dt>Estado</dt><dd>' . $this->badge((string)$domain['status']) . '</dd></div>'
            . '</dl></div></section>';

        $body .= '<section class="panel"><div class="panel-heading">Editar</div><div class="panel-body">'
            . '<form method="post" action="/admin/domains.php" class="admin-form">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="form-group"><label for="domain-status">Estado</label>'
            . '<select id="domain-status" name="status" class="form-control">'
            . '<option value="Pending"' . ((string)$domain['status'] === 'Pending' ? ' selected' : '') . '>Pendente</option>'
            . '<option value="Active"' . ((string)$domain['status'] === 'Active' ? ' selected' : '') . '>Activo</option>'
            . '<option value="Expired"' . ((string)$domain['status'] === 'Expired' ? ' selected' : '') . '>Expirado</option>'
            . '<option value="Cancelled"' . ((string)$domain['status'] === 'Cancelled' ? ' selected' : '') . '>Cancelado</option>'
            . '</select></div>'
            . '<div class="form-group"><label for="domain-expiry">Data de expiração</label>'
            . '<input type="date" id="domain-expiry" name="expirydate" class="form-control" value="' . $this->e((string)($domain['expirydate'] ?? '')) . '"></div>'
            . '<button type="submit" class="btn btn-primary">Guardar</button>'
            . '</form></div></section>';

        return $this->render($response, 'Domínio ' . (string)$domain['domain'], 'domains', $body, $admin);
    }

    /**
     * POST /admin/domains.php
     */
    public function update(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $id = (int)($data['id'] ?? 0);

        if ($id <= 0) {
            return $this->badRequest($response, 'O domínio', $this->adminName());
        }

        $domain = $this->repository->findDomain($id);

        if ($domain === null) {
            return $this->notFound($response, 'O domínio', $this->adminName());
        }

        $update = [
            'status' => (string)($data['status'] ?? $domain['status']),
            'expirydate' => (string)($data['expirydate'] ?? $domain['expirydate']),
        ];

        $this->repository->updateDomain($id, $update);

        return $response
            ->withHeader('Location', '/admin/domains.php?id=' . $id . '&saved=1')
            ->withStatus(302);
    }

    /**
     * GET /admin/affiliates.php
     */
    public function affiliates(Request $request, Response $response): Response
    {
        $page = $this->page($request);

        $total = $this->repository->countAffiliates();
        $affiliates = $this->repository->listAffiliates(
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($affiliates as $affiliate) {
            $name = trim(sprintf(
                '%s %s',
                (string)($affiliate['firstname'] ?? ''),
                (string)($affiliate['lastname'] ?? '')
            ));

            $rows[] = [
                'id' => '<a href="/admin/clients.php?id=' . (int)($affiliate['clientid'] ?? 0) . '">#'
                    . (int)($affiliate['id']) . '</a>',
                'name' => $name !== '' ? $this->e($name) : '—',
                'email' => $this->e($affiliate['clientemail'] ?? ''),
                'visitors' => number_format((float)($affiliate['visitors'] ?? 0), 0, ',', ' '),
                'balance' => $this->money($affiliate['balance'] ?? 0),
                'withdrawn' => $this->money($affiliate['withdrawn'] ?? 0),
                'created' => $this->date($affiliate['date'] ?? null),
            ];
        }

        $body = AdminLayout::header('Afiliados')
            . $this->table(
                [
                    ['label' => 'Id', 'value' => 'id'],
                    ['label' => 'Nome', 'value' => 'name'],
                    ['label' => 'Email', 'value' => 'email'],
                    ['label' => 'Visitantes', 'value' => 'visitors', 'numeric' => true],
                    ['label' => 'Saldo', 'value' => 'balance', 'numeric' => true],
                    ['label' => 'Retirado', 'value' => 'withdrawn', 'numeric' => true],
                    ['label' => 'Criado', 'value' => 'created'],
                ],
                $rows,
                'Não há afiliados.'
            )
            . $this->pager($total, $page);

        return $this->render($response, 'Afiliados', 'affiliates', $body, $this->adminName());
    }
}
