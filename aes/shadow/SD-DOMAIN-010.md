# SD-DOMAIN-010 — WHMCS admin auth: tbladmins table, separate from tblclients

- **Insight**: WHMCS uses separate `tbladmins` table for admin accounts, distinct from `tblclients`. Password hashing uses the same md5+salt algorithm as clients. Admin sessions stored in `$_SESSION['admin_id']`, `$_SESSION['admin_username']`.
- **Impact**: AdminAuthService handles authentication. AdminAuthMiddleware provides PSR-15 route protection.
- **Applied To**: T012 admin panel. Future T019 will extend with full CRUD operations.
- **Date**: 2026-10-20