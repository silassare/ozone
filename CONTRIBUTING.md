# Contributing to OZone

Contributions are welcome: [github.com/silassare/ozone](https://github.com/silassare/ozone/).

## Development environment

Everything runs in Docker: the host only needs Docker (with Compose v2) and `make`.
`docker/compose.yaml` defines PHP with the extensions OZone uses, Composer and the tools, and the
servers the tests use: MySQL, PostgreSQL, Redis and MinIO. The containers run as your user, so
the files they write stay yours.

```sh
make install           # Composer dependencies
make test              # every suite
make test-unit         # unit suite, no server needed
make test-services     # Redis, MinIO and ClamAV tests, against their servers
make test-runtimes     # OZone served by real FrankenPHP, RoadRunner and Swoole servers
make test-provision    # provisions throwaway Debian and Alpine containers for real
make test-integration  # CLI and HTTP tests, on SQLite, MySQL and PostgreSQL
make test-coverage     # unit suite coverage report in .ozone/coverage/
make benchmark-http    # HTTP throughput next to Laravel and Symfony (runs on the host)
make ci                # what CI runs: install, cs, lint, then every suite
make lint              # psalm
make cs                # code style check
make fix               # psalm, then the code style fixer
make shell             # a shell in the PHP container, servers running
make down              # stop the servers
```

`OZ_PHP_VERSION=8.1 make docker-build test` runs the suites on another PHP version.

Run the suites through `make`, never `vendor/bin/phpunit` directly: the targets pass
`-c phpunit.xml.dist`, and PHPUnit would otherwise prefer a local `phpunit.xml` and quietly change
which servers the suites see and which groups run. **Do not create a `phpunit.xml`** — it is
git-ignored, so it drifts out of date without anyone noticing; the unit bootstrap warns when one
exists. Server addresses belong in the environment: `docker/compose.yaml` exports every `OZ_TEST_*`,
`OZ_REDIS_*`, `OZ_MINIO_*` and `OZ_CLAMAV_*` variable into the PHP container.

Docker is not a requirement of the project, only the supported path. Without it, install PHP with
the extensions in `composer.json`, run the servers yourself and export the same variables.

Installing the CLI outside this repository: the root `install` script puts `oz` and `ozone` on
`PATH` and nothing else (`./install --help`). It checks PHP and the tools by default and installs
them only with `--with-php`.

## Tests

- The unit suite boots OZone in a sandbox project created for each run (`tests/autoload.php`):
  its own `.env`, data, cache and generated ORM classes, removed when the run ends. Tests never
  read or write the repository's `data/` or `.ozone/`.
- Each test class declares `@covers` for the classes it tests (the code style fixer adds
  `@coversNothing` to a class that declares none).
- Tests needing Redis, MinIO or ClamAV belong to the `redis` / `minio` / `clamav` groups, left out
  of the default run: `make test-services` runs them, and fails rather than skips when a server is
  missing. Never fake one of those servers in a test: run the real one.
- Worker mode is proven by serving OZone for real: `make test-runtimes` starts FrankenPHP (worker
  mode), RoadRunner and Swoole containers (`docker/runtimes/`), each serving
  `tests/Support/Servers/` through its bridge, and `tests/Runtime/Servers/` is their HTTP client
  (groups `frankenphp`, `roadrunner`, `swoole`). The containers are recreated on every run: a worker
  keeps the code it booted with.
- `oz server provision` is proven by provisioning a real container (group `provision`), never by
  asserting strings alone: `make test-provision` runs it through the host's Docker.
- `oz deploy run` is proven by deploying for real: `DeployRunTest` releases a local git repository
  into a deploy root, swaps `current`, deploys over it, rolls back, and checks that a failed health
  check rolls the release back.
- The integration suite runs on SQLite, MySQL and PostgreSQL. It refuses to start when MySQL or
  PostgreSQL is not configured, rather than quietly covering SQLite alone; `OZ_TEST_ALLOW_PARTIAL=1`
  runs it on the configured drivers only, knowing the others are then not covered at all.
- Integration tests create real projects with `OZTestProject`; see the instructions in
  `.github/copilot-instructions.md`. Every package those projects need is on Packagist, so a full
  run needs no GitHub token.
