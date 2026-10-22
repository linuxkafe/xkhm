import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/session.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';
import 'affiliate_screen.dart';
import 'announcements_screen.dart';
import 'client_area_screen.dart';
import 'domain_checker_screen.dart';
import 'domains_screen.dart';
import 'home_screen.dart';
import 'invoices_screen.dart';
import 'knowledgebase_screen.dart';
import 'orders_screen.dart';
import 'order_screen.dart';
import 'security_screen.dart';
import 'server_status_screen.dart';
import 'network_status_screen.dart';
import 'services_screen.dart';
import 'ssl_screen.dart';
import 'tickets_screen.dart';

/// One entry of the client-area navigation.
class LkDestination {
  const LkDestination({
    required this.label,
    required this.icon,
    required this.builder,
  });

  final String label;
  final IconData icon;
  final WidgetBuilder builder;
}

/// The authenticated shell.
///
/// * **Wide screens** — a navigation rail with every destination.
/// * **Narrow screens** — a bottom navigation bar with the four primary
///   destinations plus a *drawer* holding all of them. The drawer is what
///   guarantees that no destination is unreachable on a phone.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  /// The Scaffold lives *below* this state, so `Scaffold.of(context)` cannot
  /// find it (it looks up the tree). The key is the way to reach it.
  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();

  /// The destinations shown in the bottom navigation bar. The rest live in
  /// the drawer, so no destination is unreachable on a phone.
  static const List<int> _primaryDestinations = [0, 1, 2, 3];

  static final List<LkDestination> _destinations = [
    LkDestination(
      label: 'Início',
      icon: Icons.home_outlined,
      builder: (_) => const HomeScreen(),
    ),
    LkDestination(
      label: 'Painel',
      icon: Icons.dashboard_outlined,
      builder: (_) => const ClientAreaScreen(),
    ),
    LkDestination(
      label: 'Faturas',
      icon: Icons.receipt_long_outlined,
      builder: (_) => const InvoicesScreen(),
    ),
    LkDestination(
      label: 'Tickets',
      icon: Icons.support_agent_outlined,
      builder: (_) => const TicketsScreen(),
    ),
    LkDestination(
      label: 'Serviços',
      icon: Icons.dns_outlined,
      builder: (_) => const ServicesScreen(),
    ),
    LkDestination(
      label: 'Segurança',
      icon: Icons.shield_outlined,
      builder: (_) => const SecurityScreen(),
    ),
    LkDestination(
      label: 'Pedidos',
      icon: Icons.shopping_bag_outlined,
      builder: (_) => const OrdersScreen(),
    ),
    LkDestination(
      label: 'Encomendar',
      icon: Icons.add_shopping_cart_outlined,
      builder: (_) => const OrderScreen(),
    ),
    LkDestination(
      label: 'Domínios',
      icon: Icons.language_outlined,
      builder: (_) => const DomainsScreen(),
    ),
    LkDestination(
      label: 'Certificados SSL',
      icon: Icons.lock_outline,
      builder: (_) => const SslScreen(),
    ),
    LkDestination(
      label: 'Afiliados',
      icon: Icons.groups_outlined,
      builder: (_) => const AffiliateScreen(),
    ),
    LkDestination(
      label: 'Base de Conhecimento',
      icon: Icons.menu_book_outlined,
      builder: (_) => const KnowledgebaseScreen(),
    ),
    LkDestination(
      label: 'Anúncios',
      icon: Icons.campaign_outlined,
      builder: (_) => const AnnouncementsScreen(),
    ),
    LkDestination(
      label: 'Procurar Domínio',
      icon: Icons.search_outlined,
      builder: (_) => const DomainCheckerScreen(),
    ),
    LkDestination(
      label: 'Status do Servidor',
      icon: Icons.dns_outlined,
      builder: (_) => const ServerStatusScreen(),
    ),
    LkDestination(
      label: 'Estado da Rede',
      icon: Icons.cloud_off_outlined,
      builder: (_) => const NetworkStatusScreen(),
    ),
  ];

  void _select(int index) {
    setState(() => _index = index);
  }

  @override
  Widget build(BuildContext context) {
    final destination = _destinations[_index];
    final width = MediaQuery.sizeOf(context).width;
    final wide = width >= LkBreakpoints.breakpointLg;

    final content = Column(
      children: [
        LkAppBar(
          title: destination.label,
          leading: wide
              ? null
              : Semantics(
                  identifier: 'nav-drawer',
                  child: IconButton(
                    tooltip: 'Abrir menu de navegação',
                    icon: const Icon(Icons.menu),
                    onPressed: () => _openDrawer(context),
                  ),
                ),
          actions: [
            IconButton(
              tooltip: 'Terminar sessão',
              icon: const Icon(Icons.logout),
              onPressed: () => context.read<LkSession>().logout(),
            ),
            const SizedBox(width: 8),
          ],
        ),
        Expanded(
          child: SafeArea(
            top: false,
            child: LkContentWidth(
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
              child: destination.builder(context),
            ),
          ),
        ),
      ],
    );

    if (wide) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: _index,
              labelType: NavigationRailLabelType.all,
              onDestinationSelected: _select,
              // A barra rola: com quinze destinos, os últimos ficam
              // abaixo da dobra num ecrã baixo — e ficavam
              // **inalcançáveis**, sem-scroll nenhum.
              scrollable: true,
              leading: Padding(
                padding: const EdgeInsets.symmetric(vertical: 12),
                // A barra vive sobre o fundo da pagina — claro ou
                // escuro, conforme o modo. Por isso a variante do
                // logotipo segue o modo.
                child: Image.asset(
                  Theme.of(context).brightness == Brightness.dark
                      ? 'assets/images/linuxkafe.png'
                      : 'assets/images/linuxkafe-claro.png',
                  height: 26,
                  semanticLabel: 'LINUXKAFE',
                ),
              ),
              destinations: [
                for (final d in _destinations)
                  NavigationRailDestination(
                    icon: Icon(d.icon),
                    label: Text(d.label),
                  ),
              ],
            ),
            const VerticalDivider(width: 1),
            Expanded(child: content),
          ],
        ),
      );
    }

    return Scaffold(
      key: _scaffoldKey,
      drawer: _buildDrawer(context),
      body: content,
      bottomNavigationBar: NavigationBar(
        // -1 means "no destination of the bar is selected" — which is the
        // case when the user picked one from the drawer.
        selectedIndex: _primaryDestinations.indexOf(_index),
        onDestinationSelected: (i) => _select(_primaryDestinations[i]),
        backgroundColor: Theme.of(context).colorScheme.surface,
        indicatorColor: Theme.of(context).colorScheme.primary,
        destinations: [
          for (final index in _primaryDestinations)
            NavigationDestination(
              icon: Icon(_destinations[index].icon),
              label: _destinations[index].label,
            ),
        ],
      ),
    );
  }

  void _openDrawer(BuildContext context) {
    _scaffoldKey.currentState?.openDrawer();
  }

  /// The drawer holds **every** destination, so none is unreachable on a
  /// narrow screen.
  Widget _buildDrawer(BuildContext context) {
    final theme = Theme.of(context);

    return Drawer(
      child: SafeArea(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            Container(
              color: theme.appBarTheme.backgroundColor,
              padding: const EdgeInsets.fromLTRB(16, 20, 16, 20),
              child: Row(
                children: [
                  // O **logotipo**: a chaven com o wordmark. O
                  // cabecalho e' escuro, e por isso a variante escura
                  // (o wordmark branco).
                  Image.asset(
                    'assets/images/linuxkafe.png',
                    height: 30,
                    semanticLabel: 'LINUXKAFE',
                  ),
                ],
              ),
            ),
            for (var i = 0; i < _destinations.length; i++)
              Semantics(
                identifier: 'drawer-destination-$i',
                child: ListTile(
                  leading: Icon(_destinations[i].icon),
                  title: Text(
                    _destinations[i].label,
                    style: TextStyle(
                      color: theme.colorScheme.onSurface,
                      fontWeight:
                          i == _index ? FontWeight.w600 : FontWeight.normal,
                    ),
                  ),
                  selected: i == _index,
                  // A tint of the brand: the raw yellow would put the
                  // label on the same colour as the background.
                  selectedTileColor:
                      theme.colorScheme.primary.withValues(alpha: 0.16),
                  onTap: () {
                    Navigator.of(context).pop();
                    _select(i);
                  },
                ),
              ),
            const Divider(),
            Semantics(
              identifier: 'drawer-logout',
              child: ListTile(
                leading: const Icon(Icons.logout),
                title: const Text('Terminar sessão'),
                onTap: () {
                  Navigator.of(context).pop();
                  context.read<LkSession>().logout();
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
