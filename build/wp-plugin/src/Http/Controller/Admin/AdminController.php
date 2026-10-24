<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;
use Xkhm\Repository\AdminPanelRepositoryInterface;

/**
 * The base of the administration panel's controllers.
 *
 * It holds what all of them need: the repository, the escaping, the
 * validation of the values that come from the URL, and the pager.
 *
 * The validation is not decoration. The `id` of a row comes from the
 * URL, and a panel that renders a row's data must not render a
 * different row because of a typo — nor crash with a 500 because of
 * one. An `id` that does not exist is a **404**, and an `id` that is
 * not a number is a **400**.
 */
abstract class AdminController
{
    /** How many rows a page shows. */
    public const PAGE_SIZE = 25;

    /** The largest page the pager will build. */
    private const MAX_PAGE = 100000;

    public function __construct(protected AdminPanelRepositoryInterface $repository)
    {
    }

    // ─── The helpers ────────────────────────────────────────────────────────

    /**
     * The name of the administrator who is signed in.
     */
    protected function adminName(): string
    {
        $name = $_SESSION['admin_name'] ?? '';

        return is_string($name) ? $name : '';
    }

    /**
     * Escape a value for HTML.
     *
     * The value comes from the database, and the database has more
     * than one type for the same thing: a `userid` is an integer, an
     * `amount` is a string, a `date` is a string. The view layer
     * casts, and does not complain.
     */
    protected function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES);
    }

    /**
     * The `id` of the URL, or null when there is none.
     *
     * @return int|null null when the parameter is absent, or when it is
     *                  present and **not** a positive number
     */
    protected function id(Request $request, string $parameter = 'id'): ?int
    {
        $raw = $request->getQueryParams()[$parameter] ?? null;

        if ($raw === null || $raw === '') {
            return null;
        }

        if (!is_scalar($raw)) {
            return null;
        }

        $value = filter_var((string)$raw, FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            return null;
        }

        return $value;
    }

    /**
     * A positive integer, from wherever it came.
     */
    protected function idFrom(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            return null;
        }

        $value = filter_var((string)$raw, FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            return null;
        }

        return $value;
    }

    /**
     * Is the `id` of the URL **present and malformed**?
     *
     * A request with `?id=abc` is not a request for the list: it is a
     * request with a mistake in it. Answering with the list hides the
     * mistake; answering 400 says it out loud.
     */
    protected function hasInvalidId(Request $request, string $parameter = 'id'): bool
    {
        $raw = $request->getQueryParams()[$parameter] ?? null;

        if ($raw === null || $raw === '' || !is_scalar($raw)) {
            return false;
        }

        $value = filter_var((string)$raw, FILTER_VALIDATE_INT);

        return $value === false || $value < 1;
    }

    /**
     * The 400 for a malformed parameter.
     */
    protected function badRequest(Response $response, string $what, string $adminName = ''): Response
    {
        $body = AdminLayout::header('Pedido inválido')
            . '<div class="alert alert-danger">'
            . $this->e($what) . ' não é válido.</div>';

        $response->getBody()->write(
            AdminLayout::render('Pedido inválido', $body, 'dashboard', $adminName)
        );

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('X-Xkhm-Chrome', 'none');
    }

    /**
     * The search term of the URL.
     */
    protected function searchTerm(Request $request): ?string
    {
        $raw = $request->getQueryParams()['q'] ?? null;

        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string)$raw);

        return $value === '' ? null : $value;
    }

    /**
     * The status filter of the URL.
     *
     * Only the statuses the table actually has are accepted: anything
     * else is no filter, not a filter that matches nothing.
     */
    protected function status(Request $request, array $allowed): ?string
    {
        $raw = $request->getQueryParams()['status'] ?? null;

        if (!is_scalar($raw)) {
            return null;
        }

        $value = trim((string)$raw);

        return in_array($value, $allowed, true) ? $value : null;
    }

    /**
     * The page of the URL.
     */
    protected function page(Request $request): int
    {
        $raw = $request->getQueryParams()['page'] ?? null;

        if (!is_scalar($raw)) {
            return 1;
        }

        $value = filter_var((string)$raw, FILTER_VALIDATE_INT);

        if ($value === false || $value < 1) {
            return 1;
        }

        return min($value, self::MAX_PAGE);
    }

    /**
     * The pager.
     *
     * @param array<string, string|int> $query the query of the page, to keep
     */
    protected function pager(int $total, int $page, array $query = []): string
    {
        $pages = (int)ceil($total / self::PAGE_SIZE);

        if ($pages <= 1) {
            return '';
        }

        $link = function (int $target) use ($query): string {
            $query['page'] = $target;

            return '?' . http_build_query($query);
        };

        $html = '<nav class="admin-pager" aria-label="Paginação">';

        if ($page > 1) {
            $html .= sprintf(
                '<a class="btn btn-sm" href="%s">&larr; Anterior</a> ',
                $this->e($link($page - 1))
            );
        }

        $html .= sprintf(
            '<span class="admin-pager-info">Página %d de %d · %d registos</span>',
            $page,
            $pages,
            $total
        );

        if ($page < $pages) {
            $html .= sprintf(
                ' <a class="btn btn-sm" href="%s">Seguinte &rarr;</a>',
                $this->e($link($page + 1))
            );
        }

        $html .= '</nav>';

        return $html;
    }

    /**
     * The filter bar: the search and the status.
     *
     * @param list<string> $statuses
     */
    protected function filters(
        string $action,
        ?string $search,
        ?string $status,
        array $statuses,
        string $label = 'Pesquisar'
    ): string {
        $searchValue = $this->e((string)$search);
        $statusValue = $this->e((string)$status);

        $options = '<option value="">Todos os estados</option>';
        foreach ($statuses as $option) {
            $selected = $option === $status ? ' selected' : '';
            $options .= sprintf(
                '<option value="%s"%s>%s</option>',
                $this->e($option),
                $selected,
                $this->e($option)
            );
        }

        return <<<HTML
        <form class="admin-filters" method="get" action="{$this->e($action)}">
            <input type="search" name="q" value="{$searchValue}" placeholder="{$this->e($label)}" aria-label="{$this->e($label)}">
            <select name="status" aria-label="Estado">
                {$options}
            </select>
            <button type="submit" class="btn">Filtrar</button>
        </form>
        HTML;
    }

    /**
     * A table.
     *
     * @param list<array{label: string, value: string, numeric?: bool}> $columns
     * @param list<array<string, mixed>> $rows
     */
    protected function table(array $columns, array $rows, string $empty = 'Nada a mostrar.'): string
    {
        if ($rows === []) {
            return '<p class="text-muted">' . $this->e($empty) . '</p>';
        }

        $head = '';
        foreach ($columns as $column) {
            $head .= '<th' . ($column['numeric'] ?? false ? ' class="numeric"' : '') . '>'
                . $this->e($column['label']) . '</th>';
        }

        $body = '';
        foreach ($rows as $row) {
            $body .= '<tr>';
            foreach ($columns as $column) {
                $value = $row[$column['value']] ?? '';
                $body .= '<td' . ($column['numeric'] ?? false ? ' class="numeric"' : '') . '>'
                    . (is_string($value) ? $value : $this->e((string)$value))
                    . '</td>';
            }
            $body .= '</tr>';
        }

        return <<<HTML
        <div class="table-scroll">
            <table class="table table-striped">
                <thead><tr>{$head}</tr></thead>
                <tbody>{$body}</tbody>
            </table>
        </div>
        HTML;
    }

    /**
     * A badge with the status of a row.
     */
    protected function badge(string $status): string
    {
        $class = match (strtolower($status)) {
            'paid', 'active', 'completed', 'closed' => 'badge badge-ok',
            'unpaid', 'open', 'pending' => 'badge badge-warn',
            'cancelled' => 'badge badge-bad',
            default => 'badge',
        };

        return sprintf('<span class="%s">%s</span>', $class, $this->e($status));
    }

    /**
     * The reference of a ticket.
     *
     * Some tickets in the production database have no `tid`: the id is
     * then what identifies them.
     */
    protected function ticketReference(array $ticket): string
    {
        $reference = trim((string)($ticket['tid'] ?? ''));

        return $reference !== '' ? $reference : '#' . (int)($ticket['id'] ?? 0);
    }

    /**
     * The number of an invoice.
     *
     * Some invoices in the production database have no number: the id
     * is then what identifies them.
     */
    protected function invoiceNumber(array $invoice): string
    {
        $number = trim((string)($invoice['invoicenum'] ?? ''));

        return $number !== '' ? $number : '#' . (int)($invoice['id'] ?? 0);
    }

    /**
     * A money value.
     */
    protected function money(mixed $value): string
    {
        return number_format((float)$value, 2, ',', ' ') . ' €';
    }

    /**
     * A date, in the pt-BR format.
     */
    protected function date(mixed $value): string
    {
        $text = $value === null ? '' : (string)$value;

        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '—';
        }

        $timestamp = strtotime($text);

        return $timestamp === false ? $this->e($text) : date('d/m/Y', $timestamp);
    }

    /**
     * A datetime, in the pt-BR format.
     */
    protected function datetime(mixed $value): string
    {
        $text = $value === null ? '' : (string)$value;

        if ($text === '' || str_starts_with($text, '0000-00-00')) {
            return '—';
        }

        $timestamp = strtotime($text);

        return $timestamp === false ? $this->e($text) : date('d/m/Y H:i', $timestamp);
    }

    /**
     * Render a page of the panel.
     *
     * @param array<string, string|int> $query
     */
    protected function render(
        Response $response,
        string $title,
        string $section,
        string $body,
        string $adminName = '',
        array $query = []
    ): Response {
        $html = AdminLayout::render($title, $body, $section, $adminName, $query['actions'] ?? []);

        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            // The panel has its own chrome; the public one does not
            // belong above it.
            ->withHeader('X-Xkhm-Chrome', 'none');
    }

    /**
     * A 404 that looks like the rest of the panel.
     */
    protected function notFound(Response $response, string $what, string $adminName = ''): Response
    {
        $body = AdminLayout::header('Não encontrado')
            . '<div class="alert alert-warning">'
            . $this->e($what) . ' não existe.</div>';

        $response->getBody()->write(
            AdminLayout::render('Não encontrado', $body, 'dashboard', $adminName)
        );

        return $response
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('X-Xkhm-Chrome', 'none');
    }
}
