/// The mobile (and test) implementation: there is no URL to read.
///
/// A link on a phone arrives through the platform (a universal link or
/// an app link), not through a browser address bar, and that is a
/// different mechanism — with its own routing, its own verification
/// and its own rules. This stub does not pretend to handle it: it
/// returns nothing, and the flow waits for the platform to deliver it.
String? takeResetToken() => null;
