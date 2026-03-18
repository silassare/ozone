.PHONY: test test-unit test-integration benchmark cs fix clean

PHPUNIT = ./vendor/bin/phpunit
PSALM   = ./vendor/bin/psalm
CS_FIX  = ./vendor/bin/oliup-cs fix
PHP     = php

## Run the unit test suite
test-unit:
	$(PHPUNIT) --testsuite Unit --testdox --do-not-cache-result

## Run the integration test suite
test-integration:
	$(PHPUNIT) --testsuite Integration --testdox --do-not-cache-result

## Run all test suites
test: test-unit test-integration

## Run benchmarks
benchmark:
	$(PHP) tests/run_benchmarks.php

## Run static analysis (psalm)
cs:
	$(PSALM) --no-cache

## Run code style fixer
fix:
	$(PSALM) --no-cache
	$(CS_FIX)

## Remove blate caches and temp test artefacts
clean:
	find . -name blate_cache -exec rm -rf {} + 2>/dev/null || true
	rm -rf /tmp/_oz_tests_/
