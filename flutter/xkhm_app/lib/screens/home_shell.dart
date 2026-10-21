import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/session.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';
import 'announcements_screen.dart';
import 'client_area_screen.dart';
import 'domain_checker_screen.dart';
import 'invoices_screen.dart';
import 'knowledgebase_screen.dart';
import 'server_status_screen.dart';
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

/// The authenticated shell: a navigation rail on wide screens, a bottom bar
/// on narrow ones.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static final List<LkDestination> _destinations = [
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
  ];

  @override
  Widget build(BuildContext context) {
    final destination = _destinations[_index];
    final wide = MediaQuery.sizeOf(context).width >= 900;

    final content = Column(
      children: [
        LkAppBar(
          title: destination.label,
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
          child: LkContentWidth(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            child: destination.builder(context),
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
              onDestinationSelected: (i) => setState(() => _index = i),
              leading: const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Text(
                  'LINUXKAFÉ',
                  style: TextStyle(
                    color: LkColors.header,
                    fontWeight: FontWeight.bold,
                    fontSize: 12,
                  ),
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
      body: content,
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        backgroundColor: LkColors.bgCard,
        indicatorColor: LkColors.primary,
        destinations: [
          for (final d in _destinations.take(4))
            NavigationDestination(
              icon: Icon(d.icon),
              label: d.label,
            ),
        ],
      ),
    );
  }
}
