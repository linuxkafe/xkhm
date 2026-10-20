# SD-DOMAIN-002 — ionCube decode path is inline, not a hookable function

- **Insight**: T048 proved that the ionCube Loader has NO discrete decode function. The decode logic is inline in a runtime hook installed at `EG(compile_file)` by `FUN_001e5245` during MINIT. `zend_compile_file` hooks never fire for encrypted files. This means:
  1. You cannot hook a "decode function" to intercept decrypted content
  2. The only way to observe decoded content is through the `RESERVED[3]` literal buffer (statistical convergence, T041)
  3. arm56's `compile_file` hook is a decoy — it only sees plain PHP, not encrypted files

- **Impact**: Any future REA work on the ionCube Loader should focus on the MINIT hook installation (`FUN_001e5245`) and the `RESERVED[3]` buffer, not on finding a decode function. This is a dead end for direct interception.

- **Applied To**: T048 is closed. No further tickets should attempt to hook the decode path.

- **Date**: 2026-10-20
