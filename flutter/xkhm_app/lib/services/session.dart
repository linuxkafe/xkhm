import 'package:flutter/foundation.dart';

import '../models/models.dart';
import 'api_client.dart';

enum LkAuthState { unknown, authenticated, unauthenticated }

/// Holds the authenticated session and the loaded client-area data.
class LkSession extends ChangeNotifier {
  LkSession(this._api);

  final LkApiClient _api;

  LkAuthState _state = LkAuthState.unknown;
  LkClient? _client;
  String? _error;

  LkAuthState get state => _state;
  LkClient? get client => _client;
  String? get error => _error;
  bool get isAuthenticated => _state == LkAuthState.authenticated;

  /// Restores a stored session cookie and validates it against the API.
  ///
  /// The API is always probed, even without a stored cookie: on the web
  /// build the browser owns the cookie jar, so a user who is already signed
  /// in on the website must land straight in the client area.
  Future<void> bootstrap() async {
    await _api.restoreSession();

    try {
      final data = await _api.session();
      final clientJson = data['client'] as Map<String, dynamic>? ?? const {};
      _client = LkClient.fromJson(clientJson);
      _state = LkAuthState.authenticated;
    } on LkApiException {
      _state = LkAuthState.unauthenticated;
    } on LkNetworkException {
      // Offline: keep the state unknown so the UI can show a retry.
      _state = LkAuthState.unauthenticated;
      _error = 'Não foi possível contactar o servidor.';
    }
    notifyListeners();
  }

  Future<bool> login(String email, String password) async {
    _error = null;
    notifyListeners();

    try {
      final clientJson = await _api.login(email, password);
      _client = LkClient.fromJson(clientJson);
      _state = LkAuthState.authenticated;
      notifyListeners();
      return true;
    } on LkApiException catch (e) {
      _error = e.message;
      _state = LkAuthState.unauthenticated;
      notifyListeners();
      return false;
    } on LkNetworkException catch (e) {
      _error = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<void> logout() async {
    await _api.logout();
    _client = null;
    _state = LkAuthState.unauthenticated;
    notifyListeners();
  }
}
