<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\Layout;
use Xkhm\Service\DomainService;
use Xkhm\Service\SslService;

class SslController
{
    public function __construct(
        private SslService $service,
        private DomainService $domainService
    ) {
    }

    /**
     * GET /ssl.php
     */
    public function index(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = (int)($session['client_id'] ?? 0);

        if ($clientId === 0) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        // The certificates belong to the domains the client actually owns.
        $domains = $this->domainService->getClientDomains($clientId);

        $html = '<h2>Certificados SSL</h2>';

        if ($domains === []) {
            $html .= '<div class="alert alert-info">Nenhum certificado SSL encontrado.</div>';
        } else {
            $html .= '<table class="table table-striped">';
            $html .= '<thead><tr><th>Domínio</th><th>Válido de</th><th>Expira</th>'
                . '<th>Dias Restantes</th><th>Emissor</th><th>Auto-Renovação</th></tr></thead><tbody>';

            $autoRenew = $this->service->isAutoRenewalEnabled() ? 'Sim' : 'Não';

            foreach ($domains as $domain) {
                $name = (string)($domain['domain'] ?? '');
                $certificate = $this->service->getCertificateInfo($name);

                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td>%s</td><td>%s</td></tr>',
                    htmlspecialchars($name, ENT_QUOTES),
                    htmlspecialchars((string)($certificate['valid_from'] ?? ''), ENT_QUOTES),
                    htmlspecialchars((string)($certificate['valid_to'] ?? ''), ENT_QUOTES),
                    (int)($certificate['days_remaining'] ?? 0),
                    htmlspecialchars((string)($certificate['issuer'] ?? ''), ENT_QUOTES),
                    $autoRenew
                );
            }

            $html .= '</tbody></table>';
        }

        $html .= '<p><a href="/clientarea.php" class="btn btn-default">Voltar</a></p>';

        $response->getBody()->write(Layout::render('Certificados SSL', $html));

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
