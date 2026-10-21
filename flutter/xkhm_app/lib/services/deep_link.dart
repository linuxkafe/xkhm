import 'deep_link_stub.dart'
    if (dart.library.js_interop) 'deep_link_web.dart' as impl;

/// Reads a token from the URL, and takes it out of the address bar.
///
/// The credential-recovery link is the one place where a **secret
/// travels in a URL**, so the details matter:
///
/// - the **fragment** (`#reset=…`) is preferred, because the browser
///   never sends it to the server: the token does not reach the access
///   log, nor a `Referer` header;
/// - the **query string** (`?reset=…`) is the fallback, for the
///   browsers or the links that do not carry a fragment;
/// - either way, the token is **removed from the address bar** with
///   `replaceState`, so that it does not stay in the history — and so
///   that a reload does not try to use it a second time.
class LkDeepLink {
  const LkDeepLink._();

  /// The name of the parameter that carries the token.
  static const String parameter = 'reset';

  /// The token in the URL, or null.
  ///
  /// The URL is cleaned as a side effect: the token is read once and
  /// never again.
  static String? takeResetToken() => impl.takeResetToken();
}
