---
ticket: T009
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T009.md
produces:
  - aes/tickets/T009-learn.md
  - aes/shadow/SD-DOMAIN-007.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T009 — Learn: Support Tickets

## What Was Done

Implemented support ticket system for xkhm — ticket creation, listing, viewing, and replies.

### Implementation Details

1. **TicketService** (`src/Service/TicketService.php`)
   - `createTicket()`: Creates new ticket with department and priority
   - `getClientTickets()`: Lists all tickets for a client
   - `getTicketWithReplies()`: Fetches ticket with its replies
   - `addReply()`: Adds a reply to a ticket
   - `getStatusLabel()`: Portuguese status labels (Aberto, Fechado, Respondido)
   - `getPriorityLabel()`: Portuguese priority labels (Baixa, Média, Alta)
   - `getDepartments()`: Returns ticket departments
   - `getPriorities()`: Returns priority options

2. **TicketController** (`src/Http/Controller/TicketController.php`)
   - `list()`: GET `/supporttickets.php` — client ticket listing
   - `new()`: GET `/supporttickets.php?do=new` — ticket creation form
   - `create()`: POST `/supporttickets.php` — create ticket
   - `view()`: GET `/supporttickets.php?do=ticket&id=X` — ticket detail with replies
   - `reply()`: POST `/supporttickets.php?action=reply` — add reply

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Ticket Status Flow

WHMCS ticket statuses:
- **Open** → Aberto (new ticket)
- **Answered** → Respondido (staff replied, awaiting client)
- **In Progress** → Em Andamento (actively working)
- **Closed** → Fechado (resolved)

### Portuguese UI

All user-facing strings in Brazilian Portuguese:
- "Meus Tickets" (My Tickets)
- "Criar Novo Ticket" (Create New Ticket)
- "Assunto" (Subject)
- "Status" (Status)
- "Prioridade" (Priority)
- "Responder" (Reply)

## Feynman Method

### For a Child

When you have a problem, you can open a ticket (like a question form) at the help desk. You pick a department (who will help you), write your problem, and they reply when they can. You can see all your tickets and keep the conversation going.

### For an Expert

The TicketService follows the WHMCS 5.3.x ticket lifecycle. The repository pattern isolates `tbltickets` and `tblticketreplies` table access. The TicketController uses PSR-7 request/response with Portuguese UI strings.

## Chain of Whys

- **Why Portuguese labels?** → Live service uses Brazilian Portuguese UI
- **Why simple ticket creation form?** → The live service has a multi-step wizard; simplified for initial implementation
- **Why no attachment support?** → Deferred to T013 (advanced features)

## Shadow Docs Banked

1. **SD-DOMAIN-007** — WHMCS ticket lifecycle: open → answered → in progress → closed

## Next Steps

T010: Announcements & Knowledgebase — public-facing content management.