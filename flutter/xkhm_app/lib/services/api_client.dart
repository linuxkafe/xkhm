import 'dart:convert';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

/// Raised when the API answers with `ok: false`.
class LkApiException implements Exception {
  LkApiException(this.code, this.message, this.statusCode);

  final String code;
  final String message;
  final int statusCode;

  bool get isUnauthorized => statusCode == 401;

  @override
  String toString() => message;
}

/// Raised when the API cannot be reached at all.
class LkNetworkException implements Exception {
  LkNetworkException(this.message);

  final String message;

  @override
  String toString() => message;
}

/// HTTP client for the xkhm `/api/v1` backend.
///
/// The session is the *same* PHP session the website uses, so the cookie is
/// captured and replayed. There is no separate token system — that keeps the
/// WHMCS authentication semantics in exactly one place (the PHP backend).
///
/// ## Cookie handling differs per platform
///
/// On the **web** build the application is served from the same origin as
/// the API (`/app/` next to `/api/v1/`), so the browser manages the
/// `PHPSESSID` cookie itself. Manually setting a `Cookie` header would be
/// rejected by the browser, so we do not attempt it.
///
/// On **native** platforms there is no shared cookie jar, so the value is
/// captured from the `Set-Cookie` response header and replayed explicitly.
class LkApiClient {
  LkApiClient({http.Client? httpClient, String? baseUrl})
      : _http = httpClient ?? http.Client(),
        baseUrl = baseUrl ?? defaultBaseUrl;

  /// The API base URL.
  ///
  /// Empty (the default) means *same origin* — the correct setting for the
  /// web build, which is served next to the API. Native builds must pass an
  /// absolute URL:
  ///
  ///     flutter build apk --dart-define=API_BASE_URL=https://api.linuxkafe.com
  static const String defaultBaseUrl = String.fromEnvironment('API_BASE_URL');

  static const String _cookieKey = 'xkhm.session_cookie';

  final http.Client _http;
  final String baseUrl;

  String? _cookie;

  /// The current session cookie, if any. Always `null` on the web build,
  /// where the browser owns the cookie jar.
  String? get currentCookie => kIsWeb ? null : _cookie;

  /// The base URL of the API, without the trailing slash.
  String get apiRoot => '$baseUrl/api/v1';

  /// Restores a previously stored session cookie (native platforms only).
  Future<void> restoreSession() async {
    if (kIsWeb) return;

    final prefs = await SharedPreferences.getInstance();
    _cookie = prefs.getString(_cookieKey);
  }

  Future<void> _persistCookie(String? cookie) async {
    if (kIsWeb) return;

    _cookie = cookie;
    final prefs = await SharedPreferences.getInstance();
    if (cookie == null) {
      await prefs.remove(_cookieKey);
    } else {
      await prefs.setString(_cookieKey, cookie);
    }
  }

  void _captureCookie(http.Response response) {
    if (kIsWeb) return;

    // `package:http` joins repeated headers with ", " and the server may also
    // send several `Set-Cookie` headers separated by newlines. The login
    // response carries TWO PHPSESSID cookies: the one `session_start()`
    // created and the one `session_regenerate_id(true()` produced — the
    // first is dead, the second is the live session. Take the LAST one.
    final raw = response.headers['set-cookie'];
    if (raw == null || raw.isEmpty) return;

    final values = <String>[];
    for (final header in raw.split(RegExp(r'[,\n]'))) {
      final trimmed = header.trim();
      if (!trimmed.startsWith('PHPSESSID=')) continue;

      values.add(trimmed.split(';').first.split('=').skip(1).join('='));
    }

    if (values.isEmpty) return;
    _persistCookie(values.last);
  }

  Map<String, String> get _headers => {
        'Accept': 'application/json',
        if (_cookie != null) 'Cookie': 'PHPSESSID=$_cookie',
      };

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? query,
    Object? body,
  }) async {
    final uri = Uri.parse('$apiRoot$path').replace(
      queryParameters: query?.map((k, v) => MapEntry(k, '$v')),
    );

    late http.Response response;
    try {
      final headers = {..._headers};
      switch (method) {
        case 'GET':
          response = await _http.get(uri, headers: headers);
        case 'POST':
          response = await _http.post(
            uri,
            headers: {...headers, 'Content-Type': 'application/json'},
            body: jsonEncode(body ?? const <String, dynamic>{}),
          );
        default:
          throw ArgumentError('Unsupported method $method');
      }
    } on LkApiException {
      rethrow;
    } catch (error) {
      throw LkNetworkException(
        'Não foi possível contactar o servidor. Verifique a sua ligação.',
      );
    }

    _captureCookie(response);

    Map<String, dynamic> payload;
    try {
      final decoded = jsonDecode(response.body);
      payload = decoded is Map<String, dynamic> ? decoded : const {};
    } catch (_) {
      throw LkNetworkException(
        'Resposta inválida do servidor (${response.statusCode}).',
      );
    }

    final ok = payload['ok'] == true;
    if (!ok) {
      final error = payload['error'] as Map<String, dynamic>?;
      throw LkApiException(
        error?['code'] as String? ?? 'ERROR',
        error?['message'] as String? ?? 'Ocorreu um erro desconhecido.',
        response.statusCode,
      );
    }

    final data = payload['data'];
    return data is Map<String, dynamic> ? data : const {};
  }

  // ─── Auth ──────────────────────────────────────────────────────────────────

  /// The login.
  ///
  /// The answer is either the client — the session is open — or a
  /// **challenge**: the client has a second factor, and this device is
  /// not one it trusts.
  Future<Map<String, dynamic>> login(
    String email,
    String password, {
    String? deviceId,
  }) async {
    final data = await _send('POST', '/auth/login', body: {
      'email': email,
      'password': password,
      'deviceId': ?deviceId,
    });

    if (data['mfaRequired'] == true) {
      return data;
    }

    final client = data['client'] as Map<String, dynamic>? ?? const {};
    return client;
  }

  /// The answer to a challenge: the code from the authenticator app.
  Future<Map<String, dynamic>> mfa(
    String challenge,
    String code, {
    String? deviceId,
  }) async {
    final data = await _send('POST', '/auth/mfa', body: {
      'challenge': challenge,
      'code': code,
      'deviceId': ?deviceId,
    });

    return data['client'] as Map<String, dynamic>? ?? const {};
  }

  /// Whether the client has a second factor.
  Future<bool> securityEnrolled() async {
    final data = await _send('GET', '/client/security');
    return data['enrolled'] == true;
  }

  /// A new secret, and the QR the authenticator app reads.
  Future<Map<String, dynamic>> securityEnrol() =>
      _send('POST', '/client/security/enrol');

  /// The code that proves the secret works.
  Future<bool> securityConfirm(String code) async {
    final data = await _send('POST', '/client/security/confirm', body: {
      'code': code,
    });

    return data['enrolled'] == true;
  }

  /// Turn the second factor off.
  Future<bool> securityDisable() async {
    final data = await _send('POST', '/client/security/disable');
    return data['enrolled'] != true;
  }

  Future<Map<String, dynamic>> session() => _send('GET', '/auth/session');

  Future<void> logout() async {
    try {
      await _send('POST', '/auth/logout');
    } finally {
      await _persistCookie(null);
    }
  }

  // ─── Client area ───────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> clientSummary() => _send('GET', '/client/summary');

  Future<Map<String, dynamic>> home() => _send('GET', '/home');

  Future<Map<String, dynamic>> forgotPassword(String email) =>
      _send('POST', '/auth/forgot-password', body: {'email': email});

  /// Set a new password with a recovery token.
  Future<Map<String, dynamic>> resetPassword(String token, String password) =>
      _send('POST', '/auth/reset-password', body: {
        'token': token,
        'password': password,
      });

  Future<List<Map<String, dynamic>>> clientOrders() async {
    final data = await _send('GET', '/client/orders');
    return (data['orders'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> clientSsl() async {
    final data = await _send('GET', '/client/ssl');
    return (data['certificates'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> clientAffiliate() =>
      _send('GET', '/client/affiliate');

  /// The payment notice, as a PDF.
  ///
  /// It is a **commercial** document: it says what is owed and how to
  /// pay it, and it says on its face that it is not a fiscal document.
  String clientInvoicePdfUrl(int invoiceId) => '/api/v1/client/invoices/$invoiceId/pdf';

  /// Send the payment notice again, by e-mail.
  Future<bool> clientInvoiceResend(int invoiceId) async {
    final data = await _send('POST', '/client/invoices/$invoiceId/resend');
    return data['message'] != null;
  }

  Future<List<Map<String, dynamic>>> clientInvoices() async {
    final data = await _send('GET', '/client/invoices');
    return (data['invoices'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> clientInvoice(int id) =>
      _send('GET', '/client/invoices/$id');

  /// The services the client has, with their type.
  Future<List<Map<String, dynamic>>> clientServices() async {
    final data = await _send('GET', '/client/services');
    return (data['services'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> clientDomains() async {
    final data = await _send('GET', '/client/domains');
    return (data['domains'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> clientTickets() async {
    final data = await _send('GET', '/client/tickets');
    return (data['tickets'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> clientTicket(int id) =>
      _send('GET', '/client/tickets/$id');

  Future<int> createTicket({
    required String subject,
    required String message,
    String priority = 'Medium',
  }) async {
    final data = await _send('POST', '/client/tickets', body: {
      'subject': subject,
      'message': message,
      'priority': priority,
    });
    return (data['id'] as num?)?.toInt() ?? 0;
  }

  // ─── Public ────────────────────────────────────────────────────────────────

  Future<List<Map<String, dynamic>>> knowledgebaseCategories() async {
    final data = await _send('GET', '/knowledgebase/categories');
    return (data['categories'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> knowledgebase({
    int? categoryId,
    String? search,
  }) async {
    final query = <String, dynamic>{};
    if (categoryId != null) query['category'] = categoryId;
    if (search != null && search.isNotEmpty) query['search'] = search;

    final data = await _send('GET', '/knowledgebase', query: query);
    return (data['articles'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> knowledgebaseArticle(int id) =>
      _send('GET', '/knowledgebase/$id');

  Future<List<Map<String, dynamic>>> announcements() async {
    final data = await _send('GET', '/announcements', query: {'limit': 20});
    return (data['announcements'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> serverStatus() async {
    final data = await _send('GET', '/serverstatus');
    return data['status'] as Map<String, dynamic>? ?? const {};
  }

  Future<List<Map<String, dynamic>>> domainPricing() async {
    final data = await _send('GET', '/domains/pricing');
    return (data['pricing'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> checkDomain(String domain) async {
    final data = await _send('GET', '/domains/check', query: {'domain': domain});
    return (data['results'] as List<dynamic>? ?? const [])
        .cast<Map<String, dynamic>>();
  }

  void dispose() => _http.close();
}
