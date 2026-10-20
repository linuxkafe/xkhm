# VISION — XKHM

## Problem

The live service at `xk.linuxkafe.com` runs WHMCS 5.3.9, a PHP 5.6 application locked behind ionCube encryption. The operational risks are:

1. **No source control** — 693 of 1,386 PHP files are encrypted; changes require modifying the live container
2. **PHP 5.6 end-of-life** — no security patches, no modern language features
3. **Vendor lock-in** — ionCube Loader is a hard dependency; the license mechanism prevents running without the original server's approval
4. **No testability** — encrypted code cannot be unit-tested or statically analyzed

## Solution

Reimplement the live WHMCS service as a new, clean codebase ("xkhm") that:
- Reproduces all functionality observable on the live service
- Connects to the same MySQL database (`linuxkonect`)
- Uses the same `configuration.php` values (credentials, encryption hash)
- Runs on PHP 8.4+ without any ionCube dependency
- Is fully version-controlled and testable

## Scope

Only the features actually deployed on `xk.linuxkafe.com` are in scope. This is a **replacement**, not an enhancement.

## Value Proposition

- Full source control and ability to audit every line of code
- PHP 8.4+ performance and security
- No ionCube dependency
- Ability to run locally for development and testing
- Same database = zero data migration required
