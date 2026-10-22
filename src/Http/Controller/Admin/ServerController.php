<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;
use Xkhm\Repository\CatalogueRepositoryInterface;

/**
 * The servers, and the groups they belong to.
 *
 * This is the WHMCS's `configservers` page: the máquinas onde os
 * serviços são alojados, e os grupos que os produtos referenciam.
 *
 * A palavra de passe de servidor é uma **credencial**: o painel mostra-a
 * apenas a quem está com a sessão de administrador, e nunca na página
 * da lista.
 */
class ServerController extends AdminController
{
    public function __construct(
        \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private CatalogueRepositoryInterface $catalogue
    ) {
        parent::__construct($repository);
    }

    /**
     * GET /admin/servers.php
     */
    public function index(Request $request, Response $response): Response
    {
        // Com `action=edit` é o formulário, não a lista: o caminho é o
        // mesmo, e o router não os distingue.
        if ((string)($request->getQueryParams()['action'] ?? '') === 'edit') {
            return $this->edit($request, $response);
        }

        $servers = $this->catalogue->servers();
        $groups = $this->catalogue->serverGroups();

        $body = AdminLayout::header('Servidores')
            . $this->notice($request)
            . '<p class="text-muted">'
            . 'As máquinas onde os serviços são alojados, e os grupos que os '
            . 'produtos referenciam.'
            . '</p>'
            . $this->serversTable($servers)
            . $this->groupsPanel($groups);

        return $this->render($response, 'Servidores', 'servers', $body, $this->adminName());
    }

    /**
     * GET /admin/servers.php?action=edit&id=N
     */
    public function edit(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $server = $id !== null ? $this->catalogue->server($id) : null;

        if ($id !== null && $server === null) {
            return $this->notFound($response, 'O servidor', $this->adminName());
        }

        $body = AdminLayout::header(
            $server === null ? 'Novo servidor' : 'Editar servidor',
            ['Servidores' => '/admin/servers.php']
        )
            . $this->form($server)
            . $this->membershipPanel($server);

        return $this->render(
            $response,
            $server === null ? 'Novo servidor' : 'Editar servidor',
            'servers',
            $body,
            $this->adminName()
        );
    }

    /**
     * POST /admin/servers.php
     */
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $id = (int)($data['id'] ?? 0);

        // Remover o servidor.
        if ((string)($data['action'] ?? '') === 'delete') {
            $this->catalogue->deleteServer($id);

            return $response
                ->withHeader('Location', '/admin/servers.php?deleted=1')
                ->withStatus(302);
        }

        // Criar um grupo, ou mudar a pertenca de um servidor a um.
        if ((string)($data['action'] ?? '') === 'group') {
            $this->catalogue->saveServerGroup(
                ['name' => (string)($data['groupname'] ?? '')],
                0
            );

            return $response
                ->withHeader('Location', '/admin/servers.php?saved=1')
                ->withStatus(302);
        }

        if (isset($data['groupid'])) {
            $this->catalogue->setServerInGroup(
                (int)$data['groupid'],
                $id,
                (string)($data['member'] ?? '') === '1'
            );

            return $response
                ->withHeader('Location', '/admin/servers.php?action=edit&id=' . $id)
                ->withStatus(302);
        }

        $this->catalogue->saveServer($data, $id);

        return $response
            ->withHeader('Location', '/admin/servers.php?saved=1')
            ->withStatus(302);
    }

    // ─── The parts ───────────────────────────────────────────────────────────

    /**
     * @param list<array<string, mixed>> $servers
     */
    private function serversTable(array $servers): string
    {
        $groups = [];

        foreach ($this->catalogue->serverGroups() as $group) {
            $groups[(int)($group['id'] ?? 0)] = (string)($group['name'] ?? '');
        }

        $rows = '';

        foreach ($servers as $server) {
            $names = [];
            foreach ($server['groups'] ?? [] as $groupId) {
                $names[] = $groups[(int)$groupId] ?? ('#' . (int)$groupId);
            }

            $rows .= sprintf(
                '<tr><td><a href="/admin/servers.php?action=edit&amp;id=%d">%s</a></td>'
                . '<td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                (int)($server['id'] ?? 0),
                $this->e((string)($server['name'] ?? '')),
                $this->e((string)($server['hostname'] ?? '')),
                $this->e((string)($server['ipaddress'] ?? '')),
                $this->money($server['monthlycost'] ?? 0),
                $this->e(implode(', ', $names) ?: '—'),
                (int)($server['disabled'] ?? 0) === 1
                    ? '<span class="badge badge-bad">Desativado</span>'
                    : '<span class="badge badge-ok">Ativo</span>'
            );
        }

        if ($rows === '') {
            return '<p class="text-muted">Não há servidores.</p>';
        }

        return '<div class="table-scroll"><table class="table table-striped">'
            . '<thead><tr><th>Servidor</th><th>Hostname</th><th>IP</th>'
            . '<th>Custo mensal</th><th>Grupos</th><th>Estado</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '<div class="admin-actions">'
            . '<a class="btn btn-primary" href="/admin/servers.php?action=edit">Novo servidor</a>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $server
     */
    private function form(?array $server): string
    {
        $id = (int)($server['id'] ?? 0);

        $field = function (string $name, string $label, string $value, string $type = 'text') {
            return sprintf(
                '<div class="form-group"><label for="server-%s">%s</label>'
                . '<input type="%s" id="server-%s" name="%s" class="form-control" value="%s"></div>',
                $this->e($name),
                $this->e($label),
                $this->e($type),
                $this->e($name),
                $this->e($name),
                $this->e($value)
            );
        };

        $inputs = $field('name', 'Nome', (string)($server['name'] ?? ''))
            . $field('hostname', 'Hostname', (string)($server['hostname'] ?? ''))
            . $field('ipaddress', 'Endereço IP', (string)($server['ipaddress'] ?? ''))
            . $field('username', 'Utilizador', (string)($server['username'] ?? ''))
            . $field('password', 'Palavra-passe', (string)($server['password'] ?? ''), 'password')
            . $field('statusaddress', 'Endereço de estado', (string)($server['statusaddress'] ?? ''))
            . $field('nameserver1', 'Servidor de nomes 1', (string)($server['nameserver1'] ?? ''))
            . $field('nameserver2', 'Servidor de nomes 2', (string)($server['nameserver2'] ?? ''))
            . $field('monthlycost', 'Custo mensal (€)', number_format((float)($server['monthlycost'] ?? 0), 2, '.', ''))
            . $field('maxaccounts', 'Contas máximas', (string)($server['maxaccounts'] ?? '0'));

        $body = '<section class="panel"><div class="panel-heading">Servidor</div><div class="panel-body">'
            . '<form method="post" action="/admin/servers.php" class="admin-form">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . $inputs
            . '<button type="submit" class="btn btn-primary">Guardar o servidor</button>'
            . '</form>';

        // A remoção só faz sentido num servidor que já existe.
        if ($id > 0) {
            $body .= '<form method="post" action="/admin/servers.php" class="admin-form">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="action" value="delete">'
                . '<button type="submit" class="btn btn-danger">Remover o servidor</button>'
                . '</form>';
        }

        return $body . '</div></section>';
    }

    /**
     * The message of the last operation.
     */
    private function notice(Request $request): string
    {
        if (isset($request->getQueryParams()['deleted'])) {
            return '<div class="alert alert-ok">O servidor foi removido.</div>';
        }

        return '';
    }

    /**
     * The server groups: the list, and the form to create one.
     *
     * @param list<array<string, mixed>> $groups
     */
    private function groupsPanel(array $groups): string
    {
        $rows = '';

        foreach ($groups as $group) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%d</td></tr>',
                $this->e((string)($group['name'] ?? '')),
                (int)($group['servers'] ?? 0)
            );
        }

        return '<section class="panel"><div class="panel-heading">Grupos de servidores</div>'
            . '<div class="panel-body">'
            . '<table class="table table-striped"><thead><tr><th>Grupo</th>'
            . '<th>Servidores</th></tr></thead><tbody>' . $rows . '</tbody></table>'
            . '<form method="post" action="/admin/servers.php" class="admin-form">'
            . '<input type="hidden" name="action" value="group">'
            . '<div class="form-group"><label for="servergroup-name">Nome do grupo</label>'
            . '<input type="text" id="servergroup-name" name="groupname" class="form-control" required></div>'
            . '<button type="submit" class="btn">Criar o grupo</button>'
            . '</form></div></section>';
    }

    /**
     * The groups the server belongs to.
     *
     * @param array<string, mixed>|null $server
     */
    private function membershipPanel(?array $server): string
    {
        $id = (int)($server['id'] ?? 0);
        $current = $server['groups'] ?? [];

        $rows = '';

        foreach ($this->catalogue->serverGroups() as $group) {
            $groupId = (int)($group['id'] ?? 0);
            $member = in_array($groupId, array_map('intval', $current), true);

            $rows .= sprintf(
                '<tr><td>%s</td><td>'
                . '<form method="post" action="/admin/servers.php" class="admin-form">'
                . '<input type="hidden" name="id" value="%d">'
                . '<input type="hidden" name="groupid" value="%d">'
                . '<input type="hidden" name="member" value="%s">'
                . '<button type="submit" class="btn btn-sm">%s</button>'
                . '</form></td></tr>',
                $this->e((string)($group['name'] ?? '')),
                $id,
                $groupId,
                $member ? '0' : '1',
                $member ? 'Retirar' : 'Adicionar'
            );
        }

        return '<section class="panel"><div class="panel-heading">Grupos</div><div class="panel-body">'
            . '<table class="table table-striped"><thead><tr><th>Grupo</th><th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<form method="post" action="/admin/servers.php" class="admin-form">'
            . '<input type="hidden" name="action" value="group">'
            . '<div class="form-group"><label for="servergroup-name">Nome do grupo</label>'
            . '<input type="text" id="servergroup-name" name="groupname" class="form-control" required></div>'
            . '<button type="submit" class="btn">Criar o grupo</button>'
            . '</form></div></section>';
    }
}
