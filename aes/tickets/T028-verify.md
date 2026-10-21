---
ticket: T028
phase: verify
status: done
created: 2026-10-21
tier: heavy
verdict: pass
---

# T028 — Verify: Alinhar com o esquema WHMCS 5.3.9 real + formatos de senha

## What the import revealed

The production dump (`/home/seyon/dev/whmcs-original/backupdb.sql`, 372 MB,
94 tables) exposed that the development fixture was built against a schema
that **does not exist in production**.

| Fact | Value |
|------|-------|
| Tables imported | 94 |
| Clients | 7 |
| Invoices / invoice items | 88 / 110 |
| Orders | 15 |
| Domains | 4 |
| Admin | 1 — `linuxkafe` |

### The two password formats, measured

| Table | Format | Size |
|-------|--------|------|
| `tbladmins` | **bcrypt** `$2y$10$…` | 60 |
| `tblclients` | **`<md5>:<5-char salt>`** | 38 |

The old `AuthService` accepted only 32-char MD5 and 34-char salt+MD5. **Neither
real format was supported** — that was the direct cause of the failed login.

## What was built

### 1. A format-aware verifier

`src/Service/PasswordHasher.php` — one definition, used by both the client
and the admin service:

| Stored value | Scheme |
|--------------|--------|
| `$2y$…`, `$argon2…` | bcrypt / argon2 (`password_verify`) |
| `<md5>:<salt>` | WHMCS 5.x MD5 with a stored salt |
| 38 chars (salt 6 + md5) | WHMCS 5.x salted double MD5 |
| 34 chars (salt 2 + md5) | the older WHMCS salted MD5 |
| 32 chars | plain MD5 |
| `salt hash` | the WHMCS 6 transitional SHA-1 form |

The WHMCS 5.x sources are not available, and the way the salt is mixed into
the hash changed between releases. The verifier therefore tries **every**
arrangement (8 for the salted form) rather than guessing one — a wrong guess
reproduces the original symptom, a login that silently never works.

New passwords are always written as **bcrypt**; the weak schemes are only
ever verified, never produced.

### 2. A schema mapping layer

`src/Repository/SchemaMap.php` — the difference between the canonical field
names and the real WHMCS 5.3.9 columns lives in **one** place:

- **rename** — `datecreated` → `date`, `subject` → `title`,
  `content` → `article`, `total` → `amount`, `period` → `registrationperiod`
- **drop** — `deptid`, `description`, `lastupdated`, `currency` and others
  do not exist in 5.3.9
- **synthesise** — `fullname` is `firstname` + `lastname`
- **invert** — the knowledge base's `private` is the opposite of `published`
- **defaults** — WHMCS 5.3.9 declares almost every column as `NOT NULL`
  **without a default**, so an insert names them all

### 3. The affiliate feature, rewritten for the real model

WHMCS 5.3.9 models the affiliate as **the client themselves**
(`tblaffiliates.clientid`), with the history in `tblaffiliateshistory` and the
withdrawals in `tblaffiliateswithdrawals`. The WHMCS 6+ programme model
(`refcode`, `commission`, `tblaffiliatesclients`) does not exist there. All the
affiliate tables are **empty** in production.

## Gates

| Gate | Result |
|------|--------|
| `make test` | **89 passed**, 186 assertions — against the **real** schema |
| `make lint` | **0 errors** (PHPStan level 5) |
| `make e2e` | **141 passed** — against the **real** data |
| `make flutter-test` | **40 passed** |
| `make tokens-check` | in sync, no hard-coded colours |

## Acceptance criteria

| AC | Criterion | Evidence | Result |
|----|-----------|----------|--------|
| AC1 | The admin login (bcrypt) works | `test_client_auth.py`, `test_admin_auth.py` | **pass** |
| AC2 | The client login (38 chars) works | `PasswordFormatTest` — a real row in `tblclients` | **pass** |
| AC3 | Every format is supported, one test each | `PasswordHasherTest` — 12 tests | **pass** |
| AC4 | No query names a column that does not exist | `SchemaParityTest` — 33 tests | **pass** |
| AC5 | The parity test runs against the real schema | `SchemaParityTest` | **pass** |
| AC6 | The application shows real data | 88 invoices, 4 domains, 15 orders read | **pass** |
| AC7 | All gates green | the table above | **pass** |

## Note on the operator's own accounts

The real `linuxkafe` admin and the seven real clients were **not touched**.
The E2E suite uses dedicated development accounts, created by
`make db-test-accounts`:

```
cliente  xkhm-test@example.com  / xkhm-test-2026
cliente  xkhm-test2@example.com / xkhm-test-2026
admin    xkhm-test              / xkhm-test-2026
```

The `linuxkafe` admin's password is the operator's; the verifier accepts it
in whatever arrangement WHMCS 5.3.8 wrote it.

## Conclusion

**PASS.** The application now reads and writes the **real** WHMCS 5.3.9
schema, and the login works with the passwords the production database
actually holds.
