# T004 — Two-Agent Architecture Decision Record

## Critic Phase

**FAILURE MODE 1 — Wrong Language**
Mechanism: Choosing PHP 8.4 locks us into the same language as the original, which means we inherit PHP's historical baggage (inconsistent standard library, legacy patterns). If we chose Node.js/Go/Rust, we'd get modern concurrency, type safety, and better performance.

**FAILURE MODE 2 — Framework Overhead**
Mechanism: Slim Framework adds ~50KB of dependencies. For a simple CRUD app, raw PHP with a minimal router might be cleaner and have zero external dependencies. The "framework" could become a maintenance burden if it's abandoned.

**FAILURE MODE 3 — Database Coupling**
Mechanism: Using the same schema means we're coupled to WHMCS 5.3.9's table design forever. If we need to change the schema, we break compatibility. A proper abstraction layer (repository pattern) is needed.

**FAILURE MODE 4 — No Testing Strategy**
Mechanism: The plan mentions "tests" but doesn't specify HOW. Without contract tests against the live service, we can't verify feature parity. Need integration tests that hit the live service.

**ASSUMPTION**
The live WHMCS schema is stable and won't change.
If false: Any schema change by the operator (adding columns, changing types) breaks our app silently.

**ALTERNATIVE FRAMING**
Instead of "reimplement WHMCS", frame it as "build a new billing panel that happens to read the WHMCS database". This shifts focus from compatibility to new features.

What are we optimizing that we shouldn't be?
We're optimizing for "same database" when we should optimize for "correct business logic". The database is an implementation detail.

---

## Implementor Phase

**ADDRESSING FAILURE MODE 1** — PHP 8.4 is the RIGHT choice because:
- Same language = direct reuse of `configuration.php` values
- Operator knows PHP; no learning curve
- PDO + prepared statements = security without framework overhead
- The WHMCS source patterns are PHP-native; porting logic is 1:1
- PHP 8.4 has JIT, union types, attributes — modern enough

**ADDRESSING FAILURE MODE 2** — Slim is MINIMAL:
- ~2MB installed, ~50KB runtime
- Only provides routing + middleware (no ORM, no DI container by default)
- Can be replaced with raw PHP if needed
- Battle-tested (PSR-15, PSR-7 compliant)

**ADDRESSING FAILURE MODE 3** — Repository Pattern will be used:
- All DB access through `Repository` classes
- Interface-based, swappable implementation
- Schema changes only touch repositories, not business logic

**ADDRESSING FAILURE MODE 4** — Test Strategy:
- Contract tests: Playwright E2E against live service
- Unit tests: Repository layer with SQLite in-memory
- Integration tests: Docker Compose with local MySQL

**ADDRESSING ALTERNATIVE FRAMING** — Compatibility IS the business requirement:
- The goal is drop-in replacement
- Same DB = zero data migration = instant rollback
- "New features" come AFTER parity (T019+)

---

## SOLUTION PROPOSAL

### Architecture: PHP 8.4 + Slim 4 + MySQL PDO + Repository Pattern

```
xkhm/
├── composer.json
├── public/
│   └── index.php          # Slim entry point
├── src/
│   ├── Domain/            # Pure business logic
│   │   ├── Client/
│   │   ├── Order/
│   │   ├── Invoice/
│   │   ├── Ticket/
│   │   ├── Domain/
│   │   └── Affiliate/
│   ├── Repository/        # Database access (interface + PDO impl)
│   │   ├── ClientRepository.php
│   │   ├── OrderRepository.php
│   │   ├── InvoiceRepository.php
│   │   ├── TicketRepository.php
│   │   ├── DomainRepository.php
│   │   └── AffiliateRepository.php
│   ├── Service/           # Application services
│   │   ├── AuthService.php
│   │   ├── BillingService.php
│   │   ├── TicketService.php
│   │   └── DomainService.php
│   ├── Http/              # Controllers, middleware
│   │   ├── Controller/
│   │   ├── Middleware/
│   │   └── Response/
│   └── Config.php         # Reads configuration.php
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── E2E/               # Playwright
├── docker-compose.yml
├── Dockerfile
└── Makefile
```

### Implementation Order

1. **Scaffold**: `composer.json`, directory structure, `.gitignore`
2. **Config**: `Config.php` reads `configuration.php`
3. **Database**: PDO connection, repository interfaces + implementations
4. **Auth**: Client login, session management, password verification (md5+salt)
5. **Client Area**: Dashboard, products, domains, tickets, invoices
6. **Billing**: Orders, invoices, payments
7. **Tickets**: CRUD + replies
8. **Domains**: Checker, registration
8. **Admin**: Core CRUD
9. **Gateways**: PayPal, Authorize.net, WorldPay
10. **API**: WHMCS API compatibility
11. **E2E Tests**: Playwright against live service
12. **Docker**: Production deployment

### Quality Gates

- `make check`: phpstan (level 5), phpunit, php-cs-fixer, playwright
- 80% unit test coverage on Domain/Repository/Service layers
- 100% E2E coverage on 19 public pages

---

## Decision Record

**Decision**: PHP 8.4 + Slim Framework 4 + MySQL PDO + Repository Pattern

**Rationale**:
1. Direct compatibility with `configuration.php`
2. Minimal framework overhead (Slim = routing + middleware only)
3. Repository pattern isolates schema coupling
4. Operator expertise in PHP
5. WHMCS logic is PHP-native

**Alternatives Rejected**:
- Node.js/Express: Different language, no config reuse, steeper learning
- Python/Django: Overkill, ORM adds complexity, no config reuse
- Go/Rust: Over-engineered, no compatibility benefit
- Laravel/Symfony: Heavy, ORM couples to schema, overkill for CRUD

**Consequences**:
- Must maintain PHP 8.4 compatibility
- Slim 4 is mature but not actively developed (Slim 5 in beta)
- Raw SQL in repositories — no ORM magic

**Validation**: T005 will implement the scaffold and prove the architecture works.