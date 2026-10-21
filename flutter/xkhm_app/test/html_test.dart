import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:xkhm_app/widgets/lk_html.dart';

void main() {
  group('LkHtml', () {
    testWidgets('renders a line break as a newline', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkHtml('Primeira<br />Segunda')),
        ),
      );

      final text = tester.widget<Text>(find.byType(Text));
      final span = text.textSpan!;
      final rendered = span.toPlainText();

      expect(rendered, contains('Primeira'));
      expect(rendered, contains('Segunda'));
      expect(rendered, contains('\n'));
    });

    testWidgets('strips the tags it does not render', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkHtml('<p>Texto <b>forte</b></p>')),
        ),
      );

      final text = tester.widget<Text>(find.byType(Text));
      final rendered = text.textSpan!.toPlainText();

      expect(rendered, contains('forte'));
      expect(rendered, isNot(contains('<b>')));
      expect(rendered, isNot(contains('</p>')));
    });

    testWidgets('decodes the HTML entities', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkHtml('S&atilde;o &amp;&Ccedil;')),
        ),
      );

      final text = tester.widget<Text>(find.byType(Text));
      final rendered = text.textSpan!.toPlainText();

      expect(rendered, contains('São &Ç'));
    });

    testWidgets('keeps the text of a link', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: LkHtml('Ver <a href="https://exemplo.pt">o site</a>'),
          ),
        ),
      );

      final text = tester.widget<Text>(find.byType(Text));
      final rendered = text.textSpan!.toPlainText();

      expect(rendered, contains('o site'));
      expect(rendered, isNot(contains('href')));
    });

    testWidgets('renders plain text unchanged', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(body: LkHtml('Sem marcação nenhuma')),
        ),
      );

      final text = tester.widget<Text>(find.byType(Text));

      expect(text.textSpan!.toPlainText(), 'Sem marcação nenhuma');
    });
  });
}
