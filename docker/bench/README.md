# HTTP throughput: OZone next to Laravel and Symfony

`make benchmark-http` (runs on the host: it drives Docker) serves three applications the same way and
measures them with [wrk](https://github.com/wg/wrk).

## Method

- **Same server for all**: FrankenPHP 1.x, PHP 8.4, OPcache on without timestamp checks
  (`php.ini`), errors logged, production mode (`APP_ENV=production` / `prod`, `ENV_MODE=production`),
  optimized Composer autoloader.
- **Built for production as each framework documents it**: OZone with what `oz project build` does
  (`docker/bench/ozone/build.php`: compiled `.env`, settings bundles, route tables, class map),
  Laravel with `php artisan optimize`, Symfony with `dump-env prod` and `cache:warmup`. In classic
  mode OPcache preloads OZone's preload script and Symfony's `config/preload.php`; Laravel ships
  none.
- **Two modes each**:
  - *classic*: the application bootstraps for every request, as under PHP-FPM (8 PHP threads);
  - *worker*: it bootstraps once per worker and serves requests in a loop -- OZone's
    `FrankenPhpBridge`, Laravel Octane, `symfony/runtime`'s own FrankenPHP support (8 workers).
- **Same routes**: one answering `pong` as text, one answering `{"hello":"world"}` as JSON, and one
  reading a row by its primary key through the framework's ORM (Gobl, Eloquent, Doctrine ORM) from a
  local SQLite file -- the same table, shaped like OZone's `oz_countries`, holding the same row, and
  answering three of its columns as JSON. Laravel's are in its stateless `api` group; OZone's are the
  API router's (`tests/Support/Servers/`), with the default authentication methods -- an anonymous
  request gets no session. Symfony's database route has a controller of its own, so its ping and JSON
  routes are resolved as before (Doctrine is installed and booted for every route, as in an
  application that uses it).
- **No interference**: one server runs at a time, pinned to CPUs 0-3; wrk runs on CPUs 4-7
  (4 threads, 64 connections, 20 s after a 5 s warm-up that is not counted).
- **Same files**: every application is served from the container's own filesystem (OZone from a copy
  of the repository made at start), never through a bind mount.

`BENCH_SERVERS`, `BENCH_DURATION`, `BENCH_CONNECTIONS` and `BENCH_THREADS` override the defaults of
`run.sh`.

## Reading the numbers

- Compare rows **within one run** only: on Docker Desktop, the same server measured twice has varied
  by about 20% between runs. `run.sh` measures every server in one go for that reason.
- These are framework-overhead numbers: no templates, and the database route reads one row of a local
  SQLite file, so it measures what the ORM costs rather than a database server. A real endpoint adds
  its own work on top, which usually dominates.
- Worker mode needs code written for it (no request state in statics -- see `EndRequestHook` in the
  instructions); classic mode is what a PHP-FPM deployment gets.

## Results, 2026-09-14

Docker Desktop on Linux (8 vCPUs, 3.9 GB), FrankenPHP 1.12.7, PHP 8.4.25, Laravel 12.69 (Octane
2.19), Symfony 7.4.18 (Doctrine ORM 3.7, DBAL 4.4), OZone at this commit with Gobl `c86a399` and
php-utils `a552f43`; each built for production, OZone and Symfony preloaded in classic mode. One run,
requests per second (p50 / p99 latency):

| Mode | OZone | Laravel | Symfony |
| --- | ---: | ---: | ---: |
| classic, text | 1,282 (48 / 87 ms) | 639 (95 / 168 ms) | 2,355 (26 / 51 ms) |
| classic, JSON | 1,196 (51 / 101 ms) | 612 (98 / 180 ms) | 2,311 (27 / 54 ms) |
| classic, database | 531 (114 / 222 ms) | 471 (129 / 222 ms) | 1,091 (55 / 114 ms) |
| worker, text | 4,292 (14 / 36 ms) | 1,489 (41 / 80 ms) | 5,217 (12 / 29 ms) |
| worker, JSON | 4,018 (15 / 38 ms) | 1,465 (42 / 79 ms) | 5,075 (12 / 30 ms) |
| worker, database | 2,573 (24 / 52 ms) | 1,180 (51 / 100 ms) | 3,505 (18 / 40 ms) |

No errors in any run.

- **Against the previous run** (2026-09-13, no framework preloaded, OZone not built: 882 / 542 /
  1,565 req/s on the classic text route, 400 / 401 / 773 on the database route, 3,658 / 1,240 / 4,488
  and 2,230 / 979 / 2,910 in worker mode), the whole table moved, Laravel's unchanged setup included
  (+18% on the classic text route): part of every difference is the machine. Within each run, Symfony
  leads OZone by 1.84x on the classic text route (1.77x before) and 2.05x on the database route
  (1.93x), 1.22x in worker mode (1.23x): preloading gave Symfony about what building and preloading
  gave OZone. OZone now leads Laravel on every row, the classic database route included (1.13x;
  level before).
- **The database route**: reading the row adds about 4.4 ms of CPU time to an OZone classic request
  (5.1 ms before), 2.2 ms to Laravel's and 2.0 ms to Symfony's; in worker mode 0.6 ms, against 0.4 ms
  for Symfony.
- **The build and preloading, measured alone** the same day: three `ozone-classic` containers on the
  same code, measured alternately -- nothing built, built, built and preloaded -- did 755, 740 and
  1,023 req/s on the text route and 393, 395 and 464 on the database route. Warm, a built project
  runs as fast as one whose first requests compiled the same caches; preloading is the gain.

### Earlier, 2026-09-13

- **The compiled route table**, measured on its own: two `ozone-classic` containers measured
  alternately, differing only by `ENV_MODE=production`, did 673 and 556 req/s on the text route --
  about 21% for the table.
- **The Gobl changes** (lazy schema, CRUD producers, metadata merges: `CHANGELOG.md`): before them, OZone did 236 req/s on the classic
  database route against Laravel's 334 (11.7 ms of CPU time for the row); after, 400 against 401
  (5.1 ms).
- Before the fixes recorded in `CHANGELOG.md` (lazy database, lazy sessions, settings fast path, no
  forced GC per request), OZone did 231 req/s on the classic text route and 1,862 in worker mode.
