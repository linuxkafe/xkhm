/// Data transfer objects mirroring the `/api/v1` payloads.
///
/// Every field is nullable-tolerant so a payload change on the server cannot
/// crash the application; missing values degrade to sensible defaults.
library;

class LkClient {
  const LkClient({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.email,
  });

  final int id;
  final String firstName;
  final String lastName;
  final String email;

  String get fullName =>
      [firstName, lastName].where((s) => s.isNotEmpty).join(' ');

  factory LkClient.fromJson(Map<String, dynamic> json) => LkClient(
        id: _asInt(json['id']),
        firstName: json['firstname'] as String? ?? '',
        lastName: json['lastname'] as String? ?? '',
        email: json['email'] as String? ?? '',
      );
}

class LkBilling {
  const LkBilling({required this.creditBalance, required this.unpaidTotal});

  final double creditBalance;
  final double unpaidTotal;

  factory LkBilling.fromJson(Map<String, dynamic> json) => LkBilling(
        creditBalance: _asDouble(json['creditBalance']),
        unpaidTotal: _asDouble(json['unpaidTotal']),
      );
}

class LkCounts {
  const LkCounts({
    required this.invoices,
    required this.unpaidInvoices,
    required this.tickets,
    required this.openTickets,
    required this.domains,
  });

  final int invoices;
  final int unpaidInvoices;
  final int tickets;
  final int openTickets;
  final int domains;

  factory LkCounts.fromJson(Map<String, dynamic> json) => LkCounts(
        invoices: _asInt(json['invoices']),
        unpaidInvoices: _asInt(json['unpaidInvoices']),
        tickets: _asInt(json['tickets']),
        openTickets: _asInt(json['openTickets']),
        domains: _asInt(json['domains']),
      );
}

class LkSummary {
  const LkSummary({
    required this.client,
    required this.billing,
    required this.counts,
  });

  final LkClient client;
  final LkBilling billing;
  final LkCounts counts;

  factory LkSummary.fromJson(Map<String, dynamic> json) => LkSummary(
        client: LkClient.fromJson(
            (json['client'] as Map<String, dynamic>?) ?? const {}),
        billing: LkBilling.fromJson(
            (json['billing'] as Map<String, dynamic>?) ?? const {}),
        counts: LkCounts.fromJson(
            (json['counts'] as Map<String, dynamic>?) ?? const {}),
      );
}

class LkInvoice {
  const LkInvoice({
    required this.id,
    required this.number,
    required this.status,
    required this.dateCreated,
    required this.dueDate,
    required this.datePaid,
    required this.total,
    required this.taxRate,
    required this.note,
  });

  final int id;
  final String number;
  final String status;
  final String dateCreated;
  final String dueDate;
  final String datePaid;
  final double total;
  final double taxRate;
  final String note;

  bool get isPaid => status.toLowerCase() == 'paid';

  factory LkInvoice.fromJson(Map<String, dynamic> json) => LkInvoice(
        id: _asInt(json['id']),
        number: json['number'] as String? ?? '',
        status: json['status'] as String? ?? '',
        dateCreated: json['dateCreated'] as String? ?? '',
        dueDate: json['dueDate'] as String? ?? '',
        datePaid: json['datePaid'] as String? ?? '',
        total: _asDouble(json['total']),
        taxRate: _asDouble(json['taxRate']),
        note: json['note'] as String? ?? '',
      );
}

class LkInvoiceItem {
  const LkInvoiceItem({
    required this.id,
    required this.description,
    required this.amount,
  });

  final int id;
  final String description;
  final double amount;

  factory LkInvoiceItem.fromJson(Map<String, dynamic> json) => LkInvoiceItem(
        id: _asInt(json['id']),
        description: json['description'] as String? ?? '',
        amount: _asDouble(json['amount']),
      );
}

class LkDomain {
  const LkDomain({
    required this.id,
    required this.domain,
    required this.status,
    required this.expiryDate,
    required this.registrationDate,
    required this.nextDueDate,
    required this.period,
  });

  final int id;
  final String domain;
  final String status;
  final String expiryDate;
  final String registrationDate;
  final String nextDueDate;
  final int period;

  factory LkDomain.fromJson(Map<String, dynamic> json) => LkDomain(
        id: _asInt(json['id']),
        domain: json['domain'] as String? ?? '',
        status: json['status'] as String? ?? '',
        expiryDate: json['expiryDate'] as String? ?? '',
        registrationDate: json['registrationDate'] as String? ?? '',
        nextDueDate: json['nextDueDate'] as String? ?? '',
        period: _asInt(json['period']),
      );
}

class LkTicket {
  const LkTicket({
    required this.id,
    required this.number,
    required this.subject,
    required this.status,
    required this.priority,
    required this.name,
    required this.email,
    required this.date,
    required this.lastReply,
  });

  final int id;
  final String number;
  final String subject;
  final String status;
  final String priority;
  final String name;
  final String email;
  final String date;
  final String lastReply;

  bool get isClosed => status.toLowerCase() == 'closed';

  factory LkTicket.fromJson(Map<String, dynamic> json) => LkTicket(
        id: _asInt(json['id']),
        number: json['number'] as String? ?? '',
        subject: json['subject'] as String? ?? '',
        status: json['status'] as String? ?? '',
        priority: json['priority'] as String? ?? '',
        name: json['name'] as String? ?? '',
        email: json['email'] as String? ?? '',
        date: json['date'] as String? ?? '',
        lastReply: json['lastReply'] as String? ?? '',
      );
}

class LkTicketReply {
  const LkTicketReply({
    required this.id,
    required this.name,
    required this.message,
    required this.date,
    required this.adminReply,
  });

  final int id;
  final String name;
  final String message;
  final String date;
  final bool adminReply;

  factory LkTicketReply.fromJson(Map<String, dynamic> json) => LkTicketReply(
        id: _asInt(json['id']),
        name: json['name'] as String? ?? '',
        message: json['message'] as String? ?? '',
        date: json['date'] as String? ?? '',
        adminReply: json['adminReply'] as bool? ?? false,
      );
}

class LkArticle {
  const LkArticle({
    required this.id,
    required this.categoryId,
    required this.categoryTitle,
    required this.title,
    required this.description,
    required this.content,
    required this.lastUpdated,
  });

  final int id;
  final int categoryId;
  final String categoryTitle;
  final String title;
  final String description;
  final String content;
  final String lastUpdated;

  factory LkArticle.fromJson(Map<String, dynamic> json) => LkArticle(
        id: _asInt(json['id']),
        categoryId: _asInt(json['categoryId']),
        categoryTitle: json['categoryTitle'] as String? ?? '',
        title: json['title'] as String? ?? '',
        description: json['description'] as String? ?? '',
        content: json['content'] as String? ?? '',
        lastUpdated: json['lastUpdated'] as String? ?? '',
      );
}

class LkCategory {
  const LkCategory({
    required this.id,
    required this.title,
    required this.description,
    required this.sortOrder,
    required this.articleCount,
  });

  final int id;
  final String title;
  final String description;
  final int sortOrder;
  final int articleCount;

  factory LkCategory.fromJson(Map<String, dynamic> json) => LkCategory(
        id: _asInt(json['id']),
        title: json['title'] as String? ?? '',
        description: json['description'] as String? ?? '',
        sortOrder: _asInt(json['sortOrder']),
        articleCount: _asInt(json['articleCount']),
      );
}

class LkAnnouncement {
  const LkAnnouncement({
    required this.id,
    required this.title,
    required this.message,
    required this.date,
  });

  final int id;
  final String title;
  final String message;
  final String date;

  factory LkAnnouncement.fromJson(Map<String, dynamic> json) => LkAnnouncement(
        id: _asInt(json['id']),
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        date: json['date'] as String? ?? '',
      );
}

class LkDomainResult {
  const LkDomainResult({
    required this.domain,
    required this.available,
    required this.tld,
  });

  final String domain;
  final bool available;
  final String tld;

  factory LkDomainResult.fromJson(Map<String, dynamic> json) => LkDomainResult(
        domain: json['domain'] as String? ?? '',
        available: json['available'] as bool? ?? false,
        tld: json['tld'] as String? ?? '',
      );
}

class LkServerStatus {
  const LkServerStatus({
    required this.uptime,
    required this.load,
    required this.memoryUsage,
    required this.maxMemory,
    required this.phpVersion,
    required this.database,
    required this.lastCheck,
  });

  final String uptime;
  final String load;
  final String memoryUsage;
  final String maxMemory;
  final String phpVersion;
  final String database;
  final String lastCheck;

  /// The API mixes types here (a float for the load, a nested object for the
  /// database status), so every field is coerced defensively.
  factory LkServerStatus.fromJson(Map<String, dynamic> json) {
    final database = json['database'];
    final databaseText = database is Map<String, dynamic>
        ? (database['status'] as String? ?? '')
        : (database as String? ?? '');

    return LkServerStatus(
      uptime: _asString(json['uptime']),
      load: _asString(json['load']),
      memoryUsage: _asString(json['memory_usage']),
      maxMemory: _asString(json['memory_peak'] ?? json['max_memory']),
      phpVersion: _asString(json['php_version']),
      database: databaseText,
      lastCheck: _asString(json['last_check']),
    );
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

int _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? 0;
  return 0;
}

double _asDouble(Object? value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? 0;
  return 0;
}

/// Coerces any JSON scalar to a string. The API is not perfectly typed
/// everywhere (a load average arrives as a float, a memory figure as an
/// int), and a hard cast would crash the screen.
String _asString(Object? value) {
  if (value == null) return '';
  if (value is String) return value;
  if (value is num) {
    return value is double && value == value.roundToDouble()
        ? value.toStringAsFixed(0)
        : value.toString();
  }
  return value.toString();
}
