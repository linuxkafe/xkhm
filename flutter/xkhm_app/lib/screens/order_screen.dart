import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The order form: the catalogue, the cycle, and the domain.
///
/// The order creates the **order** and the **invoice**; the service
/// appears when the invoice is paid.
class OrderScreen extends StatefulWidget {
  const OrderScreen({super.key});

  @override
  State<OrderScreen> createState() => _OrderScreenState();
}

class _OrderScreenState extends State<OrderScreen> {
  List<Map<String, dynamic>> _catalogue = const [];
  String? _error;
  bool _loading = true;

  /// The product being ordered, and the cycle chosen.
  Map<String, dynamic>? _product;
  String? _cycle;

  final _domainController = TextEditingController();
  bool _placing = false;
  String? _placingError;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _domainController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<LkApiClient>().catalogue();
      if (!mounted) return;
      setState(() {
        _catalogue = data;
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

  String _money(double value) =>
      '${value.toStringAsFixed(2).replaceAll('.', ',')} €';

  /// The price of the chosen cycle, plus the setup.
  double _total(Map<String, dynamic> product, String cycle) {
    var total = 0.0;

    for (final c in (product['cycles'] as List<dynamic>? ?? const [])) {
      final entry = c as Map<String, dynamic>;
      if (entry['cycle'] == cycle) {
        total = (entry['price'] as num?)?.toDouble() ?? 0;
      }
    }

    return total + ((product['setup'] as num?)?.toDouble() ?? 0);
  }

  Future<void> _place() async {
    final product = _product;
    final cycle = _cycle;

    if (product == null || cycle == null) return;

    setState(() {
      _placing = true;
      _placingError = null;
    });

    try {
      final result = await context.read<LkApiClient>().placeOrder(
            productId: (product['id'] as num).toInt(),
            cycle: cycle,
            domain: _domainController.text.trim(),
          );
      if (!mounted) return;

      final invoiceId = (result['invoiceId'] as num?)?.toInt();
      final orderId = (result['orderId'] as num?)?.toInt();

      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Encomenda registada'),
          content: Text(
            'O pedido #$orderId foi criado, com a fatura #$invoiceId.\n\n'
            'O serviço é criado quando a fatura for paga. Encontra a fatura '
            'em «Faturas».',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Fechar'),
            ),
          ],
        ),
      );

      if (!mounted) return;
      setState(() {
        _placing = false;
        _product = null;
        _cycle = null;
        _domainController.clear();
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _placingError = e.message;
        _placing = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _placingError = e.message;
        _placing = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);

    // O formulario, quando ha um produto escolhido.
    if (_product != null) {
      return _form(_product!);
    }

    if (_catalogue.isEmpty) {
      return const LkEmptyState(
        message: 'Não há produtos disponíveis de momento.',
        icon: Icons.inventory_2_outlined,
      );
    }

    final theme = Theme.of(context);

    // Os produtos, agrupados pelo grupo — como no WHMCS.
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final product in _catalogue) {
      final group = '${product['group'] ?? ''}';
      groups.putIfAbsent(group, () => []).add(product);
    }

    return ListView(
      children: [
        for (final entry in groups.entries) ...[
          LkSectionTitle(entry.key),
          for (final product in entry.value) ...[
            LkCard(
              key: Key('catalogue-product-${product['id']}'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${product['name'] ?? ''}',
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    _cyclesLabel(product),
                    style: theme.textTheme.bodySmall
                        ?.copyWith(color: theme.hintColor),
                  ),
                  const SizedBox(height: 10),
                  Align(
                    alignment: Alignment.centerRight,
                    child: Semantics(
                      // O botao diz **que** produto encomenda: sem
                      // isso, um leitor de ecra diz so «Encomendar»,
                      // dez vezes seguidas.
                      label: 'Encomendar ${product['name'] ?? ''}',
                      child: FilledButton(
                          onPressed: () => setState(() {
                          _product = product;
                          _cycle = _firstCycle(product);
                          _placingError = null;
                        }),
                        child: const Text('Encomendar'),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
          ],
        ],
      ],
    );
  }

  /// The first cycle of a product — the one the form starts with.
  String? _firstCycle(Map<String, dynamic> product) {
    final cycles = product['cycles'] as List<dynamic>? ?? const [];

    if (cycles.isEmpty) return null;

    return '${(cycles.first as Map<String, dynamic>)['cycle']}';
  }

  /// The cycles of a product, as a line of text.
  String _cyclesLabel(Map<String, dynamic> product) {
    final cycles = product['cycles'] as List<dynamic>? ?? const [];

    return cycles
        .map((c) {
          final entry = c as Map<String, dynamic>;
          return '${entry['label'] ?? ''}: ${_money((entry['price'] as num?)?.toDouble() ?? 0)}';
        })
        .join(' · ');
  }

  /// The order form.
  Widget _form(Map<String, dynamic> product) {
    final theme = Theme.of(context);
    final cycles = product['cycles'] as List<dynamic>? ?? const [];
    final needsDomain = product['needsDomain'] == true;

    return ListView(
      children: [
        LkCard(
          title: '${product['name'] ?? ''}',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (needsDomain) ...[
                const SizedBox(height: 4),
                TextField(
                  key: const Key('order-domain'),
                  controller: _domainController,
                  decoration: const InputDecoration(
                    labelText: 'Domínio',
                    hintText: 'exemplo.pt',
                    prefixIcon: Icon(Icons.language),
                  ),
                ),
                const SizedBox(height: 16),
              ],
              Text(
                'Ciclo de faturação',
                style: theme.textTheme.bodyMedium
                    ?.copyWith(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 8),
              RadioGroup<String>(
                groupValue: _cycle,
                onChanged: (value) => setState(() => _cycle = value),
                child: Column(
                  children: [
                    for (final c in cycles) ...[
                      RadioListTile<String>(
                        key: Key(
                          'order-cycle-${(c as Map<String, dynamic>)['cycle']}',
                        ),
                        value: '${c['cycle']}',
                        title: Text('${c['label']}'),
                        secondary: Text(
                          _money((c['price'] as num?)?.toDouble() ?? 0),
                        ),
                        contentPadding: EdgeInsets.zero,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Total: ${_money(_total(product, _cycle ?? ''))}',
                style: theme.textTheme.titleMedium
                    ?.copyWith(fontWeight: FontWeight.w700),
              ),
              if (_placingError != null) ...[
                const SizedBox(height: 12),
                Text(
                  _placingError!,
                  style: TextStyle(color: theme.colorScheme.error),
                ),
              ],
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _placing
                          ? null
                          : () => setState(() {
                                _product = null;
                                _placingError = null;
                              }),
                      child: const Text('Cancelar'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      key: const Key('order-submit'),
                      onPressed: _placing ? null : _place,
                      child: _placing
                          ? const SizedBox(
                              height: 20,
                              width: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Encomendar'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ],
    );
  }
}
