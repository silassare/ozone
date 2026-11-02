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

## How much of the preload list is worth preloading, 2026-11-01

`ScopeBuilder::declareFirstParty()` declares every class of OZone, `silassare/*` and `psr/*` so the
preload list holds what a request *may* load and not only what booting and building the routers did.
That is 750 files against 240, and it had never been measured against the narrower list it replaced
-- preloading was known to pay (the 2026-09-14 row below), the size of it was assumed.

Three `ozone-classic` containers on the same code, one pass measuring all three in turn, three passes
(10 s after a 5 s warm-up, 4 threads, 64 connections; classic mode only -- a worker bootstraps once
and preloading does little for it). Medians of the three passes:

| Preload list | files | `/ping` req/s | vs none | `/db` req/s | vs none |
| --- | ---: | ---: | ---: | ---: | ---: |
| OZone and its first-party packages (what is built) | 750 | 1,326 | 1.67x | 603 | 1.32x |
| what booting and the routers declared | 240 | 1,168 | 1.47x | 545 | 1.19x |
| nothing preloaded, same build | -- | 793 | -- | 457 | -- |

**The wide list is paid for**: the 510 extra files buy 13% on the text route and 11% on the database
route over the narrow one, and the order held on both routes in all three passes -- the full list beat
the narrow one by 265, 160 and 76 req/s on the text route and by 53, 65 and 37 on the database route.
Read the pairs within a pass, not the spread between passes: the text route's variants overlap by a
hair across passes (full 1,244-1,524, narrow 1,165-1,259) while the database route's do not at all,
and every pass ranked them the same way. The narrow list still gets about two thirds of the gain, so
it is what a server too small for 750 classes in `opcache.memory_consumption` should want -- there is
no option for it today, and `--no-preload` is the only way down.

Not reproducible from `make benchmark-http`: it needed a switch in `ScopeBuilder` and two bench
services, both temporary and reverted with this measurement. Redoing it means putting them back.

## What a session costs a request, 2026-11-01

Not part of `make benchmark-http`, which measures anonymous routes: measured on its own, because
"one session UPDATE per request" was written down as a cost worth removing and turns out not to
happen. `Session::save()` slides `expire_at`, `last_seen_at` and `updated_at`, all three whole
seconds, and Gobl writes the columns that changed and no others -- so a request landing in the same
second as the one before it finds the three already holding what it would write, and sends nothing.

300 rounds on this machine (Docker Desktop on Linux): the ORM path against the suite's SQLite
sandbox on a tmpfs, the statement against the real MySQL and PostgreSQL containers through PDO.

| | p50 |
| --- | ---: |
| the first request of a second: the save that writes, whole ORM path | 0.885 ms |
| any other request of that second: the save that writes nothing | 0.039 ms |
| the session row read by id, which happens anyway | 0.380 ms |
| the same three-column UPDATE, statement only, MySQL / PostgreSQL | 0.174 / 0.161 ms |

So a write costs about twice the read it accompanies, once per second per active session, and most
of it is the ORM and its transaction rather than the database. Throttling the slide would buy that
millisecond per session-second and cost an expiry that no longer slides when it says it does, which
is a security default; the second-granularity of the columns already throttles it.

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
