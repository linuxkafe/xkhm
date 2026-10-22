import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The detail of one invoice, with its line items.
class InvoiceDetailScreen extends StatefulWidget {
  const InvoiceDetailScreen({super.key, required this.invoiceId});

  final int invoiceId;

  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  Map<String, dynamic>? _invoice;
  List<Map<String, dynamic>> _items = const [];
  String? _error;
  bool _loading = true;
  bool _sending = false;

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
      final data = await context.read<LkApiClient>().clientInvoice(widget.invoiceId);
      if (!mounted) return;
      setState(() {
        _invoice = data['invoice'] as Map<String, dynamic>?;
        _items =
            ((data['items'] as List<dynamic>?) ?? const []).cast<Map<String, dynamic>>();
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

  /// Open the payment notice, as a PDF.
  Future<void> _openPdf() async {
    final url = context.read<LkApiClient>().clientInvoicePdfUrl(widget.invoiceId);

    final opened = await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);

    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Não foi possível abrir o documento.')),
      );
    }
  }

  /// Send the payment notice again, by e-mail.
  Future<void> _resend() async {
    setState(() => _sending = true);

    try {
      await context.read<LkApiClient>().clientInvoiceResend(widget.invoiceId);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('O aviso de pagamento foi enviado.')),
      );
    } on LkApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);

    final theme = Theme.of(context);
    final invoice = _invoice ?? const {};
    final total = _asDouble(invoice['total']);

    return ListView(
      children: [
        LkCard(
          title: 'Aviso de pagamento #${invoice['number'] ?? ''}',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 4),
              Text(
                'Emitida: ${invoice['dateCreated'] ?? '—'}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.hintColor,
                ),
              ),
              Text(
                'Vencimento: ${invoice['dueDate'] ?? '—'}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.hintColor,
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Text('Estado:'),
                  const SizedBox(width: 8),
                  LkStatusBadge(label: '${invoice['status'] ?? ''}'),
                ],
              ),
              if ('${invoice['note'] ?? ''}'.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text('${invoice['note']}'),
              ],
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.only(top: 4, bottom: 8),
          child: Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('invoice-pdf'),
                  onPressed: _openPdf,
                  icon: const Icon(Icons.picture_as_pdf_outlined),
                  label: const Text('PDF'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton.icon(
                  key: const Key('invoice-resend'),
                  onPressed: _sending ? null : _resend,
                  icon: const Icon(Icons.send_outlined),
                  label: Text(_sending ? 'A enviar…' : 'Reenviar'),
                ),
              ),
            ],
          ),
        ),
        Text(
          'Este documento é um aviso de pagamento: diz o que se deve e como '
          'pagar. Não é uma fatura, nem um documento fiscal — a fatura '
          'certificada é emitida separadamente.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.hintColor,
            height: 1.5,
          ),
        ),
        const SizedBox(height: 12),
        const LkSectionTitle('Itens'),
        LkCard(
          child: Column(
            children: [
              for (final item in _items)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  child: Row(
                    children: [
                      Expanded(child: Text('${item['description'] ?? ''}')),
                      Text(
                        '\$ ${_asDouble(item['amount']).toStringAsFixed(2)}',
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              const Divider(),
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Total',
                        style: TextStyle(fontWeight: FontWeight.bold),
                      ),
                    ),
                    Text(
                      '\$ ${total.toStringAsFixed(2)}',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

double _asDouble(Object? value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}
