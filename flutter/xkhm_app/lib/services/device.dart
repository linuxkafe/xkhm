import 'dart:math';

import 'package:shared_preferences/shared_preferences.dart';

/// The identity of the device the application runs on.
///
/// It is a random identifier, generated on the device and kept there. It
/// is **not** a fingerprint of the hardware: it says "this is the same
/// installation", and nothing more. The server pairs it with a signed
/// cookie, and that pair is what makes a device *trusted*.
///
/// The identifier never leaves the device except in the login request,
/// where the server uses it to recognise a device it has already seen.
class LkDevice {
  const LkDevice._();

  /// The name of the preference the identifier is kept in.
  static const String _preference = 'xkhm.device.id';

  /// The identifier of this device, generated on first use.
  static Future<String> id() async {
    final preferences = await SharedPreferences.getInstance();
    final existing = preferences.getString(_preference);

    if (existing != null && existing.isNotEmpty) {
      return existing;
    }

    final generated = _generate();
    await preferences.setString(_preference, generated);

    return generated;
  }

  /// A random identifier: 32 hexadecimal characters.
  static String _generate() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));

    return bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
  }
}
