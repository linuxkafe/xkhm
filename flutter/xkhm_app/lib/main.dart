import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:provider/provider.dart';

import 'screens/home_shell.dart';
import 'screens/login_screen.dart';
import 'services/api_client.dart';
import 'services/session.dart';
import 'theme/lk_theme.dart';
import 'theme/tokens.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const XkhmApp());
}

class XkhmApp extends StatefulWidget {
  const XkhmApp({super.key});

  @override
  State<XkhmApp> createState() => _XkhmAppState();
}

class _XkhmAppState extends State<XkhmApp> {
  final LkApiClient _api = LkApiClient();
  late final LkSession _session;

  @override
  void initState() {
    super.initState();
    _session = LkSession(_api);
    _session.bootstrap();
  }

  @override
  void dispose() {
    _api.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider<LkApiClient>.value(value: _api),
        ChangeNotifierProvider<LkSession>.value(value: _session),
      ],
      child: MaterialApp(
        title: 'LINUXKAFE',
        debugShowCheckedModeBanner: false,
        theme: LkTheme.light(),
        locale: const Locale('pt', 'BR'),
        supportedLocales: const [Locale('pt', 'BR')],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: const _Root(),
      ),
    );
  }
}

class _Root extends StatelessWidget {
  const _Root();

  @override
  Widget build(BuildContext context) {
    final state = context.watch<LkSession>().state;

    return switch (state) {
      LkAuthState.unknown => const _SplashScreen(),
      LkAuthState.authenticated => const HomeShell(),
      LkAuthState.unauthenticated => const LoginScreen(),
    };
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: LkColors.header,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              'LINUXKAFÉ',
              style: TextStyle(
                color: LkColors.primary,
                fontSize: 28,
                fontWeight: FontWeight.bold,
                letterSpacing: 1,
              ),
            ),
            SizedBox(height: 24),
            SizedBox(
              height: 28,
              width: 28,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: LkColors.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
