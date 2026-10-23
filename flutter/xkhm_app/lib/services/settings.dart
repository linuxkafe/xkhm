import 'package:flutter/material.dart';

/// The user's preferences: the theme mode and the language.
///
/// They live in memory for now (no persistence); persistence can be
/// added later without changing the callers.
class LkSettings extends ChangeNotifier {
  LkSettings() : _themeMode = ThemeMode.system, _locale = const Locale('pt');

  ThemeMode _themeMode;
  Locale _locale;

  ThemeMode get themeMode => _themeMode;
  Locale get locale => _locale;

  void setThemeMode(ThemeMode mode) {
    if (_themeMode == mode) return;
    _themeMode = mode;
    notifyListeners();
  }

  void toggleTheme() {
    _themeMode = _themeMode == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    notifyListeners();
  }

  void setLocale(Locale locale) {
    if (_locale == locale) return;
    _locale = locale;
    notifyListeners();
  }

  void toggleLocale() {
    _locale = _locale.languageCode == 'pt' ? const Locale('en') : const Locale('pt');
    notifyListeners();
  }
}
