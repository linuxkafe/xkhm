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

The palette is a **function of the mode**: the source holds a light and a
dark palette with identical keys, and the generator emits both — the CSS as
a `@media (prefers-color-scheme: dark)` block, the Dart as a parallel set of
constants. There is exactly one writer for both halves, so the two clients
cannot drift apart in either mode.

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


# ─── Naming ──────────────────────────────────────────────────────────────────


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


# ─── Colours ─────────────────────────────────────────────────────────────────


def dart_color(value: str) -> str:
    """#fbb202 -> 0xFFFBB202 (Flutter wants the 0xAARRGGBB form).

    An 8-digit CSS value is `#RRGGBBAA` (alpha last), while Dart's
    `Color(0xAARRGGBB)` puts the alpha first. The two orders are swapped
    here — getting it wrong makes a translucent colour fully transparent.

    A 3-digit value is expanded, so the CSS and the Dart representations
    accept exactly the same inputs.
    """
    value = value.lstrip("#")
    if len(value) == 3:
        value = "".join(channel * 2 for channel in value)
    if len(value) == 8:
        return "0x" + value[6:8].upper() + value[0:6].upper()
    if len(value) != 6:
        raise ValueError(f"expected a 3-, 6- or 8-digit hex colour, got {value!r}")
    return "0xFF" + value.upper()


def css_color(value: str) -> str:
    """#fbb202 -> #fbb202; #0000001a -> rgba(0, 0, 0, 0.1)."""
    value = value.lstrip("#")
    if len(value) == 3:
        value = "".join(channel * 2 for channel in value)
    if len(value) == 8:
        red = int(value[0:2], 16)
        green = int(value[2:4], 16)
        blue = int(value[4:6], 16)
        alpha = int(value[6:8], 16) / 255
        return f"rgba({red}, {green}, {blue}, {round(alpha, 3)})"
    return "#" + value.lower()


# ─── Renderers ───────────────────────────────────────────────────────────────


def css_font_stack(face: dict) -> str:
    """
    The CSS of a typeface: the family, and the fallbacks.

    A family with a space in the name has to be quoted — and the
    **whole** stack must not be, or the browser looks for a family
    literally named "Inter, system-ui, sans-serif" and finds nothing.
    That is the bug this function exists to prevent.
    """
    family = face["family"]

    if " " in family:
        family = f'"{family}"'

    fallbacks = list(face.get("fallback", []))

    return ", ".join([family] + fallbacks)


def _css_block(tokens: dict, *, dark: bool) -> str:
    """The body of a `:root` rule, for one mode."""
    colors = tokens["darkColors" if dark else "colors"]
    semantic = tokens.get("darkSemantic" if dark else "semantic", {})
    typography = tokens["typography"]
    layout = tokens["layout"]
    mobile = tokens.get("mobile", {})

    indent = "  " if dark else ""
    body: list[str] = []

    body.append("\t/* Cores */\n")
    for key, value in colors.items():
        comment = " /* Amarelo LinuxKafé */" if key == "color-primary" else ""
        body.append(f"\t{css_var(key)}: {css_color(value)};{comment}\n")

    if semantic:
        body.append("\n\t/* Cores semânticas */\n")
        for key, value in semantic.items():
            body.append(f"\t{css_var(key)}: {css_color(value)};\n")

    body.append("\n\t/* Tipografia */\n")
    for key, value in typography.items():
        body.append(f"\t{css_var(key)}: {css_font_stack(value)};\n")

    body.append("\n\t/* Layout */\n")
    body.append(f'\t{css_var("width-max")}: {layout["width-max"]}px;\n')
    body.append(f'\t{css_var("border-radius")}: {layout["border-radius"]}px;\n')
    body.append(f'\t{css_var("shadow-card")}: {layout["shadow-card"]};\n')

    if mobile and not dark:
        body.append("\n\t/* Mobile */\n")
        body.append(f'\t{css_var("touch-target-min")}: {mobile["touch-target-min"]}px;\n')
        for key in ("breakpoint-sm", "breakpoint-md", "breakpoint-lg", "breakpoint-xl"):
            body.append(f'\t{css_var(key)}: {mobile[key]}px;\n')

    # Indent every non-empty line of the rule body.
    rendered = "".join(
        (indent + line if line.strip() else line)
        for line in "".join(body).splitlines(keepends=True)
    )

    if dark:
        return f"@media (prefers-color-scheme: dark) {{\n  :root {{\n{rendered}  }}\n}}\n"

    return f":root {{\n{rendered}}}\n"


def _font_faces(tokens: dict) -> str:
    """
    The `@font-face` rules.

    The fonts are **served**, not named: before this, the tokens said
    `Inter` and the browser fell back to the system font, because
    nothing ever declared the family.

    They are self-hosted (the CSP is strict, and a request to a third
    party would be data leaving the machine) and they come from the
    application's own files, converted by `tools/build_web_fonts.py`.
    """
    fonts = tokens.get("fonts", {})

    if not fonts:
        return ""

    out: list[str] = []

    for name, face in fonts.items():
        family = face["family"]
        style = face.get("style", "normal")
        weight = face["weight"]
        file = face["file"]

        out.append(
            "@font-face {\n"
            f'\tfont-family: "{family}";\n'
            f"\tfont-style: {style};\n"
            f"\tfont-weight: {weight};\n"
            "\tfont-display: swap;\n"
            f'\tsrc: url("../fonts/{file}") format("woff2");\n'
            f"\tfont-named-instance: {name};\n"
            "}\n"
        )

    return "\n".join(out)


def render_css(tokens: dict) -> str:
    url = tokens["source"]["url"]

    # `color-scheme` tells the browser to theme the scrollbars and the form
    # controls; it belongs to the light block, outside the media query.
    light = _css_block(tokens, dark=False)
    light = light.replace(
        ":root {\n",
        ":root {\n\t/* Tell the browser to theme the scrollbars and form controls. */\n"
        "\tcolor-scheme: light dark;\n\n",
        1,
    )

    return "".join(
        [
            BANNER_CSS.format(url=url),
            "\n",
            _font_faces(tokens),
            "\n",
            light,
            "\n",
            _css_block(tokens, dark=True),
        ]
    )


def _dart_palette(palette_type: str, holder: str, light: dict, dark: dict) -> str:
    """A palette value type with a light and a dark instance."""
    out = [f"/// The {palette_type} palette — one instance per mode.\n"]
    out.append("@immutable\n")
    out.append(f"class {palette_type} {{\n")
    out.append(f"  const {palette_type}({{\n")
    for key in light:
        out.append(f"    required this.{dart_name(key)},\n")
    out.append("  });\n\n")
    for key in light:
        out.append(f"  final Color {dart_name(key)};\n")
    out.append("}\n\n")

    out.append(f"/// The {palette_type} values for each mode.\n")
    out.append(f"abstract final class {holder} {{\n")
    out.append(f"  static const {palette_type} light = {palette_type}(\n")
    for key, value in light.items():
        out.append(f"    {dart_name(key)}: Color({dart_color(value)}),\n")
    out.append("  );\n\n")
    out.append(f"  static const {palette_type} dark = {palette_type}(\n")
    for key, value in dark.items():
        out.append(f"    {dart_name(key)}: Color({dart_color(value)}),\n")
    out.append("  );\n")
    out.append("}\n\n")

    return "".join(out)


def render_dart(tokens: dict) -> str:
    url = tokens["source"]["url"]
    mobile = tokens.get("mobile", {})

    out = [BANNER_DART.format(url=url), "\n"]
    out.append("import 'package:flutter/material.dart';\n\n")
    out.append("/// LINUXKAFE design tokens.\n")
    out.append("///\n")
    out.append("/// Generated from `assets/design_tokens.json`. Never edit by hand —\n")
    out.append("/// run `make tokens` instead.\n")
    out.append("///\n")
    out.append("/// The palette is a function of the mode: every palette type has a\n")
    out.append("/// `light` and a `dark` instance, and the theme picks the one the\n")
    out.append("/// operating system asks for.\n\n")

    out.append(
        _dart_palette(
            "LkPalette",
            "LkColors",
            tokens["colors"],
            tokens["darkColors"],
        )
    )
    out.append(
        _dart_palette(
            "LkSemanticPalette",
            "LkSemantic",
            tokens.get("semantic", {}),
            tokens.get("darkSemantic", {}),
        )
    )

    out.append("/// Typography families, matching the CSS `--font-*` tokens.\n")
    out.append("abstract final class LkFonts {\n")
    for key, name in (("font-base", "base"), ("font-heading", "heading"), ("font-mono", "mono")):
        face = tokens["typography"][key]
        out.append(f'  static const String {name} = "{face["family"]}";\n')
        fallbacks = ", ".join(f'"{f}"' for f in face.get("fallback", []))
        out.append(f"  static const List<String> {name}Fallback = <String>[{fallbacks}];\n")
    out.append("}\n\n")

    out.append("/// Layout tokens, matching the CSS layout block.\n")
    out.append("abstract final class LkLayout {\n")
    out.append(f"  static const double widthMax = {tokens['layout']['width-max']};\n")
    out.append(f"  static const double borderRadius = {tokens['layout']['border-radius']};\n")
    if mobile:
        out.append(
            f"  static const double touchTargetMin = {mobile['touch-target-min']};\n"
        )
    out.append("}\n")

    if mobile:
        out.append("\n/// Responsive breakpoints, matching the CSS `--breakpoint-*` tokens.\n")
        out.append("abstract final class LkBreakpoints {\n")
        for key in ("breakpoint-sm", "breakpoint-md", "breakpoint-lg", "breakpoint-xl"):
            out.append(f"  static const double {camel(key)} = {mobile[key]};\n")
        out.append("}\n")

    return "".join(out)


# ─── Driver ──────────────────────────────────────────────────────────────────


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


def load_tokens() -> dict:
    if not SOURCE.exists():
        sys.exit(f"error: token source not found: {SOURCE}")
    with SOURCE.open(encoding="utf-8") as fh:
        tokens = json.load(fh)

    # The dark palette must cover exactly the same keys as the light one —
    # a missing key would silently fall back to the light value.
    for light_key, dark_key in (("colors", "darkColors"), ("semantic", "darkSemantic")):
        light = set(tokens.get(light_key, {}).keys())
        dark = set(tokens.get(dark_key, {}).keys())
        if light != dark:
            missing = sorted(light - dark)
            extra = sorted(dark - light)
            sys.exit(
                f"error: '{dark_key}' must define the same keys as '{light_key}'.\n"
                f"  missing: {missing}\n"
                f"  extra:   {extra}"
            )

    return tokens


if __name__ == "__main__":
    sys.exit(main())
