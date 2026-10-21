import 'package:flutter/material.dart';

import 'tokens.dart';

/// The LINUXKAFE theme, derived entirely from the generated design tokens.
///
/// No colour literal appears in this file — everything comes from
/// `LkColors`, which is generated from `assets/design_tokens.json`.
abstract final class LkTheme {
  static ThemeData light() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: LkColors.primary,
      brightness: Brightness.light,
    ).copyWith(
      primary: LkColors.primary,
      onPrimary: LkColors.text,
      surface: LkColors.bg,
      onSurface: LkColors.text,
      error: LkSemantic.error,
    );

    final base = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: LkColors.bg,
      fontFamily: 'Inter',
    );

    return base.copyWith(
      textTheme: base.textTheme.apply(
        fontFamily: 'Inter',
        bodyColor: LkColors.text,
        displayColor: LkColors.text,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: LkColors.header,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        color: LkColors.bgCard,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          side: const BorderSide(color: LkColors.border),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: LkColors.bg,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: const BorderSide(color: LkColors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: const BorderSide(color: LkColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: const BorderSide(color: LkColors.primary, width: 2),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: LkColors.primary,
          foregroundColor: LkColors.text,
          elevation: 0,
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: LkColors.text,
          side: const BorderSide(color: LkColors.border),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          ),
        ),
      ),
      dividerTheme: const DividerThemeData(color: LkColors.border, space: 1),
      navigationRailTheme: const NavigationRailThemeData(
        backgroundColor: LkColors.bg,
        indicatorColor: LkColors.primary,
        selectedIconTheme: IconThemeData(color: LkColors.text),
        unselectedIconTheme: IconThemeData(color: LkColors.textLight),
      ),
      listTileTheme: const ListTileThemeData(iconColor: LkColors.textLight),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: LkColors.header,
        contentTextStyle: const TextStyle(color: Colors.white),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        ),
      ),
    );
  }

  /// Text style for section headings, matching `--font-heading`.
  static TextStyle get heading => const TextStyle(
        fontFamily: 'Poppins',
        fontWeight: FontWeight.w600,
        color: LkColors.text,
      );

  /// Font family names, matching the `--font-*` design tokens.
  static const String fontBase = 'Inter';
  static const String fontHeading = 'Poppins';

  /// Secondary text, matching `--color-text-light`.
  static TextStyle get muted => const TextStyle(color: LkColors.textLight);
}
