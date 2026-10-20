---
ticket: T011
phase: learn
status: done
created: 2026-10-20
requires:
  - aes/tickets/T011.md
produces:
  - aes/tickets/T011-learn.md
  - aes/shadow/SD-DOMAIN-009.md
side_effects:
  - updates aes/kanban.md
blocked_by: ''
---

# T011 — Learn: Affiliates Module

## What Was Done

Implemented the affiliate/referral system for xkhm — registration, referral tracking, and commission dashboard.

### Implementation Details

1. **AffiliateRepositoryInterface** (`src/Repository/AffiliateRepositoryInterface.php`)
   - `create()`, `findByUserId()`, `findById()`, `findByRefCode()`
   - `addReferral()`, `getReferralsByAffiliate()`, `getEarningsByAffiliate()`
   - `getPayoutHistory()`

2. **AffiliateRepositoryPDO** (`src/Repository/AffiliateRepositoryPDO.php`)
   - All interface methods implemented with PDO
   - Ref code auto-generated (16-char hex)

3. **AffiliateService** (`src/Service/AffiliateService.php`)
   - `register()`: Create affiliate account
   - `trackReferral()`: Track referral from link click
   - `addCommission()`: Add commission on purchase
   - `getAffiliateLink()`: Generate referral URL
   - Portuguese labels for UI

4. **AffiliateController** (`src/Http/Controller/AffiliateController.php`)
   - `index()`: Dashboard or signup form
   - `signup()`: Create affiliate account
   - Dashboard shows: total earnings, referrals count, balance, payout history

### Quality Gates

- **Tests**: 21 total, 46 assertions — **all pass**
- **Lint**: PHPStan Level 5 — **0 errors**
- **Format**: PHP-CS-Fixer — **clean**
- **All gates**: `make check` passes

## Key Findings

### Affiliate Flow

1. Client signs up → `tblaffiliates` created
2. Client shares ref link → `cart.php?aff=CODE`
3. New client clicks link → `tblaffiliatesclients` tracking
4. Purchase completed → Commission added to affiliate balance
5. Payout requested → Admin approval (deferred to T019)

### Commission Structure

- Default rate: 10% of purchase
- Configurable per affiliate (1-50%)
- Balance tracked in `tblaffiliates.balance`
- Payouts tracked in `tblaffiliatespayouts`

## Feynman Method

### For a Child

When you tell your friends about our website and they sign up, you earn money! You get a special link, and whenever someone clicks it and buys something, you get a small cut of the price. It's like getting paid for being a good promoter.

### For an Expert

The AffiliateService implements the standard referral tracking pattern:
- Unique ref code per affiliate (16-char hex)
- Cookie-based tracking (clientid stored on click)
- Commission calculated as percentage of invoice total
- Balance accumulates in `tblaffiliates.balance`

The `trackReferral()` method uses a first-click attribution model: the first client associated with a referral link gets the credit.

## Chain of Whys

- **Why 16-char ref codes?** → Sufficient entropy (2^64 combinations) without being unwieldy
- **Why flat 10% default?** → Simple commission structure for initial implementation
- **Why no admin approval?** → Deferred to T019 admin panel

## Shadow Docs Banked

1. **SD-DOMAIN-009** — WHMCS affiliate flow: signup → ref link → tracking → commission → payout

## Next Steps

T012: Admin panel (core CRUD) — extend authenticated admin routes for client management.