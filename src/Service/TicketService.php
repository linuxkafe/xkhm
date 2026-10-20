<?php

declare(strict_types=1);

namespace Xkhm\Service;

use Xkhm\Repository\TicketRepositoryInterface;

class TicketService
{
    private TicketRepositoryInterface $ticketRepository;

    public function __construct(TicketRepositoryInterface $ticketRepository)
    {
        $this->ticketRepository = $ticketRepository;
    }

    /**
     * Create a new support ticket.
     */
    public function createTicket(array $data): ?int
    {
        $ticketData = [
            'userid' => $data['userid'],
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'priority' => $data['priority'] ?? 'Medium',
            'status' => 'Open',
            'departmentid' => $data['departmentid'] ?? 1,
            'datecreated' => date('Y-m-d'),
        ];

        return $this->ticketRepository->create($ticketData);
    }

    /**
     * Get all tickets for a client.
     */
    public function getClientTickets(int $clientId): array
    {
        return $this->ticketRepository->findByUserId($clientId);
    }

    /**
     * Get ticket with replies.
     */
    public function getTicketWithReplies(int $ticketId): ?array
    {
        return $this->ticketRepository->findWithReplies($ticketId);
    }

    /**
     * Add a reply to a ticket.
     */
    public function addReply(int $ticketId, array $data): ?int
    {
        $replyData = [
            'tid' => $ticketId,
            'userid' => $data['userid'],
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
            'datecreated' => date('Y-m-d'),
            'adminreply' => $data['adminreply'] ?? 0,
        ];

        return $this->ticketRepository->addReply($ticketId, $replyData);
    }

    /**
     * Get ticket status label in Portuguese.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'Open' => 'Aberto',
            'Closed' => 'Fechado',
            'Answered' => 'Respondido',
            'In Progress' => 'Em Andamento',
            default => $status,
        };
    }

    /**
     * Get priority label in Portuguese.
     */
    public function getPriorityLabel(string $priority): string
    {
        return match ($priority) {
            'Low' => 'Baixa',
            'Medium' => 'Média',
            'High' => 'Alta',
            'Urgent' => 'Urgente',
            default => $priority,
        };
    }

    /**
     * Get available departments.
     */
    public function getDepartments(): array
    {
        return [
            ['id' => 1, 'name' => 'Geral'],
            ['id' => 2, 'name' => 'Técnico'],
            ['id' => 3, 'name' => 'Financeiro'],
            ['id' => 4, 'name' => 'Vendas'],
        ];
    }

    /**
     * Get available priorities.
     */
    public function getPriorities(): array
    {
        return [
            ['value' => 'Low', 'label' => 'Baixa'],
            ['value' => 'Medium', 'label' => 'Média'],
            ['value' => 'High', 'label' => 'Alta'],
            ['value' => 'Urgent', 'label' => 'Urgente'],
        ];
    }
}
