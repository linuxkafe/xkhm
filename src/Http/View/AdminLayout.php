<?php

declare(strict_types=1);

namespace Xkhm\Http\View;

/**
 * The shell of the administration panel.
 *
 * The client area is Flutter; the panel stays in PHP (T025). The panel
 * therefore has its own shell — but it uses the **same design tokens**
 * and the **same stylesheet**, so it follows the system colour scheme
 * and behaves on a phone exactly like the rest of the product.
 *
 * The navigation is a sidebar on a wide screen and a bar that scrolls
 * horizontally on a narrow one: the panel has to be usable from a
 * telephone, because that is where the operator often is.
 */
final class AdminLayout
{
    /**
     * The areas of the panel.
     *
     * @var list<array{key: string, label: string, href: string}>
     */
    public const SECTIONS = [
        ['key' => 'dashboard', 'label' => 'Painel', 'href' => '/admin'],
        ['key' => 'clients', 'label' => 'Clientes', 'href' => '/admin/clients.php'],
        ['key' => 'invoices', 'label' => 'Faturas', 'href' => '/admin/invoices.php'],
        ['key' => 'orders', 'label' => 'Pedidos', 'href' => '/admin/orders.php'],
        ['key' => 'tickets', 'label' => 'Tickets', 'href' => '/admin/tickets.php'],
        ['key' => 'domains', 'label' => 'Domínios', 'href' => '/admin/domains.php'],
        ['key' => 'affiliates', 'label' => 'Afiliados', 'href' => '/admin/affiliates.php'],
        ['key' => 'search', 'label' => 'Pesquisar', 'href' => '/admin/search.php'],
        ['key' => 'config', 'label' => 'Configuração', 'href' => '/admin/config.php'],
    ];

    /**
     * @param string               $title    the page title
     * @param string               $body     the content, already HTML
     * @param string               $section  the key of the current section
     * @param string               $adminName the administrator's name
     * @param array<string, string> $actions the "actions" of the page
     */
    public static function render(
        string $title,
        string $body,
        string $section = 'dashboard',
        string $adminName = '',
        array $actions = []
    ): string {
        $nav = '';
        foreach (self::SECTIONS as $item) {
            $active = $item['key'] === $section ? ' class="active"' : '';
            $nav .= sprintf(
                '<li><a href="%s"%s>%s</a></li>' . "\n",
                htmlspecialchars($item['href'], ENT_QUOTES),
                $active,
                htmlspecialchars($item['label'], ENT_QUOTES)
            );
        }

        $actionsHtml = '';
        foreach ($actions as $label => $href) {
            $actionsHtml .= sprintf(
                '<a class="btn btn-primary" href="%s">%s</a>' . "\n",
                htmlspecialchars($href, ENT_QUOTES),
                htmlspecialchars($label, ENT_QUOTES)
            );
        }

        $titleEsc = htmlspecialchars($title, ENT_QUOTES);
        $adminEsc = htmlspecialchars($adminName, ENT_QUOTES);

        $head = '<link href="/templates/default/css/tokens.css" rel="stylesheet">' . "\n"
            . '<link href="/templates/default/css/whmcs.css" rel="stylesheet">' . "\n";

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#ffffff">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#141414">
<meta name="color-scheme" content="light dark">
<meta name="robots" content="noindex, nofollow">
<title>{$titleEsc} - Administração LINUXKAFE</title>
{$head}</head>
<body class="admin-body">
<header class="admin-header">
    <div class="admin-header-inner">
        <a class="admin-brand" href="/admin">LINUXKAFÉ</a>
        <span class="admin-header-title">Administração</span>
        <div class="admin-header-right">
            <span class="admin-user">{$adminEsc}</span>
            <a class="btn btn-sm" href="/admin/logout.php">Terminar sessão</a>
        </div>
    </div>
    <nav class="admin-nav" aria-label="Secções da administração">
        <ul>
{$nav}        </ul>
    </nav>
</header>
<main class="admin-main">
{$body}
</main>
</body>
</html>
HTML;
    }

    /**
     * The page header: the title, the actions and the messages.
     *
     * @param array<string, string> $actions
     */
    public static function header(string $title, array $actions = []): string
    {
        $actionsHtml = '';
        foreach ($actions as $label => $href) {
            $actionsHtml .= sprintf(
                '<a class="btn btn-primary" href="%s">%s</a>' . "\n",
                htmlspecialchars($href, ENT_QUOTES),
                htmlspecialchars($label, ENT_QUOTES)
            );
        }

        $titleEsc = htmlspecialchars($title, ENT_QUOTES);

        return <<<HTML
        <div class="admin-page-head">
            <h1>{$titleEsc}</h1>
            <div class="admin-actions">
                {$actionsHtml}
            </div>
        </div>
        HTML;
    }
}
