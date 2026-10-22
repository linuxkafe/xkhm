import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/session.dart';
import '../theme/lk_theme.dart';
import '../widgets/lk_widgets.dart';
import 'password_reset_screen.dart';

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
  final _codeController = TextEditingController();

  bool _busy = false;
  bool _obscure = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _codeController.dispose();
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
      final session = context.read<LkSession>();

      if (session.needsSecondFactor) return;

      final message = session.error ?? 'Falha ao iniciar sessão.';
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  Future<void> _submitCode() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _busy = true);

    final ok = await context.read<LkSession>().submitSecondFactor(
          _codeController.text.trim(),
        );

    if (!mounted) return;
    setState(() => _busy = false);

    if (!ok) {
      final message =
          context.read<LkSession>().error ?? 'Código inválido.';

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final needsCode = context.watch<LkSession>().needsSecondFactor;

    return Scaffold(
      body: Container(
        color: theme.colorScheme.surface,
        child: SafeArea(
          child: SingleChildScrollView(
            // The keyboard's inset, as a bottom padding. The content
            // stays at the top, so the field does not jump when the
            // keyboard appears — that is what made the focus drop on
            // Android.
            padding: EdgeInsets.only(
              left: 24,
              right: 24,
              top: 24,
              bottom: MediaQuery.viewInsetsOf(context).bottom + 24,
            ),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'LINUXKAFÉ',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: theme.colorScheme.onSurface,
                      fontSize: 30,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Área do Cliente',
                    textAlign: TextAlign.center,
                    style: LkTheme.muted(context),
                  ),
                  const SizedBox(height: 28),
                  LkCard(
                    title: needsCode
                        ? 'Verificação em dois passos'
                        : 'Iniciar Sessão',
                    child: needsCode
                        ? _secondFactor(theme)
                        : _credentials(theme),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Servidores, Software Livre e Soberania Digital',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.hintColor,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// The email and the password.
  Widget _credentials(ThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 8),
        TextFormField(
          key: const Key('login-email'),
          controller: _emailController,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          autocorrect: false,
          enableSuggestions: false,
          autofillHints: const [AutofillHints.email],
          decoration: const InputDecoration(
            labelText: 'Email',
            prefixIcon: Icon(Icons.email_outlined),
          ),
          validator: (value) {
            final v = (value ?? '').trim();
            if (v.isEmpty) return 'Indique o seu email';
            if (!v.contains('@')) return 'Email inválido';
            return null;
          },
        ),
        const SizedBox(height: 16),
        TextFormField(
          key: const Key('login-password'),
          controller: _passwordController,
          obscureText: _obscure,
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.password],
          onFieldSubmitted: (_) => _submit(),
          decoration: InputDecoration(
            labelText: 'Senha',
            prefixIcon: const Icon(Icons.lock_outlined),
            suffixIcon: IconButton(
              icon: Icon(
                _obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined,
              ),
              onPressed: () => setState(() => _obscure = !_obscure),
            ),
          ),
          validator: (value) =>
              (value ?? '').isEmpty ? 'Indique a sua senha' : null,
        ),
        const SizedBox(height: 24),
        ElevatedButton(
          key: const Key('login-submit'),
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
              : const Text('Entrar'),
        ),
        const SizedBox(height: 12),
        TextButton(
          key: const Key('login-forgot'),
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) => const PasswordResetScreen(),
            ),
          ),
          child: const Text('Esqueci a senha?'),
        ),
      ],
    );
  }

  /// The code from the authenticator app.
  Widget _secondFactor(ThemeData theme) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const SizedBox(height: 8),
        Text(
          'Este dispositivo ainda não é de confiança. Abra a aplicação de '
          'autenticação e introduza o código de seis dígitos.',
          style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
        ),
        const SizedBox(height: 20),
        TextFormField(
          key: const Key('login-code'),
          controller: _codeController,
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.done,
          autofillHints: const [AutofillHints.oneTimeCode],
          onFieldSubmitted: (_) => _submitCode(),
          decoration: const InputDecoration(
            labelText: 'Código de verificação',
            prefixIcon: Icon(Icons.pin_outlined),
          ),
          validator: (value) {
            final v = (value ?? '').trim();
            if (v.isEmpty) return 'Indique o código';
            if (v.length < 6) return 'O código tem seis dígitos';
            return null;
          },
        ),
        const SizedBox(height: 24),
        ElevatedButton(
          key: const Key('login-code-submit'),
          onPressed: _busy ? null : _submitCode,
          child: _busy
              ? SizedBox(
                  height: 20,
                  width: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: theme.colorScheme.onPrimary,
                  ),
                )
              : const Text('Verificar'),
        ),
        const SizedBox(height: 12),
        TextButton(
          key: const Key('login-code-cancel'),
          onPressed: () => context.read<LkSession>().cancelSecondFactor(),
          child: const Text('Voltar'),
        ),
      ],
    );
  }
}
