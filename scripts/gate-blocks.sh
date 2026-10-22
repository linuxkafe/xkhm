#!/usr/bin/env bash
# gate-blocks.sh — T290: record REAL gate blocks at the moment they happen.
#
# Before this script, rework was only detectable post-hoc via
# `make rework-detect` (git archaeology: fix commits, multi-edit files). T290
# instruments the hooks so that when a gate actually blocks a phase transition
# or a commit, the event is appended immediately — with ticket + gate + date —
# to aes/metrics/gate-blocks.log. Post-hoc archaeology becomes a cross-check,
# not the only source of truth.
#
# Usage:
#   ./scripts/gate-blocks.sh log <gate> [ticket]      # record a block now
#   ./scripts/gate-blocks.sh report                   # aggregate blocks per gate
#   ./scripts/gate-blocks.sh --dry-run log <gate>     # print, do not append
#
#   gate: kanban-check|adversarial-gate|debt-gate|verify-ac-evidence|
#         verify-implementation|lint|test-check|validate-phases|divergence-audit
#
# The instrumentation points (hooks) call `log` only when the gate returns
# non-zero — i.e. a REAL block that sent a ticket back to build. Each row is
# one rework event: ticket + gate + timestamp.
#
# Exit: 0 always (logging is non-blocking); 2 on usage errors.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LOG="${AES_GATE_BLOCKS_LOG:-$ROOT/aes/metrics/gate-blocks.log}"
DRY=0

while [ "$#" -gt 0 ]; do
	case "$1" in
	--dry-run)
		DRY=1
		shift
		;;
	-h | --help)
		grep '^#' "$0" | head -16
		exit 0
		;;
	*) break ;;
	esac
done

CMD="${1:-}"
[ -n "$CMD" ] || {
	echo "usage: gate-blocks.sh log <gate> [ticket] | report" >&2
	exit 2
}
shift || true

kanban_ticket() {
	grep "^current_ticket:" "$ROOT/aes/kanban.md" 2>/dev/null | head -1 | awk '{print $2}' | tr -d '[:space:]' || true
}

case "$CMD" in
log)
	GATE="${1:-}"
	T="${2:-}"
	[ -n "$GATE" ] || {
		echo "usage: gate-blocks.sh log <gate> [ticket]" >&2
		exit 2
	}
	[ -n "$T" ] || T="$(kanban_ticket)"
	[ -n "$T" ] || T="unknown"
	TS="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
	if [ "$DRY" -eq 1 ]; then
		echo "  (dry-run) block would be logged: $T | $GATE | $TS"
		exit 0
	fi
	mkdir -p "$ROOT/aes/metrics"
	echo "$TS|$T|$GATE" >>"$LOG"
	echo "  gate-blocks: logged block — $T blocked by $GATE ($TS)"
	;;
report)
	[ -f "$LOG" ] || {
		echo "  gate-blocks: no blocks logged yet (aes/metrics/gate-blocks.log)"
		exit 0
	}
	python3 - "$LOG" <<'PY'
import sys
from collections import Counter, defaultdict
rows = []
for line in open(sys.argv[1]):
    line = line.strip()
    if not line or line.startswith("date"):
        continue
    parts = line.split("|")
    if len(parts) == 3:
        rows.append(parts)  # ts, ticket, gate
by_gate = Counter(r[2] for r in rows)
by_ticket = defaultdict(list)
for ts, tk, gate in rows:
    by_ticket[tk].append(gate)
print("── rework by gate (from gate-blocks.log) ──")
for gate, n in by_gate.most_common():
    print(f"  {gate:24s} blocked {n}x")
if not by_gate:
    print("  (no blocks)")
print("── rework by ticket ──")
for tk, gates in sorted(by_ticket.items()):
    print(f"  {tk}: blocked by {', '.join(gates)}")
print(f"  TOTAL: {len(rows)} block event(s)")
PY
	;;
*)
	echo "gate-blocks: unknown command '$CMD' (log|report)" >&2
	exit 2
	;;
esac
