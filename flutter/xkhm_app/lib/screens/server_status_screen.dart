import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The server status page.
class ServerStatusScreen extends StatefulWidget {
  const ServerStatusScreen({super.key});

  @override
  State<ServerStatusScreen> createState() => _ServerStatusScreenState();
}

class _ServerStatusScreenState extends State<ServerStatusScreen> {
  LkServerStatus? _status;
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
      final data = await context.read<LkApiClient>().serverStatus();
      if (!mounted) return;
      setState(() {
        _status = LkServerStatus.fromJson(data);
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
    } catch (e) {
      // A malformed payload must show an error, not a blank screen.
      if (!mounted) return;
      setState(() {
        _error = 'Não foi possível interpretar a resposta do servidor.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);
    final status = _status;
    if (status == null) return const LkEmptyState(message: 'Sem dados.');

    return ListView(
      children: [
        LkCard(
          title: 'Estado do Servidor',
          child: Column(
            children: [
              _row('Tempo de atividade', status.uptime),
              _row('Carga do sistema', status.load),
              _row('Uso de memória', status.memoryUsage),
              _row('Memória máxima', status.maxMemory),
              _row('Versão do PHP', status.phpVersion),
              _row('Base de dados', status.database),
              _row('Última verificação', status.lastCheck),
            ],
          ),
        ),
      ],
    );
  }

  Widget _row(String label, String value) {
    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.hintColor,
              ),
            ),
          ),
          Text(
            value.isEmpty ? '—' : value,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
