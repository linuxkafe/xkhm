<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\ServerStatusService;

class ServerStatusController
{
    private ServerStatusService $service;

    public function __construct(ServerStatusService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /serverstatus.php
     */
    public function index(Request $request, Response $response): Response
    {
        $status = $this->service->getServerStatus();
        $dbStatus = $status['database']['status'] === 'connected' ? 'success' : 'danger';
        $dbMessage = $status['database']['status'] === 'connected' ? 'Connected' : $status['database']['message'];

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Status do Servidor - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>Status do Servidor</h2>
        <table class="table table-striped">
            <tr><td>Tempo de Atividade</td><td>{$status['uptime']}</td></tr>
            <tr><td>Carga do Sistema</td><td>{$status['load']}</td></tr>
            <tr><td>Uso de Memória</td><td>{$status['memory_usage']} MB</td></tr>
            <tr><td>Máximo de Memória</td><td>{$status['memory_peak']} MB</td></tr>
            <tr><td>PHP Versão</td><td>{$status['php_version']}</td></tr>
            <tr><td>Database</td><td class="text-{$dbStatus}">{$dbMessage}</td></tr>
            <tr><td>Última Verificação</td><td>{$status['last_check']}</td></tr>
        </table>
        <p><a href="/" class="btn btn-default">Voltar</a></p>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
