import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The domains the client owns, with their expiry and status.
class DomainsScreen extends StatefulWidget {
  const DomainsScreen({super.key});

  @override
  State<DomainsScreen> createState() => _DomainsScreenState();
}

class _DomainsScreenState extends State<DomainsScreen> {
  List<Map<String, dynamic>> _domains = const [];
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
      final data = await context.read<LkApiClient>().clientDomains();
      if (!mounted) return;
      setState(() {
        _domains = data.cast<Map<String, dynamic>>();
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
    if (_domains.isEmpty) {
      return const LkEmptyState(
        message: 'Ainda não tem domínios registados.',
        icon: Icons.language_outlined,
        action: TextButton(onPressed: null, child: Text('')),
      );
    }

    final theme = Theme.of(context);

    return LkRefreshable(
      onRefresh: _load,
      child: ListView.separated(
        itemCount: _domains.length,
        separatorBuilder: (_, _) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final domain = _domains[index];

          return LkCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '${domain['domain'] ?? ''}',
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    LkStatusBadge(label: '${domain['status'] ?? ''}'),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  'Registado: ${domain['registrationDate'] ?? '—'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.hintColor,
                  ),
                ),
                Text(
                  'Expira: ${domain['expiryDate'] ?? '—'}',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.hintColor,
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
