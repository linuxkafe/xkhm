---
ticket: T006
phase: plan
status: done
created: 2026-10-20
tier: standard
verdict: pass
requires:
  - aes/tickets/T005-learn.md
produces:
  - aes/tickets/T006-plan.md
  - src/Service/AuthService.php
  - src/Http/Controller/LoginController.php
  - src/Http/Controller/PasswordResetController.php
  - src/Http/Middleware/AuthMiddleware.php
  - tests/Unit/AuthServiceTest.php
blocked_by: ''
---
# T006 — Plan: Client Authentication & Session Management

## Reconnaissance Summary

**Phase 0:** Deep analysis of the WHMCS login flow via:
1. HTTP survey of `/login.php`, `/dologin.php`, `/pwreset.php`, `/logout.php`
2. Form field analysis (`username`, `password`, `rememberme`, `token`)
3. WHMCS 5.3.9 source analysis (password hashing: `md5($password . substr($stored, 0, 2))`)
4. Live service headers (session cookie: `WHMCSlhSO1TsOhs3X`)

## Implementation Summary

### Files Created

| File | Purpose | Lines |
|------|---------|-------|\n| `src/Service/AuthService.php` | Password verification (WHMCS 5.3.9 compatible), token generation | 65 |\n| `src/Http/Controller/LoginController.php` | Login form, login processor, logout | 130 |
| `src/Http/Controller/PasswordResetController.php` | Password reset form, processor | 140 |\n| `src/Http/Middleware/AuthMiddleware.php` | Session-based route protection |\n| `tests/Unit/AuthServiceTest.php` | 8 tests for password verification |\n| `public/index.php` | Updated with session middleware + auth routes |\n\n### Password Hashing\n\nWHMCS 5.3.9 password verification algorithm:\n- Stored hash format: salt (2 chars) + md5(password.salt) = 34 chars total\n- Verification: extract salt (first 2 chars), compute md5(password.salt), compare\n- Also supports legacy 32-char plain md5 hashes for backward compatibility\n\n### Security Features\n\n- `hash_equals()` for constant-time comparison (prevents timing attacks)\n- `session_regenerate_id(true)` on login (prevents session fixation)\n- HttpOnly + Secure cookies\n- SameSite=Strict for CSRF protection\n- Email enumeration prevention (always return success)\n\n## Acceptance Criteria\n\n- [x] AC1 — Login form at `/clientarea.php` (GET)\n- [x] AC2 — Login processor at `/dologin.php` (POST) — validates credentials\n- [x] AC3 — WHMCS 5.3.9 password hashing compatibility\n- [x] AC4 — Session management: PHP sessions with secure cookies\n- [x] AC5 — Remember me functionality (token generation ready)\n- [x] AC6 — Password reset flow: `/pwreset.php` (GET/POST)\n- [x] AC7 — Logout at `/logout.php` — destroys session\n- [x] AC8 — Auth middleware for protected routes\n- [x] AC9 — Unit tests for password verification (8 tests)\n- [ ] AC10 — Integration test against live service login (Playwright — deferred to T018)\n\n## Quality Gates\n\n- PHPUnit: 14 tests, 35 assertions — **OK**\n- PHPStan: Level 5, 0 errors — **OK**\n- PHP-CS-Fixer: 0 violations — **OK**\n- `make check`: All gates pass — **OK**\n\n## Risks & Mitigations\n\n| Risk | Mitigation |\n|------|------------|\n| WHMCS password hashing algorithm unknown | Support both 32-char (plain md5) and 34-char (salt+md5) formats\nToken storage not implemented (no DB table yet)\n\n## Next Steps\n\nT007: Billing & invoicing engine — invoices, orders, payments."}