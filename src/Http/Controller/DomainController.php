<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\DomainService;

class DomainController
{
    private DomainService $domainService;

    public function __construct(DomainService $domainService)
    {
        $this->domainService = $domainService;
    }

    /**
     * GET /domainchecker.php
     */
    public function checker(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $domain = $queryParams['domain'] ?? '';

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Verificador de Domínio - LINUXKAFE</title></head><body>';
        $html .= '<div class="container"><h2>Verificador de Domínio</h2>';
        $html .= '<form method="get" action="/domainchecker.php">';
        $html .= '<div class="form-group"><label for="domain">Nome do domínio</label>';
        $html .= '<input type="text" name="domain" id="domain" class="form-control" placeholder="ex: meuSite" required></div>';
        $html .= '<button type="submit" class="btn btn-primary">Verificar</button></form>';

        if ($domain) {
            $results = $this->domainService->checkAvailability($domain);
            $html .= $this->renderResults($results);
        }

        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * GET /domain.php
     */
    public function list(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $domains = $this->domainService->getClientDomains($clientId);
        $rows = '';
        foreach ($domains as $d) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td><a href="/domain.php?id=%d">Manage</a></td></tr>',
                htmlspecialchars($d['name']),
                $d['regdate'],
                $d['expirydate'],
                $d['status'],
                $d['id']
            );
        }
        if (empty($domains)) {
            $rows = '<tr><td colspan="5" class="text-center">No domains found</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Meus Domínios - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>Meus Domínios</h2>
        <table class="table table-striped">
            <thead><tr><th>Domain</th><th>Registration</th><th>Expiry</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <p><a href="/domainchecker.php" class="btn btn-primary">Register New Domain</a></p>
        <p><a href="/clientarea.php" class="btn btn-default">Back</a></p>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /domain.php?action=register
     */
    public function register(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $domainName = $data['domain'] ?? '';
        $tld = $data['tld'] ?? '.com';
        $fullDomain = $domainName . $tld;

        [$success, $message, $domainId] = $this->domainService->registerDomain(
            $fullDomain,
            $clientId,
            $data
        );

        if ($success) {
            $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Domínio Registrado</title></head><body>';
            $html .= '<div class="container"><div class="alert alert-success"><h3>Domínio registrado com sucesso!</h3>';
            $html .= '<p>Domain: ' . htmlspecialchars($fullDomain) . '</p>';
            $html .= '<p>Domain ID: ' . $domainId . '</p></div>';
            $html .= '<p><a href="/domain.php" class="btn btn-default">Back to Domains</a></p></div></body></html>';
        } else {
            $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Erro no Registro</title></head><body>';
            $html .= '<div class="container"><div class="alert alert-danger"><h3>Erro</h3><p>' . htmlspecialchars($message) . '</p></div>';
            $html .= '<p><a href="/domainchecker.php" class="btn btn-default">Try Again</a></p></div></body></html>';
        }

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function renderResults(array $results): string
    {
        $rows = '';
        foreach ($results as $r) {
            $badge = $r['available']
                ? '<span class="badge badge-success">Available</span>'
                : '<span class="badge badge-danger">Registered</span>';
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                $r['domain'],
                $r['tld'],
                $badge,
                $r['available'] ? '$' . number_format($r['price'], 2) : '-'
            );
        }

        return <<<HTML
<h3>Results for your search</h3>
<table class="table table-striped">
    <thead><tr><th>Domain</th><th>TLD</th><th>Status</th><th>Price/yr</th></tr></thead>
    <tbody>{$rows}</tbody>
</table>
HTML;
    }
}
