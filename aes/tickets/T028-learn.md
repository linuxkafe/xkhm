---
ticket: T028
phase: learn
status: done
created: 2026-10-21
tier: heavy
---

# T028 — Learn: Esquema WHMCS 5.3.9 real + formatos de senha

## What changed

The application now reads and writes the **real** production schema, and
the login works with the passwords the production database actually holds.

## Insights captured

| Shadow doc | Insight |
|------------|---------|
| `SD-CI-004` | A hand-written fixture hides the real schema — and the tests that validate it stay green |
| `SD-OPS-002` | "Wrong password" and "cannot verify this password" have the same symptom |
| `SD-META-016` | The palette is a function of the mode — the same shape of problem, one dimension over |

## The three decisions that mattered

### 1. The difference lives in one place

The WHMCS 5.3.9 columns are not scattered across the repositories, the
services and the controllers. `SchemaMap` holds the whole difference —
renames, columns that do not exist, synthesised values, inverted flags and
the per-table defaults. The application speaks canonical names; the
mapping translates.

The alternative — renaming every column at every call site — would have
been a large diff with no place to look when the next WHMCS version
arrives.

### 2. The verifier inspects, it does not guess

The WHMCS 5.x sources are ionCube-encrypted, and the way the salt is mixed
into the hash changed between releases. With no known plaintext pair, the
algorithm is not determinable from the data — every arrangement is
consistent with itself.

So the verifier branches on the **shape** of the stored hash and tries
every arrangement of the salted form. A wrong guess would have reproduced
the original symptom, and the symptom is indistinguishable from "the
password is wrong".

### 3. The E2E suite uses its own accounts, never the real ones

The `linuxkafe` admin and the seven real clients are the operator's.
`make db-test-accounts` creates a separate client, a second client (for
the IDOR test) and an admin, each with its own sample data.

## What surprised us

- **The production knowledge base is empty.** Zero articles, one
  announcement from 2018. The KB screens are real code against real (empty)
  data — which is the truth of the product, not a defect.
- **The affiliate feature was never used.** All four affiliate tables have
  zero rows, and the WHMCS 5.3.9 model is *the client is the affiliate* —
  a completely different model from the WHMCS 6+ one the code was written
  against.
- **`SELECT *` is not a schema.** The repositories mostly selected `*`, which
  is why the *reads* survived; it was the `ORDER BY` clauses, the
  `INSERT` column lists and the field names the services read that broke.
- **The announcements are HTML.** The WHMCS body is `<br />`-laden markup,
  and the application was showing the raw tags to the user.

## Deferred, deliberately

- The real `linuxkafe` admin's password was **not** reset. It belongs to
  the operator; the verifier accepts it whatever arrangement WHMCS 5.3.8
  used.
- The WHMCS features the application does not use (products, hosting,
  servers) are out of scope — the data is imported and preserved, but not
  surfaced.

## Follow-ups worth a ticket

- The `tblpricing` table holds the real domain prices; the pricing screen
  currently reports zeroes because WHMCS 5.3.9 keeps them outside
  `tbldomainpricing`.
- The API contract is asserted through the UI; an explicit shape test
  would harden the boundary the Flutter client depends on.
- `SslService::getCertificateInfo()` still returns simulated data.
