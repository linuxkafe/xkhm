import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The SSL certificates of the client's domains.
class SslScreen extends StatefulWidget {
  const SslScreen({super.key});

  @override
  State<SslScreen> createState() => _SslScreenState();
}

class _SslScreenState extends State<SslScreen> {
  List<Map<String, dynamic>> _certificates = const [];
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
      final data = await context.read<LkApiClient>().clientSsl();
      if (!mounted) return;
      setState(() {
        _certificates = data.cast<Map<String, dynamic>>();
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
    if (_certificates.isEmpty) {
      return const LkEmptyState(
        message: 'Nenhum certificado SSL encontrado.',
        icon: Icons.lock_outline,
      );
    }

    final theme = Theme.of(context);

    return ListView.separated(
      itemCount: _certificates.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final certificate = _certificates[index];
        final days = (certificate['daysRemaining'] as num?)?.toInt() ?? 0;

        return LkCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${certificate['domain'] ?? ''}',
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                  LkStatusBadge(
                    label: certificate['autoRenew'] == true
                        ? 'Auto-Renovação'
                        : 'Renovação manual',
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Válido de ${certificate['validFrom'] ?? '—'} até ${certificate['validTo'] ?? '—'}',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.hintColor,
                ),
              ),
              Text(
                'Emissor: ${certificate['issuer'] ?? '—'}  •  $days dias restantes',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.hintColor,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
