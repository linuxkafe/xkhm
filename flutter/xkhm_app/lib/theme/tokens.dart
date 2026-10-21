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
abstract final class LkColors {
  static const Color primary = Color(0xFFF8B400);
  static const Color text = Color(0xFF212121);
  static const Color textLight = Color(0xFF555555);
  static const Color bg = Color(0xFFFFFFFF);
  static const Color bgCard = Color(0xFFFFFFFF);
  static const Color header = Color(0xFF2D2D2D);
  static const Color border = Color(0xFFE0E0E0);
  static const Color link = Color(0xFF0056B3);
}

/// Semantic colours (success / warning / danger / info).
abstract final class LkSemantic {
  static const Color successBg = Color(0xFFD4EDDA);
  static const Color successFg = Color(0xFF155724);
  static const Color warningBg = Color(0xFFFFF3CD);
  static const Color warningFg = Color(0xFF856404);
  static const Color dangerBg = Color(0xFFF8D7DA);
  static const Color dangerFg = Color(0xFF721C24);
  static const Color neutralBg = Color(0xFFE9ECEF);
  static const Color neutralFg = Color(0xFF383D41);
  static const Color error = Color(0xFFDC3545);
  static const Color infoBg = Color(0xFFD1ECF1);
  static const Color infoFg = Color(0xFF0C5460);
  static const Color shadow = Color(0x0000001A);
}

/// Typography families, matching the CSS `--font-*` tokens.
abstract final class LkFonts {
  static const String base = "Inter, system-ui, -apple-system, sans-serif";
  static const String heading = "Poppins, Inter, system-ui, -apple-system, sans-serif";
  static const String mono = "Fira Code, monospace";
}

/// Layout tokens, matching the CSS layout block.
abstract final class LkLayout {
  static const double widthMax = 1100;
  static const double borderRadius = 8;
}
