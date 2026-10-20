<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\AffiliateService;

class AffiliateController
{
    private AffiliateService $service;

    public function __construct(AffiliateService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /affiliates.php
     */
    public function index(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $affiliate = $this->service->getAffiliateByRefCode($session['affiliate_code'] ?? '');

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>Afiliados - LINUXKAFE</title></head><body>';
        $html .= '<div class="container">';
        $html .= '<h2>Programa de Afiliados</h2>';

        if ($affiliate) {
            $html .= $this->renderDashboard($affiliate, $clientId);
        } else {
            $html .= $this->renderSignup();
        }

        $html .= '</div></body></html>';
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /affiliates.php?action=signup
     */
    public function signup(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $affiliateId = $this->service->register(
            $clientId,
            $data['name'] ?? '',
            $data['email'] ?? '',
            (float)($data['commission'] ?? 10)
        );

        if ($affiliateId) {
            $response = $response->withHeader('Location', '/affiliates.php')->withStatus(302);
        } else {
            $response->getBody()->write('<div class="alert alert-warning">Você já é um afiliado!</div><a href="/affiliates.php" class="btn btn-default">Voltar</a>');
        }

        return $response;
    }

    private function renderDashboard(array $affiliate, int $clientId): string
    {
        $referrals = $this->service->getReferrals((int)$affiliate['id']);
        $earnings = $this->service->getEarnings((int)$affiliate['id']);
        $link = $this->service->getAffiliateLink((int)$affiliate['id']);

        $referralRows = '';
        foreach (array_slice($referrals, 0, 10) as $ref) {
            $referralRows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>$%.2f</td></tr>',
                $ref['date'] ?? 'N/A',
                $ref['clientname'] ?? 'Cliente',
                (float)($ref['commission'] ?? 0)
            );
        }
        if (empty($referrals)) {
            $referralRows = '<tr><td colspan="3" class="text-center">Nenhuma indicação ainda</td></tr>';
        }

        $count = $affiliate['count'] ?? count($referrals);
        $balance = $affiliate['balance'] ?? 0;
        $unpaid = $affiliate['unpaidbalance'] ?? 0;

        return <<<HTML
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">Total Ganho</div>
            <div class="panel-body text-center">
                <h3>\\${$earnings}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">Indicações</div>
            <div class="panel-body text-center">
                <h3>{$count}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">Saldo Disponível</div>
            <div class="panel-body text-center">
                <h3>\\${$balance}</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">Saldo Não Pago</div>
            <div class="panel-body text-center">
                <h3>\\${$unpaid}</h3>
            </div>
        </div>
    </div>
</div>

<div class="panel panel-default">
    <div class="panel-heading">Seu Link de Afiliado</div>
    <div class="panel-body">
        <input type="text" class="form-control" value="{$link}" readonly>
        <p class="text-muted">Compartilhe este link para ganhar comissão por cada indicação</p>
    </div>
</div>

<h3>Suas Indicações</h3>
<table class="table table-striped">
    <thead><tr><th>Data</th><th>Cliente</th><th>Comissão</th></tr></thead>
    <tbody>{$referralRows}</tbody>
</table>

<h3>Solicitar Pagamento</h3>
<button class="btn btn-success">Solicitar Saque</button>
HTML;
    }

    private function renderSignup(): string
    {
        return <<<HTML
<div class="alert alert-info">
    <h3>Participe do nosso programa de afiliados!</h3>
    <p>Ganhe comissão por cada cliente que você indicar. Quanto mais indicações, mais ganhos!</p>
</div>

<form method="post" action="/affiliates.php">
    <div class="form-group">
        <label>Nome para exibição</label>
        <input type="text" name="name" class="form-control" required>
    </div>
    <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" class="form-control" required>
    </div>
    <div class="form-group">
        <label>Taxa de Comissão (%)</label>
        <input type="number" name="commission" class="form-control" value="10" min="1" max="50">
    </div>
    <button type="submit" class="btn btn-primary">Cadastrar como Afiliado</button>
</form>
HTML;
    }
}
