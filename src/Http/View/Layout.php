<?php

declare(strict_types=1);

namespace Xkhm\Http\View;

/**
 * Shared page shell for every server-rendered page.
 *
 * Single source of truth for:
 *  - the stylesheet links (the "sem CSS" defect: several pages shipped no
 *    stylesheet link at all, so they rendered unstyled),
 *  - the LINUXKAFE header / navigation / footer,
 *  - the pt-BR document metadata.
 */
final class Layout
{
    public const STYLESHEETS = [
        '/templates/default/css/tokens.css',
        '/templates/default/css/bootstrap.css',
        '/templates/default/css/whmcs.css',
    ];

    /**
     * Primary navigation entries shown in the header.
     *
     * @var list<array{label: string, href: string}>
     */
    public const NAV = [
        ['label' => 'Área do Cliente', 'href' => '/clientarea.php'],
        ['label' => 'Procurar Domínio', 'href' => '/domainchecker.php'],
        ['label' => 'Anúncios', 'href' => '/announcements.php'],
        ['label' => 'Base de Conhecimento', 'href' => '/knowledgebase.php'],
        ['label' => 'Suporte', 'href' => '/supporttickets.php'],
        ['label' => 'Status do Servidor', 'href' => '/serverstatus.php'],
    ];

    public static function render(string $title, string $body, string $extraHead = ''): string
    {
        $head = '';
        foreach (self::STYLESHEETS as $href) {
            $head .= sprintf('<link href="%s" rel="stylesheet">' . "\n", $href);
        }
        if ($extraHead !== '') {
            $head .= $extraHead . "\n";
        }

        $nav = '';
        foreach (self::NAV as $item) {
            $nav .= sprintf(
                '<li><a href="%s">%s</a></li>' . "\n",
                htmlspecialchars($item['href'], ENT_QUOTES),
                htmlspecialchars($item['label'], ENT_QUOTES)
            );
        }

        $titleEsc = htmlspecialchars($title, ENT_QUOTES);

        return <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titleEsc} - LINUXKAFE</title>
{$head}</head>
<body>
<header class="site-header">
    <div class="header-container">
        <a class="site-brand" href="/">LINUXKAFÉ</a>
        <nav class="main-navigation">
            <ul>
{$nav}            </ul>
        </nav>
    </div>
</header>
<main class="container">
{$body}
</main>
<footer>
    <p>LINUXKAFÉ &mdash; Servidores, Software Livre e Soberania Digital</p>
</footer>
</body>
</html>
HTML;
    }
}
