.PHONY: test test-unit test-integration benchmark cs lint fix clean

PHPUNIT = ./vendor/bin/phpunit
PHP     = php

# = Tests

## Run the unit test suite
test-unit:
	$(PHPUNIT) --testsuite Unit --testdox --do-not-cache-result

## Run the integration test suite
test-integration:
	$(PHPUNIT) --testsuite Integration --testdox --do-not-cache-result

## Run all test suites
test:
	$(PHPUNIT) --testdox --do-not-cache-result

# = Benchmarks

## Run benchmarks
benchmark:
	$(PHP) tests/run_benchmarks.php

# = Code quality

## Check code style
cs:
	vendor/bin/phpcs

## Run static analysis (psalm)
lint:
	vendor/bin/psalm --no-cache

## Run code style fixer
fix: lint
	vendor/bin/oliup-cs fix

## Remove blate caches and temp test artefacts
clean:
	find . -name blate_cache -exec rm -rf {} + 2>/dev/null || true
	rm -rf /tmp/_oz_tests_/
