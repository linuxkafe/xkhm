import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The client-area dashboard: identity, billing summary and counters.
class ClientAreaScreen extends StatefulWidget {
  const ClientAreaScreen({super.key});

  @override
  State<ClientAreaScreen> createState() => _ClientAreaScreenState();
}

class _ClientAreaScreenState extends State<ClientAreaScreen> {
  LkSummary? _summary;
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
      final data = await context.read<LkApiClient>().clientSummary();
      if (!mounted) return;
      setState(() {
        _summary = LkSummary.fromJson(data);
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
    final summary = _summary;
    if (summary == null) return const LkEmptyState(message: 'Sem dados.');

    final counts = summary.counts;
    final theme = Theme.of(context);

    return ListView(
      children: [
        LkCard(
          title: 'Bemvindo',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                summary.client.fullName.isEmpty
                    ? 'Cliente'
                    : summary.client.fullName,
                style: theme.textTheme.titleLarge?.copyWith(
                  fontSize: 20,
                  fontWeight: FontWeight.bold,
                  color: theme.colorScheme.onSurface,
                ),
              ),
              const SizedBox(height: 4),
              Text(summary.client.email),
            ],
          ),
        ),
        const SizedBox(height: 16),
        const LkSectionTitle('Resumo Financeiro'),
        Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            SizedBox(
              width: 240,
              child: LkStatTile(
                label: 'Crédito disponível',
                value: _money(summary.billing.creditBalance),
                icon: Icons.account_balance_wallet_outlined,
              ),
            ),
            SizedBox(
              width: 240,
              child: LkStatTile(
                label: 'Total em aberto',
                value: _money(summary.billing.unpaidTotal),
                icon: Icons.payments_outlined,
              ),
            ),
          ],
        ),
        const SizedBox(height: 16),
        const LkSectionTitle('Serviços'),
        LayoutBuilder(
          builder: (context, constraints) {
            final columns = constraints.maxWidth > 720 ? 3 : 2;
            final width =
                (constraints.maxWidth - (columns - 1) * 12) / columns;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                SizedBox(
                  width: width,
                  child: LkStatTile(
                    label: 'Faturas',
                    value: '${counts.invoices}',
                    icon: Icons.receipt_long_outlined,
                  ),
                ),
                SizedBox(
                  width: width,
                  child: LkStatTile(
                    label: 'Faturas por liquidar',
                    value: '${counts.unpaidInvoices}',
                    icon: Icons.error_outline,
                  ),
                ),
                SizedBox(
                  width: width,
                  child: LkStatTile(
                    label: 'Tickets',
                    value: '${counts.tickets}',
                    icon: Icons.support_agent_outlined,
                  ),
                ),
                SizedBox(
                  width: width,
                  child: LkStatTile(
                    label: 'Tickets abertos',
                    value: '${counts.openTickets}',
                    icon: Icons.mark_email_unread_outlined,
                  ),
                ),
                SizedBox(
                  width: width,
                  child: LkStatTile(
                    label: 'Domínios',
                    value: '${counts.domains}',
                    icon: Icons.language_outlined,
                  ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }

  static String _money(double value) =>
      '\$ ${value.toStringAsFixed(2)}';
}
