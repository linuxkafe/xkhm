import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';
import 'invoice_detail_screen.dart';

/// The invoice list, with a detail sheet.
class InvoicesScreen extends StatefulWidget {
  const InvoicesScreen({super.key});

  @override
  State<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends State<InvoicesScreen> {
  List<LkInvoice> _invoices = const [];
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
      final data = await context.read<LkApiClient>().clientInvoices();
      if (!mounted) return;
      setState(() {
        _invoices = data.map(LkInvoice.fromJson).toList();
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

  void _openDetail(BuildContext context, int invoiceId) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => InvoiceDetailScreen(invoiceId: invoiceId),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);
    if (_invoices.isEmpty) {
      return const LkEmptyState(
        message: 'Ainda não tem faturas.',
        icon: Icons.receipt_long_outlined,
      );
    }

    final theme = Theme.of(context);

    return LkRefreshable(
      onRefresh: _load,
      child: ListView.separated(
        padding: const EdgeInsets.only(top: 4, bottom: 24),
        itemCount: _invoices.length + 1,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          // A primeira linha e a nota: o que estes documentos sao.
          if (index == 0) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                'Estes documentos são avisos de pagamento: dizem o que se deve '
                'e como pagar. Não são faturas, nem documentos fiscais — a '
                'fatura certificada é emitida separadamente.',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.hintColor,
                  height: 1.5,
                ),
              ),
            );
          }

          final invoice = _invoices[index - 1];
          return LkCard(
            child: InkWell(
              onTap: () => _openDetail(context, invoice.id),
              borderRadius: BorderRadius.circular(LkLayout.borderRadius),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Fatura #${invoice.number}',
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: theme.colorScheme.onSurface,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          'Emitida: ${invoice.dateCreated}  •  Vencimento: ${invoice.dueDate}',
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.hintColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 12),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Text(
                        '\$ ${invoice.total.toStringAsFixed(2)}',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.bold,
                          fontSize: 16,
                          color: theme.colorScheme.onSurface,
                        ),
                      ),
                      const SizedBox(height: 6),
                      LkStatusBadge(label: invoice.status),
                    ],
                  ),
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
