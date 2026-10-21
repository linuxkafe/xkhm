import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
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
    final announcements =
        (data['announcements'] as List<dynamic>?) ?? const [];
    final articles = (data['articles'] as List<dynamic>?) ?? const [];

    final name = [
      client['firstname'] ?? '',
      client['lastname'] ?? '',
    ].where((part) => '$part'.isNotEmpty).join(' ');

    return ListView(
      children: [
        LkCard(
          title: 'Bemvindo',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                name.isEmpty ? 'Cliente' : name,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                    ),
              ),
              const SizedBox(height: 4),
              Text('${client['email'] ?? ''}'),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  SizedBox(
                    width: 220,
                    child: LkStatTile(
                      label: 'Crédito disponível',
                      value:
                          '\$ ${_asDouble(billing['creditBalance']).toStringAsFixed(2)}',
                      icon: Icons.account_balance_wallet_outlined,
                    ),
                  ),
                  SizedBox(
                    width: 220,
                    child: LkStatTile(
                      label: 'Total em aberto',
                      value:
                          '\$ ${_asDouble(billing['unpaidTotal']).toStringAsFixed(2)}',
                      icon: Icons.payments_outlined,
                    ),
                  ),
                ],
              ),
            ],
          ),
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
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text('${item['message'] ?? ''}'),
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
                      style: Theme.of(context)
                          .textTheme
                          .titleMedium
                          ?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 4),
                    Text('${item['description'] ?? ''}'),
                  ],
                ),
              ),
            ),
        ],
      ],
    );
  }
}

double _asDouble(Object? value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}
