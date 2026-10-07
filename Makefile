# Code style, static analysis, the fixer and the ORM generator run on the host by default (RUNNER=host):
# they need nothing but PHP and `vendor/`, which `make install` writes in the container so Composer
# resolves as CI does (the first-party packages land there as real directories, so the host reads them).
# `make host-check` says whether the host's PHP satisfies composer.json.
#
# Everything else runs in Docker (docker/compose.yaml) whatever RUNNER says, and so does `make ci`: that
# image is the reference environment. The unit suite is among them: `ImagesTest` drives every image
# driver OZone supports (gd, imagick, vips) and only the image has all three. RUNNER=docker puts the
# host-side targets back in the container too.

.PHONY: docker-build install shell host-check down test test-unit test-services test-runtimes test-provision test-integration test-coverage ci benchmark cs orm lint fix clean

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

PHPUNIT = vendor/bin/phpunit -c phpunit.xml.dist --do-not-cache-result

# = Environment

## Build the PHP image (OZ_PHP_VERSION=8.3 make docker-build for another PHP version)
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

## Run every test suite
test: test-unit test-services test-runtimes test-provision test-integration

## Run the unit test suite (no server needed, but `ImagesTest` needs the image's imagick and vips)
test-unit:
	$(RUN_NODEPS) $(PHPUNIT) --testsuite Unit --testdox

## Run the Redis, MinIO and ClamAV tests against their servers; a missing server fails
test-services:
	$(DOCKER_COMPOSE) --profile clamav up -d --wait clamav
	$(RUN) env OZ_TEST_REDIS_REQUIRED=1 OZ_TEST_MINIO_REQUIRED=1 OZ_TEST_CLAMAV_REQUIRED=1 \
		$(PHPUNIT) --group redis,minio,clamav --testdox

## Serve OZone from real FrankenPHP, RoadRunner and Swoole servers and test it over HTTP; a missing server fails
test-runtimes:
	$(DOCKER_COMPOSE) --profile runtimes up -d --build --force-recreate --wait frankenphp roadrunner swoole
	$(RUN_NODEPS) env OZ_TEST_RUNTIMES_REQUIRED=1 $(PHPUNIT) --group frankenphp,roadrunner,swoole --testdox

## Provision a throwaway Debian container for real (runs on the host: it drives Docker itself)
test-provision:
	OZ_TEST_PROVISION_REQUIRED=1 vendor/bin/phpunit -c phpunit.xml.dist --do-not-cache-result \
		--group provision --testdox

## Run the integration test suite, on SQLite, MySQL and PostgreSQL
test-integration:
	$(RUN) $(PHPUNIT) --testsuite Integration --testdox

## Write the unit suite coverage report in .ozone/coverage/
test-coverage:
	$(RUN_NODEPS) php -d pcov.enabled=1 $(PHPUNIT) --testsuite Unit --coverage-html .ozone/coverage

## What CI runs, in the PHP container: dependencies, code style, static analysis, then every suite
ci:
	$(MAKE) RUNNER=docker install cs lint test

# = Benchmarks

## Run benchmarks (in the container whatever RUNNER says: a number is only comparable against the same environment)
benchmark:
	$(RUN_NODEPS) php tests/run_benchmarks.php

## Measure HTTP throughput of OZone next to Laravel and Symfony (runs on the host: it drives Docker)
benchmark-http:
	sh docker/bench/run.sh

# = Code quality

## Check code style
cs:
	$(RUN_FREE) vendor/bin/phpcs

## Generate OZone's ORM classes in .ozone/plugins/, which psalm reads
orm:
	$(RUN_FREE) php tests/orm_build.php

## Run static analysis (psalm), with freshly generated ORM classes
lint: orm
	$(RUN_FREE) vendor/bin/psalm --no-cache

## Run code style fixer
fix: lint
	$(RUN_FREE) vendor/bin/oliup-cs fix

## Remove blate caches, and stop the test servers
clean:
	find . -name blate_cache -exec rm -rf {} + 2>/dev/null || true
	$(DOCKER_COMPOSE) --profile clamav --profile runtimes down --volumes --remove-orphans
