## Unreleased

### Breaking changes

- **A table's create could never be granted as a right, and `create_all` was never checked**:
  `RESTFulService` registered six actions per table, without `create`, while `BaseHandler` checks
  `{morph type}.create` and `/access-rights/allow` refuses an action not registered; and
  `BaseHandler::allow('create_all')` was accepted but no CRUD event ever asked for it. The table
  actions are now one list read off gobl's `ActionType` (`CRUD\TableActions`): `create`,
  `create_bulk`, `read`, `read_all`, `update`, `update_all`, `delete`, `delete_all`, used by the
  registry, by `allow()` and by the checks. `create_all` is gone: a handler calling
  `allow('create_all')` is refused (it never applied).
- **Several rows of one table created through relations in one request are a bulk create**: an
  account posted with two transactions needs `{transactions}.create_bulk` once, beside each row's
  `create`, and a table opens it for whom it chooses. One row through a relation is a create as
  before. `TableCRUDListenerTrait::onBeforeCreateBulk()` refuses, as `onBeforeDeleteAll()` does, so a
  listener registered whole refuses bulk creates unless it overrides it; a table with no listener at
  all refuses them (gobl's default).
- **What says "users" while serving every auth user type is named for auth users**: `oz_users` is
  the default auth user table (type `user`), not the only one, and the names now say so. No alias is
  kept:

  | Before | After |
  | --- | --- |
  | namespace `OZONE\Core\Users` | folded into `OZONE\Core\Auth` (and `Columns\Countries`) |
  | `Users\UsersRepository`, `DEFAULT_USER_TYPE` | `Auth\AuthUsersRepository`, `DEFAULT_AUTH_USER_TYPE` |
  | `Users\Traits\UserEntityTrait` | `Auth\Traits\AuthUserEntityTrait` |
  | `Users\AnonymousUser` | `Auth\AnonymousAuthUser` |
  | `Users\UsernameUtils`, `Users\Services\SignUp` | `Auth\UsernameUtils`, `Auth\Services\SignUp` |
  | `Users\Cli\UsersCmd`, `oz users add/grant` | `Auth\Cli\AuthUsersCmd`, `oz auth-users add/grant` (options by short name: `--email`) |
  | `AuthenticatedUserRouteGuard`, `UserRoleRouteGuard`, `UserAccessRightsRouteGuard` | `AuthenticatedAuthUserRouteGuard`, `AuthUserRoleRouteGuard`, `AuthUserAccessRightsRouteGuard` |
  | `UserRateLimit` | `AuthUserRateLimit` |
  | settings group `oz.users`, keys `OZ_USER_*` | `oz.auth.users`, keys `OZ_AUTH_USER_*` |
  | API doc tag `Users`, operations `Users.signUp`, `Users.picture` | `AuthUsers`, `AuthUsers.signUp`, `AuthUsers.picture` |

  Kept as they were: the `AuthUser*` layer (`AuthUserInterface`, `AuthUsers`, its events and
  methods, `makeAuthUserTable()`), `hasAuthenticatedUser()`, `withAuthenticatedUser()`,
  `assertUser*()`, `logUserIn()`, the global `user()`, the lang keys, TNet's `_current_user`, and
  every table and column. A project's entity classes are generated again (`oz db build`) to take the
  renamed trait.
- **`AuthUserRateLimit` and `StatefulAuthRateLimit` count per route**, as `IPRateLimit` does: their key
  was the user's ref or the session ID alone, so every route limited one of these ways drew on one
  quota shared by all of them, and a request to one route spent the others'. A user or a session now
  has a quota on each route (`user:{route key}:{ref}`, `session:{route key}:{session id}`, built by
  `RateLimit::routeKey()`, which `IPRateLimit` uses too, its keys unchanged). A project that relied
  on one quota across routes declares it on a group, or gives a `RateLimit` its own key; counts
  kept under the old keys start over. Both take the `$kind` `IPRateLimit` takes.

- **A process OZone starts while serving a request no longer inherits that process's environment**
  beyond `Process::CHILD_ENV` (PATH, HOME, TMPDIR, LANG, LC_ALL, TZ): a `Cron::command()` that read
  another variable of the web server's when cron's requests mode ran it gets it only by naming it
  (`['env', 'NAME=value', ...]`, or `NAME=value command` for a shell command). From the command line
  nothing changes. See Fixed.
- **`Cli\Server\Interfaces\HostInterface` has two more methods**, `findExecutable(array $names)`
  and `isExecutableFile(string $path)`, which a host of a project's own has to implement:
  `oz deploy run` asks the server for its PHP through them. `hasBinary()` is answered through
  `findExecutable()` on both hosts OZone ships, so it means one thing on both: an executable file on
  the host's PATH. `LocalHost` took a directory of that name for one, and `SshHost` asked
  `command -v`, which also answers a shell builtin, a function or an alias.
- **`oz jobs supervisor` writes the PHP binary in full** (`Process::hostPhpBinary()`: this machine's,
  or `--php`) where it wrote `php`, which supervisord looked up on its own PATH: another version's
  PHP, or none.

- **`/robots.txt` is a route, answered in every scope by a provider, and no longer a file.** The
  `oz:robots` route (`Web\RobotsTxt`, `GET /robots.txt`, `text/plain; charset=utf-8`) answers what
  the class named in `OZ_ROBOTS_TXT_PROVIDER` (`oz.request`) returns: a
  `Web\Interfaces\RobotsTxtProviderInterface`, given the request's `Context` so it can answer per
  scope and per host, named by a project, a plugin or a scope's own `oz.request.php`. The default,
  `DefaultRobotsTxtProvider`, answers the rules the file held (`User-agent: *`, `Disallow: /`)
  without its comments. `oz scopes add` (and so `oz project create`) no longer writes
  `public/{scope}/robots.txt`, and every server configuration OZone generates or ships -- the
  scope `.htaccess` files, the nginx vhost of `oz deploy init --target=bare`, the Docker sample's
  nginx, the samples in `conf/` and `oz project serve`'s router -- sends `/robots.txt` to
  `index.php` even where a file of that name exists, so no leftover file shadows a provider.
  Nothing changes for an existing scope until its server configuration does: its own `.htaccess`
  and vhost still answer from the file it was given, which says what it always said.
  `oz doctor check` warns about such a file (saying whether its rules are the default's or its
  own) and about an `.htaccess` that still exempts the path, and removes neither: without the file,
  a server still exempting `/robots.txt` answers 404, which a crawler reads as everything allowed.
  The route is held by a maintenance like any other, so a crawler is answered 503 with the
  maintenance's `Retry-After` and waits. A project that mapped `/robots.txt` itself names a provider
  instead of keeping a second route on the path.

- **The auto name of a route not named explicitly comes from its definition alone**: its methods,
  its host and its path, derived when the name is read, so a `host()` chained after the route is
  mapped is part of it. A route that declares no host keeps the name it had; a route that declares
  one gets a new name; and the `_2`, `_3` suffix that kept two identical definitions apart, in the
  order they were mapped, is gone: two routes not named explicitly with the same methods, host and
  path are refused, with an error naming the definition and the providers that mapped both, since a
  request could only ever reach the first. State keyed by `Route::key()` (form sessions, route rate
  limits) of a route whose name changed starts over.

- **A route provider says what version its routes are** (`RouteProviderInterface::routesVersion():
  ?string`), and the production route table's key includes it. Null is routes written in code, and
  `Service` answers it, so a provider extending `Service` changes nothing; a class implementing the
  interface directly must add it. A provider whose routes come from data that changes without a
  deploy or a setting -- files edited on the server, rows in a database -- answers a version of that
  data, and a new one compiles a new table on the next request, where before the old table kept
  serving the old routes until the next release. Asked on every request served from a table, so it
  answers from something cheap to read.

- **A plugin boots after the plugins it requires, and one whose required plugin is disabled is
  refused.** What a plugin requires is what its package requires through Composer, among the
  packages of the plugins `oz.plugins` lists (`PluginInterface::getRequiredPackages()`, read by
  `AbstractPlugin` from the `composer.json` it already reads; a class implementing the interface
  directly must add it). Composer enforces the versions at install; what it cannot know is what is
  enabled and in what order to boot, so `Plugins::boot()` boots in `Plugins::bootOrder()`: each
  plugin after its requirements, the list's own order otherwise, and a refusal naming the plugin and
  what it requires when that is disabled, or naming the cycle when plugins require each other. A
  disabled entry whose class is not installed is skipped, as before.
- **A plugin names what it ships, and its boot adds it.** `PluginInterface::getSettingsSource()` is
  the directory of the settings it ships and `getSchemaFile()` the file declaring its tables, which
  returns `function (NamespaceBuilder $ns): void` like OZone's own `oz_schema.php`; both default to
  null in `AbstractPlugin`, whose boot adds the first as a settings source and runs the second for
  the plugin's namespace whenever a database collects its schema. A plugin that called
  `Settings::addSource()` from its `boot()` keeps working, and can name the directory instead.
  `CorePlugin` names OZone's own (`CorePlugin::settingsSource()`, `schemaFile()`), which `Settings`
  and `Db` now read, and adds neither again.
- **The QR code service encodes and no longer decodes.**
  `Services\QRCode\Interfaces\QRCodeEncoderDecoderInterface` is now `QRCodeEncoderInterface`
  without its `decode()` method, and `BuiltinQRCodeEncoderDecoder` is `BuiltinQRCodeEncoder`. The
  `OZ_QR_CODE_ENCODER` setting is unchanged in name and still the way a project plugs its own
  encoder, so a project that kept the default needs no change; one that named the class explicitly,
  implemented the interface, or called `decode()` does.

  Reading a QR code that OZone did not draw means reading a photograph: finding the finder patterns
  in it, correcting the perspective, sampling the grid and repairing the damage through the
  error-correction codewords. The builtin decoder did none of those -- it located the grid by
  scanning for the first dark pixel at a fixed threshold and performed no error correction at all --
  so it could only read a pristine, square, evenly lit image that this encoder had produced. Nothing
  in OZone called it, and where a camera is involved the client that holds it decodes and sends the
  text. The 357 lines went, with the interface documenting what to reach for instead.

- **A login attempt is counted where it is let through, not after it has failed.** `LoginThrottle`
  was asked `isLocked()`, the password was verified, and a failure was recorded afterwards: three
  steps, each atomic, the sequence not. Attempts that arrived together all read the same count
  before any of them had been recorded, so a burst of a hundred got a hundred tries whatever
  `OZ_AUTH_LOGIN_MAX_FAILURES` said -- concurrency alone multiplied what the throttle allowed, which
  is the one thing a throttle exists to prevent. `LoginThrottle::claim()` counts the attempt and
  answers whether it may go ahead in one step, and it is what `AuthUsers::checkPassword()` and
  `DigestAuth` now call. `recordFailure()` stays for a flow that learns of a failure elsewhere than
  where it let the attempt through, with the gap written down in its docblock.

  Three consequences for a project. The counter counts attempts rather than failures, so a reading
  of `failures()` taken mid-attempt includes the one in progress; a right password still clears it,
  so what accumulates between two successes is unchanged. An attempt refused by the limit is counted
  too -- counting is how it was refused -- and cannot extend a lockout, because the window runs from
  the first failure and counting keeps its expiry. And a right password on an account that cannot be
  used yet now clears the count before `OZ_AUTH_USER_UNVERIFIED` is returned: nothing was being
  guessed, and holding it against the account would lock out whoever still has to verify it with the
  one password that works. `LoginThrottle::release()` gives a claim back for a step that turns out to
  be part of the protocol rather than a guess, which is what a digest nonce of ours that aged out
  does before it answers with a fresh challenge.

- **`ShellRunnerInterface` has a new method, `withTimeout()`, and a step says how long it is worth
  waiting for.** A host carried one bound for every command it ran -- 900 seconds, the size of a
  cold `composer install` -- and a deploy ran twelve steps under it: a symbolic link swap, an ORM
  build, a migration and a package install alike. A bound sized for the slowest work means nothing
  for the rest, so a step that hangs is waited out for fifteen minutes and a step that needs longer
  fails anyway. `ProvisionStep` takes a `$timeout` (null for the host's own, 0 for none) and
  `ProvisionPlan::run()` runs the step on `$host->withTimeout(...)`, leaving the host as it is for
  the next one. A project with its own `HostInterface` implements `withTimeout()`, returning the
  same host with that bound and changing nothing else.

  `run()` gains an outcome with it: a command killed for taking longer than it was given answers
  `ShellRunnerInterface::TIMED_OUT` (124, as `timeout(1)` reports one) instead of raising
  `ProcessTimedOutException` out of the host. That exception reached nothing that could say what had
  been cut; a plan now reports the step, its bound and what the command had printed, like any other
  failure. An implementation that lets a timeout raise instead will have it cross a plan again.

  The deploy's own steps say what they are worth: `dependencies` 1800 seconds (a cold install on a
  small server with a far mirror is slower than 900), `restart` 120 (a unit that has not restarted
  in two minutes is wedged, and waiting tells no one anything), and `migrations` **none at all** --
  it is the step a rollback cannot undo, it backs the database up first, and both take as long as
  the data does. Cutting that one off leaves a schema half migrated, which is what the build's old
  300-second cap did under contention. An SSH session now ends on a dead peer of its own accord
  (`ConnectTimeout`, `ServerAliveInterval`, `ServerAliveCountMax`), which is what bounds a step that
  has no bound.

- **A date column is documented as the string of digits it is.** It is stored as a big integer, or
  a decimal when it keeps microseconds, and Gobl answers both as strings so no digit is lost through
  JSON -- the same reason an id is a string. The OpenAPI schema said `type: string` and then gave it
  `format: int64` (`float` with microseconds), so a generator emitted a number for a field that
  arrives quoted: `Date.parse("1763452800")` is `NaN`, `new Date(1763452800)` is January 1970, and
  neither raises. The numeric format is gone and the unit is in the description, which a bare count
  of seconds does not carry. A client reads a date with `Number(x)`.

- **A date column answers a Unix timestamp, not an ISO 8601 string.** A date column is stored as a
  timestamp and was read back formatted; Gobl now reads it back as what it stores, and a format is
  asked for where the column is declared. OZone's schema needed nothing but the removal of the three
  `->format('timestamp')` calls that were working around it. A client reading `created_at`,
  `expire_at`, `last_seen_at` or any other date off a row gets a number. No DDL changes and no
  migration; the OpenAPI schema follows the column, so it says so. The bug this closes is under
  **Fixed**.

- **`JobStoreInterface` has a new method, `claimable()`.** A queue is run from it rather than from
  `iterator()`, which stays as it is for reading a queue. A project with its own job store
  implements it; see **Fixed** for why running a queue is not iterating it.

- **Per-entry state in the session store is written through a branch handle, not a built-up dot
  path.** The uploads a session has in flight, the links, QR codes and captchas it has been handed
  are kept one entry per key, and each was written as `'<branch>.' . $key`. They go through
  `requireAuthStore()->entries(self::STORE_BRANCH)` now (`set()`, `get()`, `remove()`, `has()`,
  `all()`, `names()`), which writes and removes one entry at a time and treats the key as a
  **member's name** whatever it looks like -- a key of all digits down a dot path is ambiguous,
  since PHP reads `$a['12']` and `$a[12]` as one slot and JSON does not. `UploadFiles::getKey()` is
  gone, replaced by the handle; the branch of each is a public constant (`STORE_BRANCH`). A project
  reaching those branches by their literal path should move to the handle.

- **The session store keeps the writes made to it, instead of their result being compared
  afterwards.** What a request changed was worked out at the end by walking the document it started
  with against the one it ended with, and that walk had to decide what each writer had meant: whether
  a key that was gone had been one entry of a map or the branch itself, whether an array of numbers
  was a list or a map keyed by id, whether a branch this request was first to fill was a branch at
  all. Three of those readings came out wrong and cost a bug each; a request whose patch could not be
  expressed cost a fourth. `StatefulAuthenticationMethodStore` extends the new
  `Stores\RecordingStore`, so every `set()`, `remove()`, `merge()` and array write is kept as the
  path it changed and nothing is read off the values. A write that goes around the store -- the data
  reached and changed in place -- is noticed rather than left out of the UPDATE: the store says its
  paths no longer account for its document, the change is worked out the old way, and the session id
  and the keys are logged.

  `oz_sessions.data` is declared `patchOnly()`, so an UPDATE that cannot send it as paths raises
  instead of writing the document and undoing whatever another request wrote. A fallback on any
  other column is logged through `Gobl\DBAL\Events\JsonPatchNotExpressible`, which nothing used to
  report.

- **Access rights and two-factor state are written as the keys that changed.**
  `UserEntityTrait::setAuthUserDataStore()` wrote the whole store under one key, so a right granted
  by one request and two-factor enabled by another came down to whichever answered last.
  `AuthUserDataStore` records its writes like the session store, and the trait sends them as paths
  under the store's own key.

- **PHPUnit 13 is what the test kit asks for**, and a project using it must move with it: `OZONE\Core\Testing\IntegrationTestCase` and the testing traits are exercised against 13.4, and a project's own tests need the same major. PHPUnit 13 reads **no** doc-comment metadata and reports none it finds, so a project's `@dataProvider` does not warn -- the test runs with no arguments -- and a `@group` makes a `--group` selection match nothing. The attributes in `PHPUnit\Framework\Attributes` are the only form that works, and `oliup/oliup-cs-php` now carries `php_unit_attributes`, so `make fix` converts them. Two CLI changes bite the same way: one group per `--group` flag (a comma-separated list is read as a single group name), and `--do-not-cache-result` is `--do-not-record-test-run-history`.

- **A redirection may only send a client to a host this server allows.** `Navigator::redirect()`, which every redirection goes through (`Context::redirect()`, `Navigator::redirectRoute()`), replaced the destination only when it named no host: an absolute URL was emitted as a `Location` whatever host it named, so any route handing `redirect()` something it took from the request was an open redirect. It now refuses a destination whose host is neither the request's own nor one `OZ_REDIRECT_ALLOWED_HOSTS` names, with `OZ_REDIRECT_HOST_NOT_ALLOWED`. That setting already existed and was read in one place only, the `next` field of `/logout`, so every other redirection was unchecked; it now decides them all, and takes `'*'` for any host, spelled as `OZ_CORS_ALLOWED_ORIGIN`'s own wildcard. Its default is unchanged (`[]`, this host only), so **a project redirecting off-site must list the hosts it sends clients to**. The decision is one class, `Http\RedirectPolicy` (`fromContext()`, `allows()`, `hosts()`, `allowsAnyHost()`), which the `/logout` form reads too, so the field and the redirection cannot disagree -- and an entry may be written as a host or as a URL, since the two settings next to it hold URLs. With it, `redirect()` validates the destination through `Uri::isStrictUrl()` instead of `FILTER_VALIDATE_URL`, which accepts `javascript://comment%0Aalert(1)`.

- **A URL is read by the engine's own `uri` extension, and one that is not a URL is refused.** `Uri::createFromString()` took the authority apart with `parse_url()`, which answers a different host than a browser does for an authority holding a backslash or a tab: `http://evil.com\\@good.com/` is a request to **evil.com**, and it reported `good.com`. Every decision made on that host -- the CORS allow-list (`CorsPolicy::normalize()`), the requested host (`ClientInfo::host()`), a redirect target -- was made about a host the client was never going to reach. An absolute URL is now read as a browser reads it (`Uri\WhatWg\Url`), so the host is the one a client would connect to, an internationalised host arrives in the ASCII form that goes on the wire (`http://例え.jp` is `xn--r8jz45g.jp`), a default port is dropped and `..` is resolved, which makes two spellings of one origin compare equal. A reference carrying no scheme (`/next`, `?q=1`, `#f`, `//host/p`) has no browser reading without a base and is read as RFC 3986 defines a relative reference.

  **A malformed URL now throws `InvalidArgumentException` where it used to become `/`.** `parse_url()` answers `false` for `https://example.com:99999/p` or `https://`, every component then defaulted, and the result was a request for the site root that said nothing about it. What else changes: a path keeps a per cent sign that is not an escape instead of encoding it a second time (`/%zz` came back `/%25zz`, a different path from the one asked for), and the scheme list is one exported constant (`Uri::SCHEMES`) rather than a literal inside the scheme filter. `ClientInfo::host()` falls back to the default origin's host when the `Host` or `X-Forwarded-Host` it was given is not a URL, since that header is the client's to write and a bad one is an input to refuse rather than a request to fail.

- **PHP 8.5 is the minimum** (`"php": ">=8.5"`, `config.platform.php` 8.5.12), and every first-party package of the suite moves with it, since they all answer for OZone's floor. Nothing was blocking it: `composer why-not php 8.5` named only `roave/security-advisories`, which asks for 8.5.12 within the branch, the same patch-pin shape the 8.3 floor already carried. It removes no dependency -- the polyfills in the tree belong to the development tools, which support older PHP and ship them whatever our floor says, and the one in the runtime tree (`symfony/polyfill-ctype`) comes from `symfony/yaml`, whose own floor is lower; the platform move does let Symfony 8 in, which **adds** `symfony/polyfill-php85` to the development tree. What it buys is what can be written: typed property hooks and asymmetric visibility for generated code, and the engine's own `uri` extension. The PHPCompatibility target (`testVersion`) and the CI matrix say 8.5 too, the matrix now being one version since 8.6 is not out.

- **`oz server provision` names the PHP series in every apt package, and adds a PHP repository when the distribution has none.** It asked for `php-cli`, `php-fpm` and `php-<ext>`, the unversioned meta-packages, which follow the distribution's **default** series: on Debian 13 that is 8.4, below the minimum, so the provision stopped at the `php:version` guard. Worse, adding a PHP repository does not change it -- that repository's own meta-package tracks the same default (with `deb.sury.org` on Debian 13, `php-cli` is still 8.4 while `php8.5-cli` is 8.5). So the plan now asks for `php8.5-cli`, `php8.5-fpm` and `php8.5-<ext>` (`PackageManager::aptPrefix()`, the dotted sibling of the Alpine prefix), and a new `php:repository` step before `php` adds `deb.sury.org` **only when the distribution's index does not already carry them**. The keyring is fetched to a file of its own, checked against a digest the code pins rather than trusted for arriving over TLS, and the source trusts that one file through `Signed-By`, so it signs nothing else; no `apt-key`, no `trusted=yes`. The service to enable is not always the package either (`PackageManager::phpFpmService()`): Debian names both after the series, Alpine installs `php85-fpm` and starts `php-fpm85`, which the hardcoded `php-fpm` got wrong there already. The failure message no longer names distributions, since the three it named have all fallen below the floor.

- **A project redeclaring a framework constant must declare its type too, and its generated classes must be rebuilt** (`oz db build --class-only`). Every class constant whose value settles its type is now typed, in OZone and in Gobl, and PHP 8.3 refuses a subclass that redeclares a typed constant without the type. What that reaches in a project: a query class generated before this (`protected const FILTER_METHODS = [...]` against Gobl's typed `ORMTableQuery::FILTER_METHODS`), and a REST service generated before this or written by hand (`public const SERVICE_NAME = '...'` against `RESTFulService`'s typed one, likewise `SERVICE_PATH`, `TABLE_NAME`, `KEY_COLUMN`) -- the service template now writes `public const string`. A generated entity's own `TABLE_NAME` / `TABLE_NAMESPACE` / `COL_*` have no parent to answer to and keep working untyped until they are regenerated.

- **A generated column is a typed property, and a project's generated classes must be rebuilt** (`oz db build --class-only`). The ORM no longer describes a column with a `@property` docblock line and a magic accessor: it declares the column as a real typed property with `get` and `set` hooks, so the engine enforces the column's type at the assignment and one magic dispatch per column read goes away. OZone's own 12 entities carry 155 such properties now. Four things change for code that touches an entity's columns directly. Assigning a value of the wrong type raises `TypeError` where the column's own validation used to answer later, and assigning `null` to a column that is not nullable raises it too. `isset($entity->column)` answers whether that column holds a value, where it used to answer whether the table *has* such a column -- so it is now false for a nullable column that is null, and it throws on a partially loaded entity, exactly as reading the column does. `unset($entity->column)` raises `Error: Cannot unset hooked property`, where it used to do nothing at all. And `get_object_vars()` on an entity now lists its columns. The accessors (`getX()` / `setX()`), the relation methods and the fluent shape are untouched, and a class generated before this keeps working until it is rebuilt. Nothing in `oz/` or `tests/` needed a change: neither holds an `isset()`, an `unset()` or a `??` on an entity column.
- **A job a dead worker left behind is settled against its retry policy, not handed straight back.** `JobStoreInterface` gains `listStale()` -- the jobs of a queue that are running, locked and past their lock lease, which each store answers its own way: `DbJobStore` by when the job was last written, `RedisJobStore` by its lock key having expired, which it never acted on before. `JobsManager::reclaimStale()` settles them through `finish()`: the abandoned attempt is counted, the job waits out `job_retry_delay` while it has tries left, and it is dead-lettered once it does not. `DbJobStore::iterator()` no longer resets them itself, so reading a queue is a read again. `JobContractInterface` gains `touch()`, which a `work()` that can outlast its store's lease calls as it goes. **A project with its own job store must implement `listStale()`, and its own job contract `touch()`.**
- **The test kit gives each test class its own database.** `DbTestConfig::allConfigured()` now creates one per class and per process for MySQL and PostgreSQL (`DbTestConfig::own()`), dropping it when the process ends, as it already did with a file per class for SQLite; a generated project's foreign keys are named after its tables without its table prefix (`fk_oz_users_oz_usernames`), which MySQL requires unique per database, so classes sharing one database could not each install their schema. `OZTestProject::cleanDb()` drops only the tables of its own project (`OZ_DB_TABLE_PREFIX`, `OZTestProject::tablePrefix()`) instead of every table it can see, which took the tables of the classes running beside it. MySQL's test user may not create databases, so `docker/compose.yaml` passes the server's root credentials to the PHP container as `OZ_TEST_MYSQL_ROOT_USER` / `OZ_TEST_MYSQL_ROOT_PASSWORD`.
- **An email or phone ownership verification says what it is for, and only serves that.** Whoever started a verification received its reference, and the owner who opened the link granted it: a verification started for someone else's address, confirmed from an email that only said "verify your email address", let its starter recover that account. `/auth/verify/email` and `/auth/verify/phone` now take a `purpose` -- `check` (a plain proof of ownership, the default), `sign-up` or `account-recovery` -- and an `auth_user_type` for the account an `account-recovery` is for. The email, the SMS and the link page say which it is (a lang key per purpose, `VerificationPurpose::text()`; the old `OZ_AUTH_VERIFY_EMAIL_*` and `OZ_AUTH_VERIFY_PHONE_SMS` keys are gone, replaced by `_CHECK`, `_SIGN_UP` and `_ACCOUNT_RECOVERY` ones, plus `OZ_AUTH_VERIFY_LINK_*`), and `/signup` and `/account-recovery` refuse a verification started for anything else (`OZ_AUTH_WRONG_PURPOSE`), or for another user type. **An account is recovered where the link is opened**: the link of an `account-recovery` verification opens a form (`oz.auth.link.recovery.blate`) that sets the new password on that page and deletes the authorization, so the starter is granted nothing and only the owner of the mailbox recovers the account; the starter recovers with the code, typed where they asked. The link page says what confirming grants, instead of "continue on this device". `EmailOwnershipVerificationProvider` and `PhoneOwnershipVerificationProvider` share a new `OwnershipVerificationProvider`: their constructor takes the purpose and the user type, `getIdentifier()` and `identifierType()` are new, and a project building one directly passes them. `AuthorizationProvider::redeem()` checks a secret and deletes the authorization instead of granting it.
- **The default pages and messages are redrawn, translated and themable.** Every page OZone renders (error, redirect, welcome, authorization link confirmation and result, access grant form and prompt) extends one layout, `oz.page.layout.blate`, and is styled by `oz.css`, rewritten with BEM class names (`oz-card__title`, `oz-btn--block`, ...) on CSS variables (`--oz-color-primary`, `--oz-radius`, `--oz-logo`, ...) with dark-mode defaults, logical properties, visible focus and zoom allowed, and no Google Fonts request. Every page then loads `oz.theme.css`, empty in OZone: a project overrides the variables in its own `app/templates/oz.theme.css`. A project template using the old classes (`oz-title`, `oz-desc-item`, `oz-box`, ...) must be updated; `/logo.png` is no longer shown (set `--oz-logo`). Every text is a lang key in `oz.en` and `oz.fr`: the error page is titled by its status (`OZ_VIEW_ERROR_TITLE_<status>`, `oz_error_title` is now that key instead of the English reason phrase), shows the URL and the message code only outside production (`oz_error_details`), and an internal error, or a message that is not a lang key, shows `OZ_ERROR_INTERNAL`. An authorization link that is unknown or no longer valid shows `oz.auth.link.view.blate` (`oz_auth_link_done` false) with the error's status, instead of the error page. The emails extend `oz.mail.layout.html.blate` (inline styles, as mail clients ignore CSS variables), themed by `OZ_MESSAGING_MAIL_THEME` (`color`, `logo_url`, `name`); every message template gets `oz_locale`, `oz_dir`, `oz_name`, `oz_initial`, `oz_mail`, `oz_otp_domain` and `oz_vars` (what `$t()` fills a text with: a template's own data is an object there), and is written in the message's language. `OZ_MESSAGING_OTP_DOMAIN` ends an SMS carrying a code with the `@domain #code` line browsers and phones read. Blate globals `dir`, `project_name` and `project_initial`, and `Polyglot::direction()`, are added. `AuthorizationCredentials::toArray()` gives `auth_link` as a string. Fixed with it: the access grant form rendered every field as a hidden input (a field object hands a template its `hidden()` setter, always true); it now gets plain values, and its fields are labelled.
- **`OZONE\Core\Senders` is `OZONE\Core\Messaging`, and messages are really sent.** Nothing listened to the `SendMail`, `SendSMS` and `SendNotification` events `send()` dispatched, and `oz.senders` was read by no code, so every message -- OZone's own email and phone verifications and two-factor codes included -- was dropped silently unless a project wired a listener. The events are gone; `Messaging\Messenger` records each message once per recipient in the new `oz_messages` table (a migration), then skips it (no destination, channel disabled, transport unavailable: recorded with the reason and logged), delivers it now (`Message::sync()`, or a channel with `async` off: a failure is recorded, then thrown), or queues it on `OZ_MESSAGING_QUEUE` (`oz:messages`) as a job whose payload is encrypted, run once the response is sent and by the every-minute `oz:messages` cron task, which runs the retries (`retry_max`, `retry_delay`). Channels and their transports are declared in `oz.messaging` (`OZ_MESSAGING_CHANNELS`, replacing `oz.senders`): `mail`, `sms`, `notification` and any a project adds, each naming a `Transports\TransportInterface`; `LogTransport`, the default, writes messages to the log and is unavailable in production, where `oz doctor check` fails while an enabled channel uses it (`Messenger::channelsNotSending()`). A message's template is now a name without extension, looked up per locale and channel (`{template}.{lang}.{channel}.blate` ... `{template}.blate`, and `.html.blate` for a channel showing HTML); `Message` is concrete (`new Message('whatsapp', 'app.reminder')`), takes a subject (a lang key), a locale, `once($key)` (sent once per template, channel and key, enforced by a unique key) and `sync()`, and `send()` returns the records. The constructor's `$template_rich` and `$attributes`, `getContent()`, `getRichContent()` and `getAttribute(s)()` are gone. OZone's templates are renamed accordingly (`oz.auth.messages.2fa.blate`, `.2fa.html.blate`, `.2fa.sms.blate`, `oz.auth.messages.verify.email.html.blate`), and the text part of the email verification is plain text. `Messaging\Gsm7` folds a text to the GSM 03.38 alphabet (a channel's `gsm7` option) and counts its SMS segments. The hourly garbage collection (`oz:gc`, collector `oz:messages`) deletes the settled records older than `OZ_MESSAGING_RETENTION` days (90; null keeps them), and those of messages sent once after `OZ_MESSAGING_ONCE_RETENTION` days (null by default: kept, since deleting one lets the message be sent again); queued records are never deleted.

- **Security:** `Hasher::hash32()` and `hash64()` hash what they are given, always. They answered a **random** hash when the string was `null` or empty -- so a caller hashing a value that may be absent got a different hash on every call, and two hashes of one input never matched -- and their parameter is now a required `string`, which is where that mistake is caught. The three callers that wanted a random value and relied on the empty call (`QRCode`, `LinkTo` and `CaptchaCode`, for the key of a store entry they put in a URL) take `Keys::id32()` instead: 128 random bits from `random_bytes()`, where the old path was `md5(sha256(Random::string(64) . microtime()))`. No reachable bug came of it, since every other caller hashes a value it has already refused to be empty (`BearerAuth` and `ApiKeyHeaderAuth` check their header in `satisfied()`), but a hash function that sometimes does not hash is not one a caller can reason about.
- **`SessionHijackingDetected` carries values, not the session.** Its `$session` property is gone, replaced by `$auth_user` (the user the session is attached to), `$session_id` and the two source keys, `$opened_source_key` and `$request_source_key`: a listener could not report what changed anyway, since `Session::sourceKey()` answers the request's key and the stored one was out of reach (`Session::openedSourceKey()`, new, is the baseline). `Session` is `@internal`, so a listener in a project's namespace was reaching into framework internals to find out who the session belonged to; and the session ID it handed out is the credential the request is being refused the use of, for a session that is now left alive for its owner -- the framework's own listener logged it. That warning line now records the user, both keys and the IP instead, and the event says plainly that the ID is a secret. `Session::peekID()` (new) answers the ID without `id()`'s side effect of keeping the session.
- **Security:** the same source session protection runs. `SessionAuth` compared the session's source key with the value that same `Session` had just been constructed with, so the branch holding the check was unreachable and the source of a request was never compared with anything: `Session::start()` ignored the stored `oz_sessions`.`request_source_key`, which was written and read nowhere, and `OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE` and `OZ_SESSION_SOURCE_KEY` had no effect at all (`SessionHijackingDetected` could never be dispatched). `Session::start()` now compares the loaded row's stored key with the current request's and says so (`Session::sourceChanged()`; `sourceKey()` still answers the request's own key), and `SessionAuth` acts on it once per request, as it meant to: a session carrying a user is refused with `OZ_SESSION_HIJACKING_DETECTED` (403) after dispatching the event, one carrying none is restarted. **A signed in user whose source changes is now logged out**, which is the point of the setting and never happened before. `OZ_SESSION_SOURCE_KEY` defaults to `user_agent` (was `user_ip`), the source that survives a network change: with `user_ip`, a mobile network or a VPN logs the user out, as the setting's own comment warns. A source key is also prefixed now (`User-Agent-Hash-...`, `User-IP-...`), so it says which setting produced it and is never shorter than the column's `min(6)` (an IPv6 loopback source key was `::1`), and a request with no `REMOTE_ADDR` no longer reaches `Session`'s `string` parameter with the `null` of `Context::getUserIP()`. A client that sends no `User-Agent` has a source key like any other, now that `Hasher::hash64()` hashes the empty string instead of answering a random hash (below); it used to be given a new key on every request, and would have been refused the session it had just opened. **Every session open at the upgrade mismatches** -- their stored keys hold the old, unprefixed form -- so signed in users are logged out once; `oz_sessions` can be emptied instead.
- **Security:** a table's CRUD rules hold whichever generated ORM class a request loads first. The database initializes when a request first loads a generated class, while that class is still loading, and the CRUD listeners attached right then (`DbReadyHook`, once per process): a listener using the generated classes met the class still loading. `BaseHandler` reached its table through the entity, and `class_exists()` is false for a class still loading: when the entity came first (`new OZFile()`), the table's rules were skipped for the worker's life and Gobl's CRUD allowed what they refuse; when another class came first (`new OZFilesQuery()`), the request stopped with a compile error ("Could not check compatibility between ...Base::qb() ... is not available"), no handler able to catch it. `DbReadyHook` now waits for the class loading to be complete (`ClassLoader::afterLoad()`), so a listener may use the generated classes and their constants as before. `TableCRUD::events('<table>')` gives a table's CRUD events (a Gobl `CRUDEventProducer`) without loading a generated class; `BaseHandler` listens through it with its table: `BaseHandler::crud()` returns it, never null, and `withCrud()`'s factory takes it, where they gave the generated CRUD class. Needs Gobl's generated classes rebuilt (`oz db build --class-only`): their constructors no longer load the entity.
- Two REST services may serve the same table. The access rights actions a `RESTFulService` registers are named after its table (`<table>.read`, ...), and `AtomicActionsRegistry::register()` threw on any name already registered, so a second service over the same table stopped the boot. An action registered again with the same definition is now kept as it is; another definition under a registered name still throws.
- The rate limiter (`RouteRateLimiter`) and the login throttle (`LoginThrottle::recordFailure()`) count through `KeyValueStore::count()`, in one atomic step: a burst of requests read the same count and passed together (12 at once against a limit of 5 let up to 11 through). A route's window is fixed from its first hit, and a refused hit counts too; the status's `remaining` is never below 0.
- `DbStore::set()` inserts or updates in one statement (an upsert on the group and key). It read, then inserted: two requests writing a new key at once (the first hits of a rate limit) met on its unique key, and all but one answered 500.
- A user's `data` and `is_valid`, and every soft-deletable table's `deleted` and `deleted_at` (Gobl's `softDeletable()`), are private columns: the server's own. No form asks for them (`Form::fromTable()` leaves private columns out, so the sign-up form no longer let a client set `user_is_valid` or `user_data`), the CRUD layer refuses a request writing one, a request may not filter, sort or page on one, and no answer carries one: `UserEntityTrait::toArray()` no longer adds back the public part of `user_data`. Server code reads, filters on and saves them as before (an entity's own `save()` may write them). `Utils::buildTableCliOptions()` offers a private column as an option, the command line being an administrator on the server; it used to leave them out (`oz users add` still leaves out `data` and `is_valid` by name).
- `Form::fromTable()` requires a field only when its column has neither a null nor a default: a column the database fills is never asked for.
- Two sign-ups with one email (or phone) at once answer the field's error to all but one (`OZ_FIELD_EMAIL_ALREADY_REGISTERED`, the column's own "not registered" rule run again), never a 500. Both passed the rule, a read before the insert; the second met the unique key, which Gobl now raises as `DBALUniqueViolationException`. On SQLite they also failed with "database is locked", now that Gobl's SQLite transactions begin `IMMEDIATE`. The sign-up form's values are keyed by the columns' full names before they reach the controller.
- An image's plain URL is served without its metadata (location, camera, comments; `OZ_IMAGE_STRIP_METADATA`, on by default): JPEG and PNG losslessly, their metadata blocks dropped and their pixels and color profile kept (`FS\Images\MetadataStripper`), a JPEG the EXIF turns and other formats re-encoded upright, the result kept as a rendition; an image that cannot be served so is an error, never the raw file. Its untouched original is the token `original` (`/files/ozone-{id}-{key}-original.jpg`), served to whom `OZ_IMAGE_ORIGINAL_ACCESS` allows (an `ImageOriginalAccessInterface`; by default its uploader and administrators), 403 to anyone else. An image OZone does not render (SVG) is served as it is.
- A stored file records its uploader: a new file saved while a user is signed in takes that user as `uploader_type` / `uploader_id` (`FileEntityTrait::save()`), unless the code set one. The CRUD listener that was meant to (`FS\Handlers\FilesHandler`, removed with its `oz.gobl.crud` entry) only ran for records created through the CRUD layer, so no upload ever had an uploader.
- A new image file keeps its size as shown (its orientation applied) and its dominant color in its data, under `image` (`{width, height, color: '#rrggbb'}`), read once when it is first saved (`FS\Images\ImageProbe`, `ImageProcessorInterface::probe()`); a clone keeps its source's. An image that does not decode, or is larger than `OZ_IMAGE_PROBE_MAX_SIZE` (30 MB by default), is stored without them, and an upload never fails on it. A page can reserve an image's place and show its color before it loads. Files stored before keep no such data.
- New image filter tokens, each bounded like the others (`ImageFilterTokens`): `r90` / `r180` / `r270` (turned clockwise), `fliph` / `flipv`, `area{x}x{y}x{w}x{h}` (a part of the image in thousandths, snapped to 5), `focal{x}x{y}` (the point a crop to a size keeps in view, snapped to 10), `webp` / `avif` and `auto` (the best format the browser accepts, `Accept`, and this server writes: AVIF, then WebP; the response varies on `Accept`), `invert`, `bright{N}` / `brightn{N}` and `contrast{N}` / `contrastn{N}` (up or down, snapped to 5), `pixel{N}` (blocks of 4 to 64), and `wm{name}`, a watermark declared in `OZ_IMAGE_WATERMARKS` (`oz.files`: image, position, opacity, width and margin). A watermark can be forced, on every rendition and on the image's plain URL, by `OZ_IMAGE_WATERMARK_POLICY` (per `for_label`) or a file's own `watermark` data; a rendition that cannot be drawn with its watermark fails rather than serve the image without. A project adds its own tokens (`FS\Images\Interfaces\ImageTokenInterface`, registered with `ImageTokens::register()` or `OZ_IMAGE_TOKENS`), drawn with Intervention Image. `OZ_IMAGE_FILTERS_MAX_TOKENS` defaults to 12 (was 8). `ImageProcessorInterface::render()` answers a `RenderedImage` (bytes and media type) and the interface gains `supports()`, which writes a pixel to tell, since a driver may read a format it cannot write (libheif without an AV1 encoder). An image that cannot be rendered is served as it is without being kept as a rendition.
- `oz server provision` checks the PHP it installed against OZone's minimum (step `php:version`, after `php`) and stops when the distribution's is older rather than leaving a server whose PHP could not run OZone; where apt can be pointed at a repository that has the series, the `php:repository` step above does that instead of stopping. The `php` step installs PHP's command line too (`PackageManager::phpCliPackage()`), which `oz`, its cron and its workers need and Alpine's FPM package does not bring. A new project's `composer.json` takes OZone's PHP constraint and platform pin (`Requirements::phpConstraint()`, the new `phpPlatform()`) instead of a copy of them in the template, and the test kit (`OZTestProject`) pins a test project's platform to the running repository's, so a project scaffolded before a change of minimum still installs.
- A store counts in whole numbers until a fraction is added, and counts **atomically**. `StoreDriverInterface::add($key, $by, $create, $expiresAt)` replaces the drivers' `increment()` and `decrement()`: it answers the new number (null when the entry is missing and not created), and concurrent adds, from requests or processes, are never lost. `MemoryStore` adds under its lock, `FileStore` under an exclusive `flock` (on `{path}.lock`) after reloading the file, which it reloads too when another process replaced it; `DbStore` compares and sets (the new number written only where the row still holds what was read, read again otherwise) and creates a missing counter with an insert that ignores a conflict; `RedisStore` uses `WATCH` / `MULTI` / `EXEC`, `MemcachedStore` `gets` / `cas`. Each read then wrote, so two requests counting at once lost one of their hits. A counter of 1 incremented by 2 was `3.0`, never `3`, in every store; `MemcachedStore` used to give Memcached a `float`, a `TypeError` under strict types, so its counters never counted. `KeyValueStore::increment()` / `decrement()` answer `false|float|int`, and the new `KeyValueStore::count($key, $by, $ttl)` creates a missing counter at `$by` with its TTL, an existing one keeping its expiry. A driver that gives up after `StoreNumbers::MAX_ATTEMPTS` contended tries throws.
- OZone needs **PHP 8.3** (was 8.1). Images are rendered by **Intervention Image 4**, which needs it, instead of `claviska/simpleimage`, behind an interface of OZone's (`FS\Images\Interfaces\ImageProcessorInterface`, `FS\Images\Images::processor()`): the driver is the first the server has of libvips (`intervention/image-driver-vips` with `ext-ffi`), Imagick and GD, or the one `OZ_IMAGE_DRIVER` (`oz.files`) names. Every rendition is now upright (a photo's EXIF orientation applied; `ext-exif` is suggested for GD) and carries no metadata: location, camera and comments are stripped from JPEG, PNG and WebP renditions, where they used to be kept. `sepia` is grayscale warmed toward brown (the library has none), and a blur pass is level 5 of the library's 0 to 100: both look slightly different.
- A `RESTFulService` declares `SERVICE_NAME`, a stable name its routes are named after (`events.get_all`, was `/events.get_all`, after the path): a client calls a service by name, so a path may change without breaking it. `SERVICE_NAME`, `SERVICE_PATH` and `TABLE_NAME` have no sample defaults any more (`/svc-path-sample`, `table_name_sample`): a service missing one, or whose name is not lowercase words joined by `_` or `.` (`RESTFulService::SERVICE_NAME_REG`), refuses to register with a message naming it (`RESTFulService::assertDeclared()`). A REST operation's OpenAPI `operationId` is its route name (`events.get_all`, `events.get_relation.tickets`), no longer the table's singular name. `oz services generate` writes `SERVICE_NAME`, from `--name` or from the base path (`/shop/orders` gives `shop.orders`), never from the table, whose name a client would then see; `ServiceGenerator::generateClass()` takes the name after the base path.
- Two routes may not share a name: a second route with a name already taken, explicit or another route's auto name, is refused (`RuntimeException` naming both paths and providers) when the routes are listed (`Router::getRoutes()` and the static / dynamic listings), when the route table is compiled, and when every provider is registered at once. A lookup by name, a built URI and the route table used to reach whichever route was mapped first, silently.
- New `oz routes export [--json]` (`Router\RouteExport::of()`): the explicitly named, non-internal routes of the API router, sorted by name, each with its methods, path, path parameters (their pattern, whether the path requires them, false in an optional part, and whether it is a global parameter, which the router fills from the context in the URLs it builds, as a client may), whether it declares a form and a resumable one, the descriptions of its shortcut guards (`guards`) and the number of guards without one (`opaque_guards`). A client builds its requests from it and a build checks and types the route names it uses; forms are still discovered at runtime.
- A route parameter's pattern, declared with `param()` / `params()` or `Router::addGlobalParam()`, must be a portable pattern body (`PHPUtils\PortablePattern`, delimited with `~`), so a client checks a value the way the router matches it; it still may not start with `^` or end with `$` (`Route::assertParamPattern()`). The old check compiled the pattern after `preg_quote()`, which escaped it into a literal, so it refused nothing: `param('id', '[a-z')` was accepted and only failed when a request was matched. A dynamic route's regular expression (`Route::getRegExp()`, the compiled route table's too, whose `RouteTable::FORMAT` is now 2 so a saved table is compiled again) runs as a portable pattern does: in Unicode mode (`.{2}` is two characters, not two bytes; a path that is not valid UTF-8 matches nothing) and with `$` at the very end (`[0-9]+` no longer matches `42` followed by a newline).
- The filter tokens of an image URL are bounded (`FS\Filters\ImageFilterTokens`): a width, a height or a thumbnail size is snapped to the nearest of `OZ_IMAGE_FILTERS_SIZES` (`oz.files`; the larger on a tie), a quality to a step of 5, a blur to `OZ_IMAGE_FILTERS_MAX_BLUR` passes, unknown tokens are dropped and at most `OZ_IMAGE_FILTERS_MAX_TOKENS` are kept; an image is never enlarged beyond its own size. A URL could make the server render an image of any size (`w99999`, upscaled in memory) and cache a rendition for every distinct token list (`w1001`, `w1002`, or the same size followed by any unknown token), filling the disk; equivalent requests now share one rendition. `w100` now renders 96 pixels wide: a page asks for the listed sizes.
- Translations are written in one message syntax, which a client of the API reads the same way (`OZONE\Core\Lang\Message\`: a hand-written parser and a renderer, behind `Polyglot`). Every text written so far reads the same (`{var}`, `{var | filter}`, `{{KEY}}`); added are paths (`{user.name}`), filter arguments (`{price | number: 2}`), `plural` / `selectordinal` / `select` choices in ICU's form (`{n, plural, =0 {none} one {# file} other {# files}}`, selectors tried in the order written: `=N`, the comparisons `>N` `>=N` `<N` `<=N`, a CLDR category, then `other`), and the escapes `\{`, `\}`, `\\` and `\#`. A CLDR category is answered by ext-intl (now a `suggest`); without it a category never matches, and `oz doctor check` warns when a catalog relies on one. Built-in filters: `upper`, `lower`, `capitalize`, `number`; a filter declared with `Polyglot::declareFilter()` now also receives the text's arguments, after the language, and takes the place of a built-in one of the same name. A text that does not parse is written as it is and logged; `oz lang export` fails on it and lists it (`errors`, `OZ_LANG_CATALOG_INVALID`), and `oz doctor check` fails. `Polyglot::PORTION_COPY_REG`, `SIMPLE_REPLACE_REG` and `FILTERS_SEP` are removed: the syntax is no longer regular expressions.
- A form's version (`Form::getVersion()`, the bundle's `version`) is the one its author sets with the new `Form::version()`, else a fingerprint of the whole bundle a client is sent, previews of public values left out. It used to fingerprint the fields, the fieldsets' names and fields and the `expect()` / `ensure()` sets only, so a changed condition, label or fieldset rule kept the version; and inside the preview scope a preview entered it, so the version a client got differed from the server's and changed whenever a preview did (seats left), making every saved draft look stale. A form with neither id nor name gets a new resume key.
- `AsyncValue` says at construction whether a client may see it: its constructor is private, `AsyncValue::secret($factory)` never leaves the server, and `AsyncValue::public($factory, $preview)` requires the preview a client is sent. A hidden value used to become public by adding an optional second argument. On the wire a withheld rule set is `{ref, $secret: true}` (was `$async`), a public value `{$preview: {value}}` (was `{$async: true, $preview}`), and a rule no longer carries `server_only`, which was always `false` in what a client received. Previews are computed in `Form::toClientArray()`, which every path sending a form to a client uses (`JSONResponse::toArray()`, and the `init_form` of the OpenAPI `x-oz-form` extension, which used to send a public value as `$preview: null`), so `withPreview()` is internal and the discovery interceptor and the form session no longer wrap their answers. `isClientResolvable()` is replaced by `isSecret()`, and serializing a secret value throws.
- A form sends every rule set it holds: `Form::toArray()` gains `ensure` (the form's `ensure()` sets, a `doubleCheck()`'s among them) and `Fieldset::toArray()` gains `expect` and `ensure` (its own, `null` for a dynamic fieldset, whose rules are those of the fieldset its factory builds), serialized as `expect` is: `{ref, $secret: true}` when server-only. They used to stay on the server, so a client could neither check a password confirmation while it was typed nor know a fieldset had rules at all. A literal operand is now sent to the client as it is: keep a value it must not see in an `AsyncValue::secret()`. The form session's `evaluate` answers a shown fieldset's server-only sets too, after the form's own, in `expect` and `ensure`.
- A step of a resumable form session carries the previews of its values, as discovery does. A value with a preview arrived as `$preview: null` while its rule set was not server-only, so neither the client nor `evaluate` could decide it before the step was submitted.
- A `multiple` field given something else than a list is refused with `OZ_FIELD_SHOULD_BE_A_LIST` and the field's ref in `field`. `Field::validateType()` passed the value as the exception's data, which must be an array, so any scalar was a `TypeError` and the client got a 500 (`OZ_ERROR_INTERNAL`) instead of a form error; `null` was refused as `Expected an array`, a text that is not a message key. The value is kept for the logs under `_value`, and not sent to the client.
- A rule compares two numbers by value: `eq`, `neq`, `in` and `not_in` stay strict, except that an int and a float that are equal are the same number. A float field's clean value is always a float, so `->eq('price', 3)` compared `3.0 === 3` and could never pass, whatever was sent; JSON also writes `3.0` as `3`, so a client could not have told the two apart. `3` and `3.5` stay different, and a number is still never the same as a numeric string or a boolean.
- A password, username, email, gender or url checks the string `"0"`. Their checks ran under `!empty($value)`, and PHP counts `"0"` as empty, so it was accepted untouched: as a password with no length or `secure` check, as a username with no length or pattern, as an email, a gender and a url. They now test for `null` and `''` only.
- A username's pattern (`OZ_USER_NAME_PATTERN`) runs through `PortablePattern::toPcre()`: in Unicode mode, `$` at the very end. It used to run as a raw `preg_match()`, byte by byte, so a client running the pattern a discovered form sends could disagree with the server; `.{2}` now means two characters, not two bytes.
- A discovered password, username or gender field sends the rules it takes from the settings (`FrontendTypeOptionsInterface`, merged by `Field::frontendType()`): a password its effective `min` and `max` (its own, else `OZ_USER_PASS_MIN_LENGTH` / `OZ_USER_PASS_MAX_LENGTH`), a username its `min`, `max` and, when `PortablePattern` accepts it, its `pattern` (`OZ_USER_NAME_*`), a gender its `allowed` values (`OZ_USER_ALLOWED_GENDERS`). A client could not check any of these without copying the settings itself, which is stale as soon as a project changes one.
- A discovered enum field sends its cases: the type carries `enum_cases`, `[{name, value}]` in declaration order (`Field::frontendType()`, used by `Field::toArray()` and `TypesSwitcher::toArray()`, which now hold the type as an array). The class name alone told a client nothing it could render or check.
- A discovered field says whether it holds a list: `Field::toArray()` sends `multiple`. A field declared `->multiple()` used to discover as a single value of its type, so a client could neither render it as a list nor clean its value.
- A rule on a cleaned value must use an operator its field's type allows. `Form::validate()` and `Form::toArray()` throw a `RuntimeException` for `->if()->gt('active', 1)` on a boolean field, where the rule used to compare `true > 1` and answer whatever PHP's loose ordering gave. Checked: `ensure()`, `Field::if()`, `Fieldset::if()` and `TypesSwitcher::when()` rules (`RuleSet::assertOperatorsFit()`); not checked: `expect()`, which reads the raw payload, `is_null` / `is_not_null`, a field whose type is a `TypesSwitcher`, and a field the form does not know.
- One envelope everywhere. An error built from an exception carries `utime`, as a service's answer does (`{error, msg, data, utime}`), and a command in JSON mode (`--json`) answers with that same envelope through `JSONResponse` instead of its own `{ok, ...}` object: the command's result is under `data`, what it reported under `data.messages`, and a failure is `error: 1` with the message in `msg`. `Cli::writeJson(array $data, bool $ok, int $exit, string $msg)` builds it.
- `oz server provision|status` and `oz deploy run|rollback|releases` act on a host: this machine, or a server over SSH with `--host=user@host[:port]` (and `--identity=<key>`; the SSH agent otherwise). `Cli\Server\Interfaces\HostInterface` (extending `ShellRunnerInterface`) runs commands and reads the target -- binaries, root, paths, links, directories, files, uploads -- with `LocalHost` and `SshHost` (system `ssh` client, one reused connection, `OZ_SSH_COMMAND` to replace it). `ShellRunner` is removed (use `LocalHost`); `PackageManager::detect()` takes the host and `PackageManager::hasBinary()` is removed (`HostInterface::hasBinary()`); a `ProvisionStep` check is `fn (HostInterface $host): bool` and `isSatisfied()` takes the host; `ProvisionPlan::run()` and `Releaser::run()` take a `HostInterface`; `ProvisionManifest::load()`, `Releaser::rollbackSteps()` and the `ReleaseLayout` readers take an optional host (this machine by default). `oz deploy run --host` copies a local archive or bundle file to `{root}/uploads/` before releasing.
- A worker server that does not read PHP's output can take the `Response` object instead. `OZone::handleRequest()` accepts a `Request` (or an `HTTPEnvironment`) and a `Runtime\Interfaces\ResponseSinkInterface`: the finished response is given to the sink exactly where it would have been written -- after `ResponseHook` and `ResponseEmitter::prepare()`, before `FinishHook` -- and a sub-request answers through its root's (`Context::getResponseSink()`). With a sink, `Context::finish()` no longer flushes PHP's output and `BaseException::criticalDie()` sends its error page to the sink rather than `echo`: under RoadRunner the process output is the protocol pipe. `Context`'s constructor gained the sink as its fifth argument.
- New server bridges in `Runtime\Bridges\`: `FrankenPhpBridge::serve($app)` (worker mode; FrankenPHP resets the superglobals, so no sink), `RoadRunnerBridge::serve($app)` (needs `spiral/roadrunner-http`; the bridge is the sink and streams bodies above 1 MiB) and `SwooleBridge::attach($server, $app)` (needs `ext-swoole`; bootstraps in each worker's `workerStart` and turns request coroutines off, since OZone keeps one request per process). Both packages are `suggest`ed; psalm reads their APIs from `stubs/`. `make test-runtimes` serves OZone from real FrankenPHP, RoadRunner and Swoole containers and tests it over HTTP.
- Building a request that did not come through PHP: `HTTPEnvironment::fromParts($method, $uri, $headers, $server)` derives the CGI variables, and `Request::createFromHTTPEnvironment()` takes an optional body, parsed body and uploaded files -- given a body, `php://input`, `$_POST` and `$_FILES` are never read. `UploadedFile::fromFilesArray($files, $sapi)` builds a `$_FILES`-shaped tree whose temporary files PHP did not register (moved with `rename()`). `ResponseEmitter::bodyChunks()` reads a prepared response's body in chunks, honouring a byte range, for a sink to stream.
- Sessions start lazily: an anonymous request no longer creates and saves a session. `OZ_AUTH_API_AUTH_METHODS` ends with `SESSION`, which is always satisfied, so every request without a bearer token, API key or session cookie used to insert an `oz_sessions` row and receive a cookie -- a database write per anonymous request, and a table anyone could fill. A new session is now saved, with its cookie and its XSRF cookie, only when it is used: its ID handed out (`Session::id()`, i.e. a CSRF token, a rate-limit key or a form-resume scope bound to it), a user attached, or its store data changed. An existing session keeps sliding its expiry as before. A session cookie that points to no session is dropped. `Polyglot::getLanguage()` no longer writes the browser's `Accept-Language` advice into the session on every request (it is recomputed from the header; `setUserLanguage()` still stores a choice).
- New `Hooks\Events\EndRequestHook`, dispatched by `OZone::endRequest()` with the root context being released (or null), for per-request cleanup of anything kept outside the `Context`: register the listener once in a boot hook receiver's `boot()`, as an application can now do too. The framework's own releases (`ApiDoc::release()`, `BaseException::release()`, `MemoryStore::release()`) moved from `endRequest()` to such a listener in `MainBootHookReceiver::boot()`; `endRequest()` releases the context tree itself, last, even when a listener throws (the failure is logged). New `Context::hasRoot()`.
- `ApiDoc::get()` takes no argument and the spec keeps no context: what needs the request (`viewInject()`, `OperationBuilder`) reads the current one through `context()`. `OperationBuilder`'s constructor lost its `Context` parameter.
- `OZone::hasDbAccess()` and `OZone::hasSuperAdmin()` keep a positive answer for the process and ask a negative one again, at most once per request (`CacheRegistry::runtime()`). Their function statics kept whatever came first for the life of the process: under PHP-FPM that is one request, but a worker running while the project was installed kept reporting no database or no super admin until it restarted. Steady state still costs no query.
- `TableCRUDListenerInterface::register()` takes no argument, `TableCRUD::registerListeners()` neither, `TableCRUDListener` has no constructor and `BaseHandler`'s is `__construct(Table $table)`. Listeners are registered once per process, and they kept the context they were registered with: under PHP-FPM that is the request's, but under a worker it was the boot context -- no request, no user -- for every request, so `BaseHandler` evaluated CRUD access rules for an anonymous caller whoever was logged in, and `FilesHandler` saved every file without its uploader. A listener now reads the request being handled when an event fires, with `TableCRUDListener::context()` (or `Context::current()`, which is also a sub-request's). A project listener must drop the `Context` parameter of `register()` and of its handlers' constructors, and read `$this->context()` instead of `$this->context`.
- The session's cookies (session ID, XSRF token) are added to a response's `Set-Cookie` lines instead of replacing them: `Session::responseReady()` used `withHeader('Set-Cookie', ...)`, so a cookie a handler had set was dropped from every response that started or refreshed a session, under every runtime. New `Cookies::applyTo(Response)` adds a jar's cookies, replacing only a line that sets a cookie of the same name.
- Under a worker runtime, a throwable that reaches the global exception handler -- one that escaped the loop itself, since `OZone::handleRequest()` answers every request's -- is written to stderr and the process exits with status 1. It used to be logged in `.ozone/logs/` only and the process ended with status 0, which a supervisor reads as a clean stop: a worker that failed at bootstrap showed nothing but "worker has not reached frankenphp_handle_request()" or "WorkerAllocate: EOF".
- FrankenPHP is detected as a worker only when it started a worker script (`FRANKENPHP_WORKER`). It defines `frankenphp_handle_request()` in classic mode too, so a classic FrankenPHP site was taken for a worker: `OZone::run()` answered the mock boot request instead of the real one, then threw `RequestFinished` out of the script. `make test-runtimes` now also serves a classic FrankenPHP site.
- `OZone::bootstrap()` builds its boot context from a mock environment under a persistent runtime, as it does on the command line: a worker bootstraps before its first request, so `$_SERVER` holds no host or URI and building the context from it failed with a `TypeError` in `Uri` -- every FrankenPHP, RoadRunner or Swoole worker died before serving anything. The boot context is released by the first `handleRequest()`.
- `oz project create` no longer declares VCS repositories: `silassare/blate`, `silassare/php-utils` and `oliup/code-generator` are on Packagist, so a generated project resolves with no `repositories` entry and no GitHub token, and the integration suite no longer needs `COMPOSER_AUTH`.
- `Router::runRoute()` closes the output buffer it opened when the handler throws, instead of only emptying it (`ob_clean()` leaves the buffer open). Nothing closed it but the error path, which a handler that responds and _then_ throws never reaches -- the pattern `respond(): never` publishes -- so in a worker every such request leaked one buffer level for the life of the process, with the response nesting deeper inside orphaned buffers. Under one-request-per-process the leak was invisible: the process exited.
- How a request ends is now the runtime's decision (`oz/Runtime/`): `Context::finish()` keeps its job -- flush, then dispatch `FinishHook` -- and asks `Runtime::current()->terminate()` to stop. `CgiRuntime` (PHP-FPM, mod_php, the built-in server) and `ConsoleRuntime` (the `oz` command line) `exit`; `WorkerRuntime` (FrankenPHP worker mode, RoadRunner, Swoole, ReactPHP) throws `RequestFinished`, so the stack unwinds exactly as far as `exit` would without taking the process with it. It must unwind rather than merely skip the exit: `respond()` and `finish()` being `: never` is a published contract, so a handler, guard or view -- in an application as much as in the framework -- may respond and then fall through to a throw or a `return` it expects to be unreachable, and skipping the exit would run all of it. Application code that catches `Throwable` around a `respond()` must re-throw `RequestFinished`. `OZone::handleRequest()` is the worker entry point, `OZone::run()` stays the one-shot one. A loop OZone cannot recognise declares itself with `Runtime::set(new WorkerRuntime('swoole'))` or `OZ_RUNTIME=worker`; an installed extension is never taken as evidence.
- `OZone::isCliMode()` now means "there is no HTTP client to answer" and reads it from the runtime, instead of `PHP_SAPI === 'cli'`. RoadRunner, Swoole and ReactPHP workers all run under the `cli` SAPI, so under any of them it was true while real clients were waiting: `BaseException::informClient()` printed the message to the terminal and exited, killing the worker on its first 404 with no client ever answered, `ErrorUtils::exceptionHandler()` did the same, and `bootstrap()` mocked the HTTP environment and swallowed a `Db::init()` failure that is meant to be fatal on a web request. Runtime detection asks about the worker loop before it asks about the SAPI. Anything implementing `RuntimeInterface` must provide `isConsole()`.
- `BaseException::criticalDie()`, `dieWithAnUnhandledErrorOccurred()` and `dieWithAnErrorHandlingErrorOccurred()` are typed `: never` and stop through the runtime rather than `exit`. In an exception handler or a shutdown function, where PHP treats unwinding as a fatal error, the handlers log and return instead -- reaching them is a bug in the loop, since `OZone::handleRequest()` answers what it can and logs the rest so one bad request cannot take a worker down.
- New `OZone::endRequest()`, for a persistent worker: it releases the context tree (`Context::release()`, which is also what lets the next request own a root -- a second root context is refused while the first is alive), the OpenAPI spec object (`ApiDoc::release()`), the "an error was already handled" flag (`BaseException::release()`, never unset before, so the first handled error made every later one die with no response) and the runtime cache (`MemoryStore::release()`). Process-wide configuration -- routers, settings, database, job stores, cron tasks, registries -- is deliberately kept.
- `Router::runRoute()`'s recursion guard is per request instead of per process: the trail is recorded on the root context (`Navigator::recordRouteRun()`). One request making ten sub-requests used to fail with "Possible recursive redirection" -- reachable today, without a worker.
- `oz server provision` is now proven against real Debian (apt) **and** Alpine (apk) containers. `PackageManager` gained Homebrew detection and refuses it as a server target (a Mac has no systemd and no distribution service names; `install` still supports it for getting `oz` onto one), reports through `isVerified()` which managers the container matrix covers, and the command warns when the detected one is not among them. Alpine's `libxml` comes from `php{ver}-xml` -- there is no `php{ver}-libxml` package, and asking for it aborted the whole install.
- The stream factories of `Http\Traits\StreamSourcesTraits` (`create()`, `fromString()`, `fromPath()`) now throw when `fopen()` fails instead of handing the `false` to the stream constructor: `FileStream::fromPath()` on a missing or unreadable file used to return a stream that failed later, elsewhere, naming neither the path nor the reason.
- `AbstractLocalStorage` checks its `filesize()` and `fopen()` calls, so a full disk or a permission change reports itself rather than raising a `TypeError` from `setSize(false)`.
- `Form::fromTable()` throws a clear error for an unknown table name instead of a fatal on null. `Session` gained private `entry()` / `store()` accessors carrying the session-started guarantee in their return types. `oz migrations run` on a project with no migration file says so. Several `$debug['...'] = ...` writes to an undeclared variable are initialised properly -- they emitted an "Undefined variable" warning exactly when an upload had already failed (`TypeFile`, `UploadedFile`, `AbstractLocalStorage`).
- `Context::$_root` / `$_current`, `OZone::$_app` and `ValidatedFile::$_loaded_file` are renamed without the underscore prefix (`$root_context`, `$current_context`, `$app_instance`, `$loaded_file`); all four are private.
- `RedisStore` and `MemcachedStore` check for their extension in the constructor and in `fromConfig()` instead of at file scope: the old file-level `throw` fired while the class was being autoloaded, so naming the class in a settings array -- or any `is_subclass_of()` check -- failed on a host without the extension, before anyone asked for that store.
- New `oz deploy` command. `init` generates a project's deployment files (`--target=docker` copies `conf/docker/` out of the package, `--target=bare` generates an nginx vhost and a PHP-FPM pool per scope plus the systemd cron service and timer, the worker template unit and a logrotate file, `--ci=github` a workflow); it never overwrites without `--force`. `run` deploys an atomic release into `{root}/releases/...` behind a `current` symlink, with `data/`, `.ozone/` and `.env` shared beside the releases and linked into each one: everything that can fail runs before the swap, a failed `--health-url` check rolls `current` back, and pruning never removes the live release. `rollback` and `releases` complete it.
- The Docker sample of `conf/docker/` drops the `public-static` volume -- one `data` volume now covers every state directory, since `public/*/static` are symlinks into `data/static` -- and its entrypoint runs `oz project link` before anything else, because those symlinks are not in the image.
- `oz scopes add` refuses an existing scope with a message that says so, and its emptiness check works: the guard asserted `{project}/{scope}` -- a path that never exists -- so re-adding a scope failed with "no directory found" and never checked whether the existing directory was empty. A pre-created empty directory is still accepted.
- Cache and durable state are now separate: `oz.stores.state` declares the stores whose loss a user would notice and `Stores\StateRegistry::store()` reads them, while `oz.stores.cache` and `CacheRegistry` keep only what can be recomputed. `oz:form:sessions`, `oz:form:resume`, `oz:rate_limit` and `oz:auth:digest:nonces` moved to the state group (`oz:fs:image:filters` stays a cache), so a project overriding any of them must move its override too. `CacheRegistry::store()` now throws on a state store name instead of silently falling back to the default persistent driver.
- `StoreCapabilities` gained `durable`, distinct from `persistent`: it says whether losing an entry is _allowed_, which depends on where the instance writes. `StateRegistry` refuses a driver that does not promise it -- `DbStore` and `RedisStore` always do, `MemoryStore` and `MemcachedStore` never, and `FileStore` only with `options: {"root": "state"}`, which puts its files under `data/{scope}/state` instead of `.ozone/cache/`. A custom `StoreDriverInterface` driver must pass the new argument.
- The database is initialized when first used, outside the command line: `OZone::bootstrap()` no longer calls `Db::init()` for a web request, and a request that never touches the database never builds the schema. `Db::initOnFirstUse()` runs `init()` when a class of an ORM namespace (OZone's, the project's, an enabled plugin's) is first loaded, since Gobl only knows those namespaces once `init()` declared them; `Db::registerTypes()` registers OZone's column types at bootstrap. `Db::init()` throws instead of recursing when the database is used from inside its own initialization. Route providers should not read generated ORM classes while registering routes: that loads them, and so initializes the database for every request (`AuthorizationService` used `OZAuth::COL_REF` as its route parameter name).
- The CRUD listeners (`TableCRUD::registerListeners()`) and the entity collections register when the database is initialized (`DbReadyHook`), gated on the recorded migration version (`OZ_MIGRATION_VERSION`) instead of `OZone::isInstalled()`. That demanded a super admin, which access control does not depend on, and cost two queries per request: a worker started before the super admin was created ran without the CRUD listeners until it restarted.
- A new session builds no `OZSession` entity and no ID until it is kept: the entity is made by `save()`, and the ID -- a hash over `$_SERVER` and 128 random characters -- when first asked for. Together they were about half of an anonymous request's time in worker mode, and the entity alone made every request initialize the database.
- `Settings::get()`, `has()` and `load()` read a loaded group's merged array directly for a top-level key, instead of parsing the key as a dot path through the group's store a few hundred times per request; the array is dropped whenever the group is reloaded or modified. `Settings::has()` no longer generates a random string per call. `TrustedProxies::fromSettings()` returns one instance per process, rebuilt when `oz.proxies` changes. A route's full path is computed once per option revision (`RouteSharedOptions::getPath()`), and `RouteOptions::fullPathPrefix()` now invalidates it.
- `FrankenPhpBridge` no longer calls `gc_collect_cycles()` after every request: it cost about 40% of a request's time in worker mode, for the few cycles a request leaves, which PHP's own collector reclaims in batches.
- Image filter renditions (`ImageFileFilterHandler`) are files in the scope's cache directory, served as a stream, instead of entries of the `oz:fs:image:filters` store (the `DbStore` by default): a large image travelled through the database and sat in memory whole. Renditions older than `OZ_IMAGE_FILTERS_CACHE_TTL` (`oz.files`, 7 days) are removed by the `oz:fs:image-filters` garbage collector; the handler is a boot hook receiver. The `oz:fs:image:filters` store no longer exists, and `oz.stores.cache` ships no store.
- `OZONE\Core\Cache` and `OZONE\Core\State` are one namespace, `OZONE\Core\Stores`, and the names that called every store a cache are gone: the drivers are `Drivers\DbStore` (was `DbCache`), `RedisStore` (`RedisCache`), `MemcachedStore` (`MemcachedCache`), `FileStore` (`PHPCache`) and `MemoryStore` (`RuntimeCache`); `Interfaces\StoreDriverInterface` (`CacheProviderInterface`), `Interfaces\StoreEntryExpiryListenerInterface` (`CacheEntryExpiryListenerInterface`), `StoreEntry` (`CacheEntry`), `StoreCapabilities` (`CacheCapabilities`), `StoresGarbageCollector` (`CacheGarbageCollector`). `CacheRegistry`, `StateRegistry` and `KeyValueStore` (was `Cache\CacheStore`) keep their names. The settings groups are `oz.stores` (was `oz.cache`), `oz.stores.cache` (`oz.cache.stores`) and `oz.stores.state` (`oz.state.stores`); a project's overrides must be renamed with them. `OZ_CLEAR_SITE_DATA_HEADER_ON_LOGOUT` and `OZ_CLEAR_SITE_DATA_HEADER_VALUE` moved from `oz.cache` to `oz.auth`, next to the logout they configure.
- In production, outside the console, requests are routed through a compiled route table (`Router\RouteTable`) instead of registering every route provider: loading and running all of them was about a quarter of a request in classic mode. The first router of a release registers everything and saves the table in the scope's cache directory (`Router::compileTable()`); the next ones register at once only the providers that do more than map routes -- none, or a global parameter, like `Polyglot`'s -- then the provider of the route a request matched, or of a route looked up by name (`Router::registerProviders()`, which replaces `OZone`'s private registration). `getRoutes()`, `getStaticRoutes()` and `getDynamicRoutes()` register every provider first, and `find($method, $path, true)` too. The file is named by the project directory (a new release), OZone's version, the providers and the mtimes of the application's and the scope's stateful settings directories, which `Settings::set()` / `unset()` now touch; a table whose matched route disagrees with the code is deleted and the router registers everything. A route provider's `registerRoutes()` must map routes and nothing a request could depend on: anything else only runs when that provider is registered.
- `OZone::bootstrap()` builds the current scope before anything else, so its stateful settings (`data/{scope}/settings`, which `oz settings set --scope=...` writes) are a settings source before any group loads. They used to join only when something first asked for the scope -- a log line, a temp file -- and every group loaded before that ran without the scope's overrides: a scope's stateful settings now apply to the whole of every request.
- A route not named explicitly is named from its methods and full path (`route_` and an xxh3 hash, `_2` onwards for an identical definition) instead of a counter shared by every router: the counter depended on the order routes were registered in, which a route table no longer fixes. `Route::key()` of such a route changes with it, so per-route state keyed by it (form sessions, the form resume cache, per-route rate limits) starts over for unnamed routes. `RouteOptions` no longer names itself (`RouteOptions::autoName()`, called by `Router::map()`); a `Route` records the provider that mapped it and its position there.
- The schema of the installed migration is loaded lazily (`Db::new()` calls `setLazySchema()`, new in `silassare/gobl`): each table is built when first used -- asked for, listed, or reached through a relation or foreign key -- instead of the whole schema being built and locked for every request that touches the database (about 8 ms for OZone's 11 tables, warm).
- New `oz project build [--mode=classic|worker] [--no-preload] [--skip-orm] [--clear]` prepares a project for production. It generates the ORM classes, then, in production, compiles ahead what requests would compile at first use -- for each scope, in a process of its own set up as the scope's `index.php` sets itself up: the compiled `.env`, the settings bundles, the route tables -- and writes a class map and, in classic mode (a bootstrap per request: PHP-FPM, Apache), a preload list: every class of OZone and of its first-party packages (`silassare/*`, `psr/*`) that preloading can compile -- the request path and Gobl's ORM runtime included, not only what booting declared -- and what else booting and building the routers declared; it leaves out the command line (`OZONE\Core\Cli\`, Kli), tests, the generated ORM classes (their first use initializes the database), Composer's files and what extends them. All of it is named after the release, since `.ozone/` is shared by the releases of an `oz deploy` root: `opcache.preload` points to `.ozone/preload.php`, the same script for every release, which preloads the list of the release `current` points to when PHP starts (of the project itself outside a deploy root), so a release that fails before going live is never preloaded and a rollback preloads the release it went back to once PHP restarts. `oz deploy run` runs it before going live. The Docker entrypoint runs it for the `php-fpm` and Apache containers and points `opcache.preload` to the script, unless `OZ_PRELOAD=0`.
- `oz deploy init --target=bare` also generates `deploy/php/{project}-preload.ini`, the PHP-FPM `conf.d` file that turns preloading on (`opcache.preload`, and `opcache.preload_user` as the pools' user, since PHP-FPM starts as root); `--no-preload` leaves it out. PHP-FPM reads it once for all of its pools, so one project per PHP-FPM server can preload, and a deploy must restart PHP-FPM (`oz deploy run --restart=php8.4-fpm`) for new code to be preloaded.
- Cron runs wherever the project runs, and each due task once. Ticks (`CronRunner::tick()`) come from a scheduler -- `oz cron run` every minute, or the new `oz cron work`, a long-running process (the Docker sample's `cron` container now runs it; `oz deploy init --target=bare --cron=daemon` generates its systemd service, `--cron=timer` the timer as before) --, from the new `oz:cron` route (`/oz-cron`), which an external service calls with `OZ_CRON_WEB_KEY` (`oz.cron`; no route without it), and, by default (`OZ_CRON_RUNNER=auto`) while no scheduler has checked in for two minutes, from requests: the first request of a minute runs a tick once its response is sent, so a host without a scheduler (shared hosting) needs no set-up; `OZ_CRON_RUNNER=scheduler` keeps tasks out of requests. A task's job is named after the task and its minute, and a job store now refuses a ref it has (`JobStoreInterface::add()`; the Redis store used to overwrite the job), so a task runs once per scheduled minute however many servers, requests or services tick -- two servers each running `oz cron run` used to run every task twice. A tick also dispatches the minutes no tick ran, an hour back at most. `Cron::runDues()` takes the time of the last tick; `Queue::push()` takes an optional ref.
- The garbage collection runs on requests (`OZ_GC_PROBABILITY`) only while no scheduler runs cron (`oz cron run`, `oz cron work` or the `oz:cron` route, none for an hour): with one, its hourly `oz:gc` task collects, and requests no longer do. Without one, requests collect as before.
- `oz doctor check` reports the production build: in production, a warning when this release has no class map, when classes were added, moved or removed since it was written, or when the preload list names files changed or gone since the build (preloaded code only changes when PHP restarts).
- Settings source directories are read, in production outside the console, from a compiled bundle each (`.ozone/build/settings/`): one array per directory instead of a stat and an include per group and directory. A bundle is named after the directory, the release, OZone's version, the `.env` file and the directory's mtime, so an edit of `.env` or a deployment gives new ones; a directory whose settings hold objects is not bundled. Stateful directories are always read file by file: `Settings::addSource()` takes `$stateful`, which `AbstractApp` and `AbstractScope` pass for `data/{scope}/settings`.
- `.env` is read through a compiled copy (`.ozone/build/env/`, named after the file's path and content) instead of being parsed for every request. `Env::getSignature()` returns a hash of the content.
- Template set-up waits for the first template: `BlatePlugin::boot()` defers loading the framework's and the project's `.blate.php` and setting Blate's cache directory to `Blate::onFirstUse()`, and Blate registers its own blocks, helpers and globals on first use too, so a request that renders no template -- an API call -- does no Blate work (it registered about 90 helpers, blocks and globals, each dispatching an event, on every request). Needs the `silassare/blate` that has `Blate::onFirstUse()`.
- `ClassLoader` registers ahead of Composer's autoloader (a class of a namespace both know is now found by OZone's first), and returns at once for a class whose root namespace none of its namespaces has (Gobl, php-utils, a PSR interface: Composer's); knows lazy namespaces (`addLazyNamespace()`: the directory is resolved by the namespace's first class -- how the generated ORM classes now initialize the database, `Db::initOnFirstUse()`); finds a class's file without loading it (`findFile()`); and, in production outside the console, reads the class map `oz project build` writes (`useClassMap()`, `mapNamespaces()`).
- `StateLayout::path()`: a scope's state directory as a path, kept for the life of the scope object (the stateful settings sources and the route table key read it on every request).
- `StoresGarbageCollector` scans both store groups, so an expiry listener on a state store (`FormSessionStore`) is called as before.
- `FilesManager::writeAtomic()` writes through a temporary file and `rename()`; `FileStore` and `TempFS` use it, so a reader on a volume shared between instances can no longer catch a half-written entry.
- State directories are laid out **scope first** under one root: `data/{scope}/settings`, `data/{scope}/files`, `data/{scope}/static`, `data/{scope}/tmp-fs` and `data/{scope}/state`, where `{scope}` is `ScopeInterface::getStateSlug()` (`root`, a scope name, or `plugins/{name}`). It reads as the rest of a project already does (`scopes/{scope}`, `public/{scope}`, `.ozone/cache/{scope}`), and the whole of a scope's state is one subtree: one path to move, mount, measure, back up or remove, where a glob over `data/{kind}/{scope}` gives none -- a mount point and a rename take a prefix. A per-kind rule stays one glob over the scopes (`data/{scope}/tmp-fs`), the form a backup exclude takes. The root is still whatever `$scope->getDataDir()` returns, so an app or a single scope may keep its state outside the project. Existing projects must move `data/settings/*.php` to `data/root/settings/`, `data/files` to `data/root/files`, `data/scopes/{s}/settings` to `data/{s}/settings` (same for `files`), `data/plugins/{p}/settings` to `data/plugins/{p}/settings`, and `public/static` to `data/root/static`; a project already on the kind-first layout swaps the two levels (`data/{kind}/{scope}` to `data/{scope}/{kind}`). `Scopes\StateLayout` owns the layout.
- Public files moved out of the web root: they live in `data/{scope}/static` and are reached through a `{document root}/static` symlink created by `oz project create`, `oz scopes add` and the new idempotent `oz project link` (run it after a clone, or after a deploy that produces a new release directory -- the links are not version-controlled). PHP resolves the real path, so uploads work without the link; only the web server follows it.
- `TempFS` moved from `.ozone/cache/scopes/{scope}/tmp-fs` to `data/{scope}/tmp-fs`: an in-flight chunked upload and a file a form has accepted are a user's work in progress, and `.ozone/` is per instance and may be deleted at any time. In-flight uploads do not survive the upgrade.
- `.ozone/` is laid out **by lifetime**, and `App\InstanceLayout` owns it. `build/` holds what `oz project build` produces and what a request fills in when no build has run -- the compiled `.env` (`build/{release}/env/`), the settings bundles (`build/{release}/settings/`), the route tables (`build/{release}/routes/{scope}/`), the class map (`build/{release}/classmap.*.php`), the preload list (`build/{release}/preload.php`) and the generated ORM classes (`build/{release}/plugins/`, was `.ozone/plugins/`), each release under a directory of its own -- all of it derived from the release's code, so a deployment may drop the directory whole and pay only a rebuild. `cache/{scope}/` holds what a request fills on demand (image renditions, compiled templates, a file store's disposable entries, `oz project serve`'s `server.json`) and may be dropped mid-flight; it replaces `cache/scopes/{scope}/` and, for a plugin, `cache/plugins/{name}`, and uses `getStateSlug()` so it reads as `data/{scope}` does. `logs/` is unchanged, and `preload.php` stays directly under `.ozone/`, since `opcache.preload` points at that one path for every release. `ProjectBuilder::clear()` now removes build output only, never a request's caches. Nothing has to be moved by hand: everything here is rebuilt on demand, but a deployment that pins `opcache.preload` must still restart PHP after the first build.
- Scope and plugin names are checked before anything writes a directory for them. The first level of `data/` is one namespace shared by the scopes and by `plugins/`, so `StateLayout::scopeNameFault()` refuses `root` (the application's own state slug: the scope would have read and written the application's settings, files and state store), `plugins` (the directory every plugin's state lives under), the name of a state kind, and anything that is not a plain slug -- `.` and `..` included, which could name something outside the project. `oz scopes add` reports the reason and `SubScope`'s constructor throws, so a scope directory created by hand is caught too. `StateLayout::pluginSlugFault()` refuses a plugin whose name slugs to nothing (its state would have landed in the directory shared by every plugin), and `Plugins::scopeOf()` refuses two plugins whose names slug alike (they would have shared one state directory, one cache and one assets directory, and read each other's settings). A project whose scope name was not a slug -- uppercase, a space, an underscore -- must rename it, directories and `data/` included.
- `data/` is never created automatically any more (`AbstractApp::getDataDir()`): it is the volume a deployment mounts, so a node whose disk did not come up fails loudly instead of starting on an empty one. Everything inside it is still created on demand.
- `ScopeInterface` gained `getStateSlug()`, `getTempDir()`, `getStateStoreDir()` and `getDocumentRootDir()`; `getPublicDir()` now means the **document root** on every scope (`public/` for the application, `public/{scope}` for a sub-scope) and delegates to `getDocumentRootDir()`, while public _files_ are `getPublicFilesDir()`. A scope's `getDataDir()` is the one project data root rather than a per-scope directory. Custom `ScopeInterface` implementations must add the four new methods.
- `ext-mbstring` is declared in `require`: `silassare/kli` renders every table and help screen through `mb_strlen()` / `mb_strimwidth()` and declared it only in its own requirements, so `oz doctor check` did not check it, the `install` script did not report it and `oz server provision` did not install it -- every table-rendering command then died with `Call to undefined function mb_strimwidth()` on a host without it.
- `RedisStore::clear()` deleted nothing: its `SCAN` started at cursor `0`, which phpredis reads as "iteration finished", so the first call returned false and the method reported success without touching a key. It now starts at `null`. Any project relying on `CacheStore::clear()` with the Redis driver was silently keeping its entries.
- New `oz server provision` command, for a server that will not run Docker: PHP-FPM and the extensions the project requires, a web server (`--web-server=nginx|apache|caddy|none`), a database (`--db=mysql|postgresql|none`), `--redis`, a TLS certificate (`--domains`, `--tls-email`) and the firewall (SSH on `--ssh-port` plus HTTP/HTTPS, `--no-firewall` to skip). `--mode=docker` installs Docker instead. `--dry-run` prints the exact commands and runs nothing; without `--yes` the plan is confirmed first; what was installed is recorded in `/etc/ozone/provision.json`, so a second run skips it and `oz server status` reports it. Package names come from `Cli\Server\Enums\PackageManager` per manager (apt, dnf, pacman, apk) and never ask for an extension the distribution builds into PHP.
- The object storage no longer moves a whole file through memory. `S3Client` gained a transport seam (`FS\S3\Interfaces\S3TransportInterface`): `Transport\CurlTransport` streams the request and response bodies and is used whenever `ext-curl` is loaded (a `suggest`, not a `require`), and `Transport\StreamWrapperTransport` is the fallback, which streams responses and sends uploads part by part since the stream wrapper cannot stream a request body. New: `putObjectFromStream()` (one request below the part size, multipart above it), `getObjectTo()` (writes into a stream), `objectSize()`; `putObject()` and `getObject()` are kept for small bodies. The payload hash is computed by reading a seekable body once instead of buffering it, and `UNSIGNED-PAYLOAD` is used only for a body that cannot be read twice. `MinioStorage` streams uploads, downloads and `serve()` in proxy mode, and `append()` / `prepend()` spool through a temporary file instead of concatenating in memory. New `OZ_MINIO_PART_SIZE` in `oz.files.minio` (16 MiB, raised to the service's 5 MiB minimum when set lower).
- `Migrations::getSourceCodeDbVersion()` now returns the version of the latest migration **file**, not `OZ_MIGRATION_VERSION`. That setting only ever recorded the version the database is at (it is written when a migration runs, never when one is created), so `Migrations::getState()` reported `INSTALLED` with migrations waiting and `MigrationsState::PENDING` was unreachable. Read the setting with the new `Migrations::getInstalledDbVersion()`, which is what `Db::init()` uses to pick the schema matching the live database. `hasPendingMigrations()` is unchanged in behaviour and now shares that comparison.
- A `Db::init()` failure at bootstrap is no longer fatal in CLI mode: the schema error is recorded (`OZone::getDbInitError()`) so `oz doctor check` and `oz migrations rollback` can run on a project whose schema cannot be prepared, and anything that touches the database still throws it. A web request still fails at bootstrap. `OZone::hasDbAccess()` catches any throwable, not only `PDOException`.
- The root `install` script no longer provisions a server: it checks PHP, the extensions, Composer and git and prints what is missing, installs OZone in `~/.ozone/tool` (`--system` for `/opt/ozone`) and puts `oz` / `ozone` in `~/.local/bin` (`--system` for `/usr/local/bin`). It needs no root unless `--with-php` is passed, which installs PHP, its extensions, Composer and git and nothing else -- no nginx, no database, no firewall; `--version=<tag>` and `--uninstall` are new. It no longer pins PHP 8.1, no longer installs ufw, nginx, MySQL or p7zip, and no longer clones into `/opt/ozone/<version>` or writes `/usr/bin/oz`.
- New `oz doctor check` command: PHP version, the required extensions (read from `composer.json`, the project's included), Composer, git, and inside a project the `.env` keys, writable state directories, database reachability and migration state. `--json` for CI; exits non-zero when a check fails.
- The test image carries what the CLI shells out to: `mysqldump` and `pg_dump` (so `oz db backup`, and `oz migrations rollback` which backs up first, are actually exercised on MySQL and PostgreSQL) and `ext-pcntl` for the signal handling of `oz jobs work` and `oz project serve`. Debian ships MariaDB's client, which -- unlike MySQL's own -- verifies the server certificate by default and refuses the MySQL container's self-signed one, so the image turns that check off for the test containers. A generated project's `.env` now also receives the Redis / MinIO / ClamAV addresses the environment provides, shared with the unit sandbox through `tests/Support/ServiceEnv` -- the two lists had drifted, and since the image ships `ext-redis` (which makes `OZ_REDIS_ENABLED` default to true) every integration project was dialling `127.0.0.1`.
- Tests: the integration suite refuses to start when MySQL or PostgreSQL has no server configured (`DbTestConfig::allConfigured()` throws), instead of covering SQLite alone; `OZ_TEST_ALLOW_PARTIAL=1` runs it on the configured drivers only. `tests/Integration/bootstrap.php` is removed: PHPUnit 9 has one bootstrap for the whole run, so it never ran and both suites use `tests/autoload.php`. Run the suites through `make` (`make ci` is what CI runs); a local `phpunit.xml` is unsupported and the bootstrap warns when one exists.
- Resume classes moved to `OZONE\Core\Forms\Resume`:
  - `Forms\AbstractResumableFormProvider` -> `Forms\Resume\AbstractResumableFormProvider`
  - `Forms\Interfaces\ResumableFormProviderInterface` -> `Forms\Resume\Interfaces\ResumableFormProviderInterface`
  - `Forms\FormResumeProgress` -> `Forms\Resume\FormResumeProgress`
  - `Forms\Enums\FormResumePhase` -> `Forms\Resume\Enums\FormResumePhase`
  - `Forms\Services\ResumableFormService` -> `Forms\Resume\Services\ResumableFormService`
  - `Router\RouteResumableFormProvider` -> `Forms\Resume\ResumableFormProvider`
  - `Router\RouteFormResumeInterceptor` -> `Forms\Resume\FormResumeRouteInterceptor`
- Renamed: `Router\RouteFormDiscoveryInterceptor` -> `Forms\FormDiscoveryRouteInterceptor`, and `Forms\DynamicValue` -> `Forms\AsyncValue`.
- Identifiers returned by `getName()` now mirror their classes: `route-form-discovery` -> `oz:form:discovery:route:interceptor`, `oz:route:form:resume:interceptor` -> `oz:form:resume:route:interceptor`, `oz:route:resumable:form:provider` -> `oz:form:resumable:provider`.
- `ResumableFormService` statics moved to `FormSessionStore`: `requireCompletion()`, `dropSession()` (now `drop()`), `CACHE_NAMESPACE`. The `oz:form:sessions` expiry listener is `FormSessionStore::class`; projects overriding that store in `oz.stores.cache` must update it. The six public handlers (`initSession()`, `getState()`, ...) are replaced by `handleStandalone(RouteInfo, ACTION_*)`.
- `FormSessionStore::requireCompletion()` requires the expected provider class. Sessions are bound to their provider and, when opened through a route, to that route; route-resumable sessions opened before the upgrade are rejected once.
- Typed field helpers (`string()`, `int()`, ...) return the `Field`; configure the type with `Field::configureType()`. `->multiple()` chained after a helper now means field-level multiple.
- `AsyncValue::toArray()` emits `$async` instead of `$dynamic`.
- `Form::validate()` lost its `$shallow` parameter.
- A field or fieldset condition that reads a field validated later in the same pass throws a `RuntimeException` instead of silently seeing null.
- Route `->resumable()` no longer arms `Form::resumable()`. A form's own `->resumable()` is replayed by the router, partitioned by `Route::key()`, so entries written before the upgrade are not replayed. `Form::buildResumeCacheKey()`, `resume()` and `saveForLater()` gained an optional `$partition`.
- The form session `evaluate` endpoint keys `fieldsets` by fieldset ref instead of name, and cleans the step's input by type instead of mixing in raw values.
- `TypeFile` rejects a temporary `ValidatedFile` whose file no longer exists, or whose TempFS ref has expired.
- A temporary `ValidatedFile` stores the `{tmpfs_ref}/{name}` TempFS reference instead of an absolute path, so it survives a deploy that moves the project directory and resolves on any instance that reaches the temp directory. `ValidatedFile::forTempFile(ref, name)` is the factory to use; `forTempPath()` converts a path under the temp directory, and `forTempValue()` reads a stored value. `->temp()` columns therefore store a reference, and values written before the upgrade are still read as paths. `UploadFiles` chunk sessions record `chunk_ref` instead of `chunk_path`; sessions started before the upgrade must be restarted.
- `Lang\PolyglotRoute` is removed: `Polyglot` is now both the boot hook receiver and the route provider for the `lang` route param. Replace `PolyglotRoute::class` with `Polyglot::class` in `oz.routes` overrides.
- `OZ_CORS_ALLOWED_ORIGIN` defaults to `'self'` instead of `'*'`, accepts a list of origins, and compares scheme, host and port. `'*'` now sends `Access-Control-Allow-Origin: *` without credentials. Frontends on another origin (e.g. `http://localhost:3000` in development) must be listed. The decision uses the `Origin` header only, no longer the `Referer`.
- Login failures return `OZ_AUTH_INVALID_CREDENTIALS` for both an unknown account and a wrong password (was `OZ_AUTH_USER_UNKNOWN` / `OZ_FIELD_PASS_INVALID`); `OZ_AUTH_USER_UNVERIFIED` is only returned after a right password. An account (or unknown identifier) is locked with `OZ_AUTH_TOO_MUCH_ATTEMPT` after `OZ_AUTH_LOGIN_MAX_FAILURES` failures within `OZ_AUTH_LOGIN_FAILURES_WINDOW`, and `POST /login` is rate-limited per IP. Basic auth uses the same checks.
- `GET /logout` requires a CSRF token (`_csrf`, see the `csrf_token` template global), and its `next` parameter only accepts local paths, the current host, or hosts listed in `OZ_REDIRECT_ALLOWED_HOSTS`.
- `TypePassword` rejection data no longer contains the submitted value, and `OZ_FORM_MISSING_REQUIRED_FIELD` carries `_submitted` (field names) instead of `_data` (the raw payload).
- `Context::getUserIP()` lost its `$risky` and `$allowed_proxies_only` parameters. Forwarding headers are only read when `REMOTE_ADDR` is listed in `oz.proxies` (which now accepts CIDR ranges), and the client is the right-most untrusted hop of `Forwarded` / `X-Forwarded-For` instead of the left-most public IP; `Client-IP` and the other non-standard headers are no longer read, and `CF-Connecting-IP` only when named in `OZ_CLIENT_IP_HEADER` (`oz.request`). `X-Forwarded-Host` is likewise only honoured from a trusted proxy.
- `RequestScope::USER_IP` no longer includes the client port, so IDs of that scope change once (CSRF tokens, form sessions and resume caches scoped by it).
- `IPRateLimit` counters are keyed per route (`ip:<route key>:<ip>`), so existing counters reset; `POST /login` uses `IPRateLimit`.
- `Keys::id32()` / `id64()` take no argument and return random hex (`random_bytes()`).
- CSRF tokens are `id.issued_at.mac` and expire after `OZ_CSRF_TOKEN_LIFETIME`; tokens issued before the upgrade are rejected.
- `GET /auth/link/:ref/:token` shows a confirmation page (`oz.auth.link.confirm.blate`); the authorization happens on the `POST` it submits to the same URL (route `oz:auth_link:confirm`).
- `DbStore` and `FileStore` sign their payloads with the app secret: entries written before the upgrade, or before `OZ_APP_SECRET` changes, read as misses.
- Requests from a disallowed origin are rejected by a `RequestHook` listener (`MainBootHookReceiver::onRequest()`), before routing, instead of once their handler ran; `onResponse()` only sets CORS headers.
- Behind a trusted proxy, the request URI takes its scheme (then port) from `Forwarded` `proto=` / `X-Forwarded-Proto` (`X-Forwarded-Port`): request URLs, redirects, the request's own CORS origin and the cookie `Secure` flag follow the client's https.
- The root scope logs to `.ozone/logs/ozone.root.log` instead of `ozone.log` in the working directory: `AbstractApp::getLogsDir()` returns `.ozone/logs/` like the other scopes (so does the default log dir of `oz jobs supervisor`). With no app running, logs go to the system temp dir.
- Behind a trusted proxy, the request URI also takes its host from `Forwarded` `host=` / `X-Forwarded-Host` (last value), like `Context::getHost()`, which now reads `Forwarded` `host=` too; a forwarded host must be a valid `host[:port]`, and the port then no longer comes from `SERVER_PORT`.
- `DigestAuth` locks an auth key after `OZ_AUTH_LOGIN_MAX_FAILURES` failed attempts within `OZ_AUTH_LOGIN_FAILURES_WINDOW` (`OZ_AUTH_TOO_MUCH_ATTEMPT`), through `LoginThrottle`.
- `REST\Traits\ApiDocManipulationTrait` is removed: its code moved to the `REST\ApiDoc\` builders (see Added). `ApiDoc` keeps all its public methods.
- A `RESTFulService` action set to `false` in `$available_actions` is no longer registered nor documented; it used to be both despite being disabled (`RESTFulService::isActionEnabled()`). Disable actions by redeclaring the property in the subclass: changing the inherited array changes every service.
- Digest auth only accepts nonces it issued (HMAC-signed issue time), for `OZ_AUTH_DIGEST_NONCE_LIFETIME` seconds (default 300), and each only once (RFC 2617: once per increasing `nc`), tracked in the new `oz:auth:digest:nonces` store. An expired nonce gets a new challenge with `stale=true`; a `username` without `:` is rejected.
- Unsafe requests (POST, PUT, PATCH, DELETE) authenticated by the session cookie need a CSRF token (`_csrf` field or `X-XSRF-TOKEN` header) unless the route or its group calls `->withoutCSRF()`; `OZ_CSRF_SESSION_DEFAULT` (`oz.request`) turns the default off. Bearer and API-key requests, and requests without the session cookie, are not concerned. Sessions hand the token to scripts in the `XSRF-TOKEN` cookie (`OZ_CSRF_COOKIE_NAME`), which axios and Angular send back on their own. CSRF failures now return the public code `OZ_CSRF_TOKEN_INVALID`.
- Garbage collection (expired sessions, auth entities, TempFS directories, cache entries) runs on 1 request in `OZ_GC_PROBABILITY` (`oz.gc`, default 100) instead of about 1 in 2 (and the cache collector on every request), and from the hourly `oz:gc` cron task. Collectors register with `GarbageCollector::register()`.
- `Random::bool($frequency)` returns true once in `$frequency` calls (it was about 50% whatever the argument); its default frequency is 2.
- Full log files are rotated (`ozone.root.log` -> `ozone.root.1.log`, ..., keeping `OZ_LOG_MAX_FILES`) instead of being truncated.
- Responses carry `X-Content-Type-Options: nosniff` and `Referrer-Policy: strict-origin-when-cross-origin` besides `X-Frame-Options`, all configurable in `OZ_SECURITY_HEADERS` (`oz.request`); a header set by the handler wins.
- English is the default language (`lang/oz.lang.list`), with `en` and `fr` enabled; every message code has an English and a French translation. `OZ_FILE_UPLOAD_FAIL` is now `OZ_FILE_UPLOAD_FAILS`, as the code uses.
- Guards and credential checks return public codes instead of a generic message: `OZ_ERROR_USER_IS_MISSING_REQUIRED_ROLE`, `OZ_ERROR_MISSING_ACCESS_RIGHTS`, `OZ_ERROR_USER_TYPE_NOT_ALLOWED`, `OZ_AUTH_INVALID_PROVIDER`, `OZ_AUTH_NOT_AUTHORIZED`, `OZ_AUTH_USER_UNVERIFIED`.
- The deprecated `user_name` column type alias is removed; use `username`.
- Framework classes no longer define `__destruct()`; subclasses calling `parent::__destruct()` must drop the call.
- `Rule::evaluate()` no longer traces rules reading an absent field.
- Exception data is only redacted in production mode (`ENV_MODE=production`) unless `OZ_REDACT_SENSITIVE_DATA` is set; `ENV_MODE` defaults to development. Generated projects get `ENV_MODE=development` in `.env`.
- `oz_files` has a new column, `file_scan_state` (`FS\Enums\FileScanState`, default `unscanned`): projects must create and run a migration (`oz migrations create`, `oz migrations run`).

### Added

- **Rights per role**: a role is given or refused access rights (`oz_role_rights`, edited live), and
  an auth user's rights are their roles' joined -- allowed where any of their roles allows -- then
  their own over them (`AccessRights::of()`, read by `SessionAuth` and `BasicAuth`, so every guard,
  CRUD check and `oz:access-rights.me` follows); a scoped authentication still caps them. A role
  holds what it was given and nothing of the lighter roles'. A super admin may do every action: the
  root wildcard `*` now stands for every action, a more specific rule still deciding over it.
  `GET /access-rights/roles` (`oz:access-rights.roles`, administrators) answers the roles and their
  weights, every registered action and each role's map, and whether the reader may change it;
  `POST /access-rights/roles` (`oz:access-rights.roles.update`, **super admins only**) gives a role
  `allow_actions` and refuses it `deny_actions`, registered actions or wildcards over them.
- **A bulk create**: `POST /{service}/bulk` (`RESTFulAction::CREATE_BULK`) creates `items`, one form
  each, all or none, through gobl's `addItems()`; a refused row is located at
  `items.{position}.{field}`. `{morph type}.create_bulk` is administrators only in every
  `BaseHandler` by default (a handler's own rule kept, before its constructor or after). At most
  `OZ_DB_CREATE_BULK_MAX_ITEMS` (`oz.db`, 100; `0` or `null` for no limit) rows, on the route and
  through relations alike.
- **A project may have no `oz_users`**: `OZ_AUTH_USER_DEFAULT_TABLE` (`oz.auth.users`, `true` by
  default) set to `false` leaves the default auth user table out of the schema and its `user` type
  out of `oz.auth.users.repositories` (`AuthUsers::repositoryClass()`), for a project whose auth
  users are all of its own `makeAuthUserTable()` tables; a migration then drops the table, reviewed
  as any other. **The default auth user type is a setting**, `OZ_AUTH_USER_DEFAULT_TYPE` (`user` by
  default, marked for the client): sign-up, `oz auth-users add|grant`, the email and phone columns'
  "already registered" check and a verification naming no type read it through
  `AuthUsersRepository::defaultType()`, which refuses a type no repository serves (the schema's build
  included) and names `OZ_AUTH_USER_DEFAULT_TABLE` when it left `user` out. `oz doctor` says what
  the default type is and fails one with no `makeAuthUserTable()` table; `oz auth-users` still
  registers when it is wrong, so the doctor can say so.
- **An auth user's picture has a route of its own, `oz:auth-user-picture`**
  (`GET /auth-users/{type}/{id}/picture[/{tokens}]`, `Auth\Views\AuthUserPictureView`, in every
  scope as `oz:files` is), for **any auth user type**: the owner is found through its type's
  repository by id, and its picture through `AuthUserPictureInterface::getAuthUserPicture()`, which
  every entity of a `makeAuthUserTable()` table implements (its `pic`); a type whose entity does not
  has no picture. No file key is ever in its address: whether one may see another's picture is the
  project's decision, made per request by the class `OZ_AUTH_USER_PICTURE_ACCESS` (`oz.auth.users`)
  names, an `AuthUserPictureAccessInterface` taking an `AuthUserInterface`.
  `SelfOrAdminPictureAccess` (the auth user themself, the same type and id, and administrators) is
  the default; `SignedInPictureAccess` and `PublicPictureAccess` ship too. The picture is served
  through the filter chain of `oz:files` (the same bytes for the same tokens, a project's own
  `OZ_IMAGE_TOKENS` included, its metadata always stripped), never as its original (`original` is
  refused, to the owner too). `OZ_AUTH_USER_PICTURE_TOKENS` are rendered first and the tokens asked
  for on what they gave, so a size cap or a crop holds whatever a link asks. A viewer the rule
  refuses gets 403 and an allowed one asking for an auth user with no picture 404 -- a type no
  repository serves, an id no auth user of it has, or a deleted one being a refusal -- unless
  `OZ_AUTH_USER_PICTURE_FALLBACK` (one value, or `refused` and `missing` apart) names an image file
  or an `AuthUserPictureFallbackInterface` class drawing one (an `AuthUserPictureImage`: a path, or
  bytes and their type), rendered as a picture is. The answer is `Cache-Control: private, no-cache`
  with an `ETag` from the file and the tokens, so a browser revalidates it (304) and a viewer who
  loses the right stops seeing it. `ImageFileFilterHandler::render()` renders an image that is no
  stored file through the same tokens and renditions, and `GetFilesView::ORIGINAL_TOKEN` /
  `FILTERS_PARAM_PATTERN` name what both routes share.
- **Sign-up and `oz auth-users add` take an auth user type**, `user` by default. `POST /signup`
  reads `auth_user_type` and signs up only a type `OZ_AUTH_USER_SIGN_UP_TYPES` (`oz.auth.users`,
  `['user']` by default) lists and whose table `makeAuthUserTable()` made, since sign-up is public
  and a listed type is one anybody may create an account of; its form is the standard columns by
  their short names, and a value for a column the chosen table lacks is refused. `oz auth-users add
  --type` offers the same standard columns by short name (`--email`, not `--user_email`), mapped to
  the chosen type's table. `Utils::buildTableCliOptions()` takes `$short_names` for that.
- **A form discovery of a rate-limited route is counted against a limit of its own**, per IP and per
  route: `OZ_FORM_DISCOVERY_IP_RATE` in `OZ_FORM_DISCOVERY_IP_INTERVAL` (`oz.forms`, 120 a minute by
  default; 0 counts none), never against the route's, and reported in the `X-RateLimit-*` headers of
  the discovery. `IPRateLimit` takes a `$kind`, which keeps such a count apart from the route's.

- **`Process::php($args, $cwd, $env, ...)` starts a PHP process** -- with `Process::phpBinary()`,
  and, from a process that serves requests, in a clean environment -- and is how every PHP process
  OZone starts is built: an async job, a scope's build, `oz project serve`, the test kit's. A
  plugin starting a PHP process of its own uses it too.
- **`--php` names the PHP command line of a deploy's host** (`oz deploy run`, and for the units it
  writes, `oz deploy init --target=bare` and `oz jobs supervisor`). Without it, `oz deploy run` asks
  the host (`Process::hostPhpBinary()`).

- **`OZ_PHP_CLI_BINARY` (`oz.config`, or the `.env`) names the PHP command-line binary OZone starts
  a PHP process with**, and `Cli\Process::phpBinary()` is the one place that binary is decided: an
  async job's `oz jobs run`, a scope's build (`oz project build`), `oz project serve`'s `php -S`,
  and the test kit's `Sandbox` and `OZTestProject` processes all start with it, and so can a
  plugin's own PHP process. Null by default: `PHP_BINARY` where this process is the command line
  (the `cli` SAPI, or the built-in server, a mode of the same executable), else a `php` beside
  `PHP_BINARY`, in `PHP_BINDIR` or on PATH, this version's own name first in each (`php8.5`,
  `php85`, then `php`), never this process's own binary. Nothing is run to find out. A setting that
  is not the absolute path of an executable file is an error, never a reason to guess, and so is
  finding nothing; both name the setting (`suspectConfig()`). It sits in `oz.config` because that
  group cannot be written at runtime (`Settings::set()` refuses it): the value is a program OZone
  executes. `oz doctor check` shows the binary resolved in a `PHP CLI` row, with
  `(OZ_PHP_CLI_BINARY)` when the setting chose it, and fails that row on an unusable setting.
  `Process::which()` is the PATH lookup `oz doctor check` already did, now public.

- **A route of any scope of the project can be called as an anonymous visitor, in process**:
  `Context::callAnonymously($route_name, $params, $query, $scope, $language)` (through
  `Navigator::callAnonymously()`), from a request, a command or a job. It answers the `Response` a
  visitor with no credentials would get, unsent, `ResponseHook` headers included: a GET built from
  the route's name alone, from `127.0.0.1`, to the scope's `OZ_DEFAULT_ORIGIN`, with no cookie, no
  `Authorization`, no `Origin`, no forwarding header and an empty body, `Accept: application/json`
  for an API scope and `Accept-Language` only when a language is given. It is a request of its own
  and not a sub-request (`callRoute()` keeps the visitor's identity, body and language): a new root
  context, nothing of the caller's authentication, internal routes refused, the scope's maintenance,
  rate limits (counted on `127.0.0.1`) and `RequestHook` / `RouteFound` / `RouteBeforeRun` as for any
  visitor. No session is written, `FinishHook` and `EndRequestHook` are not dispatched for it, a
  redirection comes back as a 3xx, an error as its status, and the caller goes on whatever the
  runtime, the console included. Refused inside an open transaction of the process's connection (it
  would read rows no visitor can see). The same call made again inside itself is refused as a cycle,
  naming the calls in flight; calls that differ are bounded by `OZ_ANONYMOUS_CALL_MAX_DEPTH`
  (`oz.request`, 16).
  Another scope is put in use for the call by `OZone::inScope()` (`@internal`), which runs a function
  with the scope in use and puts the caller's back whatever happens: what `scope()` answers, the
  scope's asset sources, its routers with their providers and route tables (in production the
  scope's own table), and its settings -- the same sources with the scope's own directories in place
  of the served scope's. A scope's settings and routers are put together the first time and kept, so
  a switch back and forth is a few array moves and `Settings::get()` is untouched; a worker keeps them
  for its life. The groups a process reads once and wires for good (`oz.boot`, `oz.plugins`, `oz.db`
  and `oz.db.*`, `oz.gobl.crud`, `oz.stores` and `oz.stores.*`, `oz.logs`) are compared the first
  time, and a scope declaring one of them otherwise than the served scope is refused, naming the
  group; a scope asked for while its settings are being put together is refused, naming it.
  Entering a scope also makes the project namespace read its `scopes/{name}/` directory, as its
  entry point does, so a class only that scope uses -- a route provider its settings enable -- is
  found; additively and for the rest of the process, since PHP cannot unload a class, and
  `oz doctor check` warns (`Scope classes`) of a class two scopes declare, of which the first loaded
  wins. The database connection, plugins, listeners, the class loader's other namespaces, runtime,
  log writer and trusted proxies are never swapped. A **settings-only** switch
  (`inScope($scope, $fn, true)`) is for a caller that reads the scope's settings and does nothing
  else: its settings and classes are in use, its assets and routers are not, the groups wired for
  good are not compared (a read wires nothing), and no other switch may run inside it, an anonymous
  call included; settings put together for a read are compared the first time a call enters them.
  `DeployInitializer::serverName()` reads a scope's `OZ_DEFAULT_ORIGIN` that way, as the scope
  resolves it, where it read the scope's settings files itself: a scope with logs or plugins of its
  own still gets its vhost from `oz deploy init --target=bare`, a scope that declares no origin now
  gets the one it would answer with (the application's), and a stateful value wins over the source
  file's.
- **A scope records whether it serves the API or the web**, in `scopes/{name}/scope.json`
  (`{"kind": "api"}` or `{"kind": "web"}`, `Scopes\ScopeKind`), written by `oz scopes add` (and so
  `oz project create`) and read by what needs the kind of a scope it does not serve: the build of
  each scope (`scope_build.php`, which searched the scope's `index.php` for
  `OZ_OZONE_IS_WEB_CONTEXT`) and the anonymous call. `oz project link` writes the record of a scope
  made before it, from what its entry point declares, which every deploy and clone already runs
  before `oz project build`; a build of a scope without a record fails, naming `oz project link`.
  The record is the project's to commit.
- **`OZ_PUBLIC_FILES_BASE_URL` (`oz.files`) says where the web server exposes the public files
  directory**, for the direct links of `PublicLocalStorage`: an absolute http(s) URL (a subdomain, a
  CDN) or a path read from the root of the host being served, ending with `/`. No default, since
  nothing exposes the directory until the web server is told to; a value of another shape is refused
  naming the setting. `oz doctor check` has a `Public files` row: the base direct links use, or a
  failure when direct access is on without a usable one, whose fix names
  `https://<root host>/static/` where `oz deploy init --target=bare` wrote the root scope's vhost
  (`DeployInitializer::serverName()` and `nginxVhostPath()`, public now).
- **Maintenance mode**: `oz maintenance on|off|status` holds the requests of every scope, of chosen
  scopes (`--scopes=`), or of the API or the web scopes only (`--only=api|web`), and serves the rest.
  The switch is a file, `data/root/state/maintenance.json`, and not a setting, so turning it on
  compiles nothing: every worker sees it on its next request, it survives a restart, and every
  instance sharing `data/` is held with the others; while it is off, a request pays one stat. A held
  request gets a 503, with `Retry-After` when `--retry-after=` gave one and `Cache-Control:
  no-store`: an API request (and a web one asking for JSON only) in the envelope of every answer,
  message `OZ_MAINTENANCE`, `data` holding `retry_after` and `message`; a web page the maintenance
  page, `oz.maintenance.blate` on the page layout, which a project overrides as any template and
  which shows `--message=`. Who gets through: the addresses and CIDR ranges of `--allow=`, the
  client address read through `oz.proxies`; a browser that followed the secret link `on` prints
  once (`?oz_maintenance=<secret>`, kept only as its hash), which is sent back to the same path
  without the secret and given a cookie signed with the project secret and bound to that run, so
  the next `on` closes it; and a signed-in administrator (admin, or a role weighing at least as
  much), as the matched route's own authentication says -- a check that fails closed, so a
  session, a user or roles that cannot be read while a migration runs keep the request held, a
  scoped authentication grants nothing, and a refused request writes nothing of its session back.
  Whatever else stops a held request -- no route, a project's answer to an unknown path, an origin
  refused -- it is answered as maintenance. The `oz:cron` route is served, with its own key, so
  cron keeps running; `--pause-jobs` holds cron and the queued jobs instead: a tick is skipped
  without checking in (the first one after `off` catches up the minutes it missed, an hour back at
  most), a run takes no job and reclaims none, the jobs stay pending and unlocked, and a request
  collects no garbage. A record that cannot be read holds every scope, lets only administrators
  through and pauses the jobs, since the file exists only because someone turned maintenance on.
  `oz doctor check` warns while a maintenance is on (since when, what it holds, whether the jobs
  wait) and fails on a record it cannot read. `Http\IpRange` is the address and range matching
  `TrustedProxies` used privately, now shared.
  A held request dispatches `RouteFound` only once its route's authentication has let it through,
  so no project listener runs on a request answered 503; it still comes before the guards. Forced
  and manual runs wait for a pause too: `JobsManager::forceRunJob()` throws and `Cron::start()`
  throws while the jobs are paused, unless given `$despite_maintenance` (`oz jobs run --job=
  --despite-maintenance`, `oz cron start --force`), and `runJob()` takes the same argument. A job
  handed off to a process of its own before the pause is given back, pending, unlocked and untried
  (`JobsManager::runHandedOff()`, which the hand-off reaches through `oz jobs run --handoff`):
  its work had not begun, and left locked it would be reclaimed as stale and charged a failed try.

- **`oz deploy run --maintenance [--retry-after=N]`** holds the project under maintenance, jobs
  paused, from just before the migrations (`maintenance-on`, run from the new release; the file is
  in the shared `data/`, so the live release is held) to after go-live and the restart
  (`maintenance-off`), before the health check, which reaches the release as anyone does. It is
  refused, before anything is created, when the live release's `oz` cannot answer
  `maintenance status` (an OZone that predates maintenance would go on serving), and a step that
  fails in between leaves maintenance on, the failure saying so: what failed may be the migration.

- **Routing by host**: `host()` restricts a route, or a group with its routes, to the requests made
  to a host, inherited down the group tree with the innermost declaration winning. The host is a
  template in the syntax of a path (`{tenant}.example.com`), its params declared with `param()` and
  read with `RouteInfo::param()` like path ones; a host param with no declared pattern matches one
  label (`[^.]+`), and a host param may not take the name of a path param of the same route. Hosts
  compare case-insensitively. A route that declares no host matches any host, as before, and among
  routes of one priority a literal host is tried first, then a host with a part, then a route of
  every host, as a static path comes before a dynamic one: `www.example.com` is not shadowed by
  `{tenant}.example.com`, nor the same path on one host by a route of every host. `Router::find()` takes the request host, the compiled route
  table carries each route's host pattern (its format is bumped, so a table saved before is
  ignored), and `Context::buildRouteUri()` (and so `route_uri()`) answers a URI on the route's host,
  with the request's scheme and port, when it is not the host being served; a host param not given
  is taken from the host being served when the route matches it, and a value that would build a
  host the route does not match, or move the URI's authority, is refused. `redirectRoute()` sends
  the client to the route's host with no entry in `OZ_REDIRECT_ALLOWED_HOSTS`, since the host is
  built from the route's own template, declared in code; a `redirect()` to a URL keeps the check.
  `oz routes export` lists each route's `host` and its params, and the API doc gives the route's
  operations a `servers` entry on the host template, one server variable per host param: its
  default is the value of the host serving the document when the route matches it, else the
  param's name as a placeholder, and its description gives the param's pattern, which a server
  variable cannot declare.

- **A role guard compares by a strategy**: `withRoles(RoleStrategy $strategy, ?RoleInterface
  $at_least, RoleInterface ...$roles)` -- any of the roles, all of them, or at least the weight of
  the lightest (`RoleStrategy::GTE`, "an editor or above") -- and a user whose role weighs at least
  `$at_least` passes whatever the list (`super-admin` for "the role that may do everything is not
  stopped by a list it is not on"; null for a rule about a role rather than about power).
  `Roles::satisfies()` is the check, `UserRoleRouteGuard` carries the strategy and the role through
  a compiled route table, and `withRole()` and `withRoleOrAdmin()` mean what they did.

- **A plugin adds its own checks to `oz doctor check`**: the doctor dispatches `DoctorCheckHook`
  after its own project checks, and a listener adds rows of the same shape (`add($name, $status,
  $detail, $fix)`, status `ok`, `warn` or `fail`), reported and counted with OZone's, a failure
  failing the run. Each listener runs on its own: one that throws is a failed check named after it
  (`Class::method`), and the others still report.

- **Resources kept with their history** (`Revisions\Revisions`, table `oz_revisions`). A resource
  lives under a namespace its owner names and a key; every write is a row, never updated or deleted,
  and the current content is the latest one. The content is text, as written -- a format telling
  `{}` from `[]` at any depth reads back what it wrote, which a PHP array cannot hold -- digested
  `sha256:` over its bytes. Null content is a revision too, "nothing stored for this key", so a
  reset stays in the history, and `restore()` writes a past content again as the
  next revision. A write names the revision it was based on and is refused with the current one
  when that moved (`RevisionWrite`, the rule of `StoreWrite`); two writers based on one revision
  both ask for the next number and the table's unique key keeps one. `currentAll()` answers the
  current revision of every key, `version()` what a cache of them is keyed on, each revision keeps
  its author, note, digest and the owner's own `meta`, and `RevisionWritten` is
  dispatched with values only. `oz revisions history` and `oz revisions restore` (`--json`) are for
  an operator; who may write what is the owner's to guard.

- **Seeders, and `oz db seed`.** A seeder (`Seeders\Interfaces\SeederInterface`) writes the rows that
  are missing and answers how many; listed in `oz.seeders` (class => enabled), so a plugin ships its
  own in its settings and a disabled one seeds nothing. **Reference** seeders run on every
  `oz db seed`, **demo** ones (`isDemo()`) only on `oz db seed --demo`, which a production deploy
  never runs. Each runs in a transaction of its own. The rule a seeder follows is one helper,
  `Seeders::insertMissing($table, $key, $rows)`: a row whose key no row has yet is inserted through
  the table's entity, so its CRUD rules run, and a row that exists is never updated, so a re-run
  leaves an administrator's edit as it found it. `--json` reports what each seeder wrote.
- **`oz plugins`**: `list` shows the plugins `oz.plugins` lists, enabled ones in the order they boot,
  with what each requires; `describe --plugin=<class or package>` says what enabling one adds -- its
  identity, the plugins it requires, every settings group it ships with its keys (routes, commands,
  cron jobs, CRUD rules, texts) and its tables, run into a database of its own in memory -- read
  without booting it (`Plugins::describe()`, `SettingsSchema::keysIn()`). Both take `--json`. What
  a plugin ships that another plugin defines is that plugin's to read: `describe` triggers
  `PluginDescribeHook`, and a listener adds a section of its own (`addSection()`, names by label,
  one listener per section), listed under `sections`.
- **A form field built from a foreign column says which relation it holds the key of.** Every field
  of a form bundle carries `relation`: `{name, entity, host, candidates}` for a column that is the
  key of a relation on its own table -- the relation, its target's and the form's table as morph
  types, and whether it offers candidates -- else `null`. `Form::fromTable()` names, of the
  relations whose key is the column, the one offering candidates when exactly one does, else the
  only one, else none. A client draws its picker from it: the candidates route of the host's
  service, or the target's list.
- **A morph pair is described for one control.** In a form built from a table, the key of a morph
  pair carries `morph: {type_field, host, targets}` -- the form's table as a morph type, whose
  relations each target's candidates are asked through, and one target per morph relation over the
  pair, `{type, entity, relation, candidates}` -- and its type field carries `morph_of`, the key's
  name. The shapes are named once on `Field` (`FieldRelation`, `FieldMorph`).
  Both stay fields, validated and submitted as before; a client hides the type and fills it from
  the key's control: the type first, then a record of it.
- **The texts of the tables are translatable, under keys derived from their names**
  (`Lang\EntityTexts`). Each table has `ENTITY.<TYPE>.SINGULAR`, `.PLURAL` and `.DESCRIPTION`, and
  each column a client can read `ENTITY.<TYPE>.COLUMN.<NAME>.LABEL`, `.DESCRIPTION` and `.HELP`: the
  morph type and the column's short name upper-cased, held nested in a catalog so one table's texts
  sit together. `oz db build` writes the keys the default language's catalog misses into the
  project's own (`app/settings/lang/oz.<lang>.php`), for the tables of the namespaces it builds and
  as their migrations have installed them: the schema's text where it has one (`field.label`,
  `field.description`, `field.help`, a table's `api.doc.description`), else the name made readable
  for a label and a table's names (`first_name` -> `First name`); a description or a help nobody
  wrote gets no key. What a translator changed is never written again, and `--json` says what was
  written under `texts`. `Form::fromTable()` labels a field by the derived key where the catalog
  holds it, a key the column's meta names winning, and by the schema's text otherwise, so a project
  not rebuilt since reads what it did. Also `Polyglot::hasText()` and `Settings::setMany()`, which
  writes several keys of a group with one write of its file.
  A table also has `ENTITY.<TYPE>.CHOOSE`, what a picker of its records says before one is picked
  ("Choose a client"): a sentence per language, since an article follows the noun, scaffolded as
  "Choose" and the readable singular for a translator to finish. **OZone ships the texts of its own
  tables in English and French**, written for readers rather than from the schema, so a project's
  build writes none of them; `tests/Lang/EntityTextsTest` fails on any it misses in either
  language. `Polyglot::textsOf()` is public: a catalog's texts by full key, its groups flattened.

- **Writing a row's relatives in its request covers linking and removing as gobl now does.** An entry
  of a relation field carrying `_attach` links an existing record, on `POST` as on `PATCH`; `_delete`
  removes as the relation declares (gobl unlinks by default); both markers are taken off before the
  entry is read as a row, and an entry carrying both is refused (`OZ_RELATION_ENTRY_ATTACH_OR_DELETE`).
  A relation's own refusal (`GOBL_ORM_RELATION_*`: a field the relation fixes, a record that is not
  a candidate, a link that cannot be removed, ...) reaches the client as itself with the `relation`
  and the `field`, translated, instead of `OZ_RELATION_PROCESSING_FAILED`.
- **`get_candidates`**: `GET {path}/relations/{relation}/candidates` answers the records a relation
  declaring candidates offers, for the `host` query object -- the stored row under the values given
  when it names the key, else a row not saved yet (`OZ_CANDIDATES_HOST_INVALID` for a field the
  table does not have). Searched and paged like a list, read with the target's read rights, and
  documented per relation. Registered only for a table with such a relation. `oz db build` types the
  generated candidates contexts with `Context`, and boot hands the ORM the current one, so a
  provider decides, and a write is checked, with the request's context. `routeName()` names any
  action of the enum, so a service that redeclares `$available_actions` with a list written before
  `get_candidates` existed keeps working: the action is off for it, not unknown. The services export
  lists `get_candidates` only for a table that serves it.


- **`tnet` answers named sections** (`oz.tnet.sections`, a name to a `TNetSectionInterface`), all of
  them or those `GET /tnet?sections=a,b` names, under a new `sections` key. A section that needs a
  signed-in user is not built for nobody, and one that throws is left out and logged, never failing
  the heartbeat. `tnet` is now sent with `Cache-Control: no-store`, being per user.
- **The `reader` section**: the signed-in identity's type and id, its roles as the request decides
  with them (none under a scoped auth), and the weights of the project's role enum, so a client can
  decide "this role or above" without a round trip. A project that must not show its role hierarchy
  to signed-in users removes it (`'reader' => null`) or replaces it.

- **A client can be told what each REST service answers.** `oz services export --json` lists the
  enabled `RESTFulService`s of the API router: each one's stable name, the **morph type** of the
  entity it answers, its table, the **full name** of its key column and the actions it really
  serves (`REST\ServiceExport::of()`). The twin of `oz routes export`, which says where a service
  is called and nothing about what comes back -- so a client that had a name and a path can now
  join a service to the shapes Gobl generates for that table and type the rows it draws.
  Read off the declarations and never off the registered routes: a service declares its name, its
  table and its key column as constants and may turn actions off, which is the answer, where
  reading it back out of the routes would be guessing at the declaration from what it produced.
  The key column travels by its **full** name (`event_id`) and not the short one a service declares
  (`id`), because a row carries the full one and reading an id off a row is the whole of what a
  client does with it.

- **`oz db ts-bundle --shapes`** writes each table's shape beside its generated entity: what a row
  is, what writing one takes, what identifies one, and what may be read beside it. Gobl has written
  these for a release and nothing called for them, so no project had them. The files import a type
  from `@oweb-io/schema`, which is why they are off unless asked.

- **A store entry has a version, a write can be conditional on it, and a client can read what
  changed.** `oz_db_stores` gains a `version` column (unsigned bigint, default 0), which **needs a
  migration in a project that already has the table**. Every entry now carries the microsecond it
  was written (`StoreEntry::$version`, `StoreEntry::nextVersion()`), `KeyValueStore::setIf()` and
  `deleteIf()` write only where the version is still the one the caller read, and
  `changedSince()` answers what moved since a version, oldest first. `StoreCapabilities` gains
  `versioned` and `changes`, and `StoreDriverInterface` gains `setIf()`, `deleteIf()`,
  `changedSince()` and `pruneRemoved()` -- a **breaking change for a project with a driver of its
  own**, which must implement the four (answering `StoreWrite::refused()` / `[]` / `0` where it
  keeps no version is a faithful implementation, and `KeyValueStore` refuses before reaching it
  when its capabilities say so).

  **The version is the clock, which is one choice answering two questions.** A conditional write
  needs a token to compare and a client reading changes needs a cursor; the microsecond an entry was
  written is both, so there is one column and nothing to keep in step. A counter would need an
  allocation of its own, and a sequence allocated apart from the write it belongs to can be read by
  a client in between -- a change it would then never see, which is the one failure a sync must not
  have. Two writes to one key cannot share a version, since the second reads what the first wrote
  and a store read costs far more than a microsecond; two keys can, so a client asks from slightly
  before its cursor and takes a repeat, which costs nothing.

  **Removals are kept only where a store asks for it** (`'changes' => true` in its options): such a
  store soft-deletes instead of deleting and marks every key on `clear()`, so a client is told what
  it no longer has rather than keeping it forever, and `pruneRemoved()` forgets removals older than
  a boundary the caller picks (`DbStore::CHANGES_RETENTION`, 30 days). A cache has no use for those
  rows, which is why it is off by default. `DbStore` and `RedisStore` keep a version;
  `MemoryStore` and `FileStore` keep one and answer conditional writes under their own lock;
  `MemcachedStore` keeps none and says so, because an entry it can evict between a read and the
  write conditional on it is not something two sides can share.

- **The settings are exportable as declarations, and a key can say a browser may read it.**
  `oz settings export` answers every key OZone declares with its type, its default, its
  documentation and whether it is for the client; `--json` gives one object for a tool, and
  `--client` answers instead the keys marked for a browser, with the values this project resolves.
  `App\SettingsSchema` is what reads them.

  **Read from the files as text**, because that is where the documentation is. A settings file
  documents each key with a block comment above it, inside the array it returns, so the comment
  belongs to no declaration and nothing reflective reaches it: `token_get_all()` pairs each comment
  with the element after it. A group whose keys are expressions -- the authentication methods by
  scheme, a provider by its `NAME` -- is a map and not a schema, and is listed by name with its file
  never run.

  **A default is answered only when answering it is safe.** Running a file resolves `1000 * 1000` and
  a class constant, and resolves `env()` with it: on a configured machine that is the deployment's
  secret (`OZ_CRON_WEB_KEY`, the MinIO keys). A key whose declaration reads the environment is
  answered with its source text and `from_env`, never with the value it holds here. The schema reads
  OZone's own source directories alone, so a project's file is never run for it.

  **`@client` in the key's own comment** is how a key says a browser may read it, marked beside what
  it describes rather than in a list to be held in step -- the shape that let a hand-copied list of
  OZone's settings drift from the real key names in a front end, so an override never reached it.
  A project marks its own keys in its own files. Sixteen of OZone's are marked: the header and cookie
  names a client has to agree with the server on (`OZ_AUTH_API_KEY_HEADER_NAME`,
  `OZ_CSRF_COOKIE_NAME`, the form discovery and resume headers, the real-method header),
  the upload limits a client needs to refuse a file before sending it, the rendition sizes it may ask
  for, and `OZ_AUTH_CODE_LENGTH`. **A key marked for the client and read from the environment is an
  error and not an entry**: a secret marked by accident would reach every browser, and a value a
  browser is meant to have belongs written in the file.

- **A response can carry a content security policy, and it is declared as directives.**
  `OZ_CONTENT_SECURITY_POLICY` (`oz.request`) maps a directive name to the sources it allows, and
  `MainBootHookReceiver::onResponse()` sends what it composes as `Content-Security-Policy`, unless
  the handler set that header itself or `OZ_SECURITY_HEADERS` carries it written out. The new
  `Http\ContentSecurityPolicy` reads it, reads a header back (`fromHeader()`), answers what a
  directive allows through the fallback chain CSP level 3 defines (`sourcesOf()`, `allows()`), and
  grants a page what it needs beyond the policy (`with()`).

  **Directives, and not the string they become**, because that is what layers: a settings map merges
  key by key, so a plugin, a scope or a project tightens `img-src` alone and keeps every other
  directive the sources before it declared, where a policy written as one string can only be
  restated whole -- and restating it is how a directive gets dropped by accident. A source that
  would rather add to the lists than replace them says `Settings::MERGE_APPEND`.

  **`with()` is why this is a class and not an `implode()`.** A directive nobody wrote is answered
  by the one it falls back to, so granting `'unsafe-inline'` for styles to a policy of
  `default-src 'self'` has to send `style-src 'self' 'unsafe-inline'`: writing the grant on its own
  would take `'self'` away from every stylesheet the page loads. `'none'` makes way for a real
  source, a list holding both being one a browser refuses to read, and a directive nothing answers
  for is left alone -- a policy that does not restrict it cannot be loosened, and writing the grant
  there is the one way a grant ends up restricting the page it was granted to.

  **Shipped empty, so nothing changes for a project that says nothing.** Which origins a page may
  load is a fact about a project and not about a framework: a policy sent by default is wrong for
  every project that loads a font, a map, a payment widget or an analytics script, and a default
  that breaks a working page is one a project switches off rather than tightens.
  `ContentSecurityPolicy::STRICT` is the policy to start from, in one line
  (`'OZ_CONTENT_SECURITY_POLICY' => ContentSecurityPolicy::STRICT`), and widen for what the project
  really loads. It allows `'unsafe-inline'` for styles, every HTML page OZone serves inlining its
  stylesheet into the layout and a style attribute being unable to carry a nonce, and nothing for
  script beyond `'self'`, script being what an injected value needs in order to run.

  The API doc view sends a policy of its own, `OZ_API_DOC_CSP` (`oz.api.doc`), because it is the one
  page here that third-party code renders: ReDoc comes from a CDN, boots from an inline script,
  draws through a blob worker and takes its fonts from Google. It is exempted rather than given a
  policy guessed at for code that is not ours, so switching a project's policy on cannot break it.

- **A username can be given to a user.** `oz_usernames` is the one place a username is unique across
  every auth user repository a project registers, and `users.name` has a foreign key to it -- but
  nothing ever wrote to that table: `UsernameUtils` only read it, so the column could point at
  nothing and no user could hold a username at all. `UsernameUtils::claim(string $name,
  AuthUserInterface $user)` takes a free name for an auth user of any repository (the row records the
  holder as a morph) and answers whether it got it. The primary key is what decides rather than the
  check above it: two requests claiming one name both read it as free, and the one the database
  refuses is told the name is taken instead of raising. A name that was held and released is kept,
  soft-deleted rather than removed, and is not handed to anyone else -- recycling a freed name lets
  one user be addressed by what another was known by, so it is a project's decision and not a
  default. Setting the name on the user's own entity stays the caller's: OZone knows what a username
  is and deliberately not what holds it.

- **A request can search text.** Gobl gained two operators, `matches` and `starts_with`, so a REST
  request's `filters` carries them and the API documentation lists them. They take a **term** and
  not a pattern -- `%` and `_` are searched for as themselves, an empty term is refused -- which is
  the difference from `like`, and they fold case and, where the engine can, accents. A column
  declared `searchable()` in a project's schema is searched through an index; nothing here needs
  declaring for that.

- **The suite fails on a deprecation, a notice and a risky test.** It carried `failOnWarning` and
  `failOnRisky` only, so a deprecation or a notice passed unseen -- and PHP 8.5 deprecates a null
  array offset in every form, `isset()`, `??` and `empty()` included, which is exactly what a suite
  has to fail on to find. `failOnNotice` and `failOnDeprecation` are on, with the details displayed
  and `restrictNotices` / `restrictWarnings` on the sources, and the first run of it found the
  ClamAV notice below. Three `@psalm-suppress PossiblyUnusedParam` went with it: the shared analysis
  levels suppress that issue for every package, so they did nothing. The other 21 suppressions here
  were each checked by removing them and running psalm: all 21 are load-bearing.

- **The shared coding standard writes the 8.5 syntax.** The floor moved to PHP 8.5 and the fixer
  carried no version migration set, so each file kept the syntax of the year it was written:
  `$x::class` over `get_class($x)`, `0o755` over `0755`, an arrow function over a closure that only
  returns, a heredoc indented with its code. Twenty-one files here.

- **The PHP 8.3 the framework requires is now used.** `#[\SensitiveParameter]` marks every parameter that carries a secret -- `Password::hash()` / `verify()` / `resizePass()`, `DoCrypt::encrypt()` / `decrypt()` and their interface, `AuthUsers::tryLogIn()` / `checkPassword()`, `TOTP::hotp()` / `verify()`, `AuthorizationProvider::hash()`, `AuthorizationCredentials::setCode()` / `setToken()` and their interface, `AuthUserInterface::setAuthPassword()` and its implementations, `Uri::withUserInfo()`, `PasswordProtectedRouteGuard` -- so a stack trace, a log of one or `debug_print_backtrace()` prints `Object(SensitiveParameterValue)` where it used to print the password. `Random::string()` draws the whole string with `Random\Randomizer::getBytesFromString()` instead of one `random_int()` per character, which is what every session ID, CSRF token, store key and generated file name is made of: 6 times faster at 16 characters, 10 times at 64, measured in one process over the same alphabet. Every class constant that a value types beyond doubt is typed (`private const array LEVELS`, `public const string TABLE_NAME`, ...), which says what a constant is and refuses a subclass that would redeclare it as something else. The value objects that are final, parentless and hold nothing but readonly properties are `readonly` classes (`StoreEntry`, `StoreCapabilities`, `KeyValueStore`, `RenderedMessage`, `Channel`, `ImageInfo`, `ImageRecipe`, `RenderedImage`, the `S3*` request/response/signer/client, `ResolvedRouteOptions`, `RuleViolation`, `FormSessionStep`, `FormSessionManager`, `MessageNode`, `ProvisionStep`, `SshHost`, `Provisioner`, `Releaser`, `DeployFile`, `DeployInitializer`, `DbTestConfig`, `ClamAVScanner`, `ResponseBuilder`): the class answers for its immutability, and a dynamic property on one is now an error. A class a project may extend is left alone, since `readonly` would be forced on the child.
- The `VERSION` file says `ozone v3.0.0`, the version `OZ_OZONE_VERSION` and the `dev-main` branch alias have carried for a while. **Not a release**: no tag is cut, `CHANGELOG.md` keeps its `Unreleased` section, and `dev-main` stays aliased to `3.x-dev` so a project installing the development source is untouched.

- **`OZ_AUTH_SIGN_IN_ROUTE`** (`oz.auth`, empty by default): the name of the project's sign-in route, which the pages ending an authorization link now link to -- the one confirming a link (`oz.auth.link.view.blate`) and the one confirming a new password (`oz.auth.link.recovery.blate`). The owner who had just set a new password was told to sign in with no way to do it, and a project could only add one by overriding the whole template, which then drifts from OZone's. Left empty, nothing changes. It is a route name, built with the project's own router, never a URL a request could carry; a name the router of the serving scope does not know, or a route taking parameters, is logged and shows no link, since the page is reached once the authorization is already spent. The label is `OZ_VIEW_AUTH_LINK_SIGN_IN_BTN`, and the done branches get `oz_sign_in_uri`. The confirmation page says `OZ_VIEW_AUTH_LINK_DONE_SIGN_IN` ("You can now sign in.") when it carries the link, instead of `OZ_VIEW_AUTH_LINK_DONE_MESSAGE` ("close this window and go back to where you started"), which it still says without one.
- **`OZ_DB_GENERATED_TIMESTAMP`** (`oz.db`, true by default): false leaves the generation time out of the headers of what `oz db build`, `oz db ts-bundle`, `oz db dart-bundle` and `oz db generate` write, so a regeneration of an unchanged schema rewrites nothing. Needs the Gobl that has `Gobl::enableGeneratedAtDate()`.
- **`oz db build`, `oz db ts-bundle` and `oz db dart-bundle` name the tables they left out.** On an installed database the schema is the installed migration's, so a table added to the code since gets no class until its migration has run; the commands reported success all the same. They now warn with the table names and what to run (`oz migrations run`, then generate again), and `oz db build --json` lists them under `not_installed`.
- `OZTestProject::startServer($scope, $host, $workers)`: the number of requests the test server answers at once (`PHP_CLI_SERVER_WORKERS`), to test what concurrent requests do.
- A `date` filter in the message syntax: `{d | date}`, `{d | date: "long"}`, `{d | date: "short", "short"}`, `{d | date: "none", "short"}` (ICU's `none`, `short`, `medium`, `long`, `full`), written in UTC in the language of the text, for a UNIX timestamp in seconds or an ISO 8601 date (no offset is UTC); anything else is written as it is. Without ext-intl it writes ISO 8601 in UTC.
- `oz lang export` (`--json` for tools): the catalogs of the enabled languages, merged as the server reads them (OZone's, the plugins' and the project's), with the default language and the names of the declared translation filters (`Polyglot::exportCatalogs()`, `Polyglot::getFilterNames()`). The API sends message codes and never text, so a client translates them itself from this. A language enabled without a catalog is exported with an empty one.
- The form session's `evaluate` answers a switcher's server-only branches: `switchers` lists them as `expect` lists its sets, `{ref, passes, message}`, by the branch's ref (`<field>@switch[<n>]`), so a client knows which type such a field takes before the step is submitted.
- A refused form names what refused it: a field whose value its type refuses adds `field` (its ref) to the error's data, as a missing required field already did, and a rule set that fails adds `rule` (its ref, `@expect[0]`, `address.@ensure[0]`). Both refs are in the bundle a client holds, so it can show the server's answer next to the field or the rule; the field and the set themselves stay in the private `_suspect` and `_rule`, for the logs.
- A settings key says how it is merged, in the file that owns it: `Settings::MERGE_KEY` (`@merge`) maps a key to `default` (maps merge, the rest is replaced), `append` (lists are appended, for what every source adds to), `replace`, or `lock`. A **locked** key cannot be changed by any source after the one that declared it, nor by `Settings::set()`, and a later source may only tighten a strategy, never loosen one: an allow-list such as `OZ_REDIRECT_ALLOWED_HOSTS` can be closed to the plugins loaded after it. `OZ_CORS_ALLOWED_HEADERS` ships as `append`, since every source adds the headers its own routes need. Merging all 35 shipped groups costs 0.078 ms against 0.043 ms before.
- What a user must prove before signing up or recovering an account is configurable, per user type: `oz.auth.verification` (`OZ_SIGNUP_VERIFICATION`, `OZ_ACCOUNT_RECOVERY_VERIFICATION`) maps a user type to the authorization providers accepted for it, `VerificationPolicy::ANY_USER_TYPE` (`*`) being the rule every type falls back to. A project restricts sign-up to email, names its own provider (an invitation, an identity check), or gives a type an **empty list** so nothing has to be proven and the identifier is taken as given, to be verified later. `VerificationPolicy` reads the rules; the routes ask for an authorization at their door only when every type requires one, and the handler always enforces the rule of the type it was given (the type is a form field, which a route guard cannot see). `RouteInfo::hasGuardStoredResults()` tells a handler that a guard did not run from one that ran and refused.

- The analysis stubs ext-memcached (`stubs/memcached.phpstub`) instead of suppressing `UndefinedClass` for it. The suppression was never used in the image the analysis runs in, which has the extension, and an unused suppression is an error of its own on a cold run -- `make lint`'s cache was hiding it while `make check-full` and `make ci` failed. The stub also says what the driver may call, so a call to something the extension does not have is an error rather than waved through with the class.

- `oz deploy run --archive=<tar.gz>` deploys a release from a tar archive of the project (as `git archive` writes it), and `--repository` accepts a git bundle file (`git bundle create`): neither needs the server to reach a repository. A release takes exactly one source.
- `--json` on `oz migrations check`, `deploy releases`, `server status`, `db build`, `scopes add` and `project create`, as `oz doctor check` had: stdout holds one JSON object, `{"ok": bool, ...}` with what the command reported under `messages`, and errors are answered in JSON too (`{"ok": false, "error": ...}`, a non-zero exit), whether Kli, the command or the global exception handler reports them. `Cli::writeJson()` writes it; `Cli::inJsonMode()` tells whether the command line asked for it.
- `oz users grant --user=<id|email> --role=<role> [--by=id|email|phone|username] [--type=<user type>]` gives a role to an existing user of any user type (restoring a revoked one), so a project's first super admin needs no web installer; `oz doctor check` warns when an installed project has no super admin.
- The test kit ships with OZone (`OZONE\Core\Testing`, moved from `tests/`), so a plugin or an app tests the way OZone does: `Sandbox` (`create()` with settings sources, `bootstrap()`, `addSettingsSources()`; the build generates the ORM classes of every enabled namespace, plugins included), `SandboxApp`, `OZTestProject` (new `$repositories` argument, pins from the root package's `composer.lock` with path-repository packages resolved from their directory, `useStubsDir()`), `DbTestConfig`, `ServiceEnv`, `IntegrationTestCase` and `Traits\Requires{Redis,Minio,ClamAV}Trait`. It is left out of the preload list. `phpunit/phpunit` is suggested for `IntegrationTestCase` and the traits. `bin/oz` run outside a project loads, when OZone has no `vendor/` of its own (installed as a dependency), the autoloader of the package it is installed in.
- `Route::key()`, `RouteInfo::onSuccess()` (callbacks scoped to one dispatch), `Field::configureType()`, public `Field::validateType()`, `RuleSet::getFieldRefs()`, `TypesSwitcher::getConditions()`, `RouteSharedOptions::resolveResumeProviderClass()`.
- `Http\CorsPolicy`, `Context::getRequestOrigin()`, `Auth\LoginThrottle`, `AuthUsers::checkPassword()`, `TypeUrl::allowedHosts()`, `ErrorUtils::redactSensitiveData()`.
- `Http\TrustedProxies` (with `resolveScheme()` and `resolveHost()`), `Context::isFromTrustedProxy()`, `Crypt\SignedSerializer`, `CSRF::isValid()`, `AuthLinkView::AUTH_LINK_CONFIRM_ROUTE`, `ErrorUtils::isRedactionEnabled()`; `Uri::createFromEnvironment()` accepts a `TrustedProxies`.
- Settings: `OZ_AUTH_LOGIN_MAX_FAILURES`, `OZ_AUTH_LOGIN_FAILURES_WINDOW`, `OZ_AUTH_LOGIN_IP_RATE`, `OZ_AUTH_LOGIN_IP_INTERVAL` (`oz.auth`), `OZ_REDIRECT_ALLOWED_HOSTS`, `OZ_CSRF_TOKEN_LIFETIME`, `OZ_CLIENT_IP_HEADER` (`oz.request`), `OZ_COOKIE_SECURE` (`oz.cookie`), `OZ_REDACT_SENSITIVE_DATA` (`oz.logs`).
- `App\GarbageCollector`, `RouteSharedOptions::withoutCSRF()` / `isCSRFDisabled()`, `CSRF::isRequiredByDefault()` / `cookieName()`, `LogWriter` constructor arguments (file, max size, max files), `lang/oz.en` catalog.
- Settings: `OZ_GC_PROBABILITY` (new `oz.gc` group), `OZ_LOG_MAX_FILES` (`oz.logs`), `OZ_SECURITY_HEADERS`, `OZ_CSRF_SESSION_DEFAULT`, `OZ_CSRF_COOKIE_NAME` (`oz.request`).
- Tooling: psalm runs at level 4 with no baseline -- the level-3 checks the code has no occurrence of stay errors -- and scans the generated ORM classes (`.ozone/build/plugins`) for their types without analysing them. Every `make` target runs in Docker (`docker/compose.yaml`: PHP with the extensions OZone uses, MySQL, PostgreSQL, Redis, MinIO, ClamAV), so no server is needed on the host (`tests/prepare_test_dbs.sh` is removed). `make test-services` (replacing `make test-redis`) runs the `redis`, `minio` and `clamav` groups, excluded by default, failing when a server is unreachable (ClamAV, which needs about 3 GB of memory, is only started for it); `make test-coverage` writes a coverage report. `phpunit.xml.dist` has no database credentials (the Docker environment provides them) and uses the PHPUnit 9.6 schema; server-based integration tests fail instead of skipping when the server does not start. The unit suite boots in a sandbox project created for each run (`tests/autoload.php`) instead of the repository, and test classes declare `@covers`. See `CONTRIBUTING.md`.
- `FS\Drivers\MinioStorage`: file storage on MinIO or any S3-compatible service (`FS\S3\S3Client`, `FS\S3\S3Signer`, settings group `oz.files.minio`), used once a slot of `oz.files.storages` is mapped to it; files are streamed through PHP or redirected to a presigned URL (`OZ_MINIO_SERVE_MODE`).
- Docker deployment samples in `conf/docker/`, next to `conf/nginx/` and `conf/apache/`: PHP-FPM + nginx (default) or Apache images, and a compose file with the cron runner, a queue worker and the database; Redis, MinIO and ClamAV optional.
- Optional virus scan of new files (`FS\Scan\FileScan`, settings group `oz.files.scan`, off by default), with a ClamAV scanner (`FS\Scan\Scanners\ClamAVScanner`, clamd's INSTREAM command) behind `FS\Scan\Interfaces\FileScannerInterface`. In `sync` mode infected uploads are deleted and rejected (`OZ_FILE_INFECTED`, or `OZ_FILE_SCAN_FAILED` when the scanner fails; `Exceptions\FileScanRejectedException`); in `async` mode files are saved `pending` and scanned by a queue job (`FS\Scan\FileScanWorker`). `GetFilesView` never serves infected files, nor pending or failed ones unless `OZ_FILE_SCAN_SERVE_UNVERIFIED` is set.
- `Context::client()` (`Http\ClientInfo`), `Context::authState()` (`App\ContextAuth`), `Context::navigator()` (`App\Navigator`), `Http\ResponseEmitter`, `Request::mediaType()` (`Http\MediaType`), `Http\RequestBodyParser`, `Http\MethodOverride`: the parts `Context` and `Request` now delegate to, their existing methods unchanged.
- `RouteSharedOptions::resolved()` (`Router\ResolvedRouteOptions`): a route's effective options, cached instead of walking its groups on every call.
- `ApiDoc::schemas()`, `gobl()`, `parameters()`, `responses()`, `operations()`: the `REST\ApiDoc\` builders behind `ApiDoc`.
- `REST\Enums\RESTFulAction`, the action table driving `RESTFulService` routes and docs (`REST\RESTFulApiDoc`); `RESTFulService::beforeAction()` / `afterAction()` hooks and `configureRoute()` for per-action guards, forms or middlewares; `RESTFulService::routeName()` accepts a `RESTFulAction`.

### Fixed

- **A super admin was refused the access rights routes**: `/access-rights/read`, `list`, `allow`,
  `deny` and `update` were guarded by `withRole(admin)`, which is the exact role; they now let in an
  administrator or anyone heavier (`withRoles(RoleStrategy::GTE, null, admin)`).
- **A request to a route that stacks rate limits on one key was counted once per limit**: a group
  and its route each declaring an `IPRateLimit` share the key `ip:{route key}:{ip}`, so one
  submission spent two of the route's attempts and the group's looser limit read the same doubled
  count. A limit is now measured in a counter of its key and its window
  (`RouteRateLimiter::counter()`, `{key}:{interval}` in the `oz:rate_limit` store), and a request
  is counted once in each counter (`RouteInfo::claimRateLimitKey()`, the mechanism a form discovery
  already used). Limits on one key with one window share a counter and each holds it to its own
  rate, so the strictest governs; a limit with another window is measured in its own, so a strict
  limit over a minute still holds under a loose one over a second. Limits on different keys count
  apart, as before. `RouteRateLimiter::allows()` reads a counter without adding to it. Counts kept
  under the old keys start over.

- **A form discovery of a route that stacks rate limits** (a group's `rateLimit()` and the route's
  own) was counted once per limit against its own quota, so `OZ_FORM_DISCOVERY_IP_RATE` let through
  half as many discoveries. It is one hit per request now (`RouteInfo::claimRateLimitKey()`).
- **The API documentation of `POST /form/:provider/init` stated "30 per hour per IP"** whatever the
  project set: its limit is now written from `OZ_FORM_RESUME_INIT_IP_RATE` and
  `OZ_FORM_RESUME_INIT_IP_INTERVAL`, through `ResumableFormService::initRateLimit()`, which the route
  counts with too.
- **The message code `OZ_RATE_LIMIT_EXCEEDED` is gone from the catalogs**: nothing sends it (a rate
  limit answers `OZ_ERROR_RATE_LIMIT_REACHED`). A client that translated it has nothing to translate.

- **The API documentation of a 429 named a code nothing sends**, `OZ_RATE_LIMIT_EXCEEDED`, for
  `POST /login` and `POST /form/:provider/init`: a rate limit answers `OZ_ERROR_RATE_LIMIT_REACHED`
  (`RateLimitReachedException::DEFAULT_MESSAGE`, which the documentation now reads).

- **Asking a route for its form counted against the route's rate limit**, so every page that shows a
  sign-in form spent one of `oz:login`'s attempts (`OZ_AUTH_LOGIN_IP_RATE`, 20 per IP a minute).
  `rateLimit()` does not count a form discovery (`X-OZONE-Form-Discovery`) against it, since it
  answers the form's description and runs nothing of the route (it has a cheaper limit of its own,
  see Added); a submission is counted as
  before, and so is a form resume, which submits too, even when it also carries the discovery
  header. A route's interceptor is now selected before its middlewares run (and still runs after
  them), which is what lets the limit tell the two apart.

- **A script or a stylesheet served as `text/javascript` or `text/css` was classed a document.**
  `FileKind::fromMime()` tested `text/` before them, so its `JS` and `CSS` branches were never
  reached for those types. The specific types are tested first now (scripts, stylesheets, fonts,
  then documents). A file's kind is stored when its row is written (`oz_files.kind`), so a row
  written before keeps the kind it was given; nothing migrates it.

- **A command a request's cron tick ran carried the request's environment too**, as any process
  OZone started while serving a request did: a project's `Cron::command()` (`CommandTask`) under
  cron's requests mode, the one not PHP. The rule is in `Process::start()` now, for every process
  however it was built (`new Process()`, `fromShellCommandline()`, `Process::php()`) and however it
  is started (`run()`, `mustRun()`, `start()`, `restart()`): `Process::CHILD_ENV` of the serving
  process's environment, and the variables the caller passed (constructor, `setEnv()`, `start()`).
  A command that needs another variable names it. A command line passes its environment on, as
  before.
- **A PHP process started from a request carried the request's environment.** Under CGI the
  request is environment: an async job a request's cron tick started inherited it. The installed
  `symfony/process` (6.4.49) already drops the `HTTP_*` and CGI names from a child started outside
  the command line, by a list of names; what a server adds besides -- `SSL_CLIENT_*`, `UNIQUE_ID`,
  `CONTEXT_*`, a site's own FastCGI parameters -- went on to the job, and the `^6.3` OZone requires
  does not promise that list. A worker server's `RR_MODE`, `FRANKENPHP_WORKER` or `OZ_RUNTIME` went
  too, which `oz jobs run` reads to decide it is a worker. From a process that serves requests,
  `Process::php()` passes `Process::CHILD_ENV` (PATH, HOME, TMPDIR, LANG, LC_ALL, TZ) and nothing
  else; the job reads the project's `.env` from the file, as every OZone process does. A command
  line passes its environment on, as before.
- **`oz deploy run --host` ran the release's `oz` commands with this machine's PHP path**, and
  `oz deploy init --target=bare` wrote it into the systemd units: `PHP_BINARY` of the process
  running `oz`, which on another server may not exist or be another version. `oz deploy run` asks
  the host for its own now (the first of `php8.x`, `php8x`, `php` on its PATH, directory by
  directory), `--php` names one, and the commands quote it. `oz deploy init`, which writes files for
  this machine's layout (its project path, its PHP-FPM version), takes this machine's
  (`OZ_PHP_CLI_BINARY` included) or `--php`.
- **An async job started by a request ran nothing.** Cron's requests mode runs a tick in the process
  that served the request, and a task of that tick running a queue with an async worker's job
  started `oz jobs run` with `PHP_BINARY`: under PHP-FPM, php-cgi or FrankenPHP (where it is empty)
  that is not a command line, so the job's process never ran it, and the job stayed running and
  locked until its lease ran out and it was reclaimed as a failed attempt. It starts with `Process::phpBinary()` now (see Added); a scope's build started the same
  way, as could anything else that started PHP with `PHP_BINARY` outside the command line.

- **Naming another scope added its stateful settings to the process's.** `app()->getScope('other')`
  -- `oz project link`, `oz deploy init`, `oz maintenance` with scopes -- built a `SubScope` whose
  constructor registered `data/other/settings` as a settings source, the last one, so its values won
  over the served scope's for the rest of the process. A project's scope registers no source when it
  is built; `OZone::bootstrap()` registers the served scope's.
- **A request whose sub-request failed died on its next error.** `BaseException::$just_die`, which
  stops a failure to report a failure from looping, stayed set from the first report to the end of
  the request, so a second failing sub-request, or the request failing after one did, was answered
  "error handling error". It is set while a report is being made, and only then.
- **An error answered in a sub-request closed its caller's output buffers.** `informClient()` (and a
  maintenance's answer) closed every buffer down to level 1, the handler's that made the sub-request
  among them. A context records the buffer level it began at (`Context::outputBufferLevel()`,
  `@internal`), and an error answer drops only what is above it.
- **Under the console, an error answered in a sub-request ended the process.** `informClient()`
  printed the message and exited when there was no HTTP client, so a command or a cron job whose
  sub-request was refused stopped there. The error is answered as a response under the console too,
  and the caller reads its status.
- **A request making ten sub-requests was refused as a recursion.** `Router::runRoute()` counted
  every route run in the request tree and refused the tenth, one after the other or not. What it
  bounds now is how deep a chain of sub-requests goes, by `OZ_SUB_REQUEST_MAX_DEPTH` (`oz.request`,
  16); sub-requests made in a row are never counted, and the trail of the tree is still what a
  refusal reports.
- **A scope build could not find a class of the scope's own directory.** A scope's `index.php` makes
  the project namespace read `scopes/{name}/` too; `oz project build` set the scope up without it, so
  a route provider living there was not a class, the router refused it and the whole scope build
  failed. `ScopeBuilder` adds it as the entry point does.
- **A public file's direct link missed the file.** With `OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED`
  (`oz.files`) on, `PublicLocalStorage::publicUri()` built the file's path in the public files
  directory on the host of the request (`/uploads/Y/m/name`), which no web server maps: the
  directory is `data/root/static`, linked as `static` from the root document root (`public/`) only,
  while the `static` link of a scope's document root reaches that scope's own directory. The link is
  now `OZ_PUBLIC_FILES_BASE_URL` (see Added) followed by that path, joined with `/` whatever the
  server's directory separator. **A project that turned direct access on must set the base**:
  building a link without it throws, naming both settings, rather than handing out a link that
  answers 404. Direct access stays off by default, and links then go through the file route as
  before.
- **An unusable `OZ_ROLE_ENUM_CLASS` was reported without naming the setting.** The exception was
  thrown before `suspectConfig()` was called on it, so the hint pointing at `oz.roles` /
  `OZ_ROLE_ENUM_CLASS` never reached it. The check is `RolesUtils::checkRoleEnumClass()` now, which
  `getRoleEnumClass()` calls, and the exception carries its suspect.
- **An API scope's `.htaccess` sent every path to OZone, existing files included**, so the public
  files behind its `static` link reached OZone, which maps no route for them, and answered 404 under
  Apache while nginx and a web scope served them.
  It serves an existing file or directory as the web scope's does, `/robots.txt` still going to the
  route first. An existing scope keeps the `.htaccess` it was given.
- **A URI read from a request named the server's port where the client used the default one.** A
  `Host` header without a port took `SERVER_PORT`, so behind a port mapping with no proxy (a
  container publishing 80 on 8080) every absolute URI built from the request -- a link to another
  host, a redirect -- named the port the server listens on. A `Host` without a port is the scheme's
  default now; `SERVER_PORT` stands only where no `Host` was sent.
- **A route param name could end in a newline.** `Route::ROUTE_PARAM_REG` was anchored with `$`,
  which matches before a final newline, so `{id\n}` was accepted and the route's pattern failed to
  compile the first time it was matched. It is anchored with `D` now and the name is refused when
  the route is parsed.
- **A param of an optional part a request leaves out read as an empty string.** On
  `/docs[/:lang]/page/:id`, a request on `/docs/page/7` gave `lang` the value `''`, so
  `RouteInfo::param('lang', 'en')` answered `''` and not its default; the same happened to a host
  template's optional part, where a link built from it named a host with a leading dot. A part
  left out gives no param now, wherever it stands, as it already did at the end of a path.
- **A file's deletion refused by a listener left the row without its bytes.** `FS::deleteFileByID()`
  deleted the stored bytes, then the row; a listener of the row's deletion
  (`TableCRUD::events('oz_files')->onBeforeEntityDeletion()`) that refused it -- a plugin whose pages
  still show the file -- left a row whose file was gone. The row is deleted first now, so a refusal
  leaves both. A bulk delete of `oz_files` rows dispatches no per-row event and cannot be refused
  per file.

- **Two tests starting a server at the same moment no longer share it.** `OZTestProject::startServer()`
  let `oz project serve` take the first free port of 8080, 8888, 9000 and 2227, which it writes down
  before it binds: two processes both found 8080 free, one bound it, and the other's readiness check
  answered for that server, so a test read another project's pages (four parallel runs of six, in a
  plugin's integration suite). It now passes a port the system hands out (a bind on port 0, read and
  let go), and refuses a server that says it serves anywhere else. `oz project serve` keeps its list
  for a developer.
- **A test that sets a key of a group no longer rewrites the project's own settings with this
  process's environment.** `OZTestProject::setSetting()` ran the group's file in the test process
  and wrote back what it returned, so a generated `oz.db.php`, which reads `env('OZ_DB_RDBMS',
  'mysql')`, came back naming `mysql` on `localhost` whatever the project's `.env` said: every
  command after that connected failed with "Unable to access database". The project's file is now
  kept as it is (`{group}.php.base`), the values set are kept apart (`{group}.php.overrides`), and
  the group's file lays the second over the first when the project reads it. A group file rewritten
  since by anything else is the project's file from then on, and what was set before is dropped,
  as before.
- **A `PATCH` writing only a row's relatives is no longer refused.** `actionUpdateOne()` updated the
  row first, and an update naming none of its columns is refused
  (`GOBL_ORM_REQUEST_NO_FIELDS_TO_UPDATE`), so linking a record or removing a relative without
  changing the row never reached the relations. Such a request now reads the row -- after the
  row's update check -- and writes its relatives only.


- **An email or phone column asked to check registration without naming a user type crashed.**
  `TypeEmail` and `TypePhone` read their `registered_as` option with no default and handed it to
  `AuthUsers::identify()`, whose first parameter is a non-nullable `string`: under strict types a
  `TypeError` at the first validation of such a column. `registered()`, `notRegistered()` and
  `configure()` all store a user type, defaulting to `UsersRepository::DEFAULT_USER_TYPE`, so the
  only way in was `setOption('registered', true)` on its own, which is public API. The read applies
  that same default now.

  What had hidden it was two stacked `/** @var */` blocks over the two reads: only the lower one
  attaches to the statement, so the analyser never saw that `registered_as` could be null and
  reported nothing for five releases. Placing each annotation on the statement it types is what
  surfaced both sites, and each has a test that fails with the default removed.

- **The runtime test images are built from a pinned extension installer**, taken from that
  installer's own image as `rr` and `composer` already are, instead of a GitHub release's
  `latest/download`. No two builds were alike, and GitHub answering 503 and 504 for an afternoon
  failed `make test-runtimes` before it had started a single server.

- **A request built in an environment that names no path crashed.** `Uri::createFromEnvironment()`
  parses `SCRIPT_NAME` and `REQUEST_URI` against a stand-in domain, and an environment carrying
  neither leaves that domain with no path at all: `dirname()` then received a null and raised a
  `TypeError`. Both are read as the root when there is nothing to read.

- **`$collection[] = $value` raised a type error inside the setter.** A `Http\Collection` is a map
  of names to values, so an entry has to have a name: that is now said where it happens, instead of
  a null key reaching `set()`.

- **A class loader directory declared as an empty array crashed the boot that read it.** An entry
  of `addDirs()` written as an array is `[dir, recursive, deep]`, and one that names no directory is
  refused by name now.

- **A CSRF guard was built with the scope the route held when the guard ran, not the one it was
  declared with.** `withCSRF()` captures the scope it was given, so a later `withoutCSRF()` cannot
  leave the guard with nothing to check against.

- **An upload that declares no media type is refused by the mime check** instead of reaching it as a
  null, and **a port helper reads each `null` as the default its own signature declares**, where the
  first port of the scan used to become 1 (a port a process without privileges cannot bind) and the
  host used to reach a socket call as a null.

- **A message that was never stored is named as such in a log line** rather than passed to
  `sprintf()` as a null id, and **a command task that neither finished nor reports a kill says
  that**, where it used to read as "failed with exit code 0".

- **The service generator keeps the promise its own parameter makes**: the project's `Services`
  directory when the caller names none, which the command was computing for it while the generator
  passed the null straight to a filesystem filter. One owner, and the docblock is true now.

- Said once rather than twice, so a type carries what a comment claimed: the database the
  initialisation step registers is given to it, a dynamic fieldset's factory is read once for both
  the call and the refusal, a store row's id is read where the row is known to have one, and every
  command option is read as the type its option declares, which is how the rest of this CLI already
  read them.

- **Every QR code OZone has ever drawn was unreadable, and the error correction is why.** The
  symbols were structurally perfect -- correct version, finder patterns, timing patterns, alignment
  pattern, dark module, and format information whose BCH check passed in both copies -- and carried
  error-correction codewords that were wrong in fifteen of sixteen positions on a version 2 symbol.
  A standard decoder recomputes those before it reads anything, finds a syndrome far past what it
  could repair, and refuses the symbol: phones, browsers, jsQR and online decoders all failed on
  every badge, while OZone's own round-trip test passed because its decoder never checked them.

  `rsGenerator()` built the generator polynomial by adding logarithms, which scales a coefficient by
  `a^i` but never adds the shifted and the scaled polynomial together -- the addition that makes it a
  polynomial multiplication, and in GF(256) an XOR. Its own comment said "XOR each coefficient" and
  there was no XOR in the function. What it returned was not even monic. It is expanded on values
  now and converted to logarithms at the end, because two values cannot be added in the log domain.

  And the `EC_M` block layout was wrong in fourteen of its forty rows, every one of them in the
  number of blocks or their sizes while the data count and the codewords per block were right.
  Version 3 was split into two blocks of 22 where it is one block of 44, version 4 into four blocks
  of 16 where it is two of 32, version 5 into two of 21 and two of 22 where it is two of 43; eleven
  rows from version 25 up had block sizes that did not sum to their own data count. Each row is now
  checked by the suite against a symbol of that version: the blocks are split, the error-correction
  codewords are recomputed from the Galois field up, and the data has to read back as the payload.

  The GF(256) log and exponent tables were correct, and so were every function pattern, the mask
  conditions, the format information and the data placement -- a reader written from the
  specification recovers the payload from the old symbols too. Only the error correction was wrong,
  which is exactly the part that decides whether a decoder accepts a symbol at all.

- **Every lazy memo whose work can call back into itself now says what is *in progress*.** Found by
  auditing for the shape that killed a deploy (the settings loader, above). A memo is written when
  the work finishes, so for the whole time the work runs it answers "not done" -- which makes it a
  memo and never a re-entrancy guard. Each of these runs a project's code and could re-enter:

  - `AbstractApp::getScope()` built a scope whose constructor registers a settings source, which can
    create directories, raise a diagnostic, and reach the logger -- which asks for that very scope.
    What stopped it was PHP refusing to re-enter an error handler, not anything of ours. Live in
    `scope_build.php`, the deploy-time build the original incident died in.
  - `Logger::shouldLog()` and `Logger::writer()` held the level and the writer after reading
    `oz.logs` and resolving a project writer; both now hold a default first, so a settings file that
    logs is answered instead of starting the resolution again. `LogWriter::instance()` refuses to
    build itself twice.
  - `ClassLoader::resolveLazyNamespace()` claimed the prefix only once its provider had answered;
    the guarantee that this did not run away lived in `Db::$initializing`, another class.
  - `Plugins::getPlugin()` and `Plugins::scopeOf()` claim the key before running a plugin's
    `instance()` or building its scope.
  - `OZone::getApiRoutesProviders()` / `getWebRoutesProviders()` assign an empty list before loading
    `oz.routes*`, which `require`s project files that may return a registry's output.
  - `ApiDoc::get()` publishes the instance **before** loading the providers, which is why loading is
    no longer in the constructor: a provider calling `get()` is handed the document being assembled
    rather than building a second one.
  - `ApiDoc\SchemaBuilder::component()` reserves the component name before running the factory, so a
    type schema provider answering with a related entity's schema gets the reference, which is what a
    `$ref` is for.
  - `Cli\Utils\Utils::tryGetProjectApp()` will not `require` `app/app.php` a second time from
    inside itself.
  - `Http\Request::getParsedBody()` refuses a media type parser that reads what it is parsing.
  - `Stores\KeyValueStore::remember()` -- the framework's lazy memo, handed arbitrary factories
    (the migrations scan, a session lookup on every cookie-bearing request, a user's roles) -- keeps
    a register of the keys being computed and refuses a factory that reads its own key. One change
    covering every caller.

- **Every anonymous page view of a `/{lang}/` project started a session, so no shared cache could
  store any page.** Reported from production. `Polyglot::boot()`'s `RouteBeforeRun` hook called
  `setUserLanguage()` whenever a route carried the `lang` parameter -- which, for a project whose
  public URLs are `/{lang}/...`, is every request. That writes `oz.polyglot.favorite` into the
  session store, `Session::isKept()` is true as soon as the store's data differs from what it
  started as, and so every visitor got a session row and two cookies: `OZONE_SID` and `XSRF-TOKEN`.

  The cost was not the row. A response carrying `Set-Cookie` is one nginx will not cache and no
  shared cache may hold, so a micro-cache in front of such a site sits at a 0 % hit rate -- and one
  configured to store it anyway would hand a visitor's session id and CSRF token to every other
  visitor. Plus a database write per crawler hit, and two cookies for a visitor who had done
  nothing.

  `Polyglot::getLanguage()` already said why this must not happen, 230 lines above the hook: "Not
  stored: the browser sends it with every request, and writing it would give every anonymous visitor
  a session. Only a choice (`setUserLanguage()`) is kept." The hook was treating a path segment as a
  choice. It is not one: a language in the URL **is** the request, completely, on every request, and
  needs no memory.

  A language the request carries is now request scoped, on the `Context` (`Context::getLanguage()`,
  `Polyglot::useLanguage()`), which is where request-scoped state belongs; `getLanguage()` reads what
  the request said before anything stored, so a link naming a language wins over a remembered
  choice. `setUserLanguage()` is unchanged in meaning and still keeps the session: it is for a
  visitor who *chose*, in a switcher or a profile, which is the event worth a session.

  **What changes for a project**: a visitor who lands on `/fr/...` and then follows a link with no
  language in it is answered in the language their browser asks for, not in the one the previous URL
  named -- unless the project calls `setUserLanguage()` when they actually choose. Projects whose
  every route carries the language see no difference beyond the cookies and the rows going away.

- **A settings file reading another group turned a scope build into an unbounded loop.** The cause of
  the production incident the three entries below are about, found from the core of the killed
  process: 65 530 open handles on one `app/settings` directory, one per turn of a loop, then 4 GB of
  heap on a 3.9 GB host.

  Every memo in `Settings` said what was **finished**, never what was in progress: a group lands in
  `$settings_groups` once `mergeGroup()` has run, a directory in `$source_bundles` once
  `compileBundle()` has returned. Running a group's file is `require`, so that file -- or anything it
  calls -- may read a setting while both memos still answer "not done", and the nested read started
  the same work again from inside itself, constructing one more `RecursiveDirectoryIterator` over the
  directory each turn. The shape that triggers it is the obvious way to write a project's routes:
  `return ModuleRegistry::apiServices();`, where the registry reads the group that says which modules
  are enabled. Nothing about it is wrong, and it is still allowed.

  What is in progress is now registered on entry (`Settings::$loading_groups`,
  `$compiling_sources`): a nested read of a directory being compiled is answered as a directory
  without a bundle, so it is served from the one file it needs, and a group whose own file reads it
  gets a `RuntimeException` naming the group instead of a loop. A build runs with a worker runtime
  (`ScopeBuilder::run()`), so `bundlesEnabled()` is true there and the bundle branch is live: the
  loop needed production and a build, which is why no test and no development run had met it.

  It also explains the three truncated builds that came before: the same loop reached the descriptor
  limit first, the failure to open one more file was caught by the compile's catch-all as "this
  directory cannot be bundled", and the release shipped a settings bundle short with exit 0. Making
  the error handlers survive (below) did not cause the crash -- it removed what had been ending the
  loop early, so a silent truncation became a silent kill, which is the only reason anyone looked.

- **The database password was on `mysqldump`'s command line.** `oz db backup` passed it as
  `-p<password>` in the dump's arguments, where `ps` shows it to every user on the host, a container
  inspection keeps it, and a failure that quotes a command line writes it down. It now travels in an
  option file created empty, locked to this user (`0600`) *before* the secret is written into it, and
  deleted when the dump is over -- `--defaults-extra-file`, which mysqldump reads before its other
  option files and in addition to them, so a host's own `my.cnf` still applies. `pg_dump` already
  took its password through the environment and is unchanged.

  It also fixes an empty password: `-p` with nothing after it is the option that makes mysqldump
  *prompt*, so a database with no password made the backup wait for input instead of running. There
  is now no option at all in that case.

- **A process OZone ran and that was killed reported the signal and dropped everything else.** A
  production deploy's scope build was killed by `SIGSEGV`, and all the deploy log kept was
  `ProcessSignaledException: The process has been signaled with signal "11"` from a frame naming
  `ProjectBuilder::buildScope()`. Symfony's `wait()` throws that exception *after* reading the
  pipes, so everything the process had printed was sitting on the object and went with the exception,
  unread -- and `wait()` is where `run()` and `mustRun()` both end up, so this was every process
  OZone runs: a provisioning command, a database dump, a cron task's command, the development
  server, a scope build. Not one of them caught it, which is the one failure where there is nothing
  else to go on: a killed process runs no error handler and no shutdown function of its own, so what
  it printed before it died is the only account of it there can be.

  `Cli\Process` is where that is answered now, once, for all of them. A signal is answered as a
  failing **exit code** -- 128 plus the signal, as a shell reports the same death -- the way a
  timeout already was (`ShellRunnerInterface::TIMED_OUT`), with the output intact and the signal kept
  on the object: `killedBy()`, `killedPhrase()` (`killed by signal 9 (SIGKILL, which the kernel
  out-of-memory killer and "kill -9" both send)`), `saidBeforeItDied()`, and `signalFromExitCode()` /
  `killedPhraseFor()` for a caller that has only the code. Five files were running Symfony's `Process`
  directly rather than OZone's and now do not (`LocalHost`, `SshHost`, `JobsManager`, and the test
  kit's `OZTestProject` and `Sandbox`).

  What each of them says changed with it: a scope build names the signal, what the process printed,
  and that the reason is in the kernel's log and not in PHP; a provisioning step says `was killed by
  signal 9 (...) on: <command>` instead of `failed on`, which read as the command's own verdict when
  a package install on a small box had never reached one; a cron task's result says which signal
  instead of "failed with exit code 137"; a database dump says `mysqldump was killed by ...`, which
  is what a host short of memory does to a dump of a large database; and `oz project serve` now ends
  with the server's code, 128 plus the signal, where an uncaught throwable made it 255.

  **Breaking:** `mustRun()` on a killed process throws OZone's `RuntimeException`, naming the signal
  and what the process printed, rather than Symfony's `ProcessFailedException`, whose message is the
  exit code it was handed (`Exit Code: 139(Unknown error)`). A project catching the Symfony type
  around `OZONE\Core\Cli\Process` catches `OZONE\Core\Exceptions\RuntimeException` instead; a
  process that failed on its own still throws the Symfony one.

- **`oz doctor check` passed a production release with no build at all.** The same deploy: the build
  died, wrote neither `build.php` nor the class map, and the two rows that judge a build answered
  `warn` (`not built for this release`) and nothing -- the `Build result` row is only added when there
  is a result file to read. Warnings do not fail the command, so a deploy whose gate is `doctor check`
  would have shipped the release; what stopped that one was the build's own exit code. A production
  release without a class map is now a **failure**: every build writes one, so a release that has none
  was never built or was built by a run that did not finish, and neither serves what the project asked
  for. A missing result file is reported as a warning row of its own instead of no row, because a
  release built by an older OZone has none and is not wrong for it -- and a gate reads a row it cannot
  find as a row with no news.

- **`oz project build` reported a truncated build as a success.** Reported from a production deploy:
  a scope compiled two of its three settings bundles, printed the PHP warnings it had swallowed, then
  printed `✔ scope "api": 2 settings bundles, route tables compiled.` and exited 0; an identical
  re-run compiled three. Nothing downstream looked at the number, so what stood between a short
  release and production was a person reading two counts off a terminal and knowing what they should
  be.

  Three faults, each silent on its own. `Settings::compileBundle()` caught every throwable and
  answered null, which is right for the case it was written for -- a group holding an object no
  bundle could write back -- and swallowed everything else under the same answer, so a directory that
  failed only lowered a count. The command printed `route tables compiled` while
  `OZone::createRouter()` discarded `RouteTable::save()`'s `false`. And `oz doctor check` had drift
  checks for the class map and the preload list and none for either of these, so the one gate that
  could have caught it passed too.

  Now: `Settings::warmBundles()` answers `{bundled, expected, failed}` with the reason per directory
  and `OZone::routeTableBuilds()` says whether this release has each router's table; the build prints
  `3 of 3 settings bundles, 2 of 2 route tables`, names what was short and why, and **exits non-zero**
  so a deploy under `set -e` stops by itself; and it writes what it compiled to `build.php` in the
  release's build directory, which `oz doctor check` reads as a new failing check (`Build result`) --
  a check where there was a paragraph in a deploy document. A shortfall is still not an error where
  it happens (a settings directory that cannot be bundled is read file by file, a route table that
  cannot be written is compiled per request), which is why the build has to go and ask. What a scope
  process merely printed is still only reported: a project's own output and a dependency's
  deprecation must not fail a deploy.

  The build now also records the descriptors it held at its highest and the limit it ran under, in
  `build.php` and beside a failure (`descriptors: held 11 at most of the 1048576 this process may
  open`). What exhausted them in those deploys is not known, and a process that can open no more
  files reports what it could not read as something it could not compile -- so the reason printed
  above that line can be the symptom rather than the cause. Neither number could be had afterwards:
  the limit a build runs under belongs to whatever started it, a deploy's shell, a systemd unit or a
  cron job, and a shell asked later answers for the shell.

- **A failure to report a diagnostic replaced the diagnostic, and sometimes the run.** The same
  deploys named `Logger.php` as the file that could not be opened, which was the symptom and not the
  cause: `oz_logger()` resolves the logger on first use, and first use is the moment an error is
  raised -- with no descriptor left, the error handler autoloaded the logger, got a "class not
  found", and PHP, which does not re-enter an error handler, threw it from the line that had raised
  the original warning. That line was inside the settings compile's `catch (Throwable)`, which read
  it as its own failure: the warning, its reason and the directory being compiled all went at once.
  The exception handler and the shutdown function had it worse -- throwing out of either is a fatal
  error, so a logger that could not write ended the process with its own message in place of the one
  the process was already ending for (found by the test for this, which a broken `OZ_LOG_WRITER` made
  exit 255).

  Each of the three handlers now catches its own failure and says it through `php://stderr`, and the
  logger's classes are loaded when the handlers are registered, while the process can still open a
  file. `OZ_LOG_WRITER`'s settings are deliberately not warmed with them: the autoloader registers
  the handlers before a project's own settings sources exist, and reading the group then would hold
  OZone's defaults for the rest of the process.

- **A diagnostic its caller had silenced was logged anyway.** The error handler never looked at
  `error_reporting()`, so a warning suppressed with `@` was logged as a warning: php-utils creates a
  directory with `@mkdir()` and checks the result after, and two requests creating one directory
  wrote `mkdir(): File exists` to the log, which is how it was found in the concurrent sign-up test.
  The handler now leaves a silenced diagnostic alone -- and leaves it to PHP rather than swallowing
  it, returning `false` so that it still lands in `error_get_last()`. `@` and then `error_get_last()`
  is how a caller reads why a call it expected to be able to fail failed, and a framework that took
  the diagnostic out of the log and out of the caller's reach would have broken that for every
  project; PHP's own handler honours `error_reporting()` for displaying and logging, so nothing of it
  is printed or written. The shutdown function knows the one it passed on and does not report it as
  an error of this run's ending.

- **Every entry of a Memcached store was kept for tens of millennia.** `MemcachedStore::set()`
  computed `(int) ($expire * 1000)` from the entry's absolute expiry and gave it to
  `Memcached::set()`, which takes seconds: an entry meant to last a minute was given a timestamp
  around the year 55,000, so nothing this store wrote ever expired of its own accord -- a cached
  value, a rate-limit counter and a lock alike, until Memcached evicted it under pressure. The
  expiry is now sent as seconds, as the delay the entry has left where Memcached reads the number
  that way, and as the absolute time it ends at where it does not: over its 30-day threshold, and
  for a life already over, which a delay of zero or less would have turned into no expiry at all.

- **A server the test kit could not start was reported as a wait, not as a reason.**
  `OZTestProject::startServer()` polled for `server.json`, then for the port, until two deadlines of
  15 and 10 seconds, whatever had happened in between: an `oz project serve` that exited at once --
  a port already taken, a scope the project does not have, a project that cannot boot -- was waited
  out to the full deadline, stopped, and reported as the deadline, with everything it had printed
  thrown away. A wait now ends as soon as the process is gone, and every failure carries what it
  printed, so the reason is in the failure instead of in a rerun. The two are named constants
  (`SERVER_JSON_WAIT`, `SERVER_PORT_WAIT`) multiplied by `OZ_TEST_SERVER_WAIT_SCALE`, for a machine
  on which generous is not enough: a full `make test` reached both while the ClamAV container the
  suite starts was loading its signature database, and turned one slow start into 10 red tests in
  cron code with nothing wrong in `oz/`.

- **A request that never reached the object storage reported only the URL.** The HTTP stream
  wrapper's `fopen()` is called under `@`, which the suite needs -- a warning raised in `oz/` is
  fatal to a run -- and the exception it threw named the URL and nothing else, so a connection
  refused, a name that did not resolve, a peer that hung up and a service that was down all read
  the same. The reason is read back from the error the `@` suppressed (`error_clear_last()` before
  the call, `error_get_last()` after), and reported in the shape the cURL transport already used:
  `Unable to reach the object storage at "<url>": Failed to open stream: Connection refused.` PHP
  prefixes its own message with `fopen(<url>): `, which is dropped, so the URL is said once. Found
  when a PUT to MinIO failed once under load with nothing but the URL to go on, and covered by a
  test that sends to a port of the machine nothing listens on.

- **A scope build was capped at 300 seconds, inside a budget that was already bounded.**
  `oz project build` compiles each scope in a process of its own, and that process was given a
  300-second deadline no option and no setting could raise. A deploy reaches it as a step run
  through a host, which bounds the whole command already, so the per-scope cap could only ever fire
  while the step still had minutes left -- and it did, on a scope that takes 0.63 seconds to build
  when measured alone, having spent its time waiting on a disk busy with the database backup the
  migration step had written moments before. The deadline is gone: the process runs with none of
  its own, as every other process OZone starts for work whose length it cannot know does
  (`JobsManager`, `CommandTask`), and the host's deadline governs a deploy while a build started by
  hand is interrupted by hand. What made this expensive is where it fell -- after the migrations
  and before the swap -- so a deploy driven under `set -e` stopped with the database already
  migrated and the release not live.

- **A queue run stopped part way through a backlog and reported itself done.** `JobsManager::run()`
  iterated the pending jobs of a store and ran each as it arrived, and the database store read them
  in chunks of a hundred reached by counting the rows before them -- rows the run had just taken out
  of that count by running them. With 240 jobs waiting, one pass ran 140 and left 100 `PENDING`, and
  nothing was logged because the read simply ended. A worker that keeps running passes does drain,
  one pass later than it should; what does not recover is anything that queues work and runs the
  queue in one go before reading the result.

  A run no longer iterates. `JobStoreInterface::claimable()` hands out one job at a time, asked for
  again each time, so what it answers is the queue as it stands and not a list made earlier: the
  database store reads a single row the engine chooses, and the Redis store orders its index once
  and re-reads each job as it reaches it. A pass hands out no more jobs than were pending when it
  began, which is what stops a retry delay of zero turning one pass into a loop, and `iterator()` is
  left to reading a queue rather than running one. The chunked read itself was fixed in Gobl, which
  is where every other caller of it was exposed too.

- **Priority was filtered on and never sorted by.** `job_priority` is documented as deciding which
  job runs first and nothing ordered by it, so a job marked urgent ran first only by chance. The
  work is handed out most urgent first, and by arrival among equals.

- **A queue pass always answered zero.** `JobsManager::run()` reached its counter only inside the
  test for a maximum number of jobs, and `&&` short-circuits when no maximum was given: the default
  call ran its jobs and returned 0. `Messenger::runQueue(): int` passed that on, so a mailing worker
  reported `sent: 0` for a pass that sent, and `oz jobs daemon` backs off only when a pass ran
  nothing -- so it slept its full interval after every pass, however much work it had just done.

- **Every clock a job carried was flattened to its year the first time the row was written again.**
  A date column answers the format it declares, which defaulted to a full timestamp string, and the
  queue store read it as an integer: `2026-10-30T10:36:12+00:00` became `2026` -- half past midnight
  on 1 January 1970 -- and was written straight back, for `created_at`, `updated_at`, `started_at`
  and `ended_at` alike. `oz jobs prune` compares `created_at` against its window, so it deleted
  every terminal job whatever retention it was given; the suite could not see it because each prune
  test passes `--older-than=0`. It is also what the stale-lock fix was treating: a job was not
  "frozen at its creation", it carried the year.

  The format was the wrong half to argue with: a date column is stored as a timestamp whatever it is
  given, so Gobl reading it back as a formatted string was the surprise, and it is now read back as
  what it stores. OZone's schema lost the three `->format('timestamp')` calls that were working
  around it and declares nothing else. No DDL changes and no migration: the stored type was already
  a big integer. **Breaking for a client**: a row carrying a date answers a Unix timestamp where it
  answered an ISO 8601 string, and the OpenAPI schema says which.

- **`env()` raised in a process with no app running, which made a project's settings file unreadable
  outside a request.** A settings file is PHP and reads `env()`, so reading one needs the global to
  answer; the global went straight to the running app, and a tool, a deploy step or the test kit
  reads a settings file before there is an app to boot. `OZone::env()` answers in all three cases: the
  running app's env when there is one, the project's `.env` when this process is booting a project,
  and otherwise the variables the process itself was given (`Env::ofProcess()`, read by the same
  parser a file is, so `true` is a bool and `3306` an int there too). The signal for the middle case
  is the new `OZ_BOOTING_PROJECT` constant, kept by the bootstrap where it is still answerable:
  `OZ_PROJECT_DIR` falls back to the working directory, so after that every process looks like a
  project and a `.env` found beside it would belong to whatever directory a tool was started in.
  Found by `OZTestProject::cleanDb()`, which reads a scaffolded project's `app/settings/oz.db.php`
  for its table prefix and fatally ended any host script using the test kit on MySQL, OZone's own
  suite excepted because an app is always running in it.

- **A session request that emptied a branch of the store wrote the branch whole, undoing a key another request had just added.** What a request changed is sent as the keys it changed, so two overlapping requests keep each other's writes; the diff walked into a map on both sides, and an empty array is not a map, so a request removing the last key it saw under a branch sent the branch itself as `[]` instead of sending that key's removal. Everything another request had put there meanwhile went with it. The same held the other way: a request that saw a branch empty and filled it sent the branch whole and undid a key added meanwhile. And nothing had to be lost for a branch to be written whole -- being **new** was enough: the first request to write under a branch sent the branch, so two requests creating the same one at the same moment kept one entry between them. All three send the entries now, not the branch: an empty array is walked into when the other side is a map, and a branch a request is the first to see is written one entry at a time. **Reported from a project as a chunked upload refused one second after the ref was issued** (`upload/chunk/start` answers a ref, `upload/chunk/add` refuses it), roughly one upload in six under a browser fetching other things from the same scope: an upload finishing removes its own ref from `oz_upload_files`, which was the only one that request saw there, and the branch write took the ref just issued with it. Any store keeping an entry per key under one branch was exposed the same way, `oz.link_to_cfg`, `oz.qr_code_cfg`, `oz.captcha_cfg` and `oz.2fa` among them. Both directions have a test that drives a real server with four workers.

- **`upload/chunk/add` and `DELETE upload/chunk/<ref>` answered 500 to a ref that was never issued.** A ref becomes part of a dot path (`oz_upload_files.<ref>`), and php-utils refuses to read a path whose plain segment holds anything outside letters, digits and underscore (`^[a-zA-Z0-9_]+$`): `ref=not-a-ref` raised out of the store and came back as `OZ_ERROR_INTERNAL`, on two routes that need no authentication. A ref is now checked against the shape `newRef()` draws before anything is asked of the store, as the routes keeping a key under `oz.link_to_cfg`, `oz.qr_code_cfg` and `oz.captcha_cfg` already did through their route patterns.

- **`Invalid ref.` answered four different things, and only one of them was a bug.** A chunked upload's ref the store holds no chunks for now says which: `OZ_UPLOAD_REF_UNKNOWN` for a value this server never issued, `OZ_UPLOAD_REF_SESSION_CHANGED` when the session holds no upload at all (it is not the one the ref was issued to, or it expired), `OZ_UPLOAD_REF_DONE` when the ref is there holding the ids of the files an upload that is over produced, and `OZ_UPLOAD_REF_LOST` when the session holds other refs and not this one -- which is the server's fault, and is logged as such with the refs it does hold. A caller could not tell them apart, and neither could we when one was reported.

  The `DONE` case was not a refusal at all before. The same key holds chunk information while an upload runs and the ids of the files it produced once it is over, and `chunk/add` read whatever was there as chunks: a client sending another chunk under a finished ref had `$info['chunks']` and `$info['size']` read off a list of file ids, which raised two `Undefined array key` warnings and left the expected size at `0`, so the answer was `Total size of chunks is greater than the file size.` -- a refusal naming the wrong reason. No suite could have caught it, since the warnings are raised in the process serving the request and not in the one running the tests.

- **The test kit's vendor cache failed when the dependency set was already cached.** A project's
  `vendor/` is renamed into a directory named after the dependency set and symlinked back, and the
  rename was made whether or not that directory already held the set: `rename()` then raised
  `Directory not empty` and the `symlink()` after it `File exists`, leaving the project with a real
  `vendor/` and the suite with two warnings. Whoever filled the cache keeps it now and the second
  copy is dropped, since both hold the set the directory is named after; an empty cache directory,
  which is what an interrupted run leaves behind, is no longer taken for a cached set either -- a
  project symlinked at one had no dependencies at all. It surfaced once the notice below stopped
  ending the run before the integration suite.

- **A ClamAV scan that found a threat raised a PHP notice on the way to reporting it.** clamd closes its end as soon as it has seen enough to answer, so the next write fails with a broken pipe -- the expected end of a send, already answered for by the return value. The writes are silenced, as the connect in the same file already was. It surfaced when the suite started failing on a notice.

- **A URL column accepted `javascript:` and `data:` URLs.** `TypeUrl` validated with `FILTER_VALIDATE_URL`, which accepts `javascript://comment%0Aalert(1)` -- the newline ends the comment, and what follows runs wherever the stored value is written into an `href` -- and accepts `data:` and `ftp:` as readily. The scheme was looked at only inside the `allowed_hosts` check, and that check returns early when no list is configured, so a URL column **without** a host list took whatever the filter took. A stored URL is now checked against RFC 3986 with the scheme list applied either way (`Uri::isStrictUrl()`), and strictly: a value kept in a column is read later by something that may not be a browser, so a string whose meaning depends on which parser reads it is refused rather than normalised into one reading. **Refused now, where they were accepted:** a non-http(s) scheme, an authority a browser reads differently (`http://evil.com\\@good.com/`), a per cent sign that is not an escape, and a host not yet in its ASCII form.

- **Eight docblocks documented a method other than the one below them.** Each sat directly above another docblock, so the text and the tags of one method -- `Navigator::root()`, `Settings::modify()`, `ProjectCmd::create()`, `Releaser::run()`, `UploadFiles::uploadForm()` and three test helpers -- described whatever followed instead, while the method itself had none; `RedisStore`'s said only that a constructor is a constructor. An IDE and a documentation generator read the one above a declaration, so each was answering for the wrong signature. Each is now on its own method. They surfaced because the code style package the repository locked was the release before `no_superfluous_phpdoc_tags` was turned on in it, so the shared rule that strips a `@param` or `@return` restating the signature had never run here; the lock moved, and `make fix` then rewrote 302 files, which is where a tag naming a parameter the declaration does not have became visible.

- `RedisStore::set()` and `RedisJobStore::lock()` answer `true` only when the extension did. Both returned `Redis::set()` straight out of a method declared `: bool`, and phpredis declares `Redis|string|bool` -- a `string` when the call asks for the old value (`GET`), the client itself in a pipeline. Neither is how OZone calls it, so this never misreported at runtime; under `strict_types` it would have raised a `TypeError` rather than lie. They now compare against `true`, which is also the right reading of `SET .. NX`: anything else means the lock was not taken.

- **A session write is no longer undone by a request that overlaps it.** `Session::save()` wrote the whole store back on every response of a started session, built from the data the request read when it started, so two requests of one session each wrote every key as it was then and whichever answered last won: a sign-out that removed its key from the store was undone by any request still in flight that wrote a key of its own -- a language choice, a flash message, a CSRF-bound form step -- and the next request was authenticated again. A page served with subresources of its own opens that window on every navigation away from it, and it widens with how fast the server answers, not how slow. The save now sends what the request changed, as the keys it changed, for the database to apply to the row it holds (Gobl's JSON patch): two requests writing different keys keep each other's writes, and only two writes of the same key are still the last one in. A new session is written whole, as it must be. The data a session holds is unchanged by a request that only read it; the row's `expire_at`, `last_seen_at` and `updated_at` are written on every request as before, which is what slides a session's expiry. A sign-out should still renew the session (`Session::restart()`, `AuthenticationMethodStatefulInterface::renew()`, what `AuthUsers::logUserOut()` does) rather than remove a key: the renewal deletes the row, so nothing in flight can write to it at all, and the client is given an ID that was never signed in.

- **A new project's salt and secret are 512 random bits, where they were 416.** `Keys::newSalt()` and `Keys::newSecret()` drew 64 characters from the printable alphabet (6.5 bits each); their only callers base64-encode the result into `OZ_APP_SALT` / `OZ_APP_SECRET`, so the printable alphabet cost entropy and bought nothing. Both now return `random_bytes(64)`, as `Keys::id32()` and `id64()` already did. **A project that already has a salt and a secret keeps them**: they are read from its env file and decoded as before, and the new length is the same 64 bytes, so nothing derived from them changes. Only a project created from now on gets the wider value.
- **An error raised while a project boots is logged where it happens, and is no longer reported as a shutdown error.** `ErrorUtils::registerHandlers()` ran at the top of `OZone::bootstrap()` and `Cli::run()`, so everything before them -- the project's boot file, its app file, and every class the autoloader loaded for them -- was handled by PHP alone: the diagnostic went to PHP's own error log and `.ozone/logs/` got nothing. It then came back at the very end of the run, because the error handler returns `null`, which marks an error handled and keeps it out of `error_get_last()`, so the only errors left for the shutdown function are the ones no handler of ours saw -- and it labelled every one of them `OZone shutdown error`. A warning from early boot therefore surfaced as a failure at shutdown, pointing at the wrong end of the run, with no reason attached, since PHP keeps only the last of a pair (a failed `include` reports `Failed to open stream: <reason>` and then `Failed opening '...' for inclusion`, and only the second survives). The handlers are now registered by the autoloader (`oz/oz_default/oz_bootstrap.php`), covering web requests, the CLI and the workers alike, and only when a project is booting: any of `OZ_PROJECT_DIR` or `OZ_APP_DIR` (the generated `app/boot.php`), `OZ_SCOPE_NAME` (the scope's `public/{scope}/index.php`) or `OZ_CLI_PROJECT` (new, set by `oz/index.php`, which ships with the package and already knows it is in a project) defined before the autoload require. Several signals because one is enough, so a boot file edited into requiring the autoloader first still leaves one of the others; a tool that merely autoloads the package -- static analysis, the code style fixer, a build script -- sets none of them and keeps its own error handling and its own shutdown. `oz doctor check` has a **Boot-time errors** row that warns when the handlers went up late and says what to change, so the one case none of the signals catches is named rather than silent. The shutdown line now says which of the two cases it is, and `E_ERROR`/`E_PARSE`/`E_CORE_ERROR`/`E_COMPILE_ERROR`/`E_USER_ERROR` are one list (`FATAL_CODES`) instead of two readings of the same thing. Which of the two the line says is read from a second list (`RUN_ENDING_CODES`), those codes plus `E_RECOVERABLE_ERROR`: PHP does hand that one to an error handler, so it may not be in the list that decides to interrupt -- a handler that carries on is the point of a recoverable error -- but with no handler installed PHP ends the run on it, which is the only way it can reach the shutdown function at all, and reading it there as a stale last error points at the wrong end of the run again. Nothing in PHP 8.5 raises the code, so this is the two questions being asked separately rather than a report that changed.
- **A scope build that succeeds while printing something no longer swallows it.** `oz project build` builds each scope in a process of its own and read its output for the report alone: stderr was shown only when the build failed, and the lines of stdout before the report were dropped. So a PHP startup warning, or one raised in that process while no error handler of ours was installed, left no trace anywhere -- which is how the reason for a failed `include` was lost while the error itself still surfaced at shutdown without it. `ProjectBuilder::buildScope()` now returns those lines as `diagnostics`, both streams together, and the command reports each one as a warning naming the scope. The build still succeeds: what the process said is reported, not turned into a failure.
- `make test-services` and `make test-integration` run again: the MinIO test server is `cgr.dev/chainguard/minio`. `minio/minio` was removed from Docker Hub in September 2026 and `quay.io/minio/minio` answers 401 to an anonymous pull, so `docker compose up --wait minio` could not fetch it and both stages died before PHPUnit printed a line -- the `php` service depends on it. The rebuild is the same MinIO server, carries the patch for CVE-2026-40344 (an authentication bypass in the Snowball auto-extract handler, which the last free community build has), and needs no change to the service beyond the tmpfs ownership, since it runs as uid 65532. The healthcheck (`mc ready local`) is unchanged.
- **A release no longer reads the generated classes another release compiled.** `.ozone/` is shared by the releases of an `oz deploy` root, and every build output under it was named after the release that wrote it -- the class map, the preload list, the compiled `.env`, the settings bundles, the route tables -- except the generated ORM classes, which sat in one `build/plugins/` for all of them. Their shape comes from the Gobl and `oliup/code-generator` in the release's own `vendor/`, so two releases on disk at once cannot share them: deploying a release whose Gobl types a constant the previous one left untyped made **every** `oz` command of the new release die in `Db::init()` with `Type of ...QueryBase::FILTER_METHODS must be compatible with Gobl\ORM\ORMTableQuery::FILTER_METHODS of type array`. A redeclared constant is a compile-time fatal, so the `try`/`catch` that makes a broken schema diagnosable at CLI bootstrap could not catch it, and the one command that would repair it -- `oz db build --class-only` -- boots like the rest. It failed before `current` moved, so the live release kept serving; the way out of it did not, because deleting the shared classes takes the live release down at its next database-touching request (nothing regenerates them on demand; only `oz db build` does). **Build output is now keyed by the directory it is in**: everything goes under `build/{release}/` through `InstanceLayout::releaseBuildPath()`, so a build output added later is keyed because of where it goes rather than because someone remembered to name it after the release. The file names keep only what invalidates them. `Gobl::setDefaultOutputDir()`, what a relative ORM output directory resolves against, points there too instead of at the shared `.ozone/cache/`. **Nothing has to be moved or cleared by hand**: the first deploy of this generates into its own directory and leaves the old `build/plugins/` untouched, which is what keeps a release still running the older OZone serving, and `oz project build` removes the build directories of releases that are no longer on disk (one deploy after they go, since it runs before the next release prunes anything). The old `build/plugins/`, `build/env/`, `build/settings/` and `build/routes/` are read by nothing once every release is on this version and can be deleted then.
- `oz project build --clear` no longer removes what another release compiled. `ProjectBuilder::clear()` globbed `build/env/*.php`, `build/settings/*.php`, `classmap.*.php` and `build/routes/*/*.php` -- every release's, the live one's included, although its docblock said "only the build output of this release" -- so clearing a release being prepared recompiled the serving release's settings and class map under it, and the window where a file was gone was a window where a request failed. Only the preload list was correctly narrowed. It now reads this release's build directory, which is all it can reach. The generated ORM classes are still left alone, deliberately: a build writes them but nothing regenerates them on demand.
- **A random string is valid UTF-8 again.** `Random::CHARS_SYMBOLS` held five Latin-1 symbols (`£`, `µ`, `§`, `²`, `¨`), two bytes each in UTF-8, and a random string is drawn byte by byte: a lead byte was picked on its own about one position in twenty, so a value over the default alphabet (`CHARS_ALL`) was almost never valid UTF-8 and `json_encode()` refused it. Nothing in the framework returned one raw -- every caller hashes it, base64-encodes it or asks for an ASCII alphabet -- but the next one to return a generated secret would have. The five symbols are gone, so every built-in alphabet is ASCII; `CHARS_SYMBOLS` is 29 characters and `CHARS_ALL` 91, which is 6.5 bits per character instead of 6.7. **Values generated before this keep working** (they are compared, hashed or decoded as they always were); only what is generated from now on can no longer contain those five characters.
- An authorization link that expired, or was already answered, says so when it is opened. `AuthLinkView`'s GET checked neither (`Auth::getRequired()` reads `is_valid`, not `expire_at`), so a dead link still offered its Confirm button, and a dead recovery link still offered its password form: the owner typed a new password twice before being told the link no longer works. **A verification's lifetime is now per purpose** (`OZ_AUTH_VERIFICATION_LIFETIMES`, `oz.auth`): a day for a `sign-up`, which creates nothing until it is answered and which the owner may come back to much later, an hour for a `check` and for an `account-recovery`, which opens an account that already exists. A purpose with no entry keeps `OZ_AUTH_CODE_LIFE_TIME`.
- A session refused for a source-key mismatch (`OZ_SESSION_HIJACKING_DETECTED`, `OZ_SESSION_HIJACKING_FORCE_SAME_SOURCE`) no longer leaves the client stuck. The cookie stayed on it, so every later request carried the same ID and was refused in turn -- the home page, the sign-out route and the sign-in form -- until the session expired, up to `OZ_SESSION_LIFE_TIME` later, with nothing but clearing the cookies by hand to get out of it; and the source key changes for honest reasons too, a mobile IP or a browser that updated itself among them. The refusal now drops the session and CSRF cookies as it goes out, so the next request opens a session of its own and the user can sign in again. The stored session is left alone (`Session::abandon()`, new): the refused request is the one that may not act on it, so it may not end it either, and a client replaying the cookie of a session it cannot use does not sign its owner out by sending it.
- **Security:** a two-factor code is answered only by the session that signed in. `TwoFactorAuthorizationProvider` saved the authorization as granted, then compared the session in `onAuthorized()`: a code answered from another session was refused, but had already spent the authorization of the session that was waiting for it, which then had to sign in again. The comparison now runs before any secret is checked (`OZ_2FA_SESSION_MISMATCH`), and a foreign request leaves the authorization pending.
- A route guarded by `withAuthorization()` (`/account-recovery`, and sign-up when verification is required) accepts an authorization that was confirmed, and Bearer, API key and Digest authentication accept an authorized auth key. `Auth::provider()` rebuilt the provider with credentials carrying a newly drawn reference, so its `getState()` looked up a row that does not exist and answered `OZ_AUTH_INVALID_OR_DELETED_REF`; the callers that set the reference themselves (`AuthorizationService`, `AuthLinkView`) were the only ones that worked. The provider it returns now carries the authorization's reference.
- **Security:** an authorization's try limit holds. `authorize()` never looked at the state, so once the tries were used up and the authorization `REFUSED`, every further guess was still checked, and the right one turned it `AUTHORIZED`: a six digit code could be guessed without limit, which over `/auth/verify/email` and `/account-recovery` let anyone who started a verification for an email reset that account's password. A secret is now checked only while the authorization is `PENDING`, by the code and link path and by the authenticator app (TOTP) path of two-factor authentication: a refused one answers `OZ_AUTH_TOO_MUCH_ATTEMPT`, a granted one `OZ_AUTH_CODE_INVALID`, and neither changes. `refresh()` still reopens one with a new code.
- **Security:** an authorization grants nothing after it expires. Its row stays until the garbage collection deletes it, an hour later, and its state was read without looking at `expire_at`, so a confirmed verification kept opening `/account-recovery` and `/signup`, and an expired auth key kept authenticating Bearer, API key and Digest requests. `AuthorizationProvider::getState()` throws `OZ_AUTH_HAS_EXPIRED` (401) past it, which `GET /auth/:ref/state` now answers for an expired authorization.
- **Security:** the authorization that let an account be recovered or created is spent once it did. It stayed granted until it expired, so whoever held its reference could reset the password again, after the user changed it. `/account-recovery` and `/signup` delete it once done (`VerificationPolicy::consume()`); presented again it answers `OZ_AUTH_INVALID_OR_DELETED_REF`.
- A user type that must prove something can recover its account or sign up when another type proves nothing. The route then demands no authorization at its door, so its guard never ran, and `VerificationPolicy::resolve()` found no result and refused every authorization with `OZ_AUTH_REF_NOT_PROVIDED`. It now runs the guard's check itself when the route did not.
- **An async job no longer runs for ever beside itself.** `job_updated_at` was written back as it was read -- nothing ever stamped it, so it stayed at the job's creation time -- while the stale-lock pass took any running, locked job last written longer than two hours ago (`DbJobStore`'s lock lease) for one a dead process had abandoned. Every job older than that lease was therefore stale the instant a worker took it, and an async job is locked and running for as long as its subprocess works: the queue reset it, picked it up, and spawned another subprocess beside the one still running, continuously. A job that had answered OK could sit PENDING with dozens of attempts against a cap of three, because the reset wrote the state directly and consulted neither `job_retry_max` nor `job_retry_delay`, and because concurrent runs overwrote each other's rows. Every write now stamps the job, and a worker whose single run can outlast the lease calls `JobContractInterface::touch()` as it goes.
- `oz jobs run --job=<ref>` says that `--store` is missing instead of dying on a `TypeError`. The option is not required for the action, since leaving it out runs the queue of every registered store, so the null reached `JobsManager::getStore(string $store_name)`. The framework's own async hand-off (`JobsManager::workAsync()`) always passed `--store`, so only a hand-typed command hit it.
- `Roles::assign()` inserts a role ignoring a conflict, then reads it back: assigning one role twice at once made the second request fail on the unique key.
- `Vary` is added to, never replaced: the CORS headers set `Vary: Origin` over the `Vary: Accept` of an image served in the format a browser accepts, so a cache could serve WebP to a browser that asked for PNG.
- `oz doctor check` prints its table as it is, and each detail in full. It went through `writeLn()`'s wrapping, which cut table rows longer than 80 bytes, and cut a detail at 56 bytes, which could split a UTF-8 character. With kli's table fitting the terminal, a long detail now wraps inside its cell.
- The redirect page (`oz.redirect.blate`) shows a working link. Its text held an `<a>` tag, which the template printed escaped, so the page showed the markup instead of a link. `OZ_VIEW_REDIRECT_MESSAGE` is now plain text (it no longer takes `url`), and the link, labelled by the new `OZ_VIEW_REDIRECT_LINK`, is the template's.
- `Polyglot` fills a text's placeholders in one pass: a value is never read for placeholders. It used to search the text again after each replacement, so a value holding `{other}` was filled with another variable's value, and one holding its own placeholder (a user named `{name}` in `Hello {name}`) made the request loop forever.
- `Polyglot::parseBrowserLanguage()` takes the language a browser asks for when it is enabled: it tested the quality factor instead (`$enabled[$q]`), so with `fr` and `fr-bj` enabled a browser asking for `fr-bj` got `fr`. It takes the enabled languages as an optional second argument.
- A language enabled without a catalog falls back to the default language's texts, as a missing text does; every translation used to throw "Undefined setting group".
- `OZTestProject::setSetting()` creates the sub-directory of a group such as `lang/oz.en`.
- The session cookie was sent to every subdomain, and lost behind a proxy. `OZ_COOKIE_DOMAIN` defaults to `self`, which named the request host in a `Domain` attribute: per RFC 6265 that sends the cookie to the host **and all its subdomains** (on `example.com`, to any `*.example.com`), and behind a proxy that rewrites `Host` it named a host the browser never saw, so the browser refused it and every request started a new session. `self` is now a **host-only** cookie (no `Domain` attribute), which is what a session cookie should be; an explicit domain still shares a cookie across subdomains. Found by an end-to-end suite whose app is served by a dev server proxying the API.

- A project could not shorten a list-valued setting. `Settings::applyMergeStrategy()` merged with `array_replace_recursive()`, which overwrites element by element, so a shorter list kept the tail of the longer one: restricting `OZ_2FA_CHANNEL_PRIORITY` to `['email']` left `['email', 'email', 'sms']`, and the override its own documentation describes could not work. A **list is now replaced whole** and a map still merges key by key.

- Every JSON answer carries the same envelope. A route guard's refusal and an authentication challenge answered `{error, msg, data}` without `utime`, and the cron endpoint answered `{"accepted": true}` with no envelope at all. `JSONResponse::toEnvelope(?Context)` is now the one place it is built (`utime`, and `stime` for an authenticated stateful request), used by `Service::respond()`, `BaseException`, the guards and the cron endpoint.
- The per-IP rate limits that were written in the code are settings: `oz.forms`: `OZ_FORM_RESUME_INIT_IP_RATE` / `OZ_FORM_RESUME_INIT_IP_INTERVAL` (a new group, for `/form/:provider/init`, 30 an hour before), `oz.cron`: `OZ_CRON_WEB_IP_RATE` / `OZ_CRON_WEB_IP_INTERVAL` (`/oz-cron`, 10 a minute before), and `oz.files`: `OZ_UPLOAD_RATE_INTERVAL` (the window the upload rates are counted in) and `OZ_UPLOAD_CHUNK_MAX_SIZE` (the biggest chunk of a chunked upload, `UploadFiles::CHUNK_MAX_SIZE` before; read it with `UploadFiles::chunkMaxSize()`).
- Two-factor authentication could not be completed: the challenge told a client `OZ_2FA_REQUIRED` with `auth_ref: null`, so there was nothing to answer. `AuthorizationProvider::generate()` now publishes the reference and the refresh key in its answer, and the providers add their own keys to it (`setDataKey`) instead of replacing it (`setData`), which is what dropped them. Every flow that opens an authorization now answers `{auth_ref, auth_refresh_key, ...}`: `/auth/verify/email` and `/auth/verify/phone` too, which makes a code flow possible for a client that never sees the link.
- Every authorization failed to save, so `/auth/verify/email`, `/auth/verify/phone` and any flow built on one answered a 500. `AuthorizationScope` starts with no label, nothing set one, and `oz_auths.auth_label` requires at least one character: `AuthorizationProvider::generate()` now names an unlabelled authorization after its provider.
- `POST /upload` answered 500 for every caller. `UploadFiles::upload()` passed the validated files of the form -- objects holding the id -- where the state store and `FS::getFilesByIDs()` want that value, so the query rejected them (`invalid_bigint_type`). It now reads the value of each one.
- Every entry action of a `RESTFulService` failed when `KEY_COLUMN` is a short column name, which is the class's own default (`'id'`). `runAction()` built the key filter from the raw constant while every other use resolves it through the table (`getColumnOrFail()`, which takes a short or a full name), so on a table whose columns carry a prefix -- all of OZone's do -- `get_one`, `update_one`, `delete_one` and `get_relation` answered 500 (`Unknown column 'id' in 'where clause'`). The filter now names the column as the database does.
- A discovered form's `action` said nothing: `Form::toArray()` put the `Uri` instance of `submitTo()` there, and `Uri` has no public property and is not `JsonSerializable`, so every client received an empty `{}` (or `[]`). It is now the **absolute path** (`Uri::getAbsolutePath()`, new: the base path, path, query and fragment, without scheme or authority), so a form knows where it submits and the answer is not tied to the host it was asked on; `null` when the form declares no `submitTo()`.
- `oz deploy run --ref=<commit>` failed ("Remote branch not found"): the checkout was `git clone --depth 1 --branch`, which takes a branch or a tag only, while the option documents a commit. The release now fetches the ref and checks it out, so a full commit hash works.
- `Env::patch()` (and `upset()`) no longer needs a running app: it wrote the file through `app()->getProjectDir()`, so a standalone tool editing a project's `.env` (`OZTestProject::writeEnv()` in a script) failed with "No app is running".
- A completed form session was accepted by any resumable route, letting one flow's data stand in for another's validated input.
- `make cs` failed on a missing `api/` path and disagreed with the php-cs-fixer output of `make fix`.
- The `lang` route param listener was registered every time routes were registered; it is now registered once, in `Polyglot::boot()`. Every `::listen()` call in `oz/` now runs from a `boot()`.
- CORS reflected any request origin together with `Access-Control-Allow-Credentials: true`, so any website could make authenticated requests and read the responses.
- Raw bearer tokens, API keys, submitted passwords and whole form payloads were written to logs through exception data; secret-looking keys are now also redacted from logged and client error data.
- Login had no brute-force protection and revealed whether an account exists.
- `GET /logout?next=` was an open redirect, and any page could log users out.
- `AuthUsers::identifyBySelector()` rejected the `FormDataClean` passed by the login and admin password routes (`TypeError`), breaking both.
- Behind a trusted proxy the client IP could be chosen by the client through `X-Forwarded-For`; `USER_IP` scope IDs included the ephemeral client port, so they changed between requests; IP rate limits ignored proxies and shared one bucket across routes.
- Form session and job refs were derived from `uniqid()` / `microtime()` instead of a CSPRNG.
- Digest auth compared responses in non-constant time.
- Cache drivers unserialized any class from storage, turning write access to the cache table or directory into code execution.
- Mail scanners and link previews could consume auth links.
- CSRF tokens never expired.
- A request from a disallowed origin ran its handler (writes, emails, payments) before being rejected.
- Behind a TLS-terminating proxy, session cookies were sent without `Secure` and the request URL was http.
- The root scope wrote `ozone.log` into the working directory, which can be a public one.
- Digest auth responses could be guessed without ever being locked out.
- Digest auth used whatever nonce the client sent, so a captured `Authorization` header could be replayed until the auth key expired.
- RFC 2617 digest auth could never succeed: the unquoted `nc` and `qop` values were parsed as empty strings.
- Session-authenticated state-changing routes were only CSRF-protected when they opted in.
- The access-grant form never carried its CSRF token, and the auth link confirmation form had none.
- Garbage collection ran on about half of the requests (walking the whole TempFS directory) and the cache collector on every request.
- A full log file was truncated, losing everything logged so far.
- Most message codes had no translation, so clients saw raw codes.
- Behind a proxy that rewrites `Host`, the request URI and `Context::getHost()` disagreed.
- Every complete `POST /login` failed with `OZ_ERROR_INTERNAL`: the fields of `AuthUsers::selectorForm()` had conditions on each other, which no declaration order can satisfy. `auth_user_id` is now optional, and the identifier fields are required when it is absent.
- `OZFile::hasClones()`, and so `canDelete()`, of an unsaved file queried the clones of a null id.
- `saveRaw()` of the local storages stored an empty file: `saveStream()` copied its source from the current position, the end of a stream `FileStream::fromString()` just wrote. Seekable sources are now copied from their start.
- `OZFile::cloneFile()` always failed, and so did alias uploads: it copied the record from `toArray()`, which blanks the storage ref.
- `oz users add` could not run: it required the soft-delete flag, which no command-line value could set (bool columns rejected every string), and prompting for it crashes outside a terminal. Table-built CLI options now leave out soft-delete columns, make columns with a default optional, and parse bool columns (`true` / `false`, `1` / `0`, `yes` / `no`).

## v2.0.0 (2020-01-03)

- OZone was fully refreshed and tested with projects in production since two years

### Backup Commit (2018-07-09)

- OZone name standard
  - O'Zone for the framework name
  - OZone for the php [main class](./oz/OZone.php) name
  - OZONE for php namespace
  - OZ for prefix

### Backup Commit (2017-08-25)

- lol: new commit after a while (about 5 months)
- oz/ path logic change
  - you could have specific oz/ for each project or setup and use a globaly installed one.
- a tons of new feature added
  - WebRoute And OZoneView for website easy build
  - QrCode Authenticator
  - improvement in OZoneRequest
- Working on OZONE CLI: an interactive command line tool for ozone project administration.
- NOTE: OZone version code (v2.0.0) will not change for a while.

### Backup Commit (2017-03-19)

- ozone is reviewed and some change occurs to comply with PSR-4 namespace/class loader logic
- new features added
- code comment (phpdoc): for the first time of my whole coding life (look like wasting time before, but now it is precious, lol)
- Exception is not Error: now I guess (lol)

## v1.0.0 (2017-03-01)

- ozone at the level zero (1.0.0) work well, but not too featured and easy to use as I want

## Project Creation Date (2017-01-19)

- ozone come from base code of my favorite project: Ocaola Social Network
