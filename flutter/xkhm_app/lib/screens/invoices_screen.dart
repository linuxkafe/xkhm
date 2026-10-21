import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';

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

    return ListView.separated(
      itemCount: _invoices.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final invoice = _invoices[index];
        return LkCard(
          child: Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Fatura #${invoice.number}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: LkColors.text,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Emitida: ${invoice.dateCreated}  •  Vencimento: ${invoice.dueDate}',
                      style: const TextStyle(
                        color: LkColors.textLight,
                        fontSize: 12,
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
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 16,
                      color: LkColors.text,
                    ),
                  ),
                  const SizedBox(height: 6),
                  LkStatusBadge(label: invoice.status),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}
