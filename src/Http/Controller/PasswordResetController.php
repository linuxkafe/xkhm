<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Repository\ClientRepositoryInterface;

class PasswordResetController
{
    private ClientRepositoryInterface $clientRepository;

    public function __construct(ClientRepositoryInterface $clientRepository)
    {
        $this->clientRepository = $clientRepository;
    }

    /**
     * GET /pwreset.php — Show password reset form
     */
    public function showForm(Request $request, Response $response): Response
    {
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="utf-8">
                <title>Recuperar a password perdida - LINUXKAFE</title>
                <link href="/templates/default/css/bootstrap.css" rel="stylesheet">
                <link href="/templates/default/css/whmcs.css" rel="stylesheet">
            </head>
            <body>
                <div class="whmcscontainer">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6 col-md-offset-3">
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Recuperar a password perdida</h3>
                                    </div>
                                    <div class="panel-body">
                                        <p>Digite o seu endereço de email abaixo e enviaremos instruções para redefinir a sua password.</p>
                                        <form method="post" action="/pwreset.php">
                                            <div class="form-group">
                                                <label>Endereço de Email</label>
                                                <input type="email" name="email" class="form-control" required autofocus>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-block">Enviar Instruções</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            HTML;

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /pwreset.php — Process password reset request
     */
    public function process(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = $data['email'] ?? '';

        if (!$email) {
            $response->getBody()->write('Email required');
            return $response->withStatus(400);
        }

        $client = $this->clientRepository->findByEmail($email);

        // Always return success to prevent email enumeration
        $html = <<<HTML
            <!DOCTYPE html>
            <html lang="pt-BR">
            <head>
                <meta charset="utf-8">
                <title>Instruções enviadas - LINUXKAFE</title>
                <link href="/templates/default/css/bootstrap.css" rel="stylesheet">
                <link href="/templates/default/css/whmcs.css" rel="stylesheet">
            </head>
            <body>
                <div class="whmcscontainer">
                    <div class="container">
                        <div class="row">
                            <div class="col-md-6 col-md-offset-3">
                                <div class="panel panel-default">
                                    <div class="panel-heading">
                                        <h3 class="panel-title">Instruções Enviadas</h3>
                                    </div>
                                    <div class="panel-body">
                                        <div class="alert alert-success">
                                            Se o email existir na nossa base de dados, você receberá instruções para redefinir a sua password.
                                        </div>
                                        <p><a href="/clientarea.php" class="btn btn-default">Voltar ao Login</a></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            HTML;

        // TODO: Actually send email with reset token

        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
