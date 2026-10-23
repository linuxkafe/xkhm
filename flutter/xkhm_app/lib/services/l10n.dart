import 'package:flutter/material.dart';

/// Simple translations for the client area.
class L10n {
  L10n(this.locale);

  final Locale locale;

  static L10n of(BuildContext context) {
    final locale = Localizations.localeOf(context);
    return L10n(locale);
  }

  bool get isPt => locale.languageCode == 'pt';

  String get login => isPt ? 'Iniciar Sessão' : 'Sign In';
  String get email => isPt ? 'Email' : 'Email';
  String get password => isPt ? 'Senha' : 'Password';
  String get forgotPassword => isPt ? 'Esqueci a senha?' : 'Forgot password?';
  String get verify => isPt ? 'Verificar' : 'Verify';
  String get logout => isPt ? 'Terminar sessão' : 'Sign out';
  String get theme => isPt ? 'Tema' : 'Theme';
  String get language => isPt ? 'Idioma' : 'Language';
  String get orders => isPt ? 'Pedidos' : 'Orders';
  String get invoices => isPt ? 'Faturas' : 'Invoices';
  String get services => isPt ? 'Serviços' : 'Services';
  String get domains => isPt ? 'Domínios' : 'Domains';
  String get home => isPt ? 'Início' : 'Home';
  String get dashboard => isPt ? 'Painel' : 'Dashboard';
  String get tickets => isPt ? 'Tickets' : 'Tickets';
  String get security => isPt ? 'Segurança' : 'Security';
  String get ssl => isPt ? 'Certificados SSL' : 'SSL Certificates';
  String get affiliates => isPt ? 'Afiliados' : 'Affiliates';
  String get knowledgebase => isPt ? 'Base de Conhecimento' : 'Knowledge Base';
  String get announcements => isPt ? 'Anúncios' : 'Announcements';
  String get domainChecker => isPt ? 'Procurar Domínio' : 'Domain Checker';
  String get serverStatus => isPt ? 'Status do Servidor' : 'Server Status';
  String get networkStatus => isPt ? 'Estado da Rede' : 'Network Status';
  String get order => isPt ? 'Encomendar' : 'Order';
}
