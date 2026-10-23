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
        ['key' => 'products', 'label' => 'Produtos', 'href' => '/admin/products.php'],
        ['key' => 'servers', 'label' => 'Servidores', 'href' => '/admin/servers.php'],
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
            $label = \Xkhm\Service\Lang::t($item['key']);
            $nav .= sprintf(
                '<li><a href="%s"%s>%s</a></li>' . "\n",
                htmlspecialchars($item['href'], ENT_QUOTES),
                $active,
                htmlspecialchars($label, ENT_QUOTES)
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
        // The language, from the query or the cookie.
        if (isset($_GET['lang'])) {
            \Xkhm\Service\Lang::set((string)$_GET['lang']);
        }

        $adminEsc = htmlspecialchars($adminName, ENT_QUOTES);

        $langCode = isset($_GET['lang']) ? (string)$_GET['lang'] : 'pt';
        $langCode = in_array($langCode, ['pt', 'en'], true) ? $langCode : 'pt';
        \Xkhm\Service\Lang::set($langCode);
        $langCodeUpper = strtoupper($langCode);
        $langAdmin = htmlspecialchars(\Xkhm\Service\Lang::t('admin'), ENT_QUOTES);
        $langLogout = htmlspecialchars(\Xkhm\Service\Lang::t('logout'), ENT_QUOTES);

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
        <a class="admin-brand" href="/admin">
            <img src="/templates/default/images/linuxkafe.svg" alt="LINUXKAFE" height="32" width="110">
        </a>
        <span class="admin-header-title">{$langAdmin}</span>
        <div class="admin-header-right">
        <button id="admin-theme-toggle" class="admin-nav-toggle" aria-label="Tema" title="Tema">◐</button>
        <button id="admin-lang-toggle" class="admin-nav-toggle" aria-label="Idioma" title="Idioma">{$langCodeUpper}</button>
        <span class="admin-user">{$adminEsc}</span>
        <a class="btn btn-sm admin-logout" href="/admin/logout.php">{$langLogout}</a>
        <button id="admin-nav-toggle" class="admin-nav-toggle" aria-label="Menu">☰</button>
    </div>
    </div>
</header>
<div class="admin-shell">
    <aside id="admin-nav" class="admin-sidebar" aria-label="Secções da administração">
        <nav class="admin-nav">
            <ul>
{$nav}            </ul>
        </nav>
        <div class="admin-nav-footer">
            <a class="btn btn-sm" href="/admin/logout.php">{$langLogout}</a>
        </div>
    </aside>
    <main class="admin-main">
{$body}
    </main>
</div>
<script>
(function(){
  // Pesquisa automatica: os campos de pesquisa submetem o formulario
  // 300 ms depois de a tecla ser solta, sem precisar de Enter.
  const inputs = document.querySelectorAll('input[name="q"], input[name="search"]');
  inputs.forEach(function(input){
    let timer;
    input.addEventListener('input', function(){
      clearTimeout(timer);
      timer = setTimeout(function(){
        const form = input.closest('form');
        if (form) form.submit();
      }, 300);
    });
  });
})();
const toggle = document.getElementById('admin-nav-toggle');
const nav = document.getElementById('admin-nav');
if (toggle && nav) {
    toggle.addEventListener('click', function(){
        nav.classList.toggle('open');
    });
}

// Tema: claro/escuro manual.
const themeToggle = document.getElementById('admin-theme-toggle');
if (themeToggle) {
    const stored = localStorage.getItem('theme');
    if (stored) {
        document.documentElement.setAttribute('data-theme', stored);
    }
    themeToggle.addEventListener('click', function(){
        const current = document.documentElement.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
    });
}

// Idioma: alternancia PT/EN.
const langToggle = document.getElementById('admin-lang-toggle');
if (langToggle) {
    langToggle.addEventListener('click', function(){
        const current = document.documentElement.lang || 'pt';
        const next = current === 'pt' ? 'en' : 'pt';
        // Recarrega com o parametro de idioma.
        const url = new URL(window.location.href);
        url.searchParams.set('lang', next);
        window.location.href = url.toString();
    });
}
</script>
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
