import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/api_client.dart';
import '../theme/lk_theme.dart';
import '../widgets/lk_widgets.dart';

/// The password reset request.
///
/// The answer is deliberately the same whether or not the account exists —
/// revealing which addresses are registered would leak the client list.
class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({super.key});

  @override
  State<PasswordResetScreen> createState() => _PasswordResetScreenState();
}

class _PasswordResetScreenState extends State<PasswordResetScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();

  bool _busy = false;
  String? _message;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _busy = true;
      _message = null;
    });

    try {
      final data = await context
          .read<LkApiClient>()
          .forgotPassword(_emailController.text.trim());
      if (!mounted) return;
      setState(() {
        _message = '${data['message'] ?? 'Se a conta existir, enviámos as instruções.'}';
        _busy = false;
      });
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _message = e.message;
        _busy = false;
      });
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() {
        _message = e.message;
        _busy = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: const LkAppBar(title: 'Redefinir senha', showBrand: false),
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
                      title: 'Redefinir senha',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SizedBox(height: 8),
                          if (_message != null) ...[
                            Text(
                              _message!,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurface,
                              ),
                            ),
                            const SizedBox(height: 16),
                          ],
                          TextFormField(
                            key: const Key('reset-email'),
                            controller: _emailController,
                            keyboardType: TextInputType.emailAddress,
                            decoration: const InputDecoration(
                              labelText: 'Email',
                            ),
                            validator: (value) {
                              final v = (value ?? '').trim();
                              if (v.isEmpty) return 'Indique o seu email';
                              if (!v.contains('@')) return 'Email inválido';
                              return null;
                            },
                          ),
                          const SizedBox(height: 20),
                          ElevatedButton(
                            key: const Key('reset-submit'),
                            onPressed: _busy ? null : _submit,
                            child: _busy
                                ? SizedBox(
                                    height: 20,
                                    width: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: theme.colorScheme.onPrimary,
                                    ),
                                  )
                                : const Text('Enviar instruções'),
                          ),
                          const SizedBox(height: 8),
                          TextButton(
                            onPressed: () => Navigator.of(context).maybePop(),
                            child: const Text('Voltar ao início de sessão'),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Se o email existir na nossa base de dados, receberá as '
                      'instruções para redefinir a sua senha.',
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
