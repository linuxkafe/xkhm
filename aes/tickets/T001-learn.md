---
ticket: T001
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T001.md
  - aes/tickets/T001-plan.md
produces:
  - aes/tickets/T001-learn.md
  - aes/shadow/SD-META-001.md
  - aes/shadow/SD-DOMAIN-001.md
  - aes/shadow/SD-DOMAIN-002.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T001 — Learn

## What Was Done

Phase 0 reconnaissance completed for the xkhm project. The live WHMCS service at `xk.linuxkafe.com` was analyzed via HTTP survey, and the existing reconstruction project (`/home/seyon/dev/whmcs/`) was surveyed for artifacts and findings.

## Key Findings

### Live Service Profile
- WHMCS 5.3.9 (BuildId 3, Build Date 22 Aug 2014)
- Apache + PHP 5.6.40 + ionCube Loader 15.5.0
- Portuguese (Brazilian) UI
- 19 public pages, all returning 200 OK
- 3 active payment gateways (paypal, authorize, worldpay)
- Admin panel requires authentication (302 redirect)

### Database Reality
- Live DB (`linuxkonect`) is on a separate MySQL instance at `10.0.3.1`
- Not reachable from this machine — timeout on connection
- Local `whmcs-mysql` container has empty `whmcs` DB (different name, no data)
- Schema must be inferred from WHMCS 5.3.9 source SQL install files

### Reconstruction Project Status
- 436 of ~1386 files reconstructed (31%)
- Core bootstrap files done: `init.php`, `clientarea.php`, `cart.php`
- Core function files (`functions.php`, `dbfunctions.php`) are EMPTY in reconstruction
- Some reconstructed files have PHP 8.4 syntax errors
- T048 closed: ionCube decode path is inline, not hookable

### Architecture Decision
- **Language**: PHP 8.4 (same as reconstruction target)
- **Framework**: Slim Framework (minimal, clean routing)
- **Database**: MySQL PDO (same schema, prepared statements)
- **Rationale**: Same language family, direct config reuse, no new learning curve

## Feynman Method

### For a Child

Imagine you have a locked box (the ionCube encryption) that you can't open. Instead of trying to pick the lock, you watch what comes out of the box when someone opens it. You see 19 different things come out (the web pages). Now you want to build your own box that gives the same 19 things, but without the lock. You don't need to know how the lock works — you just need to know what comes out.

The database is like a recipe book inside the box. You can't read it directly, but you can see the cookbook that came with the box (WHMCS source code), which tells you what recipes (tables) exist.

### For an Expert

The ionCube Loader's encode format is PHP 5.0 opcodes with a custom encryption layer. The decode path is inline in a MINIT-installed hook on `EG(compile_file)` by `FUN_001e5245`, not a discrete exportable function. This was proven by T048 via REA/Ghidra analysis showing zero static xrefs to the `zend_compile_file` string at `0x107f74`.

The `RESERVED[3]` buffer is a shared 32KB literal pool that the Loader populates during decode. File-level attribution requires statistical convergence across multiple runs (T041: 7 runs, K=4 threshold, 89.8% convergence). This is the only viable path to observing decoded content without hooking the inline decode.

For xkhm, the reconstruction approach is obsolete — we're building a fresh implementation that connects to the same DB schema. The feature surface is fully enumerable from HTTP survey (19 public pages, 3 active gateways).

## Chain of Whys

- **Why PHP 8.4 + Slim?** → Same language family as original, Slim gives clean routing without Laravel's weight, PDO replaces deprecated `mysql_*`
- **Why not continue reconstruction?** → Reconstruction proved viable but left 69% of files unreconstructed; a fresh implementation is faster than completing 436 more files
- **Why infer DB schema from source?** → Live DB at `10.0.3.1` is unreachable from this host; the only complete schema reference is the WHMCS 5.3.9 SQL install files
- **Why focus on 19 public pages?** → These are the only features actually deployed; implementing 404-returning modules is scope creep
- **Why is the ionCube decode path inline?** → The Loader is designed to resist hooking; the encode format is PHP 5.0 opcodes, and the decode happens during the compile phase, not as a separate function call

## Shadow Docs Banked

1. **SD-META-001** — Live DB is not the local whmcs-mysql container (infrastructure gap)
2. **SD-DOMAIN-001** — WHMCS 5.3.9 feature surface on xk.linuxkafe.com (feature baseline)
3. **SD-DOMAIN-002** — ionCube decode path is inline, not a hookable function (proof of dead end)

## Next Steps

T002: Database schema survey — extract full table definitions from WHMCS 5.3.9 SQL install files.
