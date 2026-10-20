---
ticket: T004
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T004.md
  - aes/tickets/T004-plan.md
produces:
  - aes/tickets/T004-learn.md
  - aes/shadow/SD-META-002.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T004 — Learn

## What Was Done

Two-agent architecture decision protocol executed for the xkhm project. The Critic identified 4 failure modes and 1 critical assumption. The Implementor addressed all points.

## Key Findings

### Decision: PHP 8.4 + Slim 4 + MySQL PDO + Repository Pattern

**Why this won:**
- Direct `configuration.php` compatibility
- Minimal framework (Slim = routing + middleware only)
- Repository pattern isolates DB schema coupling
- Operator knows PHP; no learning curve
- WHMCS logic is PHP-native; 1:1 porting

**Alternatives Rejected:**
| Alternative | Reason |
|-------------|--------|
| Node.js/Express | Different language, no config reuse, steeper learning |
| Python/Django | Overkill, ORM adds complexity, no config reuse |
| Go/Rust | Over-engineered, no compatibility benefit |
| Laravel/Symfony | Heavy, ORM couples to schema, overkill for CRUD |

### Repository Pattern Structure
```
src/Repository/
├── ClientRepositoryInterface.php
├── ClientRepositoryPDO.php
├── OrderRepositoryInterface.php
├── OrderRepositoryPDO.php
├── InvoiceRepositoryInterface.php
├── InvoiceRepositoryPDO.php
├── TicketRepositoryInterface.php
├── TicketRepositoryPDO.php
├── DomainRepositoryInterface.php
├── DomainRepositoryPDO.php
└── AffiliateRepositoryInterface.php
└── AffiliateRepositoryPDO.php
```

### Quality Gates Defined
- `make check`: phpstan (level 5), phpunit, php-cs-fixer, playwright
- 80% unit test coverage on Domain/Repository/Service
- 100% E2E coverage on 19 public pages

## Feynman Method

### For a Child

Imagine you're building a new house (xkhm) that needs to fit on the exact same foundation (database) as an old house (WHMCS). You have two choices:
1. Build with the same materials (PHP) so the pipes fit perfectly
2. Build with new materials (Node/Go) but then you need to rebuild all the plumbing

The old house is made of PHP bricks. If you use PHP bricks for the new house, the pipes (database) connect directly. If you use different bricks, you need adapters for every single pipe.

The Repository Pattern is like building a standard pipe connector — if the foundation changes slightly, you only fix the connector, not the whole house.

### For an Expert

The decision optimizes for **zero migration risk** over **technical novelty**. The constraint "same database, same configuration" means:
- Schema coupling is mandatory (not optional)
- Language coupling is beneficial (not accidental)
- Framework overhead must be minimal (Slim = ~50KB runtime)

The Repository Pattern with interfaces provides the abstraction boundary:
- `RepositoryInterface` defines the contract (pure PHP)
- `RepositoryPDO` implements with raw SQL + PDO
- Business logic depends only on interfaces
- Schema changes → only `RepositoryPDO` changes

This is classic Clean Architecture / Hexagonal Architecture applied to a legacy migration.

## Chain of Whys

- **Why Repository Pattern?** → Schema is fixed (live DB), but we need testability (SQLite in-memory for unit tests)
- **Why Slim not raw PHP?** → Routing + middleware + PSR-7/15 compliance saves ~200 lines of boilerplate
- **Why not ORM?** → WHMCS schema is denormalized, legacy, and we need exact SQL control for compatibility
- **Why PHP 8.4?** → JIT, union types, attributes, readonly properties — modern PHP without breaking changes

## Shadow Docs Banked

1. **SD-META-002** — Architecture decision: PHP 8.4 + Slim + PDO + Repository Pattern

## Next Steps

T005: Implement project scaffold (composer.json, directory structure, Config.php, PDO connection, repository interfaces).