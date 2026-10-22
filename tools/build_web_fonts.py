#!/usr/bin/env python3
"""
The web fonts, derived from the application's own font files.

The application **embeds** the fonts it uses (Inter and Poppins, in
`flutter/xkhm_app/assets/fonts/`). The PHP side — the site and the
administration panel — named the same families in its design tokens,
but never **served** them: there was no `@font-face`, so the browser
fell back to the system font. The same brand, two typefaces.

This tool derives the **woff2** files for the web from the very same
`.ttf` files, so that there is one source of truth — the same pattern
as the design tokens (one source, several outputs).

The fonts are **not** fetched from a CDN: the Content-Security-Policy
is strict, and a request to a third party would be data leaving the
machine. They are self-hosted.

Run:

    python3 tools/build_web_fonts.py
"""

from __future__ import annotations

import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent

#: Where the application keeps its fonts.
SOURCE = ROOT / "flutter" / "xkhm_app" / "assets" / "fonts"

#: Where the web gets them.
TARGET = ROOT / "public" / "templates" / "default" / "fonts"

#: The files to convert. The application's own list — the mono family
#: is not embedded, and the token falls back to the system's.
FONTS = [
    "Inter-Regular",
    "Inter-SemiBold",
    "Inter-Bold",
    "Poppins-SemiBold",
]


def convert(name: str) -> bool:
    """Convert one font, and return whether it worked."""
    source = SOURCE / f"{name}.ttf"
    target = TARGET / f"{name}.woff2"

    if not source.is_file():
        print(f"  [ERRO] {source} nao existe")
        return False

    try:
        from fontTools.ttLib import TTFont
    except ImportError:
        print("  [ERRO] o fontTools nao esta instalado: pip install fonttools brotli")
        return False

    TARGET.mkdir(parents=True, exist_ok=True)

    font = TTFont(source)
    font.flavor = "woff2"
    font.save(target)

    ratio = target.stat().st_size / source.stat().st_size

    print(f"  {name}.ttf ({source.stat().st_size:,} B) → {name}.woff2 ({target.stat().st_size:,} B) — {ratio:.0%}")

    return True


def main() -> int:
    print("As fontes web, derivadas das da aplicacao:")

    failed = 0

    for name in FONTS:
        if not convert(name):
            failed += 1

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
