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
                'domain' => $this->e($domain['domain']),
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
