SHELL := /bin/sh
.PHONY: all deps composer-install npm-install build-css cache-clear help

# Top-level convenience: install deps and build static assets
all: deps build-css

# Install dependencies (both PHP and Node)
deps: composer-install npm-install

composer-install:
	@echo "-- composer-install"
	@if command -v composer >/dev/null 2>&1; then \
		composer install --no-interaction --prefer-dist --optimize-autoloader; \
	else \
		echo "composer not found in PATH. Install Composer or run composer manually."; exit 0; \
	fi

npm-install:
	@echo "-- npm-install"
	@# Use a local cache directory to avoid permission issues with global ~/.npm
	@NPM_CACHE_DIR="$(CURDIR)/.npm-cache"; \
	if command -v npm >/dev/null 2>&1; then \
		mkdir -p "$${NPM_CACHE_DIR}" && npm ci --cache "$${NPM_CACHE_DIR}" --no-audit --progress=false || npm install --cache "$${NPM_CACHE_DIR}" --no-audit --no-fund; \
	else \
		echo "npm not found in PATH. Install Node.js/npm or run npm install manually."; exit 0; \
	fi

build-css:
	@echo "-- build-css"
	@if command -v npm >/dev/null 2>&1; then \
		npm run build-css; \
	else \
		echo "npm not found; skipping CSS build"; \
	fi

cache-clear:
	@echo "-- cache-clear (Symfony)"
	@if command -v php >/dev/null 2>&1 && [ -f bin/console ]; then \
		php bin/console cache:clear --no-warmup || true; \
	else \
		echo "php or bin/console not available; skipping cache clear"; \
	fi

help:
	@echo "Available targets:"
	@echo "  make all           -> install deps and build css"
	@echo "  make deps          -> run composer & npm install"
	@echo "  make composer-install"
	@echo "  make npm-install"
	@echo "  make build-css     -> run npm run build-css"
	@echo "  make cache-clear   -> run php bin/console cache:clear"
