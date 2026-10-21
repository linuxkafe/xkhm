#!/usr/bin/env python3
"""
Guard: no hard-coded colour literal may appear in the Flutter sources or in
the hand-written CSS.

The LINUXKAFE palette lives in exactly one place — `assets/design_tokens.json`
— and is compiled into `lib/theme/tokens.dart` and `css/tokens.css` by
`tools/sync_tokens.py`. A widget or a rule that hard-codes `#f8b400` would
silently fork the brand.

This script fails when a raw colour literal is found, so the drift cannot
reach a commit.

Usage:
    python3 tools/check_no_hardcoded_colors.py           # both targets
    python3 tools/check_no_hardcoded_colors.py --dart    # only the Dart sources
    python3 tools/check_no_hardcoded_colors.py --css     # only the CSS
"""

from __future__ import annotations

import argparse
import pathlib
import re
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
DART_APP = ROOT / "flutter" / "xkhm_app" / "lib"
CSS_DIR = ROOT / "public" / "templates" / "default" / "css"

#: The generated token files are allowed to contain the literals — they *are*
#: the generated representation of them.
ALLOWED_DART = {"tokens.dart"}
ALLOWED_CSS = {"tokens.css"}

#: `0xAARRGGBB`, `#RRGGBB`, `#RRGGBBAA`, `#RGB`, and the named Material
#: colours (`Colors.white`, `Color.fromARGB(...)`) that would bypass a
#: hex-only check.
#:
#: `LkColors.*` and `LkSemantic.*` are the *generated* token holders, so
#: they are excluded; `Colors.transparent` is not a brand colour.
DART_PATTERN = re.compile(
    r"0x[0-9A-Fa-f]{6,8}\b"
    # A colour literal is `#` followed by hex digits — but not an HTML
    # entity such as `&#039;`, hence the negative look-behind for `&`.
    r"|(?<!&)#[0-9A-Fa-f]{3,8}\b"
    r"|(?<!Lk)(?<!LkSemantic)Colors\.(?!transparent\b)[a-zA-Z]+"
    r"|Color\.fromARGB\("
)

CSS_PATTERN = re.compile(
    r"(?<!&)#[0-9A-Fa-f]{3,8}\b"
    r"|\brgba?\([^)]*\)"
)


#: The marker that exempts a line from the check, with its reason.
ALLOW_MARKER = "tokens-check: allow"


def scan(directory: pathlib.Path, pattern: re.Pattern, allowed: set[str]) -> list[str]:
    offenders: list[str] = []

    for path in sorted(directory.rglob("*")):
        if not path.is_file() or path.suffix not in {".dart", ".css"}:
            continue
        if path.name in allowed:
            continue

        for number, line in enumerate(
            path.read_text(encoding="utf-8").splitlines(), 1
        ):
            # The escape hatch, for the rare case where a literal is
            # not a brand decision but a **requirement** — a QR code
            # has to be dark on a light background, or the phone cannot
            # read it.
            #
            # The marker is in the source, next to the code that needs
            # it, so every exemption is auditable.
            if ALLOW_MARKER in line:
                continue

            if pattern.search(line):
                relative = path.relative_to(ROOT)
                offenders.append(f"{relative}:{number}: {line.strip()}")

    return offenders


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--dart", action="store_true", help="only the Dart sources")
    parser.add_argument("--css", action="store_true", help="only the CSS")
    args = parser.parse_args()

    both = not args.dart and not args.css

    failed = False

    if both or args.dart:
        offenders = scan(DART_APP, DART_PATTERN, ALLOWED_DART)
        if offenders:
            failed = True
            print("FAIL: hard-coded colours in the Flutter sources:")
            print("      Use the generated tokens in lib/theme/tokens.dart.")
            for offender in offenders:
                print(f"  {offender}")
        else:
            print(f"OK: no hard-coded colours in {DART_APP.relative_to(ROOT)}")

    if both or args.css:
        offenders = scan(CSS_DIR, CSS_PATTERN, ALLOWED_CSS)
        if offenders:
            failed = True
            print("FAIL: hard-coded colours in the CSS:")
            print("      Use the generated tokens in css/tokens.css.")
            for offender in offenders:
                print(f"  {offender}")
        else:
            print(f"OK: no hard-coded colours in {CSS_DIR.relative_to(ROOT)}")

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
