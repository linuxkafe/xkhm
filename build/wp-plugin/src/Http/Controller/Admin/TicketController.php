<?php

declare(strict_types=1);

namespace Xkhm\Http\Controller\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Xkhm\Http\View\AdminLayout;
use Xkhm\Service\TicketService;

/**
 * The tickets: the list, the detail, and the reply.
 *
 * This is the one place where the panel **writes**. The write goes
 * through {@see TicketService}, the same path the client area uses —
 * the panel does not touch the tables directly.
 */
class TicketController extends AdminController
{
    private const STATUSES = ['Open', 'Pending', 'Answered', 'Closed', 'In Progress'];

    public function __construct(
        \Xkhm\Repository\AdminPanelRepositoryInterface $repository,
        private TicketService $ticketService
    ) {
        parent::__construct($repository);
    }

    /**
     * GET /admin/tickets.php
     */
    public function index(Request $request, Response $response): Response
    {
        // With an `id` in the URL it is the detail, not the list. The
        // router cannot tell them apart: the path is the same.
        if ($this->id($request) !== null) {
            return $this->detail($request, $response);
        }

        // An `id` that is present and malformed is a mistake in the
        // request, not a request for the list.
        if ($this->hasInvalidId($request)) {
            return $this->badRequest($response, 'O ticket', $this->adminName());
        }

        $search = $this->searchTerm($request);
        $status = $this->status($request, self::STATUSES);
        $page = $this->page($request);

        $total = $this->repository->countTickets($status, $search);
        $tickets = $this->repository->listTickets(
            $status,
            $search,
            self::PAGE_SIZE,
            ($page - 1) * self::PAGE_SIZE
        );

        $rows = [];
        foreach ($tickets as $ticket) {
            $rows[] = [
                'reference' => sprintf(
                    '<a href="/admin/tickets.php?id=%d">%s</a>',
                    (int)$ticket['id'],
                    $this->e($this->ticketReference($ticket))
                ),
                'subject' => sprintf(
                    '<a href="/admin/tickets.php?id=%d">%s</a>',
                    (int)$ticket['id'],
                    $this->e($ticket['subject'])
                ),
                'client' => (int)($ticket['userid'] ?? 0) > 0
                    ? sprintf(
                        '<a href="/admin/clients.php?id=%d">#%d</a>',
                        (int)$ticket['userid'],
                        (int)$ticket['userid']
                    )
                    : $this->e($ticket['name'] ?? ''),
                'date' => $this->datetime($ticket['date'] ?? null),
                'status' => $this->badge((string)$ticket['status']),
            ];
        }

        $body = AdminLayout::header('Tickets')
            . $this->filters('/admin/tickets.php', $search, $status, self::STATUSES, 'Assunto, referência ou email')
            . $this->table(
                [
                    ['label' => 'Ref.', 'value' => 'reference'],
                    ['label' => 'Assunto', 'value' => 'subject'],
                    ['label' => 'Cliente', 'value' => 'client'],
                    ['label' => 'Aberto', 'value' => 'date'],
                    ['label' => 'Estado', 'value' => 'status'],
                ],
                $rows,
                'Nenhum ticket encontrado.'
            )
            . $this->pager($total, $page, array_filter([
                'q' => $search,
                'status' => $status,
            ]));

        return $this->render($response, 'Tickets', 'tickets', $body, $this->adminName());
    }

    /**
     * GET /admin/tickets.php?id=N
     */
    public function detail(Request $request, Response $response): Response
    {
        $id = $this->id($request);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O ticket', $admin);
        }

        $ticket = $this->repository->findTicket($id);

        if ($ticket === null) {
            return $this->notFound($response, 'O ticket', $admin);
        }

        $replies = $this->repository->ticketReplies($id);

        $body = AdminLayout::header(
            sprintf('Ticket %s', (string)($ticket['tid'] ?? ('#' . $id))),
            ['Tickets' => '/admin/tickets.php']
        );

        $fields = [
            'Assunto' => $this->e($ticket['subject']),
            'Cliente' => (int)($ticket['userid'] ?? 0) > 0
                ? sprintf(
                    '<a href="/admin/clients.php?id=%d">#%d</a>',
                    (int)$ticket['userid'],
                    (int)$ticket['userid']
                )
                : $this->e($ticket['name'] ?? ''),
            'Email' => $this->e($ticket['email'] ?? ''),
            'Aberto' => $this->datetime($ticket['date'] ?? null),
            'Última resposta' => $this->datetime($ticket['lastreply'] ?? null),
            'Estado' => $this->badge((string)$ticket['status']),
            'Prioridade' => $this->e($ticket['priority'] ?? ''),
        ];

        $body .= '<section class="panel"><div class="panel-heading">Dados</div><div class="panel-body">';
        $body .= '<dl class="admin-details">';
        foreach ($fields as $label => $value) {
            $body .= '<div><dt>' . $this->e($label) . '</dt><dd>' . $value . '</dd></div>';
        }
        $body .= '</dl></div></section>';

        // ── A mensagem original ──
        $body .= '<section class="panel"><div class="panel-heading">Mensagem</div>'
            . '<div class="panel-body"><div class="admin-message">'
            . nl2br($this->e((string)($ticket['message'] ?? '')))
            . '</div></div></section>';

        // ── As respostas ──
        $body .= '<section class="panel"><div class="panel-heading">Respostas</div><div class="panel-body">';

        if ($replies === []) {
            $body .= '<p class="text-muted">Ainda não há respostas.</p>';
        } else {
            foreach ($replies as $reply) {
                $from = (int)($reply['adminreply'] ?? 0) === 1
                    ? 'Administração'
                    : (string)($reply['name'] ?? 'Cliente');

                $body .= '<div class="admin-message admin-message-reply">'
                    . '<div class="admin-message-head">'
                    . '<strong>' . $this->e($from) . '</strong> '
                    . '<span class="text-muted">' . $this->datetime($reply['date'] ?? null) . '</span>'
                    . '</div>'
                    . '<div>' . nl2br($this->e((string)($reply['message'] ?? ''))) . '</div>'
                    . '</div>';
            }
        }

        $body .= '</div></section>';

        // ── A resposta ──
        $body .= '<section class="panel"><div class="panel-heading">Responder</div><div class="panel-body">'
            . '<form method="post" action="/admin/tickets.php" class="admin-form">'
            . '<input type="hidden" name="action" value="reply">'
            . '<input type="hidden" name="id" value="' . $id . '">'
            . '<div class="form-group">'
            . '<label for="admin-reply">Resposta</label>'
            . '<textarea id="admin-reply" name="message" class="form-control" rows="6" required'
            . ' placeholder="Escreva a resposta ao cliente"></textarea>'
            . '</div>'
            . '<button type="submit" class="btn btn-primary">Responder</button>'
            . '</form>'
            . '</div></section>';

        return $this->render(
            $response,
            sprintf('Ticket %s', (string)($ticket['tid'] ?? ('#' . $id))),
            'tickets',
            $body,
            $admin
        );
    }

    /**
     * POST /admin/tickets.php
     *
     * The reply.
     */
    public function reply(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data = is_array($data) ? $data : [];

        // The form posts the id in the **body**, not in the query.
        $id = $this->idFrom($data['id'] ?? null);
        $admin = $this->adminName();

        if ($id === null) {
            return $this->notFound($response, 'O ticket', $admin);
        }

        $message = trim((string)($data['message'] ?? ''));

        if ($message === '') {
            return $response
                ->withHeader('Location', '/admin/tickets.php?id=' . $id . '&error=1')
                ->withStatus(302);
        }

        $ticket = $this->repository->findTicket($id);

        if ($ticket === null) {
            return $this->notFound($response, 'O ticket', $admin);
        }

        $this->ticketService->addReply($id, [
            // An administrator's reply: no client, and the flag that
            // says who wrote it.
            'userid' => 0,
            'name' => $this->adminName() !== '' ? $this->adminName() : 'Administração',
            'email' => (string)($_SESSION['admin_email'] ?? ''),
            'message' => $message,
            'adminreply' => 1,
        ]);

        return $response
            ->withHeader('Location', '/admin/tickets.php?id=' . $id . '&sent=1')
            ->withStatus(302);
    }
}
