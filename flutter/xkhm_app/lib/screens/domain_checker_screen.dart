import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/tokens.dart';
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
  String? _error;
  bool _loading = false;
  bool _searched = false;

  @override
  void dispose() {
    _domainController.dispose();
    super.dispose();
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
                    ? const SizedBox(
                        height: 20,
                        width: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2,
                          color: LkColors.text,
                        ),
                      )
                    : const Text('Verificar'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: const TextStyle(color: LkSemantic.dangerFg),
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
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: LkColors.text,
                      ),
                    ),
                  ),
                  LkStatusBadge(
                    label: result.available ? 'Disponível' : 'Registado',
                    tone: result.available
                        ? const LkBadgeTone(
                            background: LkSemantic.successBg,
                            foreground: LkSemantic.successFg,
                          )
                        : const LkBadgeTone(
                            background: LkSemantic.dangerBg,
                            foreground: LkSemantic.dangerFg,
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
}
