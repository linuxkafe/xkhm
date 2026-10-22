import 'package:flutter/material.dart';

import 'tokens.dart';

/// The LINUXKAFE theme, derived entirely from the generated design tokens.
///
/// No colour literal appears in this file — everything comes from
/// `LkColors` / `LkColorsDark`, which are generated from
/// `assets/design_tokens.json`.
///
/// The theme follows the **system** preference (`ThemeMode.system`): the
/// operating system decides whether the application is in light or dark
/// mode, and a change is applied without restarting the application.
abstract final class LkTheme {
  /// The light theme.
  static ThemeData light() => _build(
        colors: LkColors.light,
        semantic: LkSemantic.light,
        brightness: Brightness.light,
      );

  /// The dark theme.
  static ThemeData dark() => _build(
        colors: LkColors.dark,
        semantic: LkSemantic.dark,
        brightness: Brightness.dark,
      );

  /// One builder, two palettes — the Dart counterpart of the CSS
  /// `@media (prefers-color-scheme: dark)` block.
  static ThemeData _build({
    required LkPalette colors,
    required LkSemanticPalette semantic,
    required Brightness brightness,
  }) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: colors.primary,
      brightness: brightness,
    ).copyWith(
      primary: colors.primary,
      onPrimary: colors.onPrimary,
      surface: colors.bg,
      onSurface: colors.text,
      // The secondary text is a token, not a seed-derived colour — the two
      // clients must share the same value.
      onSurfaceVariant: colors.textLight,
      error: semantic.error,
      onError: colors.onPrimary,
      outline: colors.border,
    );

    final base = ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: colors.bg,
      fontFamily: LkFonts.base,
      fontFamilyFallback: LkFonts.baseFallback,
    );

    return base.copyWith(
      textTheme: base.textTheme.apply(
        fontFamily: LkFonts.base,
        fontFamilyFallback: LkFonts.baseFallback,
        bodyColor: colors.text,
        displayColor: colors.text,
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: colors.header,
        foregroundColor: colors.onHeader,
        elevation: 0,
        centerTitle: false,
      ),
      cardTheme: CardThemeData(
        color: colors.bgCard,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          side: BorderSide(color: colors.border),
        ),
      ),
      dividerTheme: DividerThemeData(color: colors.border, space: 1),
      iconTheme: IconThemeData(color: colors.textLight),
      listTileTheme: ListTileThemeData(
        iconColor: colors.textLight,
        selectedColor: colors.onPrimary,
        selectedTileColor: colors.primary.withValues(alpha: 0.16),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: colors.bg,
        selectedColor: colors.primary,
        labelStyle: TextStyle(color: colors.text),
        secondaryLabelStyle: TextStyle(color: colors.onPrimary),
        side: BorderSide(color: colors.border),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: colors.bg,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        labelStyle: TextStyle(color: colors.textLight),
        hintStyle: TextStyle(color: colors.textLight),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: BorderSide(color: colors.border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: BorderSide(color: colors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: BorderSide(color: colors.primary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: BorderSide(color: semantic.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          borderSide: BorderSide(color: semantic.error, width: 2),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: colors.primary,
          foregroundColor: colors.onPrimary,
          elevation: 0,
          // A 48 px minimum height keeps the target comfortably tappable.
          minimumSize: const Size(64, LkLayout.touchTargetMin),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: colors.text,
          side: BorderSide(color: colors.border),
          minimumSize: const Size(64, LkLayout.touchTargetMin),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(LkLayout.borderRadius),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: colors.primary,
          minimumSize: const Size(48, LkLayout.touchTargetMin),
        ),
      ),
      navigationRailTheme: NavigationRailThemeData(
        backgroundColor: colors.bg,
        indicatorColor: colors.primary,
        selectedIconTheme: IconThemeData(color: colors.onPrimary),
        unselectedIconTheme: IconThemeData(color: colors.textLight),
        selectedLabelTextStyle: TextStyle(color: colors.text),
        unselectedLabelTextStyle: TextStyle(color: colors.textLight),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: colors.bgCard,
        indicatorColor: colors.primary,
        elevation: 0,
        labelTextStyle: WidgetStatePropertyAll(TextStyle(color: colors.text)),
      ),
      drawerTheme: DrawerThemeData(
        backgroundColor: colors.bgCard,
        elevation: 0,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: colors.bgCard,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: colors.bgCard,
        surfaceTintColor: Colors.transparent,
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: colors.header,
        contentTextStyle: TextStyle(color: colors.onHeader),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        ),
      ),
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: colors.header,
          borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        ),
        textStyle: TextStyle(color: colors.onHeader),
      ),
    );
  }

  /// Text style for section headings, matching `--font-heading`.
  static TextStyle heading(BuildContext context) => TextStyle(
        fontFamily: LkTheme.fontHeading,
        fontFamilyFallback: LkTheme.fontHeadingFallback,
        fontWeight: FontWeight.w600,
        color: Theme.of(context).colorScheme.onSurface,
      );

  /// Font family names, matching the `--font-*` design tokens.
  static const String fontBase = LkFonts.base;
  static const String fontHeading = LkFonts.heading;

  /// The fallback chains, matching the tokens.
  static const List<String> fontBaseFallback = LkFonts.baseFallback;
  static const List<String> fontHeadingFallback = LkFonts.headingFallback;

  /// Secondary text, matching `--color-text-light`.
  static TextStyle muted(BuildContext context) =>
      TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant);
}
