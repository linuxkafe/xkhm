import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_html.dart';
import '../widgets/lk_widgets.dart';

/// The landing screen: the client's identity, the billing summary, the
/// latest news and a few knowledge-base articles.
class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<LkApiClient>().home();
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);

    final data = _data ?? const {};
    final client = (data['client'] as Map<String, dynamic>?) ?? const {};
    final billing = (data['billing'] as Map<String, dynamic>?) ?? const {};
    final announcements = (data['announcements'] as List<dynamic>?) ?? const [];
    final articles = (data['articles'] as List<dynamic>?) ?? const [];

    final name = [
      client['firstname'] ?? '',
      client['lastname'] ?? '',
    ].where((part) => '$part'.isNotEmpty).join(' ');

    return LkRefreshable(
      onRefresh: _load,
      child: ListView(
        children: [
          // The greeting: the identity and the figures, in one block.
          // On a phone the two figures share the row — they take the
          // width they need, instead of a fixed one that does not fit.
          LkHeroCard(
            title: name.isEmpty ? 'Cliente' : name,
            subtitle: '${client['email'] ?? ''}',
            figures: [
              LkHeroFigure(
                label: 'Crédito disponível',
                value:
                    '\$ ${_asDouble(billing['creditBalance']).toStringAsFixed(2)}',
                icon: Icons.account_balance_wallet_outlined,
              ),
              LkHeroFigure(
                label: 'Total em aberto',
                value:
                    '\$ ${_asDouble(billing['unpaidTotal']).toStringAsFixed(2)}',
                icon: Icons.payments_outlined,
              ),
            ],
          ),
          if (announcements.isNotEmpty) ...[
            const LkSectionTitle('Notícias'),
            for (final item in announcements.cast<Map<String, dynamic>>())
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: LkCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${item['title'] ?? ''}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 4),
                      // The WHMCS announcement body is HTML. On a phone it
                      // is summarised, and one tap shows the rest.
                      LkExpandableText(
                        text: LkHtml.toPlainText('${item['message'] ?? ''}'),
                      ),
                    ],
                  ),
                ),
              ),
          ],
          if (articles.isNotEmpty) ...[
            const LkSectionTitle('Da base de conhecimento'),
            for (final item in articles.cast<Map<String, dynamic>>())
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: LkCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${item['title'] ?? ''}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      const SizedBox(height: 4),
                      LkExpandableText(
                        text: LkHtml.toPlainText(
                          '${item['description'] ?? ''}',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

double _asDouble(Object? value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}
