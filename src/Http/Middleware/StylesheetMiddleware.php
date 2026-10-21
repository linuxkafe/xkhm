<?php

declare(strict_types=1);

namespace Xkhm\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Guarantees that every HTML response is a fully styled, branded LINUXKAFE page.
 *
 * Historically several pages shipped without a `<link rel="stylesheet">` and
 * therefore rendered unstyled ("sem CSS"), and none of them carried the site
 * header or footer. Rather than trusting every future page to remember the
 * chrome, this middleware enforces the invariant:
 *
 *   - an HTML response always links the LINUXKAFE stylesheets;
 *   - an HTML response always carries the site header and footer, unless it
 *     already declares its own `<header class="site-header">`.
 *
 * JSON, images and other content types pass through untouched.
 */
final class StylesheetMiddleware implements MiddlewareInterface
{
    /**
     * @var list<string>
     */
    private array $stylesheets;

    /**
     * @param list<string> $stylesheets
     */
    public function __construct(array $stylesheets = [])
    {
        $this->stylesheets = $stylesheets !== [] ? $stylesheets : [
            '/templates/default/css/tokens.css',
            '/templates/default/css/whmcs.css',
        ];
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        $contentType = $response->getHeaderLine('Content-Type');
        if (!str_contains($contentType, 'text/html')) {
            return $response;
        }

        // A page that brings its own chrome says so. The administration
        // panel has its own header, its own navigation and its own
        // footer: the public header above it would be a second menu,
        // pointing at the pages of the client area.
        if ($response->getHeaderLine('X-Xkhm-Chrome') === 'none') {
            return $response->withBody(
                (new StreamFactory())->createStream(
                    $this->injectStylesheets($this->injectViewport((string) $response->getBody()))
                )
            );
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return $response;
        }

        $body = $this->injectStylesheets($body);
        $body = $this->injectViewport($body);
        $body = $this->injectChrome($body);

        return $response->withBody((new StreamFactory())->createStream($body));
    }

    private function injectViewport(string $body): string
    {
        if (!str_contains($body, '</head>')) {
            return $body;
        }

        if (stripos($body, 'name="viewport"') !== false || stripos($body, "name='viewport'") !== false) {
            return $body;
        }

        $meta = '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">' . "\n";

        return str_replace('</head>', $meta . '</head>', $body);
    }

    private function injectStylesheets(string $body): string
    {
        if (!str_contains($body, '</head>')) {
            return $body;
        }

        // Add only the stylesheets that are missing — a page may already link
        // some of them (the token sheet in particular is easy to forget).
        $links = '';
        foreach ($this->stylesheets as $href) {
            if (str_contains($body, 'href="' . $href . '"') || str_contains($body, "href='" . $href . "'")) {
                continue;
            }
            $links .= sprintf('<link href="%s" rel="stylesheet">' . "\n", $href);
        }

        if ($links === '') {
            return $body;
        }

        return str_replace('</head>', $links . '</head>', $body);
    }

    private function injectChrome(string $body): string
    {
        if (str_contains($body, 'class="site-header"')) {
            return $body;
        }

        if (!preg_match('/<body[^>]*>/i', $body, $m, PREG_OFFSET_CAPTURE)) {
            return $body;
        }

        $bodyOpen = $m[0][0];
        $offset = $m[0][1] + strlen($bodyOpen);

        $header = self::renderHeader();
        $body = substr($body, 0, $offset) . "\n" . $header . substr($body, $offset);

        if (preg_match('/<\/body>/i', $body, $m2, PREG_OFFSET_CAPTURE)) {
            $footerOffset = $m2[0][1];
            $body = substr($body, 0, $footerOffset) . self::renderFooter() . substr($body, $footerOffset);
        }

        return $body;
    }

    private static function renderHeader(): string
    {
        $loggedIn = isset($_SESSION['client_id']) || isset($_SESSION['admin_id']);

        $links = [
            ['Área do Cliente', '/clientarea.php'],
            ['Procurar Domínio', '/domainchecker.php'],
            ['Anúncios', '/announcements.php'],
            ['Base de Conhecimento', '/knowledgebase.php'],
            ['Suporte', '/supporttickets.php'],
            ['Status do Servidor', '/serverstatus.php'],
        ];

        if ($loggedIn) {
            $links[] = ['Terminar Sessão', '/logout.php'];
        } else {
            $links[] = ['Iniciar Sessão', '/clientarea.php'];
        }

        $items = '';
        foreach ($links as [$label, $href]) {
            $items .= sprintf(
                '<li><a href="%s">%s</a></li>' . "\n",
                htmlspecialchars($href, ENT_QUOTES),
                htmlspecialchars($label, ENT_QUOTES)
            );
        }

        return <<<HTML
<header class="site-header">
    <div class="header-container">
        <a class="site-brand" href="/">LINUXKAFÉ</a>
        <nav class="main-navigation">
            <ul>
{$items}            </ul>
        </nav>
    </div>
</header>
HTML;
    }

    private static function renderFooter(): string
    {
        return <<<'HTML'
<footer>
    <p>LINUXKAFÉ &mdash; Servidores, Software Livre e Soberania Digital</p>
</footer>
HTML;
    }
}
