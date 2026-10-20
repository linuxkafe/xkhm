.PHONY: setup test lint format check doctor clean index wake sleep

PHP=php
PHP_VERSION=$(shell php -r "echo PHP_VERSION;")

setup: ## Initialize project dependencies
	@echo "xkhm setup — no composer/vendor expected yet"

test: ## Run test suite
	@echo "No tests yet — T001 phase"

lint: ## Lint source code
	@echo "No source yet — T001 phase"

format: ## Format source code
	@echo "No source yet — T001 phase"

check: test lint format ## Run all quality gates
	@echo "All gates passed (T001 placeholder)"

doctor: ## Check environment health
	@echo "=== xkhm doctor ==="
	@echo "PHP version: $(PHP_VERSION)"
	@php -l CLAUDE.md 2>/dev/null || true
	@echo "AES structure:"
	@test -f aes/kanban.md && echo "  [OK] kanban.md" || echo "  [MISSING] kanban.md"
	@test -d aes/tickets && echo "  [OK] aes/tickets/" || echo "  [MISSING] aes/tickets/"
	@test -d aes/sprints && echo "  [OK] aes/sprints/" || echo "  [MISSING] aes/sprints/"
	@test -f docs/VISION.md && echo "  [OK] docs/VISION.md" || echo "  [MISSING] docs/VISION.md"
	@test -f docs/REQUIREMENTS.md && echo "  [OK] docs/REQUIREMENTS.md" || echo "  [MISSING] docs/REQUIREMENTS.md"
	@test -f docs/ROADMAP.md && echo "  [OK] docs/ROADMAP.md" || echo "  [MISSING] docs/ROADMAP.md"
	@echo ""
	@echo "Live service:"
	@curl -s -o /dev/null -w "  xk.linuxkafe.com: %{http_code}\n" https://xk.linuxkafe.com/ 2>/dev/null || echo "  xk.linuxkafe.com: unreachable"
	@docker ps --format '{{.Names}}\t{{.Status}}' 2>/dev/null | grep -E "whmcs|mysql" || echo "  No whmcs/mysql containers running"

clean: ## Clean build artifacts
	@echo "Nothing to clean"

index: ## Generate AES index
	@echo "Index generation not yet configured"

wake: ## Pre-phase initialization
	@echo "Wake: loading AES context"

sleep: ## Post-phase housekeeping
	@echo "Sleep: nothing to archive yet"

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'
