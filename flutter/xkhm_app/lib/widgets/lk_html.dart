import 'package:flutter/material.dart';

/// Renders the small subset of HTML that the WHMCS content carries.
///
/// The announcements, the knowledge base articles and the ticket messages are
/// stored as HTML (`<br />`, `<b>`, `<i>`, `<a href>`, `<p>`). Rendering them
/// as plain text shows the markup to the user; a full HTML engine is far too
/// much for what these fields actually contain.
class LkHtml extends StatelessWidget {
  const LkHtml(this.source, {super.key, this.style, this.linkColor});

  final String source;
  final TextStyle? style;
  final Color? linkColor;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final base = style ?? theme.textTheme.bodyMedium ?? const TextStyle();
    final linkColour = linkColor ?? theme.colorScheme.primary;

    final spans = _parse(source, base, linkColour, theme);

    return Text.rich(
      TextSpan(children: spans),
      style: base,
    );
  }

  /// The tags that are rendered; anything else is dropped, keeping its text.
  static final RegExp _tag = RegExp(
    r'<\s*(/?)\s*(br|p|div|b|strong|i|em|u|a|ul|ol|li|h[1-6])\b[^>]*>',
    caseSensitive: false,
  );

  // A triple-quoted raw string: the pattern contains both quote characters.
  static final RegExp _link = RegExp(
    r"""<\s*a\b[^>]*href\s*=\s*["']([^"']*)["'][^>]*>(.*?)<\s*/\s*a\s*>""",
    caseSensitive: false,
    dotAll: true,
  );

  static List<InlineSpan> _parse(
    String source,
    TextStyle base,
    Color linkColour,
    ThemeData theme,
  ) {
    final spans = <InlineSpan>[];

    // Links first: they carry nested markup.
    var rest = source;

    while (true) {
      final link = _link.firstMatch(rest);
      if (link == null) {
        break;
      }

      final before = rest.substring(0, link.start);
      spans.addAll(_inline(before, base, linkColour));

      spans.add(TextSpan(
        text: _strip(link.group(2) ?? ''),
        style: base.copyWith(
          color: linkColour,
          decoration: TextDecoration.underline,
        ),
      ));

      rest = rest.substring(link.end);
    }

    spans.addAll(_inline(rest, base, linkColour));

    return spans;
  }

  static List<InlineSpan> _inline(
    String source,
    TextStyle base,
    Color linkColour,
  ) {
    final spans = <InlineSpan>[];
    var index = 0;
    var bold = false;
    var italic = false;

    for (final match in _tag.allMatches(source)) {
      final text = source.substring(index, match.start);
      if (text.isNotEmpty) {
        spans.add(TextSpan(
          text: _decode(text),
          style: base.copyWith(
            fontWeight: bold ? FontWeight.bold : null,
            fontStyle: italic ? FontStyle.italic : null,
          ),
        ));
      }

      final name = (match.group(2) ?? '').toLowerCase();
      final closing = match.group(1) == '/';

      switch (name) {
        case 'b':
        case 'strong':
          bold = !closing;
        case 'i':
        case 'em':
          italic = !closing;
        case 'br':
          spans.add(const TextSpan(text: '\n'));
        case 'p':
        case 'div':
        case 'li':
        case 'h1':
        case 'h2':
        case 'h3':
        case 'h4':
        case 'h5':
        case 'h6':
          if (!closing && spans.isNotEmpty && !spans.last.toString().endsWith('\n')) {
            spans.add(const TextSpan(text: '\n'));
          }
      }

      index = match.end;
    }

    final tail = source.substring(index);
    if (tail.isNotEmpty) {
      spans.add(TextSpan(
        text: _decode(tail),
        style: base.copyWith(
          fontWeight: bold ? FontWeight.bold : null,
          fontStyle: italic ? FontStyle.italic : null,
        ),
      ));
    }

    return spans;
  }

  /// The content as plain text, with the markup removed.
  ///
  /// Used where the HTML has to be clipped to a few lines — a summary
  /// that shows the markup would be worse than no summary.
  static String toPlainText(String source) => _decode(_strip(source));

  /// Removes every remaining tag, keeping the text.
  ///
  /// A line break or a block boundary becomes a **space**: removing it
  /// outright would glue two words together ("Olá.Hoje").
  static String _strip(String source) {
    final spaced = source.replaceAll(
      RegExp(
        r'<\s*(/?)\s*(br|p|div|li|tr|h[1-6]|blockquote)\b[^>]*>',
        caseSensitive: false,
      ),
      ' ',
    );

    return spaced.replaceAll(RegExp(r'<[^>]*>'), '').trim();
  }

  /// Decodes the HTML entities that appear in this content.
  static String _decode(String source) => source
      .replaceAll('&nbsp;', ' ')
      .replaceAll('&amp;', '&')
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .replaceAll('&quot;', '"')
      .replaceAll('&#039;', "'")
      .replaceAll('&aacute;', 'á')
      .replaceAll('&eacute;', 'é')
      .replaceAll('&iacute;', 'í')
      .replaceAll('&oacute;', 'ó')
      .replaceAll('&uacute;', 'ú')
      .replaceAll('&atilde;', 'ã')
      .replaceAll('&otilde;', 'õ')
      .replaceAll('&ccedil;', 'ç')
      .replaceAll('&Aacute;', 'Á')
      .replaceAll('&Eacute;', 'É')
      .replaceAll('&Ccedil;', 'Ç');
}
