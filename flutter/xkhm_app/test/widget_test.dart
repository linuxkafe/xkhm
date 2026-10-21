import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:xkhm_app/models/models.dart';
import 'package:xkhm_app/theme/lk_theme.dart';
import 'package:xkhm_app/theme/tokens.dart';
import 'package:xkhm_app/widgets/lk_widgets.dart';

void main() {
  group('Design tokens', () {
    test('expose the LINUXKAFE colour scheme in light mode', () {
      expect(LkColors.light.primary, const Color(0xFFF8B400));
      expect(LkColors.light.header, const Color(0xFF2D2D2D));
      expect(LkColors.light.text, const Color(0xFF212121));
      expect(LkColors.light.link, const Color(0xFF0056B3));
      expect(LkColors.light.border, const Color(0xFFE0E0E0));
    });

    test('expose a dark palette with the same shape', () {
      // The brand yellow is the identity — it does not change with the mode.
      expect(LkColors.dark.primary, LkColors.light.primary);

      // The surfaces do change.
      expect(LkColors.dark.bg, isNot(LkColors.light.bg));
      expect(LkColors.dark.text, isNot(LkColors.light.text));
    });

    test('the dark palette keeps a readable contrast', () {
      double luminance(Color color) => color.computeLuminance();

      // Body text on the page background.
      expect(
        luminance(LkColors.dark.text),
        greaterThan(luminance(LkColors.dark.bg)),
      );

      // Secondary text must still be readable.
      expect(
        luminance(LkColors.dark.textLight),
        greaterThan(luminance(LkColors.dark.bg)),
      );
    });

    test('expose the layout and mobile tokens', () {
      expect(LkLayout.widthMax, 1100);
      expect(LkLayout.borderRadius, 8);
      expect(LkLayout.touchTargetMin, 44);
      expect(LkBreakpoints.breakpointMd, 768);
    });
  });

  group('Theme', () {
    test('light and dark build without error', () {
      expect(LkTheme.light().brightness, Brightness.light);
      expect(LkTheme.dark().brightness, Brightness.dark);
    });

    test('the dark theme uses the dark surfaces', () {
      final dark = LkTheme.dark();

      expect(dark.scaffoldBackgroundColor, LkColors.dark.bg);
      expect(dark.colorScheme.surface, LkColors.dark.bg);
      expect(dark.colorScheme.onSurface, LkColors.dark.text);
    });

    test('the light theme uses the light surfaces', () {
      final light = LkTheme.light();

      expect(light.scaffoldBackgroundColor, LkColors.light.bg);
      expect(light.colorScheme.onSurface, LkColors.light.text);
    });

    test('the two themes differ', () {
      expect(
        LkTheme.light().scaffoldBackgroundColor,
        isNot(LkTheme.dark().scaffoldBackgroundColor),
      );
    });

    test('the brand primary is the same in both modes', () {
      expect(LkTheme.light().colorScheme.primary, LkTheme.dark().colorScheme.primary);
    });
  });

  group('Models', () {
    test('LkClient joins the name parts', () {
      final client = LkClient.fromJson(const {
        'id': 2,
        'firstname': 'Linuxkafe',
        'lastname': 'Hosting',
        'email': 'linuxkafe@linuxkafe.com',
      });

      expect(client.id, 2);
      expect(client.fullName, 'Linuxkafe Hosting');
      expect(client.email, 'linuxkafe@linuxkafe.com');
    });

    test('LkClient tolerates a missing payload', () {
      final client = LkClient.fromJson(const {});

      expect(client.id, 0);
      expect(client.fullName, '');
    });

    test('LkSummary parses the dashboard payload', () {
      final summary = LkSummary.fromJson(const {
        'client': {
          'id': 2,
          'firstname': 'A',
          'lastname': 'B',
          'email': 'a@b.c'
        },
        'billing': {'creditBalance': 12.5, 'unpaidTotal': 149.9},
        'counts': {
          'invoices': 2,
          'unpaidInvoices': 1,
          'tickets': 1,
          'openTickets': 1,
          'domains': 3,
        },
      });

      expect(summary.billing.creditBalance, 12.5);
      expect(summary.billing.unpaidTotal, 149.9);
      expect(summary.counts.invoices, 2);
      expect(summary.counts.domains, 3);
    });

    test('LkInvoice parses numeric strings', () {
      final invoice = LkInvoice.fromJson(const {
        'id': 7,
        'number': '7',
        'status': 'Paid',
        'total': '24.99',
      });

      expect(invoice.id, 7);
      expect(invoice.total, 24.99);
      expect(invoice.isPaid, isTrue);
    });

    test('LkTicket detects a closed ticket', () {
      final open = LkTicket.fromJson(const {'id': 1, 'status': 'Open'});
      final closed = LkTicket.fromJson(const {'id': 2, 'status': 'Closed'});

      expect(open.isClosed, isFalse);
      expect(closed.isClosed, isTrue);
    });

    test('LkServerStatus parses the status payload', () {
      final status = LkServerStatus.fromJson(const {
        'uptime': '3d 4h',
        'load': '0.42',
        'memory_usage': '12 MB',
        'database': 'connected',
      });

      expect(status.uptime, '3d 4h');
      expect(status.database, 'connected');
    });
  });

  group('Widgets', () {
    Widget wrap(Widget child, {Brightness brightness = Brightness.light}) {
      return MaterialApp(
        theme: brightness == Brightness.light ? LkTheme.light() : LkTheme.dark(),
        home: Scaffold(body: child),
      );
    }

    testWidgets('LkStatusBadge renders the label', (tester) async {
      await tester.pumpWidget(
        wrap(const LkStatusBadge(label: 'Paid')),
      );

      expect(find.text('Paid'), findsOneWidget);
    });

    testWidgets('LkStatusBadge follows the theme in dark mode', (tester) async {
      await tester.pumpWidget(
        wrap(const LkStatusBadge(label: 'Paid'), brightness: Brightness.dark),
      );

      expect(find.text('Paid'), findsOneWidget);
    });

    testWidgets('LkEmptyState renders the message', (tester) async {
      await tester.pumpWidget(
        wrap(const LkEmptyState(message: 'Sem dados')),
      );

      expect(find.text('Sem dados'), findsOneWidget);
    });

    testWidgets('LkCard renders its title', (tester) async {
      await tester.pumpWidget(
        wrap(
          const LkCard(title: 'Painel', child: Text('conteúdo')),
        ),
      );

      expect(find.text('Painel'), findsOneWidget);
      expect(find.text('conteúdo'), findsOneWidget);
    });

    testWidgets('LkCard repaints when the mode changes', (tester) async {
      Widget build(Brightness brightness) => MaterialApp(
            theme: brightness == Brightness.light
                ? LkTheme.light()
                : LkTheme.dark(),
            home: const Scaffold(body: LkCard(child: Text('x'))),
          );

      await tester.pumpWidget(build(Brightness.light));
      final lightCard = tester.widget<Container>(find.byType(Container).first);

      await tester.pumpWidget(build(Brightness.dark));
      await tester.pumpAndSettle();
      final darkCard = tester.widget<Container>(find.byType(Container).first);

      expect(lightCard.decoration, isNot(darkCard.decoration));
    });
  });
}
