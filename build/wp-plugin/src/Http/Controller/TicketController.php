<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Service\TicketService;

class TicketController
{
    private TicketService $ticketService;

    public function __construct(TicketService $ticketService)
    {
        $this->ticketService = $ticketService;
    }

    /**
     * GET /supporttickets.php
     */
    public function list(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $tickets = $this->ticketService->getClientTickets($clientId);
        $rows = '';
        foreach ($tickets as $t) {
            $statusLabel = $this->ticketService->getStatusLabel($t['status']);
            $priorityLabel = $this->ticketService->getPriorityLabel($t['priority']);
            $rows .= sprintf(
                '<tr>
                    <td><a href="/supporttickets.php?do=ticket&id=%d">%s</a></td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                $t['id'],
                htmlspecialchars($t['subject']),
                $statusLabel,
                $priorityLabel,
                $t['datecreated'],
                $t['lastreply'] ?? 'N/A'
            );
        }
        if (empty($tickets)) {
            $rows = '<tr><td colspan="5" class="text-center">Nenhum ticket encontrado</td></tr>';
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Meus Tickets - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>Meus Tickets</h2>
        <table class="table table-striped">
            <thead><tr><th>Assunto</th><th>Status</th><th>Prioridade</th><th>Data</th><th>Última Resposta</th></tr></thead>
            <tbody>{$rows}</tbody>
        </table>
        <p><a href="/supporttickets.php?do=new" class="btn btn-primary">Criar Novo Ticket</a></p>
        <p><a href="/clientarea.php" class="btn btn-default">Voltar</a></p>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * GET /supporttickets.php?do=new
     */
    public function new(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $departments = $this->ticketService->getDepartments();
        $priorities = $this->ticketService->getPriorities();

        $deptOptions = '';
        foreach ($departments as $d) {
            $deptOptions .= sprintf('<option value="%d">%s</option>', $d['id'], $d['name']);
        }

        $priorityOptions = '';
        foreach ($priorities as $p) {
            $priorityOptions .= sprintf('<option value="%s">%s</option>', $p['value'], $p['label']);
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>Criar Ticket - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>Criar Novo Ticket</h2>
        <form method="post" action="/supporttickets.php">
            <div class="form-group">
                <label>Departamento</label>
                <select name="departmentid" class="form-control">{$deptOptions}</select>
            </div>
            <div class="form-group">
                <label>Assunto</label>
                <input type="text" name="subject" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Prioridade</label>
                <select name="priority" class="form-control">{$priorityOptions}</select>
            </div>
            <div class="form-group">
                <label>Mensagem</label>
                <textarea name="message" class="form-control" rows="6" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Ticket</button>
            <a href="/supporttickets.php" class="btn btn-default">Cancelar</a>
        </form>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /supporttickets.php
     */
    public function create(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $data['userid'] = $clientId;

        $ticketId = $this->ticketService->createTicket($data);

        if ($ticketId) {
            $response = $response->withHeader('Location', '/supporttickets.php')->withStatus(302);
        } else {
            $response->getBody()->write('<html><body><h3>Error creating ticket</h3><a href="/supporttickets.php?do=new">Try again</a></body></html>');
        }

        return $response;
    }

    /**
     * GET /supporttickets.php?do=ticket&id=123
     */
    public function view(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $ticketId = (int)($queryParams['id'] ?? 0);

        if (!$ticketId) {
            return $this->errorResponse($response, 'Invalid ticket ID');
        }

        $ticket = $this->ticketService->getTicketWithReplies($ticketId);
        if (!$ticket || $ticket['userid'] !== $clientId) {
            return $this->errorResponse($response, 'Ticket not found');
        }

        $statusLabel = $this->ticketService->getStatusLabel($ticket['status']);
        $priorityLabel = $this->ticketService->getPriorityLabel($ticket['priority']);

        // Render replies
        $repliesHtml = '';
        foreach (($ticket['replies'] ?? []) as $reply) {
            $author = $reply['adminreply'] == 1 ? 'Staff' : ($reply['name'] ?? 'Client');
            $repliesHtml .= sprintf(
                '<div class="panel panel-default"><div class="panel-heading">%s - %s</div><div class="panel-body">%s</div></div>',
                htmlspecialchars($author),
                $reply['datecreated'],
                nl2br(htmlspecialchars($reply['message']))
            );
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="utf-8"><title>{$ticket['subject']} - LINUXKAFE</title></head>
<body>
    <div class="container">
        <h2>{$ticket['subject']}</h2>
        <table class="table">
            <tr><td><strong>Status:</strong></td><td>{$statusLabel}</td></tr>
            <tr><td><strong>Prioridade:</strong></td><td>{$priorityLabel}</td></tr>
            <tr><td><strong>Criado em:</strong></td><td>{$ticket['datecreated']}</td></tr>
        </table>
        <h3>Mensagem Original</h3>
        <div class="panel panel-default">
            <div class="panel-body">{$ticket['message']}</div>
        </div>
        <h3>Respostas ({$ticket['replycount']})</h3>
        {$repliesHtml}
        <h3>Responder</h3>
        <form method="post" action="/supporttickets.php">
            <input type="hidden" name="ticketid" value="{$ticketId}">
            <div class="form-group">
                <textarea name="message" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Enviar Resposta</button>
        </form>
        <p><a href="/supporttickets.php" class="btn btn-default">Voltar para Tickets</a></p>
    </div>
</body>
</html>
HTML;
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * POST /supporttickets.php?action=reply
     */
    public function reply(Request $request, Response $response): Response
    {
        $session = $request->getAttribute('session');
        $clientId = $session['client_id'] ?? 0;

        if (!$clientId) {
            return $response->withHeader('Location', '/clientarea.php')->withStatus(302);
        }

        $data = $request->getParsedBody();
        $ticketId = (int)($data['ticketid'] ?? 0);

        if (!$ticketId) {
            return $this->errorResponse($response, 'Invalid ticket ID');
        }

        $replyId = $this->ticketService->addReply($ticketId, $data + ['userid' => $clientId]);

        if ($replyId) {
            $response = $response->withHeader('Location', '/supporttickets.php?do=ticket&id=' . $ticketId)->withStatus(302);
        } else {
            $response = $this->errorResponse($response, 'Failed to add reply');
        }

        return $response;
    }

    private function errorResponse(Response $response, string $message): Response
    {
        $response->getBody()->write('<html><body><h3>Error: ' . htmlspecialchars($message) . '</h3><a href="/supporttickets.php">Back</a></body></html>');
        return $response->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
