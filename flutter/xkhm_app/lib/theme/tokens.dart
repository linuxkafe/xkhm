// GENERATED FILE — do not edit by hand.
//
// Source of truth: assets/design_tokens.json
// Regenerate with: make tokens
//
// LINUXKAFE design tokens, derived from the live theme stylesheet at
// http://www.linuxkafe.com/wp-content/themes/linuxkafe/style.css

import 'package:flutter/material.dart';

/// LINUXKAFE design tokens.
///
/// Generated from `assets/design_tokens.json`. Never edit by hand —
/// run `make tokens` instead.
///
/// The palette is a function of the mode: every palette type has a
/// `light` and a `dark` instance, and the theme picks the one the
/// operating system asks for.

/// The LkPalette palette — one instance per mode.
@immutable
class LkPalette {
  const LkPalette({
    required this.primary,
    required this.primaryHover,
    required this.onPrimary,
    required this.text,
    required this.textLight,
    required this.bg,
    required this.bgCard,
    required this.header,
    required this.onHeader,
    required this.border,
    required this.link,
    required this.logoPlate,
  });

  final Color primary;
  final Color primaryHover;
  final Color onPrimary;
  final Color text;
  final Color textLight;
  final Color bg;
  final Color bgCard;
  final Color header;
  final Color onHeader;
  final Color border;
  final Color link;
  final Color logoPlate;
}

/// The LkPalette values for each mode.
abstract final class LkColors {
  static const LkPalette light = LkPalette(
    primary: Color(0xFFFBB202),
    primaryHover: Color(0xFFE6A000),
    onPrimary: Color(0xFF212121),
    text: Color(0xFF212121),
    textLight: Color(0xFF555555),
    bg: Color(0xFFFFFFFF),
    bgCard: Color(0xFFFFFFFF),
    header: Color(0xFF2D2D2D),
    onHeader: Color(0xFFFFFFFF),
    border: Color(0xFFE0E0E0),
    link: Color(0xFF0056B3),
    logoPlate: Color(0xFFFFFFFF),
  );

  static const LkPalette dark = LkPalette(
    primary: Color(0xFFFBB202),
    primaryHover: Color(0xFFE6A000),
    onPrimary: Color(0xFF1A1A1A),
    text: Color(0xFFE8E8E8),
    textLight: Color(0xFFA0A0A0),
    bg: Color(0xFF141414),
    bgCard: Color(0xFF1E1E1E),
    header: Color(0xFF0D0D0D),
    onHeader: Color(0xFFF0F0F0),
    border: Color(0xFF333333),
    link: Color(0xFF66B2FF),
    logoPlate: Color(0xFFFFFFFF),
  );
}

/// The LkSemanticPalette palette — one instance per mode.
@immutable
class LkSemanticPalette {
  const LkSemanticPalette({
    required this.successBg,
    required this.successFg,
    required this.onSuccess,
    required this.warningBg,
    required this.warningFg,
    required this.dangerBg,
    required this.dangerFg,
    required this.neutralBg,
    required this.neutralFg,
    required this.error,
    required this.infoBg,
    required this.infoFg,
    required this.shadow,
  });

  final Color successBg;
  final Color successFg;
  final Color onSuccess;
  final Color warningBg;
  final Color warningFg;
  final Color dangerBg;
  final Color dangerFg;
  final Color neutralBg;
  final Color neutralFg;
  final Color error;
  final Color infoBg;
  final Color infoFg;
  final Color shadow;
}

/// The LkSemanticPalette values for each mode.
abstract final class LkSemantic {
  static const LkSemanticPalette light = LkSemanticPalette(
    successBg: Color(0xFFD4EDDA),
    successFg: Color(0xFF155724),
    onSuccess: Color(0xFFFFFFFF),
    warningBg: Color(0xFFFFF3CD),
    warningFg: Color(0xFF856404),
    dangerBg: Color(0xFFF8D7DA),
    dangerFg: Color(0xFF721C24),
    neutralBg: Color(0xFFE9ECEF),
    neutralFg: Color(0xFF383D41),
    error: Color(0xFFDC3545),
    infoBg: Color(0xFFD1ECF1),
    infoFg: Color(0xFF0C5460),
    shadow: Color(0x1A000000),
  );

  static const LkSemanticPalette dark = LkSemanticPalette(
    successBg: Color(0xFF16351F),
    successFg: Color(0xFF7EE2A8),
    onSuccess: Color(0xFF16351F),
    warningBg: Color(0xFF3A2F10),
    warningFg: Color(0xFFF0C674),
    dangerBg: Color(0xFF3D1A1A),
    dangerFg: Color(0xFFF28B82),
    neutralBg: Color(0xFF2A2A2A),
    neutralFg: Color(0xFFC8C8C8),
    error: Color(0xFFF28B82),
    infoBg: Color(0xFF12303A),
    infoFg: Color(0xFF7FD1E8),
    shadow: Color(0x66000000),
  );
}

/// Typography families, matching the CSS `--font-*` tokens.
abstract final class LkFonts {
  static const String base = "Inter";
  static const List<String> baseFallback = <String>["system-ui", "-apple-system", "sans-serif"];
  static const String mono = "Fira Code";
  static const List<String> monoFallback = <String>["ui-monospace", "SFMono-Regular", "Menlo", "Consolas", "monospace"];
}

/// The type scale, matching the `--type-*` design tokens.
abstract final class LkTypeScale {
  static const double displaySize = 32;
  static const int displayWeight = 700;
  static const double displayLineHeight = 1.2;
  static const double h1Size = 24;
  static const int h1Weight = 600;
  static const double h1LineHeight = 1.3;
  static const double h2Size = 20;
  static const int h2Weight = 600;
  static const double h2LineHeight = 1.3;
  static const double h3Size = 16;
  static const int h3Weight = 600;
  static const double h3LineHeight = 1.4;
  static const double bodySize = 14;
  static const int bodyWeight = 400;
  static const double bodyLineHeight = 1.5;
  static const double smallSize = 12;
  static const int smallWeight = 400;
  static const double smallLineHeight = 1.5;
}
/// Layout tokens, matching the CSS layout block.
abstract final class LkLayout {
  static const double widthMax = 1100;
  static const double borderRadius = 8;
  static const double touchTargetMin = 44;
}

/// Responsive breakpoints, matching the CSS `--breakpoint-*` tokens.
abstract final class LkBreakpoints {
  static const double breakpointSm = 576;
  static const double breakpointMd = 768;
  static const double breakpointLg = 992;
  static const double breakpointXl = 1200;
}
