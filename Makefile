# Three tiers of verification, the cadence every repository of the suite follows:
#
#   make check                              every change: code style and static analysis.
#   make test-unit FILTER=... FILE=...      a feature is done: the tests that cover it.
#   make test                               end of a cycle, and before anything is handed over.
#
# What each tier skips, and when skipping is wrong:
#
# - `check` is phpcs and psalm, on the host, with their caches (each keyed by file content and by the
#   tool's own configuration) and with phpcs forking over this machine's cores. `make check-full`
#   ignores the caches and is the run the cached one is checked against. Neither the forking nor the
#   cache changes what a check reports.
# - `test-unit` and `test-integration` take FILTER (a class or method pattern) and FILE (a path,
#   relative to the repository, which wins over the suite the target selects). They skip no setup:
#   each suite builds its own sandbox or project, and the integration one reuses the vendors cache
#   that OZTestProject keys by dependency set. What a narrow run still pays is the container start,
#   about five seconds.
# - `test` is every suite that answers for the code, the service and runtime servers included, and is
#   the only thing that says a change broke nothing elsewhere. Start it in the background and carry
#   on working. `test-provision` is deliberately not one of them: see the target.
#
# The tiers are about re-running, never about writing: a feature's test is written with the feature.
# A failure in a narrowed run is repeated with the full target before it is believed or reported.
#
# Code style, static analysis, the fixer and the ORM generator run on the host by default (RUNNER=host):
# they need nothing but PHP and `vendor/`, which `make install` writes in the container so Composer
# resolves as CI does (the first-party packages land there as real directories, so the host reads them).
# `make host-check` says whether the host's PHP satisfies composer.json.
#
# Everything else runs in Docker (docker/compose.yaml) whatever RUNNER says, and so does `make ci`: that
# image is the reference environment. The unit suite is among them: `ImagesTest` drives every image
# driver OZone supports (gd, imagick, vips) and only the image has all three. RUNNER=docker puts the
# host-side targets back in the container too.

.PHONY: docker-build install shell host-check down test test-unit test-services test-runtimes test-provision test-integration test-coverage ci benchmark check check-full cs orm lint fix clean

export RUNNER ?= host

DOCKER_COMPOSE = docker compose -f docker/compose.yaml

# The containers run as the host user, so the files they write stay the user's.
export OZ_UID := $(shell id -u)
export OZ_GID := $(shell id -g)

# The PHP container with the test servers started, or alone when a target needs none.
RUN        = $(DOCKER_COMPOSE) run --rm php
RUN_NODEPS = $(DOCKER_COMPOSE) run --rm --no-deps php

# What needs no server: the host, or the container when RUNNER=docker.
ifeq ($(RUNNER),docker)
RUN_FREE = $(RUN_NODEPS)
else
RUN_FREE =
endif

PHPUNIT = vendor/bin/phpunit -c phpunit.xml.dist --do-not-record-test-run-history

# What narrows a test target to what a change touched. FILE is a path and wins over the suite the
# target selects, so it names a file of that suite; FILTER is a class or method pattern.
NARROW = $(if $(FILTER),--filter '$(FILTER)',) $(FILE)

# phpcs reads one file at a time unless told to fork (it needs ext-pcntl). psalm counts the cores
# itself, and takes one in CI on purpose, so it is left to decide.
PHPCS_JOBS  ?= $(shell nproc 2>/dev/null || echo 1)
# phpcs caches only when given a file to cache into; psalm caches unless told not to.
PHPCS_CACHE ?= --cache=.phpcs.cache
PSALM_CACHE ?=

# = Environment

## Build the PHP image (OZ_PHP_VERSION=8.4 make docker-build for another PHP version)
docker-build:
	$(DOCKER_COMPOSE) build php

## Install the Composer dependencies
install:
	$(RUN_NODEPS) composer install

## Check that the host's PHP satisfies what composer.json requires (RUNNER=host needs it; a failure names what is missing)
host-check:
	composer check-platform-reqs

## Open a shell in the PHP container, with the test servers running
shell:
	$(RUN) bash

## Stop the test servers and remove their volumes
down:
	$(DOCKER_COMPOSE) --profile clamav --profile runtimes down --volumes --remove-orphans

# = Tests

## Run every test suite that answers for the code (`test-provision` is its own run, see below)
test: test-unit test-services test-runtimes test-integration

## Run the unit test suite (FILTER=RouterTest or FILE=tests/Router/RouterTest.php runs one of them)
#
# No server needed, but `ImagesTest` needs the image's imagick and vips.
test-unit:
	$(RUN_NODEPS) $(PHPUNIT) --testsuite Unit --testdox $(NARROW)

## Run the Redis, MinIO and ClamAV tests against their servers; a missing server fails
test-services:
	$(DOCKER_COMPOSE) --profile clamav up -d --wait clamav
	$(RUN) env OZ_TEST_REDIS_REQUIRED=1 OZ_TEST_MINIO_REQUIRED=1 OZ_TEST_CLAMAV_REQUIRED=1 \
		$(PHPUNIT) --group redis --group minio --group clamav --testdox

## Serve OZone from real FrankenPHP, RoadRunner and Swoole servers and test it over HTTP; a missing server fails
test-runtimes:
	$(DOCKER_COMPOSE) --profile runtimes up -d --build --force-recreate --wait frankenphp roadrunner swoole
	$(RUN_NODEPS) env OZ_TEST_RUNTIMES_REQUIRED=1 $(PHPUNIT) --group frankenphp --group roadrunner --group swoole --testdox

## Provision a throwaway Debian container for real (runs on the host: it drives Docker itself)
#
# Not in `test`: it builds Debian and Alpine containers and drives them over SSH, so it answers for
# this machine's network and memory as much as for the code -- a package mirror that cannot be
# reached or a container the kernel kills (exit 137) fails it with nothing wrong in `oz/`. Being a
# prerequisite of `test`, it stopped the run before `test-integration`, which is the suite that had
# something to say. Run it on its own when the provisioning code changes, and before a release.
test-provision:
	OZ_TEST_PROVISION_REQUIRED=1 vendor/bin/phpunit -c phpunit.xml.dist --do-not-record-test-run-history \
		--group provision --testdox

## Run the integration test suite, on SQLite, MySQL and PostgreSQL (FILTER= and FILE= narrow it)
test-integration:
	$(RUN) $(PHPUNIT) --testsuite Integration --testdox $(NARROW)

## Write the unit suite coverage report in .ozone/coverage/
test-coverage:
	$(RUN_NODEPS) php -d pcov.enabled=1 $(PHPUNIT) --testsuite Unit --coverage-html .ozone/coverage

## What CI runs, in the PHP container: dependencies, code style, static analysis with no cache,
## then every suite that answers for the code (`test-provision` is run on its own)
ci:
	$(MAKE) RUNNER=docker install check-full test

# = Benchmarks

## Run benchmarks (in the container whatever RUNNER says: a number is only comparable against the same environment)
benchmark:
	$(RUN_NODEPS) php tests/run_benchmarks.php

## Measure HTTP throughput of OZone next to Laravel and Symfony (runs on the host: it drives Docker)
benchmark-http:
	sh docker/bench/run.sh

# = Code quality

## Code style and static analysis: the gate for every change
check: cs lint

## The same two with their caches ignored: what a handover and CI run
check-full: PHPCS_CACHE := --no-cache
check-full: PSALM_CACHE := --no-cache
check-full: cs lint

## Check code style
cs:
	$(RUN_FREE) vendor/bin/phpcs --parallel=$(PHPCS_JOBS) $(PHPCS_CACHE)

## Generate OZone's ORM classes in .ozone/build/plugins/, the one fixed path psalm reads
orm:
	$(RUN_FREE) php tests/orm_build.php

## Run static analysis (psalm), with freshly generated ORM classes
lint: orm
	$(RUN_FREE) vendor/bin/psalm $(PSALM_CACHE)

## Run code style fixer
fix: lint
	$(RUN_FREE) vendor/bin/oliup-cs fix

## Remove blate caches, and stop the test servers
clean:
	find . -name blate_cache -exec rm -rf {} + 2>/dev/null || true
	$(DOCKER_COMPOSE) --profile clamav --profile runtimes down --volumes --remove-orphans
