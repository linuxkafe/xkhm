import 'package:flutter/material.dart';

import '../theme/lk_theme.dart';
import '../theme/tokens.dart';

/// The LINUXKAFE site header — a dark bar with the yellow brand.
class LkAppBar extends StatelessWidget implements PreferredSizeWidget {
  const LkAppBar({super.key, this.title, this.actions, this.showBrand = true});

  final String? title;
  final List<Widget>? actions;
  final bool showBrand;

  @override
  Size get preferredSize => const Size.fromHeight(56);

  @override
  Widget build(BuildContext context) {
    return AppBar(
      backgroundColor: LkColors.header,
      titleSpacing: 16,
      title: Row(
        children: [
          if (showBrand) ...[
            const Text(
              'LINUXKAFÉ',
              style: TextStyle(
                color: LkColors.primary,
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
                style: const TextStyle(color: Colors.white, fontSize: 16),
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
    return Container(
      decoration: BoxDecoration(
        color: LkColors.bgCard,
        borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        border: Border.all(color: LkColors.border),
        boxShadow: const [
          BoxShadow(
            color: LkSemantic.shadow,
            blurRadius: 6,
            offset: Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: const BoxDecoration(
                color: LkColors.header,
                borderRadius: BorderRadius.only(
                  topLeft: Radius.circular(LkLayout.borderRadius),
                  topRight: Radius.circular(LkLayout.borderRadius),
                ),
              ),
              child: Text(
                title!,
                style: const TextStyle(
                  color: Colors.white,
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
    final effective = tone ?? _toneFor(label);

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

  static LkBadgeTone _toneFor(String label) {
    switch (label.toLowerCase()) {
      case 'paid':
      case 'active':
      case 'ok':
      case 'connected':
        return const LkBadgeTone(
          background: LkSemantic.successBg,
          foreground: LkSemantic.successFg,
        );
      case 'unpaid':
      case 'open':
      case 'pending':
        return const LkBadgeTone(
          background: LkSemantic.warningBg,
          foreground: LkSemantic.warningFg,
        );
      case 'cancelled':
      case 'closed':
      case 'error':
      case 'disconnected':
        return const LkBadgeTone(
          background: LkSemantic.dangerBg,
          foreground: LkSemantic.dangerFg,
        );
      default:
        return const LkBadgeTone(
          background: LkSemantic.neutralBg,
          foreground: LkSemantic.neutralFg,
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
    return LkCard(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(LkLayout.borderRadius),
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(
            children: [
              if (icon != null) ...[
                Icon(icon, color: LkColors.primary, size: 28),
                const SizedBox(width: 12),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      value,
                      style: const TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.bold,
                        color: LkColors.text,
                      ),
                    ),
                    Text(label, style: LkTheme.muted),
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
              style: LkTheme.heading.copyWith(fontSize: 18),
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
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: LkColors.textLight),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: LkTheme.muted,
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
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48, color: LkSemantic.error),
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
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(32),
        child: CircularProgressIndicator(color: LkColors.primary),
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
