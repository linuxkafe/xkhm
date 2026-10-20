# SD-DOMAIN-004 — WHMCS 5.3.9 password hash format

- **Insight**: WHMCS 5.3.9 stores passwords as `salt(2 chars) + md5(password . salt)(32 chars)` = 34 chars total. The salt is the first 2 characters of the stored hash. Verification: `substr($hash, 0, 2)` extracts the salt, then checks `md5($password . $salt) === substr($hash, 2)`. Also supports legacy 32-char plain md5 hashes.
- **Impact**: The AuthService implements both formats for backward compatibility. Any password created or migrated from the original WHMCS will be verifiable.
- **Applied To**: T006 authentication implementation. Any future password change feature must use the same hashing format.
- **Date**: 2026-10-20