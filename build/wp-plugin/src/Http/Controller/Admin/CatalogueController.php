<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;
use Xkhm\Repository\CatalogueRepositoryInterface;
use Xkhm\Repository\CatalogueRepositoryPDO;

/**
 * The catalogue: the products (the services on sale) and their prices.
 *
 * This is the WHMCS's `configproducts` page: the products, the group
 * they belong to, and the price **per billing cycle** — a cycle the
 * product does not have is `-1.00`, and the form does not offer it.
 */
class CatalogueController extends AdminController
{
    public function __construct(
        \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private CatalogueRepositoryInterface $catalogue
    ) {
        parent::__construct($repository);
    }

    /**
     * GET /admin/products.php
     */
    public function index(Request $request, Response $response): Response
    {
        // Com `action=edit` é o formulário, não a lista: o caminho é o
        // mesmo, e o router não os distingue.
        if ((string)($request->getQueryParams()['action'] ?? '') === 'edit') {
            return $this->edit($request, $response);
        }

        $groups = $this->catalogue->productGroups();
        $groupId = $this->id($request, 'group');

        $products = $this->catalogue->products($groupId);

        $body = AdminLayout::header('Produtos e serviços')
            . $this->notice($request)
            . '<p class="text-muted">'
            . 'O catálogo: o que se vende, a que preço e em que ciclo. '
            . 'É a partir daqui que nasce um serviço.'
            . '</p>'
            . $this->groupFilter($groups, $groupId)
            . $this->productsTable($products)
            . $this->groupsPanel($groups);

        return $this->render($response, 'Produtos e serviços', 'products', $body, $this->adminName());
    }

    /**
     * GET /admin/products.php?action=edit&id=N
     *
     * The form of a product.
     */
    public function edit(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $product = $id !== null ? $this->catalogue->product($id) : null;

        if ($id !== null && $product === null) {
            return $this->notFound($response, 'O produto', $this->adminName());
        }

        $body = AdminLayout::header(
            $product === null ? 'Novo produto' : 'Editar produto',
            ['Produtos' => '/admin/products.php']
        )
            . $this->form($product);

        // Os preços só existem depois de o produto ter uma identidade:
        // são guardados contra o `id` dele.
        if ($product !== null) {
            $body .= $this->pricesForm($product);
        }

        return $this->render(
            $response,
            $product === null ? 'Novo produto' : 'Editar produto',
            'products',
            $body,
            $this->adminName()
        );
    }

    /**
     * POST /admin/products.php
     */
    public function save(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        $id = (int)($data['id'] ?? 0);

        // Remover o produto.
        if ((string)($data['action'] ?? '') === 'delete') {
            return $this->delete($id, $response);
        }

        // Criar um grupo de produtos — não um produto chamado «grupo».
        if ((string)($data['action'] ?? '') === 'group') {
            $this->catalogue->saveProductGroup(
                ['name' => (string)($data['groupname'] ?? '')],
                0
            );

            return $response
                ->withHeader('Location', '/admin/products.php?saved=1')
                ->withStatus(302);
        }

        // Os preços, por ciclo.
        $prices = [];
        foreach (array_keys(CatalogueRepositoryPDO::CYCLES) as $cycle) {
            $raw = trim((string)($data['price_' . $cycle] ?? ''));

            // Um ciclo vazio é um ciclo que o produto **não** tem.
            $prices[$cycle] = $raw === '' ? -1.0 : (float)str_replace(',', '.', $raw);
        }

        $prices['setup'] = (float)str_replace(',', '.', (string)($data['setup'] ?? '0'));

        // O formulário dos preços só muda os preços: o produto fica
        // como está, e não é reescrito com os campos do formulário.
        if ((string)($data['action'] ?? '') === 'prices') {
            $this->catalogue->saveProductPrices($id, $prices);

            return $response
                ->withHeader('Location', '/admin/products.php?action=edit&id=' . $id . '&saved=1')
                ->withStatus(302);
        }

        $productId = $this->catalogue->saveProduct($data, $id);

        $this->catalogue->saveProductPrices($productId, $prices);

        return $response
            ->withHeader('Location', '/admin/products.php?action=edit&id=' . $productId . '&saved=1')
            ->withStatus(302);
    }

    // ─── The parts ───────────────────────────────────────────────────────────

    /**
     * The message of the last operation.
     */
    private function notice(Request $request): string
    {
        $query = $request->getQueryParams();

        if (isset($query['deleted'])) {
            return '<div class="alert alert-ok">O produto foi removido.</div>';
        }

        if (isset($query['error'])) {
            return '<div class="alert alert-bad">'
                . 'O produto não pode ser removido: há serviços que dependem dele. '
                . 'Esconda-o em vez de o remover.'
                . '</div>';
        }

        return '';
    }

    /**
     * Remove a product.
     *
     * A product that a client already has is **not** removed: the
     * service would be left pointing at nothing. The operator is told
     * why, and the product stays.
     */
    private function delete(int $id, Response $response): Response
    {
        if ($id <= 0) {
            return $response
                ->withHeader('Location', '/admin/products.php?error=id')
                ->withStatus(302);
        }

        $services = $this->catalogue->servicesOfProduct($id);

        if ($services > 0) {
            return $response
                ->withHeader('Location', '/admin/products.php?error=inuse')
                ->withStatus(302);
        }

        $this->catalogue->deleteProduct($id);

        return $response
            ->withHeader('Location', '/admin/products.php?deleted=1')
            ->withStatus(302);
    }

    /**
     * @param list<array<string, mixed>> $groups
     */
    private function groupFilter(array $groups, ?int $current): string
    {
        $options = '<option value="">Todos os grupos</option>';

        foreach ($groups as $group) {
            $selected = (int)($group['id'] ?? 0) === $current ? ' selected' : '';
            $options .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int)($group['id'] ?? 0),
                $selected,
                $this->e((string)($group['name'] ?? ''))
            );
        }

        return '<form class="admin-filters" method="get" action="/admin/products.php">'
            . '<select name="group" aria-label="Grupo">' . $options . '</select>'
            . '<button type="submit" class="btn">Filtrar</button>'
            . '</form>';
    }

    /**
     * @param list<array<string, mixed>> $products
     */
    private function productsTable(array $products): string
    {
        $rows = '';

        foreach ($products as $product) {
            $id = (int)($product['id'] ?? 0);
            $prices = $product['prices'] ?? [];

            $cycle = '';
            foreach (CatalogueRepositoryPDO::CYCLES as $column => $label) {
                $price = (float)($prices[$column] ?? -1);

                if ($price >= 0) {
                    $cycle = $label . ': ' . $this->money($price) . ' · ';
                }
            }

            $rows .= sprintf(
                '<tr><td><a href="/admin/products.php?action=edit&amp;id=%d">%s</a></td>'
                . '<td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $id,
                $this->e((string)($product['name'] ?? '')),
                $this->e((string)($product['type'] ?? '')),
                $this->e((string)($product['groupname'] ?? '—')),
                $this->e(rtrim($cycle, ' · ')),
                $this->e((string)($product['servergroup'] ?? '0'))
            );
        }

        if ($rows === '') {
            return '<p class="text-muted">Nenhum produto neste grupo.</p>';
        }

        return '<div class="table-scroll"><table class="table table-striped">'
            . '<thead><tr><th>Produto</th><th>Tipo</th><th>Grupo</th>'
            . '<th>Preços</th><th>Grupo de servidores</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table></div>'
            . '<div class="admin-actions">'
            . '<a class="btn btn-primary" href="/admin/products.php?action=edit">Novo produto</a>'
            . '</div>';
    }

    /**
     * @param array<string, mixed>|null $product
     */
    private function form(?array $product): string
    {
        $groups = $this->catalogue->productGroups();
        $serverGroups = $this->catalogue->serverGroups();

        $groupOptions = '';
        foreach ($groups as $group) {
            $selected = (int)($group['id'] ?? 0) === (int)($product['gid'] ?? 0) ? ' selected' : '';
            $groupOptions .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int)($group['id'] ?? 0),
                $selected,
                $this->e((string)($group['name'] ?? ''))
            );
        }

        $serverOptions = '<option value="0">— nenhum —</option>';
        foreach ($serverGroups as $group) {
            $selected = (int)($group['id'] ?? 0) === (int)($product['servergroup'] ?? 0)
                ? ' selected'
                : '';
            $serverOptions .= sprintf(
                '<option value="%d"%s>%s</option>',
                (int)($group['id'] ?? 0),
                $selected,
                $this->e((string)($group['name'] ?? ''))
            );
        }

        $id = (int)($product['id'] ?? 0);
        $name = $this->e((string)($product['name'] ?? ''));
        $type = $this->e((string)($product['type'] ?? 'hostingaccount'));
        $hidden = $this->e((string)($product['hidden'] ?? ''));

        $body = '<section class="panel"><div class="panel-heading">Produto</div><div class="panel-body">'
            . '<form method="post" action="/admin/products.php" class="admin-form">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="form-group"><label for="product-name">Nome</label>'
            . '<input type="text" id="product-name" name="name" class="form-control"'
            . ' value="' . $name . '" required></div>'
            . '<div class="form-group"><label for="product-type">Tipo</label>'
            . '<select id="product-type" name="type" class="form-control">'
            . '<option value="hostingaccount"' . ($type === 'hostingaccount' ? ' selected' : '') . '>Alojamento web</option>'
            . '<option value="other"' . ($type === 'other' ? ' selected' : '') . '>Outro</option>'
            . '<option value="server"' . ($type === 'server' ? ' selected' : '') . '>Servidor</option>'
            . '</select></div>'
            . '<div class="form-group"><label for="product-group">Grupo</label>'
            . '<select id="product-group" name="gid" class="form-control">' . $groupOptions . '</select></div>'
            . '<div class="form-group"><label for="product-servergroup">Grupo de servidores</label>'
            . '<select id="product-servergroup" name="servergroup" class="form-control">'
            . $serverOptions . '</select></div>'
            . '<div class="form-group"><label for="product-hidden">Oculto</label>'
            . '<select id="product-hidden" name="hidden" class="form-control">'
            . '<option value="">Não</option>'
            . '<option value="on"' . ($hidden === 'on' ? ' selected' : '') . '>Sim</option>'
            . '</select></div>'
            . '<button type="submit" class="btn btn-primary">Guardar o produto</button>'
            . '</form>';

        // A remoção só faz sentido num produto que já existe.
        if ($id > 0) {
            $body .= '<form method="post" action="/admin/products.php" class="admin-form">'
                . '<input type="hidden" name="id" value="' . $id . '">'
                . '<input type="hidden" name="action" value="delete">'
                . '<button type="submit" class="btn btn-danger">Remover o produto</button>'
                . '</form>';
        }

        return $body . '</div></section>';
    }

    /**
     * The prices, by cycle.
     *
     * @param array<string, mixed>|null $product
     */
    private function pricesForm(?array $product): string
    {
        $prices = $product['prices'] ?? [];

        $inputs = '';

        foreach (CatalogueRepositoryPDO::CYCLES as $column => $label) {
            $price = (float)($prices[$column] ?? -1);

            $value = $price < 0 ? '' : number_format($price, 2, '.', '');

            $inputs .= sprintf(
                '<div class="form-group"><label for="price-%s">%s (€)</label>'
                . '<input type="text" id="price-%s" name="price_%s" class="form-control"'
                . ' value="%s" inputmode="decimal" placeholder="sem este ciclo"></div>',
                $this->e($column),
                $this->e($label),
                $this->e($column),
                $this->e($column),
                $this->e($value)
            );
        }

        $setup = (float)($prices['setup'] ?? 0);

        return '<section class="panel"><div class="panel-heading">Preços</div><div class="panel-body">'
            . '<p class="text-muted">O preço de cada ciclo. Um ciclo '
            . '**vazio** é um ciclo que o produto não tem — e que não '
            . 'aparece na encomenda.</p>'
            . '<form method="post" action="/admin/products.php" class="admin-form">'
            . '<input type="hidden" name="action" value="prices">'
            . '<input type="hidden" name="id" value="' . (int)($product['id'] ?? 0) . '">'
            . $inputs
            . '<div class="form-group"><label for="price-setup">Taxa de instalação (€)</label>'
            . '<input type="text" id="price-setup" name="setup" class="form-control"'
            . ' value="' . $this->e(number_format($setup, 2, '.', '')) . '" inputmode="decimal"></div>'
            . '<button type="submit" class="btn btn-primary">Guardar os preços</button>'
            . '</form></div></section>';
    }

    /**
     * The product groups.
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
                (int)($group['products'] ?? 0)
            );
        }

        return '<section class="panel"><div class="panel-heading">Grupos de produtos</div>'
            . '<div class="panel-body">'
            . '<div class="table-scroll"><table class="table table-striped"><thead><tr><th>Grupo</th>'
            . '<th>Produtos</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . '<form method="post" action="/admin/products.php" class="admin-form">'
            . '<input type="hidden" name="action" value="group">'
            . '<div class="form-group"><label for="group-name">Nome do grupo</label>'
            . '<input type="text" id="group-name" name="groupname" class="form-control" required></div>'
            . '<button type="submit" class="btn">Criar o grupo</button>'
            . '</form></div></section>';
    }
}
