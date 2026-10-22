import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The services the client has, **grouped by type**.
///
/// A service is not only web hosting: a backup is a service too, and a
/// server is another. What tells them apart is the product the service
/// belongs to, and the label the client sees is the product's group.
class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen> {
  List<Map<String, dynamic>> _services = const [];
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
      final data = await context.read<LkApiClient>().clientServices();
      if (!mounted) return;
      setState(() {
        _services = data.cast<Map<String, dynamic>>();
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
    if (_services.isEmpty) {
      return const LkEmptyState(
        message: 'Ainda não tem serviços subscritos.',
        icon: Icons.dns_outlined,
        action: TextButton(onPressed: null, child: Text('')),
      );
    }

    // Grouped by the type — the family of the product.
    final groups = <String, List<Map<String, dynamic>>>{};
    for (final service in _services) {
      final type = '${service['type'] ?? service['rawType'] ?? 'Serviços'}';
      groups.putIfAbsent(type, () => []).add(service);
    }

    final types = groups.keys.toList()..sort();

    final theme = Theme.of(context);

    return LkRefreshable(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          for (final type in types) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
              child: Text(
                type,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: theme.hintColor,
                  letterSpacing: 0.4,
                ),
              ),
            ),
            for (final service in groups[type]!)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: _ServiceCard(service: service),
              ),
          ],
        ],
      ),
    );
  }
}

/// One service.
class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service});

  final Map<String, dynamic> service;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    final domain = '${service['domain'] ?? ''}';
    final username = '${service['username'] ?? ''}';
    final server = '${service['server'] ?? ''}';

    // Only the fields this kind of service actually has. A backup has
    // no domain and no user: showing empty cells would be worse than
    // not showing them.
    final details = <String>[];

    if (domain.isNotEmpty) details.add('Domínio: $domain');
    if (username.isNotEmpty) details.add('Utilizador: $username');
    if (server.isNotEmpty && server != '0') details.add('Servidor: $server');

    final diskLimit = (service['diskLimit'] as num?)?.toInt() ?? 0;
    if (diskLimit > 0) {
      final used = (service['diskUsage'] as num?)?.toInt() ?? 0;
      details.add('Disco: ${_mib(used)} de ${_mib(diskLimit)}');
    }

    final bandwidthLimit = (service['bandwidthLimit'] as num?)?.toInt() ?? 0;
    if (bandwidthLimit > 0) {
      final used = (service['bandwidthUsage'] as num?)?.toInt() ?? 0;
      details.add('Banda: ${_mib(used)} de ${_mib(bandwidthLimit)}');
    }

    final amount = (service['amount'] as num?)?.toDouble() ?? 0;
    if (amount > 0) {
      details.add('Preço: ${amount.toStringAsFixed(2).replaceAll('.', ',')} €');
    }

    final cycle = '${service['billingCycle'] ?? ''}';
    if (cycle.isNotEmpty) {
      details.add('Ciclo: $cycle');
    }

    final nextDue = '${service['renewalDate'] ?? ''}';
    if (nextDue.isNotEmpty) {
      details.add('Renova: $nextDue');
    }

    return LkCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  '${service['name'] ?? ''}',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              const SizedBox(width: 8),
              LkStatusBadge(label: '${service['status'] ?? ''}'),
            ],
          ),
          if (details.isNotEmpty) ...[
            const SizedBox(height: 8),
            for (final detail in details)
              Padding(
                padding: const EdgeInsets.only(bottom: 2),
                child: Text(
                  detail,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.hintColor,
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  /// A size in megabytes, in the units a human reads.
  static String _mib(int megabytes) {
    if (megabytes >= 1024) {
      final gigabytes = megabytes / 1024;
      final text = gigabytes.toStringAsFixed(gigabytes >= 10 ? 0 : 1);
      return '${text.replaceAll('.', ',')} GB';
    }

    return '$megabytes MB';
  }
}
