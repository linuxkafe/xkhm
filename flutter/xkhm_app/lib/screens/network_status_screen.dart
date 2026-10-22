import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../widgets/lk_html.dart';
import '../widgets/lk_widgets.dart';

/// The network status page — the planned interventions, as the WHMCS's
/// `networkissues.php` shows them.
///
/// The WHMCS has two different pages and they answer two different
/// questions:
///
///   * `serverstatus.php` — **how is the machine now** (the load, the
///     memory, the PHP version). That is the *Status do Servidor*.
///   * `networkissues.php` — **what is coming** (the maintenance that
///     will take the service down). That is this page.
///
/// Mixing them hides the intervention behind a green load average: the
/// machine is fine *now*, and it will be down at 22:30. The client has
/// to be told the second thing.
class NetworkStatusScreen extends StatefulWidget {
  const NetworkStatusScreen({super.key});

  @override
  State<NetworkStatusScreen> createState() => _NetworkStatusScreenState();
}

class _NetworkStatusScreenState extends State<NetworkStatusScreen> {
  List<LkAnnouncement> _issues = const [];
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
      final data = await context.read<LkApiClient>().announcements();
      if (!mounted) return;
      setState(() {
        _issues = data.map(LkAnnouncement.fromJson).toList();
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

    return LkRefreshable(
      onRefresh: _load,
      child: ListView(
        children: [
          LkCard(
            key: const Key('network-current-state'),
            title: 'Estado actual',
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _issues.isEmpty
                      ? 'Sem intervenções comunicadas. Os serviços estão operacionais.'
                      : 'Há ${_issues.length} intervenção(ões) comunicada(s). '
                          'Consulte os avisos abaixo.',
                  style: TextStyle(color: Theme.of(context).colorScheme.onSurface),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const LkSectionTitle('Intervenções comunicadas'),
          if (_issues.isEmpty)
            const LkEmptyState(
              message: 'Não há intervenções planeadas.',
              icon: Icons.check_circle_outline,
            )
          else
            for (final issue in _issues) ...[
              LkCard(
                key: Key('network-issue-${issue.id}'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      issue.title.trim(),
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w600,
                            color: Theme.of(context).colorScheme.onSurface,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      issue.date,
                      style: Theme.of(context)
                          .textTheme
                          .bodySmall
                          ?.copyWith(color: Theme.of(context).hintColor),
                    ),
                    const SizedBox(height: 10),
                    // The WHMCS announcement body is HTML.
                    LkHtml(issue.message),
                  ],
                ),
              ),
              const SizedBox(height: 8),
            ],
        ],
      ),
    );
  }
}
