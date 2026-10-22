import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../services/api_client.dart';
import '../widgets/lk_widgets.dart';

/// The security of the account: the second factor.
///
/// The client scans the QR with an authenticator app, and from then on a
/// login from a **new device** asks for the code that app shows.
class SecurityScreen extends StatefulWidget {
  const SecurityScreen({super.key});

  @override
  State<SecurityScreen> createState() => _SecurityScreenState();
}

class _SecurityScreenState extends State<SecurityScreen> {
  bool _enrolled = false;
  bool _loading = true;
  bool _busy = false;
  String? _error;

  /// The secret and the URI, while the enrolment is in progress.
  String? _secret;
  String? _uri;

  final _codeController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final enrolled = await context.read<LkApiClient>().securityEnrolled();
      if (!mounted) return;
      setState(() {
        _enrolled = enrolled;
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

  Future<void> _startEnrolment() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final data = await context.read<LkApiClient>().securityEnrol();
      if (!mounted) return;
      setState(() {
        _secret = '${data['secret'] ?? ''}';
        _uri = '${data['uri'] ?? ''}';
        _busy = false;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    }
  }

  Future<void> _confirm() async {
    final code = _codeController.text.trim();

    if (code.length < 6) {
      setState(() => _error = 'O código tem seis dígitos.');
      return;
    }

    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final ok = await context.read<LkApiClient>().securityConfirm(code);
      if (!mounted) return;
      setState(() {
        _busy = false;
        _enrolled = ok;
        _secret = null;
        _uri = null;
        _codeController.clear();
        if (!ok) _error = 'O código não é válido.';
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    }
  }

  Future<void> _disable() async {
    setState(() {
      _busy = true;
      _error = null;
    });

    try {
      final ok = await context.read<LkApiClient>().securityDisable();
      if (!mounted) return;
      setState(() {
        _busy = false;
        _enrolled = !ok;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _busy = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();

    final theme = Theme.of(context);

    return ListView(
      padding: const EdgeInsets.only(bottom: 24),
      children: [
        LkCard(
          title: 'Verificação em dois passos',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 8),
              Text(
                _enrolled
                    ? 'A sua conta tem um segundo fator. Um início de sessão '
                        'num dispositivo novo pede o código de seis dígitos '
                        'da sua aplicação de autenticação.'
                    : 'Um segundo fator protege a conta mesmo que a senha '
                        'seja apanhada. A partir de agora, um início de '
                        'sessão num dispositivo novo pede um código.',
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.error,
                  ),
                ),
              ],
              const SizedBox(height: 20),
              if (_uri != null)
                _enrolment()
              else
                Row(
                  children: [
                    Expanded(
                      child: ElevatedButton(
                        key: const Key('security-enrol'),
                        onPressed: _busy
                            ? null
                            : () => _enrolled ? _disable() : _startEnrolment(),
                        child: _busy
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                ),
                              )
                            : Text(_enrolled
                                ? 'Desativar'
                                : 'Ativar verificação em dois passos'),
                      ),
                    ),
                  ],
                ),
            ],
          ),
        ),
        if (_enrolled && _uri == null) ...[
          const SizedBox(height: 12),
          LkCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Dispositivos de confiança',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Este dispositivo está de confiança: os próximos inícios '
                  'de sessão daqui não pedem código. Num dispositivo novo, '
                  'o código é pedido uma vez e o dispositivo passa a ser de '
                  'confiança.',
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: theme.hintColor,
                    height: 1.5,
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }

  /// The QR, the secret, and the confirmation.
  Widget _enrolment() {
    final theme = Theme.of(context);
    final uri = _uri ?? '';

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          '1. Abra a aplicação de autenticação e leia o código.',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 16),
        Center(
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              // The QR has to be dark on a light background, or the
              // phone cannot read it. That is a requirement of the
              // format, not a brand decision.
              color: Colors.white, // tokens-check: allow
              borderRadius: BorderRadius.circular(12),
            ),
            child: QrImageView(
              data: uri,
              version: QrVersions.auto,
              size: 200,
              backgroundColor: Colors.white, // tokens-check: allow
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          '2. Ou introduza a chave à mão:',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 6),
        SelectableText(
          _secret ?? '',
          style: theme.textTheme.bodySmall?.copyWith(
            fontFamily: 'monospace',
            color: theme.hintColor,
          ),
        ),
        const SizedBox(height: 20),
        Text(
          '3. Escreva o código que a aplicação mostra.',
          style: theme.textTheme.bodyMedium,
        ),
        const SizedBox(height: 12),
        TextField(
          key: const Key('security-code'),
          controller: _codeController,
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.done,
          autocorrect: false,
          enableSuggestions: false,
          maxLength: 6,
          inputFormatters: [FilteringTextInputFormatter.digitsOnly],
          decoration: const InputDecoration(
            labelText: 'Código de verificação',
            counterText: '',
            prefixIcon: Icon(Icons.lock_outlined),
          ),
        ),
        const SizedBox(height: 12),
        ElevatedButton(
          key: const Key('security-confirm'),
          onPressed: _busy ? null : _confirm,
          child: _busy
              ? const SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Confirmar'),
        ),
      ],
    );
  }
}
