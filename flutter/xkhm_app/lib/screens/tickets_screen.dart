import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import '../theme/tokens.dart';
import '../widgets/lk_widgets.dart';

/// The support ticket list, with a "new ticket" action.
class TicketsScreen extends StatefulWidget {
  const TicketsScreen({super.key});

  @override
  State<TicketsScreen> createState() => _TicketsScreenState();
}

class _TicketsScreenState extends State<TicketsScreen> {
  List<LkTicket> _tickets = const [];
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final data = await context.read<LkApiClient>().clientTickets();
      if (!mounted) return;
      setState(() {
        _tickets = data.map(LkTicket.fromJson).toList();
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

  Future<void> _openNewTicket() async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: LkColors.bg,
      builder: (_) => const NewTicketSheet(),
    );

    if (created == true) {
      await _load();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const LkLoading();
    if (_error != null) return LkErrorView(message: _error!, onRetry: _load);

    return Column(
      children: [
        Align(
          alignment: Alignment.centerRight,
          child: ElevatedButton.icon(
            key: const Key('new-ticket'),
            onPressed: _openNewTicket,
            icon: const Icon(Icons.add),
            label: const Text('Abrir Ticket'),
          ),
        ),
        const SizedBox(height: 12),
        Expanded(
          child: _tickets.isEmpty
              ? const LkEmptyState(
                  message: 'Não tem tickets de suporte.',
                  icon: Icons.support_agent_outlined,
                )
              : ListView.separated(
                  itemCount: _tickets.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final ticket = _tickets[index];
                    return LkCard(
                      child: Row(
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  '#${ticket.number} — ${ticket.subject}',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w600,
                                    color: LkColors.text,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  'Criado: ${ticket.date}  •  Prioridade: ${ticket.priority}',
                                  style: const TextStyle(
                                    color: LkColors.textLight,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          const SizedBox(width: 12),
                          LkStatusBadge(label: ticket.status),
                        ],
                      ),
                    );
                  },
                ),
        ),
      ],
    );
  }
}

/// The "new ticket" form.
class NewTicketSheet extends StatefulWidget {
  const NewTicketSheet({super.key});

  @override
  State<NewTicketSheet> createState() => _NewTicketSheetState();
}

class _NewTicketSheetState extends State<NewTicketSheet> {
  final _formKey = GlobalKey<FormState>();
  final _subjectController = TextEditingController();
  final _messageController = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _subjectController.dispose();
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _busy = true);

    try {
      await context.read<LkApiClient>().createTicket(
            subject: _subjectController.text.trim(),
            message: _messageController.text.trim(),
          );
      if (!mounted) return;
      Navigator.of(context).pop(true);
    } on LkApiException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    } on LkNetworkException catch (e) {
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        top: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 20,
      ),
      child: Form(
        key: _formKey,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const LkSectionTitle('Abrir Ticket'),
            TextFormField(
              key: const Key('ticket-subject'),
              controller: _subjectController,
              decoration: const InputDecoration(labelText: 'Assunto'),
              validator: (v) => (v ?? '').trim().isEmpty ? 'Indique o assunto' : null,
            ),
            const SizedBox(height: 12),
            TextFormField(
              key: const Key('ticket-message'),
              controller: _messageController,
              maxLines: 5,
              decoration: const InputDecoration(labelText: 'Mensagem'),
              validator: (v) => (v ?? '').trim().isEmpty ? 'Indique a mensagem' : null,
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              key: const Key('ticket-submit'),
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
                  : const Text('Enviar Ticket'),
            ),
          ],
        ),
      ),
    );
  }
}
