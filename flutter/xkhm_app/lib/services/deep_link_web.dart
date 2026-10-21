import 'dart:js_interop';

@JS('location')
external _Location get _location;

@JS('history')
external _History get _history;

/// The browser's `location`, with the one property we need.
extension type _Location._(JSObject _) implements JSObject {
  /// The whole URL, fragment included.
  external String get href;
}

/// The browser's `history`, with the one method we need.
extension type _History._(JSObject _) implements JSObject {
  /// **Replace** the current history entry.
  ///
  /// Replacing and not pushing is the point: the token must not stay
  /// in the history, where the back button would find it.
  external void replaceState(JSAny? state, JSString title, JSString url);
}

/// The name of the parameter that carries the token.
const String _parameter = 'reset';

/// Read the token from the URL, and take the URL clean.
///
/// The fragment is preferred: the browser never sends it to the
/// server, so the token does not land in the access log.
///
/// The URL is read through the browser's own `location`, and not
/// through `Uri.base`, because `Uri.base` is an opaque `external` in
/// the engine and its exact behaviour with the fragment is not
/// something to bet a credential on.
String? takeResetToken() {
  final href = _location.href;

  if (href.isEmpty) {
    return null;
  }

  final uri = Uri.parse(href);

  // The fragment first: it never reaches the server.
  var token = _readToken(uri.fragment);
  final fromFragment = token != null;

  // The query string is the fallback.
  token ??= _readToken(uri.query);

  if (token == null || token.isEmpty) {
    return null;
  }

  _cleanTheUrl(uri, fromFragment: fromFragment);

  return token;
}

/// The value of the token parameter, in a query string or a fragment.
String? _readToken(String query) {
  if (query.isEmpty) {
    return null;
  }

  final value = Uri.splitQueryString(query)[_parameter];

  return (value != null && value.isNotEmpty) ? value : null;
}

/// Take the token out of the address bar.
void _cleanTheUrl(Uri uri, {required bool fromFragment}) {
  try {
    _history.replaceState(
      null,
      ''.toJS,
      _cleanedUrl(uri, fromFragment: fromFragment).toString().toJS,
    );
  } catch (_) {
    // A browser that refuses the history change is not a reason to
    // fail the reset: the token was read, and what happens to the
    // address bar is the user's call.
  }
}

/// The URL without the token, and without the leftovers.
///
/// `Uri.replace` is not enough: replacing the query parameters with an
/// empty map leaves a dangling `?`, and replacing the fragment with an
/// empty string leaves a dangling `#`. The URL is therefore rebuilt
/// from its parts.
Uri _cleanedUrl(Uri uri, {required bool fromFragment}) {
  final parameters = Map<String, String>.from(uri.queryParameters);

  if (!fromFragment) {
    parameters.remove(_parameter);
  }

  return Uri(
    scheme: uri.scheme,
    userInfo: uri.userInfo.isEmpty ? null : uri.userInfo,
    host: uri.host,
    port: uri.hasPort ? uri.port : null,
    path: uri.path,
    query: parameters.isEmpty ? null : Uri(queryParameters: parameters).query,
  );
}
