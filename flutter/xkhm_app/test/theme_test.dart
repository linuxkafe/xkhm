import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:xkhm_app/theme/lk_theme.dart';
import 'package:xkhm_app/theme/tokens.dart';

/// The relative luminance of a colour (WCAG 2.1).
double _luminance(Color color) => color.computeLuminance();

/// The contrast ratio between two colours (WCAG 2.1).
double _contrast(Color a, Color b) {
  final la = _luminance(a);
  final lb = _luminance(b);
  final lighter = la > lb ? la : lb;
  final darker = la > lb ? lb : la;
  return (lighter + 0.05) / (darker + 0.05);
}

void main() {
  group('Theme contrast', () {
    for (final (name, theme) in [
      ('light', LkTheme.light()),
      ('dark', LkTheme.dark()),
    ]) {
      group(name, () {
        test('the app bar text is readable on the header', () {
          final background = theme.appBarTheme.backgroundColor!;
          final foreground = theme.appBarTheme.foregroundColor!;

          expect(
            _contrast(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: 'app bar: $foreground on $background',
          );
        });

        test('the body text is readable on the background', () {
          expect(
            _contrast(
              theme.colorScheme.onSurface,
              theme.colorScheme.surface,
            ),
            greaterThanOrEqualTo(4.5),
          );
        });

        test('the primary button is readable on the brand yellow', () {
          final background = theme.colorScheme.primary;
          final foreground = theme.colorScheme.onPrimary;

          expect(
            _contrast(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: 'primary button: $foreground on $background',
          );
        });

        test('the secondary text is readable on the background', () {
          expect(
            _contrast(
              theme.colorScheme.onSurfaceVariant,
              theme.colorScheme.surface,
            ),
            greaterThanOrEqualTo(4.5),
            reason: 'onSurfaceVariant is the tokenised secondary text',
          );
        });

        test('the selected list tile is readable', () {
          final tile = theme.listTileTheme;
          final background = tile.selectedTileColor!;
          final foreground = tile.selectedColor!;

          expect(
            _contrast(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: 'selected tile: $foreground on $background',
          );
        });

        test('the selected chip is readable', () {
          final chip = theme.chipTheme;
          final background = chip.selectedColor ??
              theme.colorScheme.surface;
          final foreground = chip.secondaryLabelStyle?.color ??
              chip.labelStyle?.color ??
              theme.colorScheme.onSurface;

          expect(
            _contrast(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: 'selected chip: $foreground on $background',
          );
        });

        test('the navigation rail selection is readable', () {
          final rail = theme.navigationRailTheme;
          final background = rail.indicatorColor!;
          final foreground = rail.selectedIconTheme?.color ??
              theme.colorScheme.onSurface;

          expect(
            _contrast(foreground, background),
            greaterThanOrEqualTo(4.5),
            reason: 'rail: $foreground on $background',
          );
        });

        test('the error colour is readable on the background', () {
          expect(
            _contrast(
              theme.colorScheme.error,
              theme.colorScheme.surface,
            ),
            greaterThanOrEqualTo(3.0),
          );
        });
      });
    }
  });

  group('Token contract', () {
    test('the two palettes cover the same keys', () {
      // The generator enforces this; the test makes it visible.
      expect(LkColors.light.primary, LkColors.dark.primary);
      expect(LkColors.light.onPrimary, isNot(LkColors.dark.onPrimary));
    });

    test('the semantic palettes cover the same keys', () {
      expect(LkSemantic.light.successBg, isNot(LkSemantic.dark.successBg));
      expect(LkSemantic.light.error, isNot(LkSemantic.dark.error));
    });

    test('the brand yellow is the identity in both modes', () {
      expect(LkColors.light.primary, const Color(0xFFFBB202));
      expect(LkColors.dark.primary, const Color(0xFFFBB202));
    });

    test('the fonts come from the generated tokens', () {
      expect(LkFonts.base, contains('Inter'));
      expect(LkFonts.heading, contains('Poppins'));
      expect(LkTheme.fontBase, LkFonts.base);
      expect(LkTheme.fontHeading, LkFonts.heading);
    });
  });
}
