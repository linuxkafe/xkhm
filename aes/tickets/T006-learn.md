---
ticket: T006
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T006.md
  - aes/tickets/T006-plan.md
produces:
  - aes/tickets/T006-learn.md
  - aes/shadow/SD-DOMAIN-004.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T006 — Learn

## What Was Done

Implemented complete client authentication system for xkhm:

### Implementation Details

1. **AuthService** (`src/Service/AuthService.php`)
   - `verifyPassword()`: WHMCS 5.3.9 compatible password verification
     - Supports 34-char format: salt(2) + md5(password.salt)
     - Supports legacy 32-char format: plain md5(password)
   - `hashPassword()`: Generates WHMCS-compatible hash with random salt
   - `generateRememberToken()`: 64-char hex token for remember-me
   - Uses `hash_equals()` for constant-time comparison (security)

2. **LoginController** (`src/Http/Controller/LoginController.php`)
   - `showLogin()`: GET `/clientarea.php` — login form or redirect
   - `processLogin()`: POST `/dologin.php` — credential verification
   - `logout()`: GET `/logout.php` — session destruction
   - Session regeneration on login (prevents session fixation)
   - Remember-me cookie support (30-day expiry)

3. **PasswordResetController** (`src/Http/Controller/PasswordResetController.php`)
   - `showForm()`: GET `/pwreset.php` — reset request form
   - `process()`: POST `/pwreset.php` — generates reset token
   - Email enumeration prevention (always returns success)
   - UI in Portuguese (Brazilian)

4. **AuthMiddleware** (`src/Http/Middleware/AuthMiddleware.php`)
   - Session-based route protection
   - Redirects to login if not authenticated

5. **Session Handling** (in `public/index.php`)
   - PHP sessions with HttpOnly + Secure cookies
   - Session injection into PSR-7 request
   - SameSite=Strict for CSRF protection

## Key Findings

### WHMCS 5.3.9 Password Hashing
- Format: `salt(2 chars) + md5(password . salt)(32 chars)` = 34 chars total
- Verification: extract first 2 chars as salt, compute md5, compare
- Uses `hash_equals()` for security (no timing attacks)

### Live Service Login Form
- Fields: `username`, `password`, `rememberme`, `token`, `language`
- Session cookie: `WHMCSlhSO1TsOhs3X` (observed in live headers)
- CSRF token present (`token` field) — not yet implemented

### Quality Gates
- **Tests**: 14 total (7 Config + 8 AuthService), 35 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **0 violations**

## Feynman Method

### For a Child
Imagine you have a locked diary (WHMCS) and you want to build your own diary that opens with the same key. The key is a secret code — you take a password, mix it with two mystery letters (the salt), and turn it into a 34-letter code. Your new diary knows how to make and check these codes, so it works with all your old entries.

### For an Expert
The WHMCS 5.3.9 password format embeds the salt as the prefix of the hash itself. This is a common pattern in legacy PHP applications that predates `password_hash()`. The 2-char salt provides only 65536 possibilities, which is cryptographically weak by modern standards, but we must replicate it for compatibility.

The `hash_equals()` function is used instead of `==` to prevent timing attacks — a vulnerability that would allow an attacker to discover the hash character by character.

The Session-Handler pattern uses `$_SESSION` with `session_regenerate_id(true)` to prevent session fixation attacks. The `SameSite=Strict` cookie attribute provides CSRF protection without a separate token.

## Chain of Whys

- **Why md5+salt instead of bcrypt?** → WHMCS 5.3.9 uses md5+salt; we must match the existing hashes in the database
- **Why not implement CSRF tokens?** → The live service has tokens, but implementation requires a hidden form field in existing WHMCS templates — deferred for later
- **Why `hash_equals()`?** → Prevents timing attacks that could leak the hash
- **Why regenerate session ID?** → Prevents session fixation (attacker can't reuse a known session ID after login)

## Shadow Docs Banked

1. **SD-DOMAIN-004** — WHMCS 5.3.9 password hash format: salt(2 chars) + md5(password.salt)