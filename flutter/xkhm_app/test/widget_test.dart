import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:xkhm_app/models/models.dart';
import 'package:xkhm_app/theme/tokens.dart';
import 'package:xkhm_app/widgets/lk_widgets.dart';

void main() {
  group('Design tokens', () {
    test('expose the LINUXKAFE colour scheme', () {
      expect(LkColors.primary, const Color(0xFFF8B400));
      expect(LkColors.header, const Color(0xFF2D2D2D));
      expect(LkColors.text, const Color(0xFF212121));
      expect(LkColors.link, const Color(0xFF0056B3));
      expect(LkColors.border, const Color(0xFFE0E0E0));
    });

    test('expose the layout tokens', () {
      expect(LkLayout.widthMax, 1100);
      expect(LkLayout.borderRadius, 8);
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
        'client': {'id': 2, 'firstname': 'A', 'lastname': 'B', 'email': 'a@b.c'},
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
    testWidgets('LkStatusBadge renders the label', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkStatusBadge(label: 'Paid')),
        ),
      );

      expect(find.text('Paid'), findsOneWidget);
    });

    testWidgets('LkEmptyState renders the message', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkEmptyState(message: 'Sem dados')),
        ),
      );

      expect(find.text('Sem dados'), findsOneWidget);
    });

    testWidgets('LkCard renders its title', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: LkCard(title: 'Painel', child: Text('conteúdo')),
          ),
        ),
      );

      expect(find.text('Painel'), findsOneWidget);
      expect(find.text('conteúdo'), findsOneWidget);
    });
  });
}
