<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\SslService;

class SslController
{
    private SslService $service;

    public function __construct(SslService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /ssl.php
     */
    public function index(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $domains = $this->service->getCertificateInfo('example.com') ? [$this->service->getCertificateInfo('example.com')] : [];

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Certificados SSL - LINUXKAFE</title></head><body>';
        $html .= '<div class="container"><h2>Certificados SSL</h2>';

        if (empty($domains)) {
            $html .= '<div class="alert alert-info">Nenhum certificado SSL encontrado.</div>';
        } else {
            $html .= '<table class="table table-striped">';
            $html .= '<thead><tr><th>Dominio</th><th>Expira</th><th>Dias Restantes</th><th>Auto-Renovação</th></tr></thead>';
            $html .= '<tbody>';
            foreach ($domains as $d) {
                $autoRenew = $d['autorenew'] ? 'Sim' : 'Não';
                $html .= sprintf(
                    '<tr><td>%s</td><td>%s</td><td>%d</td><td>%s</td></tr>',
                    htmlspecialchars($d['name']),
                    $d['expirydate'],
                    $d['days_remaining'],
                    $autoRenew
                );
            }
            $html .= '</tbody></table>';
        }

        $html .= '<p><a href="/clientarea.php" class="btn btn-default">Voltar</a></p>';
        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
