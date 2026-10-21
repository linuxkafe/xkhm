import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The affiliate programme: the client's referral code, earnings and the
/// referrals they brought.
class AffiliateScreen extends StatefulWidget {
  const AffiliateScreen({super.key});

  @override
  State<AffiliateScreen> createState() => _AffiliateScreenState();
}

class _AffiliateScreenState extends State<AffiliateScreen> {
  Map<String, dynamic>? _affiliate;
  List<Map<String, dynamic>> _referrals = const [];
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
      final data = await context.read<LkApiClient>().clientAffiliate();
      if (!mounted) return;
      setState(() {
        _affiliate = data['affiliate'] as Map<String, dynamic>?;
        _referrals =
            ((data['referrals'] as List<dynamic>?) ?? const []).cast<Map<String, dynamic>>();
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

    final theme = Theme.of(context);
    final affiliate = _affiliate;

    if (affiliate == null) {
      return const LkEmptyState(
        message: 'Ainda não está inscrito no programa de afiliados.',
        icon: Icons.groups_outlined,
      );
    }

    return ListView(
      children: [
        LkCard(
          title: 'Programa de Afiliados',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                '${affiliate['name'] ?? ''}',
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 4),
              Text('Código: ${affiliate['refCode'] ?? '—'}'),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  SizedBox(
                    width: 200,
                    child: LkStatTile(
                      label: 'Saldo',
                      value: '\$ ${_asDouble(affiliate['balance']).toStringAsFixed(2)}',
                      icon: Icons.account_balance_wallet_outlined,
                    ),
                  ),
                  SizedBox(
                    width: 200,
                    child: LkStatTile(
                      label: 'Ganhos',
                      value: '\$ ${_asDouble(affiliate['earnings']).toStringAsFixed(2)}',
                      icon: Icons.trending_up,
                    ),
                  ),
                  SizedBox(
                    width: 200,
                    child: LkStatTile(
                      label: 'Referidos',
                      value: '${affiliate['referralCount'] ?? 0}',
                      icon: Icons.people_outline,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        if (_referrals.isNotEmpty) ...[
          const LkSectionTitle('Referidos'),
          for (final referral in _referrals)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: LkCard(
                child: Row(
                  children: [
                    Expanded(
                      child: Text('Cliente #${referral['clientId'] ?? ''}'),
                    ),
                    Text(
                      '\$ ${_asDouble(referral['commission']).toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
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
