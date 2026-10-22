import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The domain availability checker.
class DomainCheckerScreen extends StatefulWidget {
  const DomainCheckerScreen({super.key});

  @override
  State<DomainCheckerScreen> createState() => _DomainCheckerScreenState();
}

class _DomainCheckerScreenState extends State<DomainCheckerScreen> {
  final _domainController = TextEditingController();

  List<LkDomainResult> _results = const [];
  List<LkDomainPrice> _prices = const [];
  String? _error;
  bool _loading = false;
  bool _searched = false;

  @override
  void initState() {
    super.initState();
    _loadPrices();
  }

  @override
  void dispose() {
    _domainController.dispose();
    super.dispose();
  }

  /// The price table, which the WHMCS shows before the search.
  ///
  /// Without it the client has no idea what a domain costs, and only
  /// finds out at the checkout.
  Future<void> _loadPrices() async {
    try {
      final data = await context.read<LkApiClient>().domainPricing();
      if (!mounted) return;
      setState(() {
        _prices = data.map(LkDomainPrice.fromJson).toList();
      });
    } on LkApiException {
      // The price table is a courtesy: a failure here must not stop
      // the search.
    } on LkNetworkException {
      // Ditto.
    }
  }

  Future<void> _check() async {
    final domain = _domainController.text.trim();
    if (domain.isEmpty) {
      setState(() => _error = 'Indique um domínio para verificar.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<LkApiClient>().checkDomain(domain);
      if (!mounted) return;
      setState(() {
        _results = data.map(LkDomainResult.fromJson).toList();
        _loading = false;
        _searched = true;
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
    return ListView(
      children: [
        LkCard(
          title: 'Verificar Domínio',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 4),
              TextField(
                key: const Key('domain-input'),
                controller: _domainController,
                keyboardType: TextInputType.url,
                textInputAction: TextInputAction.search,
                autocorrect: false,
                enableSuggestions: false,
                textCapitalization: TextCapitalization.none,
                decoration: const InputDecoration(
                  labelText: 'Domínio',
                  hintText: 'exemplo',
                  prefixIcon: Icon(Icons.language),
                ),
                onSubmitted: (_) => _check(),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                key: const Key('domain-check'),
                onPressed: _loading ? null : _check,
                child: _loading
                    ? SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Theme.of(context).colorScheme.onPrimary,
                        ),
                      )
                    : const Text('Verificar'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ],
            ],
          ),
        ),
        if (_searched && _results.isNotEmpty) ...[
          const SizedBox(height: 16),
          const LkSectionTitle('Resultados'),
          for (final result in _results) ...[
            LkCard(
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      result.domain,
                      style: TextStyle(
                        fontWeight: FontWeight.w600,
                        color: Theme.of(context).colorScheme.onSurface,
                      ),
                    ),
                  ),
                  LkStatusBadge(
                    label: result.available ? 'Disponível' : 'Registado',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
          ],
        ],
        if (_prices.isNotEmpty) ...[
          const SizedBox(height: 16),
          const LkSectionTitle('Preçário de Domínios'),
          LkCard(
            key: const Key('domain-price-table'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (var i = 0; i < _prices.length; i++) ...[
                  if (i > 0) const Divider(height: 16),
                  _PriceRow(price: _prices[i]),
                ],
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// One TLD of the price table.
class _PriceRow extends StatelessWidget {
  const _PriceRow({required this.price});

  final LkDomainPrice price;

  String _money(double value) =>
      '${value.toStringAsFixed(2).replaceAll('.', ',')} €';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              price.tld,
              style: theme.textTheme.titleSmall
                  ?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(width: 8),
            Text(
              'mínimo ${price.minYears} ano(s)',
              style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Wrap(
          spacing: 12,
          runSpacing: 2,
          children: [
            _PriceCell(label: 'Registar', value: _money(price.register)),
            _PriceCell(label: 'Transferir', value: _money(price.transfer)),
            _PriceCell(label: 'Renovar', value: _money(price.renew)),
          ],
        ),
      ],
    );
  }
}

/// One price of the price table.
class _PriceCell extends StatelessWidget {
  const _PriceCell({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          '$label: ',
          style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
        ),
        Text(
          value,
          style: theme.textTheme.bodyMedium
              ?.copyWith(fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
