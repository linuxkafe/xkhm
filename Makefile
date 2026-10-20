.PHONY: setup test lint format check doctor clean index wake sleep install dev

PHP=php
PHP_VERSION=$(shell php -r "echo PHP_VERSION;")
COMPOSER=composer

setup: install ## Initialize project dependencies
	@echo "Setup complete"

install: vendor/autoload.php ## Install PHP dependencies

vendor/autoload.php: composer.json composer.lock
	$(COMPOSER) install --no-interaction

test: vendor/autoload.php ## Run test suite
	@echo "Running unit tests..."
	./vendor/bin/phpunit --configuration phpunit.xml.dist

lint: vendor/autoload.php ## Lint source code
	@echo "Running PHPStan..."
	./vendor/bin/phpstan analyse --level=5 src tests

format: vendor/autoload.php ## Format source code
	@echo "Running PHP-CS-Fixer..."
	./vendor/bin/php-cs-fixer fix --allow-risky=yes --diff --dry-run

check: test lint ## Run all quality gates
	@echo "All gates passed"

doctor: ## Check environment health
	@echo "=== xkhm doctor ==="
	@echo "PHP version: $(PHP_VERSION)"
	@php -l src/Config.php 2>/dev/null && echo "  [OK] src/Config.php" || echo "  [ERROR] src/Config.php"
	@php -l public/index.php 2>/dev/null && echo "  [OK] public/index.php" || echo "  [ERROR] public/index.php"
	@test -f composer.json && echo "  [OK] composer.json" || echo "  [MISSING] composer.json"
	@test -f vendor/autoload.php && echo "  [OK] vendor/autoload.php" || echo "  [MISSING] vendor/autoload.php"
	@echo ""
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
	rm -rf vendor/
	rm -f composer.lock

index: ## Generate AES index
	@echo "Index generation not yet configured"

wake: ## Pre-phase initialization
	@echo "Wake: loading AES context"

sleep: ## Post-phase housekeeping
	@echo "Sleep: nothing to archive yet"

dev: vendor/autoload.php ## Start development server
	@echo "Starting dev server on http://localhost:8080"
	php -S localhost:8080 -t public

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-12s\033[0m %s\n", $$1, $$2}'