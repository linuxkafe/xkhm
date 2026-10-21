#!/usr/bin/env python3
"""
Generate the design-token outputs from the single source of truth.

    assets/design_tokens.json  ──►  public/templates/default/css/tokens.css
                               └─►  flutter/xkhm_app/lib/theme/tokens.dart

Why a generator instead of two hand-written files: the operator requires the
PHP/CSS front-end and the Flutter application to share "the same colour
scheme". Two hand-maintained token lists provably drift the first time
somebody edits one of them. A generator makes the drift *detectable*
(`make tokens-check` diffs the regenerated output against the committed one).

Usage:
    python3 tools/sync_tokens.py            # write the outputs
    python3 tools/sync_tokens.py --check    # fail if the outputs are stale
"""

from __future__ import annotations

import argparse
import json
import pathlib
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
SOURCE = ROOT / "assets" / "design_tokens.json"
CSS_OUTPUT = ROOT / "public" / "templates" / "default" / "css" / "tokens.css"
DART_OUTPUT = ROOT / "flutter" / "xkhm_app" / "lib" / "theme" / "tokens.dart"

BANNER_CSS = """/*
 * LINUXKAFE design tokens — single source of truth for the colour scheme.
 *
 * Derived from the live theme stylesheet at
 *   {url}
 *
 * NOTE: generated file. Do not edit by hand.
 *       Edit `assets/design_tokens.json` and run `make tokens`.
 */
"""

BANNER_DART = """// GENERATED FILE — do not edit by hand.
//
// Source of truth: assets/design_tokens.json
// Regenerate with: make tokens
//
// LINUXKAFE design tokens, derived from the live theme stylesheet at
// {url}
"""


def camel(name: str) -> str:
    """`color-text-light` -> `colorTextLight`; `width-max` -> `widthMax`."""
    parts = name.split("-")
    return parts[0] + "".join(p[:1].upper() + p[1:] for p in parts[1:])


def dart_name(name: str) -> str:
    """`color-primary` -> `primary` (the `color-` prefix is implied)."""
    if name.startswith("color-"):
        name = name[len("color-"):]
    return camel(name)


def css_var(name: str) -> str:
    """`color-primary` -> `--color-primary`."""
    return name if name.startswith("--") else "--" + name


def load_tokens() -> dict:
    if not SOURCE.exists():
        sys.exit(f"error: token source not found: {SOURCE}")
    with SOURCE.open(encoding="utf-8") as fh:
        return json.load(fh)


def render_css(tokens: dict) -> str:
    colors = tokens["colors"]
    typography = tokens["typography"]
    layout = tokens["layout"]
    url = tokens["source"]["url"]

    out = [BANNER_CSS.format(url=url), "\n:root {\n"]
    out.append("\n\t/* Cores */\n")
    for key, value in colors.items():
        comment = " /* Amarelo LinuxKafé */" if key == "color-primary" else ""
        out.append(f"\t{css_var(key)}: {value};{comment}\n")

    out.append("\n\t/* Tipografia */\n")
    for key, value in typography.items():
        out.append(f'\t{css_var(key)}: "{value}";\n')

    out.append("\n\t/* Layout */\n")
    out.append(f'\t{css_var("width-max")}: {layout["width-max"]}px;\n')
    out.append(f'\t{css_var("border-radius")}: {layout["border-radius"]}px;\n')
    out.append(f'\t{css_var("shadow-card")}: {layout["shadow-card"]};\n')
    out.append("}\n")

    return "".join(out)


def dart_color(value: str) -> str:
    """#f8b400 -> 0xFFF8B400 (Flutter wants the 0xAARRGGBB form)."""
    value = value.lstrip("#")
    if len(value) != 6:
        raise ValueError(f"expected a 6-digit hex colour, got {value!r}")
    return "0xFF" + value.upper()


def render_dart(tokens: dict) -> str:
    colors = tokens["colors"]
    typography = tokens["typography"]
    layout = tokens["layout"]
    url = tokens["source"]["url"]

    out = [BANNER_DART.format(url=url), "\n"]
    out.append("import 'package:flutter/material.dart';\n\n")
    out.append("/// LINUXKAFE design tokens.\n")
    out.append("///\n")
    out.append("/// Generated from `assets/design_tokens.json`. Never edit by hand —\n")
    out.append("/// run `make tokens` instead.\n")
    out.append("abstract final class LkColors {\n")
    for key, value in colors.items():
        out.append(f"  static const Color {dart_name(key)} = Color({dart_color(value)});\n")
    out.append("}\n\n")

    out.append("/// Typography families, matching the CSS `--font-*` tokens.\n")
    out.append("abstract final class LkFonts {\n")
    out.append(f'  static const String base = "{typography["font-base"]}";\n')
    out.append(f'  static const String heading = "{typography["font-heading"]}";\n')
    out.append(f'  static const String mono = "{typography["font-mono"]}";\n')
    out.append("}\n\n")

    out.append("/// Layout tokens, matching the CSS layout block.\n")
    out.append("abstract final class LkLayout {\n")
    out.append(f"  static const double widthMax = {layout['width-max']};\n")
    out.append(f"  static const double borderRadius = {layout['border-radius']};\n")
    out.append("}\n")

    return "".join(out)


def write_or_check(path: pathlib.Path, content: str, check: bool) -> bool:
    """Returns True when the file is up to date."""
    current = path.read_text(encoding="utf-8") if path.exists() else None

    if current == content:
        print(f"  [OK]    {path.relative_to(ROOT)}")
        return True

    if check:
        print(f"  [STALE] {path.relative_to(ROOT)} — run `make tokens`")
        return False

    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")
    print(f"  [WRITE] {path.relative_to(ROOT)}")
    return True


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--check",
        action="store_true",
        help="fail when the generated outputs are stale instead of rewriting them",
    )
    args = parser.parse_args()

    tokens = load_tokens()

    print("Design tokens")
    ok = True
    ok &= write_or_check(CSS_OUTPUT, render_css(tokens), args.check)
    ok &= write_or_check(DART_OUTPUT, render_dart(tokens), args.check)

    if args.check and not ok:
        print("\nFAIL: generated token files are out of date. Run `make tokens`.")
        return 1

    print("\nOK: design tokens are in sync")
    return 0


if __name__ == "__main__":
    sys.exit(main())
