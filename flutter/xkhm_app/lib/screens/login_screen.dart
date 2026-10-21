import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/session.dart';
import '../theme/lk_theme.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';

/// The login screen (pt-BR).
class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();

  bool _busy = false;
  bool _obscure = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _busy = true);

    final ok = await context.read<LkSession>().login(
          _emailController.text.trim(),
          _passwordController.text,
        );

    if (!mounted) return;
    setState(() => _busy = false);

    if (!ok) {
      final message = context.read<LkSession>().error ?? 'Falha ao iniciar sessão.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        color: LkColors.bg,
        child: SafeArea(
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
                      const Text(
                        'LINUXKAFÉ',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: LkColors.header,
                          fontSize: 30,
                          fontWeight: FontWeight.bold,
                          letterSpacing: 1,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Área do Cliente',
                        textAlign: TextAlign.center,
                        style: LkTheme.muted,
                      ),
                      const SizedBox(height: 28),
                      LkCard(
                        title: 'Iniciar Sessão',
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const SizedBox(height: 8),
                            Semantics(
                              identifier: 'login-email',
                              container: true,
                              explicitChildNodes: true,
                              textField: true,
                              child: TextFormField(
                                key: const Key('login-email'),
                                controller: _emailController,
                                keyboardType: TextInputType.emailAddress,
                                autofillHints: const [AutofillHints.email],
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
                            ),
                            const SizedBox(height: 16),
                            Semantics(
                              identifier: 'login-password',
                              container: true,
                              explicitChildNodes: true,
                              textField: true,
                              child: TextFormField(
                                key: const Key('login-password'),
                                controller: _passwordController,
                                obscureText: _obscure,
                                autofillHints: const [AutofillHints.password],
                                onFieldSubmitted: (_) => _submit(),
                                decoration: InputDecoration(
                                  labelText: 'Senha',
                                  suffixIcon: IconButton(
                                    icon: Icon(
                                      _obscure
                                          ? Icons.visibility_outlined
                                          : Icons.visibility_off_outlined,
                                    ),
                                    onPressed: () =>
                                        setState(() => _obscure = !_obscure),
                                  ),
                                ),
                                validator: (value) => (value ?? '').isEmpty
                                    ? 'Indique a sua senha'
                                    : null,
                              ),
                            ),
                            const SizedBox(height: 24),
                            ElevatedButton(
                              key: const Key('login-submit'),
                              onPressed: _busy ? null : _submit,
                              child: _busy
                                  ? const SizedBox(
                                      height: 20,
                                      width: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: LkColors.text,
                                      ),
                                    )
                                  : const Text('Entrar'),
                            ),
                            const SizedBox(height: 12),
                            TextButton(
                              onPressed: () {},
                              child: const Text('Esqueci a senha?'),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 24),
                      const Text(
                        'Servidores, Software Livre e Soberania Digital',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: LkColors.textLight,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
