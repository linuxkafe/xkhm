import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../theme/lk_theme.dart';
import '../widgets/lk_widgets.dart';

/// The password recovery flow (client).
///
/// Two steps: ask for the code, then set the new password with it.
///
/// The answer is always the same — whether or not the account exists — so
/// the screen never reveals which addresses are registered.
class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({super.key});

  @override
  State<PasswordResetScreen> createState() => _PasswordResetScreenState();
}

class _PasswordResetScreenState extends State<PasswordResetScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _tokenController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _busy = false;
  bool _codeRequested = false;
  String? _message;
  String? _error;
  bool _done = false;

  @override
  void dispose() {
    _emailController.dispose();
    _tokenController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _requestCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _message = null;
      _error = null;
    });

    try {
      final data = await context
          .read<LkApiClient>()
          .forgotPassword(_emailController.text.trim());

      if (!mounted) return;
      setState(() {
        _busy = false;
        _codeRequested = true;
        _message = '${data['message'] ?? 'Verifique o seu e-mail.'}';

        // In development the token comes back with the answer, so the flow
        // can be exercised without a mail server.
        final token = data['developmentToken'];
        if (token is String && token.isNotEmpty) {
          _tokenController.text = token;
          _message = '$_message\n'
              'Em desenvolvimento, o código foi preenchido automaticamente.';
        }
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    }
  }

  Future<void> _setPassword() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _message = null;
      _error = null;
    });

    try {
      await context.read<LkApiClient>().resetPassword(
            _tokenController.text.trim(),
            _passwordController.text,
          );

      if (!mounted) return;
      setState(() {
        _busy = false;
        _done = true;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _busy = false;
        _error = e.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: const LkAppBar(title: 'Recuperar senha', showBrand: false),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    LkCard(
                      title: _codeRequested ? 'Definir nova senha' : 'Recuperar senha',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SizedBox(height: 8),

                          if (_done) ...[
                            Icon(
                              Icons.check_circle_outline,
                              color: theme.colorScheme.primary,
                              size: 40,
                            ),
                            const SizedBox(height: 12),
                            const Text(
                              'Senha alterada com sucesso. Já pode iniciar sessão.',
                            ),
                            const SizedBox(height: 16),
                            ElevatedButton(
                              onPressed: () =>
                                  Navigator.of(context).maybePop(),
                              child: const Text('Voltar ao início de sessão'),
                            ),
                          ] else if (_codeRequested) ...[
                            TextFormField(
                              key: const Key('reset-token'),
                              controller: _tokenController,
                              decoration: const InputDecoration(
                                labelText: 'Código recebido',
                              ),
                              validator: (v) => (v ?? '').trim().isEmpty
                                  ? 'Indique o código'
                                  : null,
                            ),
                            const SizedBox(height: 16),
                            TextFormField(
                              key: const Key('reset-password'),
                              controller: _passwordController,
                              obscureText: true,
                              decoration: const InputDecoration(
                                labelText: 'Nova senha',
                              ),
                              validator: (v) => (v ?? '').length < 8
                                  ? 'A senha deve ter pelo menos 8 caracteres'
                                  : null,
                            ),
                            const SizedBox(height: 20),
                            ElevatedButton(
                              key: const Key('reset-submit'),
                              onPressed: _busy ? null : _setPassword,
                              child: _busy
                                  ? SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: theme.colorScheme.onPrimary,
                                      ),
                                    )
                                  : const Text('Definir nova senha'),
                            ),
                            const SizedBox(height: 8),
                            TextButton(
                              onPressed: () => setState(() {
                                _codeRequested = false;
                                _message = null;
                                _error = null;
                              }),
                              child: const Text('Voltar'),
                            ),
                          ] else ...[
                            TextFormField(
                              key: const Key('forgot-email'),
                              controller: _emailController,
                              keyboardType: TextInputType.emailAddress,
                              decoration: const InputDecoration(
                                labelText: 'Email da conta',
                              ),
                              validator: (v) {
                                final value = (v ?? '').trim();
                                if (value.isEmpty) return 'Indique o seu email';
                                if (!value.contains('@')) return 'Email inválido';
                                return null;
                              },
                            ),
                            const SizedBox(height: 20),
                            ElevatedButton(
                              key: const Key('forgot-submit'),
                              onPressed: _busy ? null : _requestCode,
                              child: _busy
                                  ? SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: theme.colorScheme.onPrimary,
                                      ),
                                    )
                                  : const Text('Pedir recuperação'),
                            ),
                          ],

                          if (_message != null) ...[
                            const SizedBox(height: 16),
                            Text(
                              _message!,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurfaceVariant,
                              ),
                            ),
                          ],

                          if (_error != null) ...[
                            const SizedBox(height: 16),
                            Text(
                              _error!,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.error,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Se a conta existir, enviámos as instruções. '
                      'O pedido é válido durante uma hora.',
                      textAlign: TextAlign.center,
                      style: LkTheme.muted(context),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
