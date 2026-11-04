SHELL := /bin/bash

# The host runs only make, docker and git. Everything that executes vendored
# code (composer, npm, bin/console, phpunit) runs in a container that sees
# only this repo. See compose.yaml. Don't run php/composer/npm on the host.
#
# Network: the toolchain containers have no internet. Targets marked [egress]
# start an allowlisting proxy (docker/egress-proxy/squid.conf) for the length
# of one command, so they can reach the package registries and nothing else.

export HOST_UID := $(shell id -u)
export HOST_GID := $(shell id -g)

DC   := docker compose --env-file .env.local
PHP  := $(DC) run --rm php
PHPX := $(DC) run --rm --no-deps php
NODE := $(DC) run --rm --no-deps node

PROXY   := http://egress-proxy:3128
NOPROXY := localhost,127.0.0.1,database,mailer,ocr-service,web
NET_ENV := -e HTTP_PROXY=$(PROXY) -e HTTPS_PROXY=$(PROXY) \
           -e http_proxy=$(PROXY) -e https_proxy=$(PROXY) \
           -e NO_PROXY=$(NOPROXY) -e no_proxy=$(NOPROXY) \
           -e npm_config_proxy=$(PROXY) -e npm_config_https_proxy=$(PROXY)
PHPN  := $(DC) run --rm --no-deps $(NET_ENV) php
NODEN := $(DC) run --rm --no-deps $(NET_ENV) node

# Prefix for a recipe line: start the proxy, and stop it when the line exits
# (success or failure).
WITH_EGRESS = $(DC) --profile egress up -d --wait egress-proxy && \
              trap '$(DC) --profile egress stop egress-proxy >/dev/null 2>&1' EXIT &&

.DEFAULT_GOAL := help
.PHONY: help env build install composer-install npm-install serve watch assets \
        console composer npm sh-php sh-node audit ocr down psql \
        db-sync-password egress-check egress-logs tests

# Preflight: compose needs .env.local for POSTGRES_PASSWORD, and .npmrc must be
# a file before it is bind-mounted (otherwise Docker creates a directory).
GOALS := $(if $(MAKECMDGOALS),$(MAKECMDGOALS),help)
ifneq ($(filter-out help env,$(GOALS)),)
  ifeq ($(wildcard .env.local),)
    $(error .env.local is missing. Run `make env` first)
  endif
  ifeq ($(wildcard .npmrc),)
    $(error .npmrc is missing. It must exist (ignore-scripts=true) before running containers)
  endif
endif

help: ## List targets
	@grep -E '^[a-z-]+:.*## ' $(MAKEFILE_LIST) | awk -F':.*## ' '{printf "  %-18s %s\n", $$1, $$2}'

env: ## Add a random POSTGRES_PASSWORD to .env.local (gitignored), once
	@touch .env.local && chmod 600 .env.local
	@grep -q '^POSTGRES_PASSWORD=' .env.local \
	  && echo ".env.local already has POSTGRES_PASSWORD" \
	  || { printf 'POSTGRES_PASSWORD=%s\n' "$$(openssl rand -hex 24)" >> .env.local; echo "Added POSTGRES_PASSWORD to .env.local"; }

build: ## Build the toolchain and egress-proxy images
	$(DC) build php node
	$(DC) --profile egress build egress-proxy

install: composer-install npm-install ## [egress] composer install + npm ci, from the lockfiles only

composer-install:
	$(WITH_EGRESS) $(PHPN) composer install

npm-install:
	$(WITH_EGRESS) $(NODEN) npm ci

serve: ## App http://127.0.0.1:8000, Mailpit :8025, Postgres :30000
	$(DC) up web

watch: ## Encore watch (offline)
	$(NODE) npm run watch

assets: ## Production asset build (offline)
	$(NODE) npm run build

console: ## bin/console (offline), e.g. make console c="cache:clear"
	$(PHP) php bin/console $(c)

composer: ## [egress] composer, e.g. make composer c="outdated". Prefer install over update.
	$(WITH_EGRESS) $(PHPN) composer $(c)

npm: ## [egress] npm, e.g. make npm c="ls webpack"
	$(WITH_EGRESS) $(NODEN) npm $(c)

sh-php: ## Shell in the php toolchain container (offline)
	$(PHP) bash

sh-node: ## Shell in the node toolchain container (offline)
	$(NODE) bash

audit: ## [egress] composer audit + npm audit
	$(WITH_EGRESS) $(PHPN) composer audit; c=$$?; $(NODEN) npm audit; n=$$?; exit $$((c || n))

egress-check: ## Show that containers are offline and the proxy only allows registries
	@$(PHPX) php -r '$$s=@fsockopen("1.1.1.1",443,$$e,$$m,3); echo $$s ? "FAIL: php container reached the internet directly\n" : "ok: no direct internet from php\n"; exit($$s?1:0);'
	@$(PHPX) sh -c 'getent hosts example.com >/dev/null && echo "note: external DNS still resolves (possible DNS side channel)" || echo "ok: external DNS does not resolve"'
	@$(WITH_EGRESS) $(PHPN) sh -c '\
	  curl -sS -o /dev/null -w "ok: repo.packagist.org via proxy -> HTTP %{http_code}\n" https://repo.packagist.org/packages.json; \
	  curl -sS -o /dev/null https://example.com 2>/dev/null && { echo "FAIL: proxy allowed example.com"; exit 1; } || echo "ok: proxy blocks example.com"'

egress-logs: ## Proxy access log from the last [egress] run (look for TCP_DENIED)
	$(DC) --profile egress logs egress-proxy

ocr: ## Start the OCR service (internal network only, no host port)
	$(DC) --profile ocr up -d --build ocr-service

psql: ## psql shell in the database container
	$(DC) exec database sh -c 'psql -U "$$POSTGRES_USER" "$$POSTGRES_DB"'

down: ## Stop everything
	$(DC) --profile ocr --profile egress --profile blackfire down

db-sync-password: ## Apply POSTGRES_PASSWORD from .env.local to an existing DB volume
	$(DC) up -d --wait database
	$(DC) exec -T database sh -c 'echo "ALTER ROLE CURRENT_USER WITH PASSWORD :'\''pw'\'';" | psql -v ON_ERROR_STOP=1 -U "$$POSTGRES_USER" -d postgres -v pw="$$POSTGRES_PASSWORD"'

# The test schema is built from the entity mappings, not migrations: the
# migration history starts mid-stream (the migrations that created "user",
# "recipe", etc. were deleted), so it can't build a database from empty.
tests: ## Recreate the test DB from entity mappings and run phpunit (offline)
	$(PHP) bash -c '\
	  php bin/console doctrine:database:drop --force --if-exists --env=test && \
	  php bin/console doctrine:database:create --env=test && \
	  php bin/console doctrine:schema:create --env=test && \
	  php bin/console doctrine:fixtures:load -n --env=test && \
	  php bin/phpunit $(MAKECMDGOALS)'
