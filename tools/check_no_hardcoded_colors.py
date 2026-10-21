#!/usr/bin/env python3
"""
Guard: no hard-coded colour literal may appear in the Flutter sources.

The LINUXKAFE palette lives in exactly one place — `assets/design_tokens.json`
— and is compiled into `lib/theme/tokens.dart` by `tools/sync_tokens.py`.
A widget that hard-codes `#f8b400` would silently fork the brand.

This script fails when a raw hex literal is found in the Dart sources, so
the drift cannot reach a commit.
"""

from __future__ import annotations

import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
APP = ROOT / "flutter" / "xkhm_app" / "lib"

#: The generated token file is allowed to contain the literals — it *is* the
#: generated representation of them.
ALLOWED = {"tokens.dart"}

HEX_RE = re.compile(r"0x[0-9A-Fa-f]{6,8}\b|#[0-9A-Fa-f]{3,8}\b")


def main() -> int:
    offenders: list[str] = []

    for path in sorted(APP.rglob("*.dart")):
        if path.name in ALLOWED:
            continue

        for number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), 1):
            if HEX_RE.search(line):
                relative = path.relative_to(ROOT)
                offenders.append(f"{relative}:{number}: {line.strip()}")

    if offenders:
        print("FAIL: hard-coded colour literals found in the Flutter sources.")
        print("Use the generated tokens in lib/theme/tokens.dart instead.\n")
        for offender in offenders:
            print(f"  {offender}")
        return 1

    print(f"OK: no hard-coded colour literals in {APP.relative_to(ROOT)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
