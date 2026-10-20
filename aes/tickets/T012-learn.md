---
ticket: T012
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T012.md
produces:
  - aes/tickets/T012-learn.md
  - aes/shadow/SD-DOMAIN-010.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T012 — Learn: Admin Panel (Core CRUD)

## What Was Done

Implemented the admin authentication layer for xkhm — admin login, session management, and route protection.

### Implementation Details

1. **AdminRepositoryInterface** (`src/Repository/AdminRepositoryInterface.php`)
   - `findByUsername()`: Lookup admin by username

2. **AdminRepositoryPDO** (`src/Repository/AdminRepositoryPDO.php`)
   - Queries `tbladmins` for admin authentication

3. **AdminAuthService** (`src/Service/AdminAuthService.php`)
   - `verifyCredentials()`: WHMCS-compatible password verification (salt + md5, plain md5)
   - `login()`: Session start with regeneration
   - `logout()`: Session destroy
   - `isLoggedIn()`: Session check

4. **AdminAuthController** (`src/Http/Controller/AdminAuthController.php`)
   - `showLogin()`: GET `/admin/login.php` — login form
   - `login()`: POST `/admin/login.php` — credential verification
   - `logout()`: GET `/admin/logout.php` — session destroy

5. **AdminAuthMiddleware** (`src/Http/Middleware/AdminAuthMiddleware.php`)
   - PSR-15 middleware for route protection
   - Uses `ResponseFactoryInterface` for proper Slim 4 compliance

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Admin Authentication

WHMCS uses separate tables for admins:
- `tbladmins`: Admin accounts (separate from `tblclients`)
- Password hashing: Same algorithm as clients (md5 + salt)

### Session Management

Admin sessions use:
- `$_SESSION['admin_id']`
- `$_SESSION['admin_username']`
- `$_SESSION['admin_name']`

### Slim 4 Response Factory

The correct way to create responses in middleware:
```php
new AdminAuthMiddleware($app->getResponseFactory());
// In middleware:
$this->responseFactory->createResponse(302)->withHeader('Location', '/path');
```

## Feynman Method

### For a Child

Admins are like teachers who can manage the school. They have their own special login (different from students) and can see things regular users can't. We built a special door that only teachers can open.

### For an Expert

The admin auth follows the same pattern as client auth but with separate tables and middleware. The `AdminAuthMiddleware` implements PSR-15 and uses the response factory for proper Slim 4 compliance. Password verification supports both 34-char (salt+md5) and 32-char (plain md5) formats.

## Chain of Whys

- **Why separate admin table?** → WHMCS uses `tbladmins` separate from `tblclients`
- **Why same password hash?** → WHMCS uses the same md5+salt algorithm for both clients and admins
- **Why not implement full CRUD?** → Deferred to T019; auth layer is the foundation

## Shadow Docs Banked

1. **SD-DOMAIN-010** — WHMCS admin auth: tbladmins table, separate from tblclients

## Next Steps

T013: Payment gateway integrations — implement real PayPal, Authorize.Net, WorldPay gateways.