import 'package:flutter/material.dart';

import '../theme/lk_theme.dart';
import '../theme/tokens.dart';

/// The LINUXKAFE site header — a dark bar with the yellow brand.
///
/// The colours come from the theme so the widget repaints when the system
/// switches between light and dark mode.
class LkAppBar extends StatelessWidget implements PreferredSizeWidget {
  const LkAppBar({
    super.key,
    this.title,
    this.actions,
    this.leading,
    this.showBrand = true,
  });

  final String? title;
  final List<Widget>? actions;

  /// The button in the leading slot (the navigation drawer on a phone).
  ///
  /// `automaticallyImplyLeading` is disabled so that Flutter does not insert
  /// a *second* drawer button of its own.
  final Widget? leading;

  final bool showBrand;

  @override
  Size get preferredSize => const Size.fromHeight(56);

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    // The AppBar sits on the header colour, so it needs the header's own
    // foreground — the body text colour would be invisible on it.
    final onHeader = theme.appBarTheme.foregroundColor ??
        theme.colorScheme.onSurface;

    return AppBar(
      backgroundColor: theme.appBarTheme.backgroundColor,
      foregroundColor: onHeader,
      automaticallyImplyLeading: false,
      leading: leading,
      titleSpacing: 16,
      title: Row(
        children: [
          if (showBrand) ...[
            Text(
              'LINUXKAFÉ',
              style: TextStyle(
                color: theme.colorScheme.primary,
                fontWeight: FontWeight.bold,
                letterSpacing: 0.5,
                fontSize: 18,
              ),
            ),
            if (title != null) const SizedBox(width: 16),
          ],
          if (title != null)
            Flexible(
              child: Text(
                title!,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(color: onHeader, fontSize: 16),
              ),
            ),
        ],
      ),
      actions: actions,
    );
  }
}

/// A bordered content card, matching the CSS `.panel`.
class LkCard extends StatelessWidget {
  const LkCard({
    super.key,
    required this.child,
    this.title,
    this.padding = const EdgeInsets.all(16),
  });

  final Widget child;
  final String? title;
  final EdgeInsets padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        border: Border.all(color: theme.dividerColor),
        boxShadow: [
          BoxShadow(
            color: theme.brightness == Brightness.dark
                ? LkSemantic.dark.shadow
                : LkSemantic.light.shadow,
            blurRadius: 6,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: theme.appBarTheme.backgroundColor,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(LkLayout.borderRadius),
                  topRight: Radius.circular(LkLayout.borderRadius),
                ),
              ),
              child: Text(
                title!,
                style: TextStyle(
                  color: theme.appBarTheme.foregroundColor,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          Padding(padding: padding, child: child),
        ],
      ),
    );
  }
}

/// A small coloured status pill.
class LkStatusBadge extends StatelessWidget {
  const LkStatusBadge({super.key, required this.label, this.tone});

  final String label;
  final LkBadgeTone? tone;

  @override
  Widget build(BuildContext context) {
    final effective = tone ?? _toneFor(label, Theme.of(context));

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: effective.background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: effective.foreground,
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  static LkBadgeTone _toneFor(String label, ThemeData theme) {
    final semantic = theme.brightness == Brightness.dark
        ? LkSemantic.dark
        : LkSemantic.light;

    switch (label.toLowerCase()) {
      case 'paid':
      case 'active':
      case 'ok':
      case 'connected':
        return LkBadgeTone(
          background: semantic.successBg,
          foreground: semantic.successFg,
        );
      case 'unpaid':
      case 'open':
      case 'pending':
        return LkBadgeTone(
          background: semantic.warningBg,
          foreground: semantic.warningFg,
        );
      case 'disponível':
      case 'disponivel':
        return LkBadgeTone(
          background: semantic.successBg,
          foreground: semantic.successFg,
        );
      case 'registado':
      case 'cancelled':
      case 'closed':
      case 'error':
      case 'disconnected':
        return LkBadgeTone(
          background: semantic.dangerBg,
          foreground: semantic.dangerFg,
        );
      default:
        return LkBadgeTone(
          background: semantic.neutralBg,
          foreground: semantic.neutralFg,
        );
    }
  }
}

class LkBadgeTone {
  const LkBadgeTone({required this.background, required this.foreground});

  final Color background;
  final Color foreground;
}

/// A labelled statistic, used on the dashboard.
class LkStatTile extends StatelessWidget {
  const LkStatTile({
    super.key,
    required this.label,
    required this.value,
    this.icon,
    this.onTap,
  });

  final String label;
  final String value;
  final IconData? icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return LkCard(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              if (icon != null) ...[
                Icon(icon, color: theme.colorScheme.primary, size: 28),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      value,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: theme.colorScheme.onSurface,
                      ),
                    ),
                    Text(label, style: LkTheme.muted(context)),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A page-level section heading.
class LkSectionTitle extends StatelessWidget {
  const LkSectionTitle(this.title, {super.key, this.trailing});

  final String title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12, top: 8),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: LkTheme.heading(context).copyWith(fontSize: 18),
            ),
          ),
          ?trailing,
        ],
      ),
    );
  }
}

/// A neutral empty-state placeholder.
class LkEmptyState extends StatelessWidget {
  const LkEmptyState({
    super.key,
    required this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
  });

  final String message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: theme.hintColor),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: LkTheme.muted(context),
            ),
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

/// An error panel with a retry action.
class LkErrorView extends StatelessWidget {
  const LkErrorView({super.key, required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.error_outline,
              size: 48,
              color: theme.colorScheme.error,
            ),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Tentar novamente'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// A centred loader.
class LkLoading extends StatelessWidget {
  const LkLoading({super.key});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: CircularProgressIndicator(color: Theme.of(context).colorScheme.primary),
      ),
    );
  }
}

/// Constrains the content to the `--width-max` token.
class LkContentWidth extends StatelessWidget {
  const LkContentWidth({super.key, required this.child, this.padding});

  final Widget child;
  final EdgeInsets? padding;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: LkLayout.widthMax),
        child: Padding(
          padding: padding ?? const EdgeInsets.all(16),
          child: child,
        ),
      ),
    );
  }
}

/// The greeting at the top of the home screen.
///
/// A modern application opens with the **identity** and the **figures**,
/// not with a panel that takes half the screen to say "hello".
///
/// The colours come from the theme's tokens — the gradient is the brand
/// primary, and the text is the "on primary" colour — so the hero
/// follows the dark mode like everything else.
class LkHeroCard extends StatelessWidget {
  const LkHeroCard({
    super.key,
    required this.title,
    this.subtitle,
    this.figures = const [],
  });

  /// The client's name.
  final String title;

  /// The e-mail, or anything else that identifies the account.
  final String? subtitle;

  /// The figures, in the order they should appear.
  final List<Widget> figures;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            theme.colorScheme.primary,
            Color.lerp(
              theme.colorScheme.primary,
              theme.colorScheme.primary,
              0.55,
            )!,
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: theme.textTheme.headlineSmall?.copyWith(
              fontWeight: FontWeight.w700,
              color: theme.colorScheme.onPrimary,
            ),
          ),
          if (subtitle != null && subtitle!.isNotEmpty) ...[
            const SizedBox(height: 2),
            Text(
              subtitle!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onPrimary.withValues(alpha: 0.8),
              ),
            ),
          ],
          if (figures.isNotEmpty) ...[
            const SizedBox(height: 18),
            // The figures share the row: two fit on a phone, and they
            // take the width they need rather than a fixed one.
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (var index = 0; index < figures.length; index++) ...[
                  if (index > 0) const SizedBox(width: 16),
                  Expanded(child: figures[index]),
                ],
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// A figure inside the hero: a value and its label.
class LkHeroFigure extends StatelessWidget {
  const LkHeroFigure({
    super.key,
    required this.label,
    required this.value,
    this.icon,
  });

  final String label;
  final String value;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(
            icon,
            size: 20,
            color: theme.colorScheme.onPrimary.withValues(alpha: 0.75),
          ),
          const SizedBox(height: 4),
        ],
        Text(
          value,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w700,
            color: theme.colorScheme.onPrimary,
          ),
        ),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.bodySmall?.copyWith(
            color: theme.colorScheme.onPrimary.withValues(alpha: 0.8),
          ),
        ),
      ],
    );
  }
}

/// A text that is **clipped** to a few lines, with a "read more" that
/// expands it.
///
/// The announcements and the knowledge-base articles carry a whole
/// article in them. On a phone, that pushes everything else off the
/// screen — so the text is summarised, and one tap shows the rest.
///
/// Nothing is lost: the full text is there, one tap away.
class LkExpandableText extends StatefulWidget {
  const LkExpandableText({
    super.key,
    required this.text,
    this.maxLines = 3,
    this.moreLabel = 'Ler mais',
    this.lessLabel = 'Ler menos',
  });

  /// The text to show.
  final String text;

  /// How many lines the summary has.
  final int maxLines;

  final String moreLabel;
  final String lessLabel;

  @override
  State<LkExpandableText> createState() => _LkExpandableTextState();
}

class _LkExpandableTextState extends State<LkExpandableText> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.bodyMedium?.copyWith(height: 1.45);

    // Whether the text is **clipped** is measured, not guessed: a
    // "read more" that opens nothing is worse than no button.
    return LayoutBuilder(
      builder: (context, constraints) {
        final painter = TextPainter(
          text: TextSpan(text: widget.text, style: style),
          maxLines: widget.maxLines,
          textDirection: TextDirection.ltr,
        )..layout(maxWidth: constraints.maxWidth);

        final clipped = painter.didExceedMaxLines;

        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.text,
              maxLines: _expanded ? null : widget.maxLines,
              overflow: _expanded ? null : TextOverflow.ellipsis,
              style: style,
            ),
            if (clipped || _expanded) ...[
              const SizedBox(height: 4),
              // The target is at least the minimum touch target, even
              // though the label itself is small.
              ConstrainedBox(
                constraints: const BoxConstraints(
                  minHeight: LkLayout.touchTargetMin,
                ),
                child: Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton(
                    onPressed: () => setState(() => _expanded = !_expanded),
                    style: TextButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 0),
                      minimumSize: const Size(0, LkLayout.touchTargetMin),
                      tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    ),
                    child: Text(
                      _expanded ? widget.lessLabel : widget.moreLabel,
                    ),
                  ),
                ),
              ),
            ],
          ],
        );
      },
    );
  }
}

/// A scrollable that can be pulled down to reload.
///
/// The gesture is the one everybody expects from a phone, and it is
/// the fastest way to see a new invoice without hunting for a menu.
class LkRefreshable extends StatelessWidget {
  const LkRefreshable({
    super.key,
    required this.onRefresh,
    required this.child,
  });

  /// The reload. It has to return a `Future` — the indicator waits
  /// for it.
  final Future<void> Function() onRefresh;

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(onRefresh: onRefresh, child: child);
  }
}
