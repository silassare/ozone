<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace OZONE\Tests\Integration\Support;

use JsonException;
use OZONE\Core\Utils\Env;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Helper that manages a throwaway OZone project inside /tmp/_oz_tests_/projects/{name}/.
 *
 * Vendor caching:
 *   - A SHA-256 hash is computed from the project's effective require + require-dev.
 *   - If /tmp/_oz_tests_/_vendors_cache_/{hash}/ already exists, vendor/ is symlinked there
 *     -- no composer install needed.
 *   - Otherwise composer install runs, the resulting vendor/ is moved to the cache
 *     dir, then symlinked back in. Subsequent projects with the same dep set reuse
 *     the cache instantly.
 *   - The ozone root is always added as a path repository so silassare/ozone is
 *     resolved locally without any download.
 *
 * Usage:
 *
 *   $proj = OZTestProject::create('my-test');
 *   $proj->writeEnv((new DbTestConfig())->toEnvArray());
 *   $proj->oz('migrations', 'run')->mustRun();
 *   $proj->destroy();
 */
final class OZTestProject
{
	private function __construct(private readonly string $dir) {}

	/**
	 * Creates a test project in /tmp/_oz_tests_/projects/{name}/ and returns a handle.
	 *
	 * Composer install only runs when the effective dependency set has never
	 * been seen before - otherwise the cached vendor/ directory is symlinked.
	 *
	 * @param string               $name   project directory name (slug)
	 * @param array<string,string> $deps   extra require packages (name -> constraint)
	 * @param bool                 $shared when true the vendor cache is shared with
	 *                                     other projects that have the same dep set;
	 *                                     pass false to isolate vendor per project name;
	 *                                     in most cases tests should set shared=true so that composer installs are minimized
	 * @param bool                 $fresh  when true, any existing project directory is destroyed before creating;
	 *                                     use when the test must start from a clean slate on every run
	 *                                     (e.g. tests that call migrations create + run inline)
	 *
	 * @throws JsonException
	 */
	public static function create(
		string $name,
		array $deps = [],
		bool $shared = true,
		bool $fresh = false,
	): static {
		$ozone_root  = self::ozoneRoot();
		$project_dir = self::projectsDir() . \DIRECTORY_SEPARATOR . $name;

		// Destroy any existing project directory when a clean slate is requested.
		if ($fresh && \is_dir($project_dir)) {
			(new self($project_dir))->destroy();
		}

		if (!\is_dir($project_dir)) {
			\mkdir($project_dir, 0o775, true);
		}

		// -- Step 1: scaffold project (idempotent) ----------------------------
		if (!\file_exists($project_dir . \DIRECTORY_SEPARATOR . 'app' . \DIRECTORY_SEPARATOR . 'boot.php')) {
			$namespace = self::toNamespace($name);
			$prefix    = self::toPrefix($name);

			$create = new Process(
				[
					\PHP_BINARY,
					$ozone_root . \DIRECTORY_SEPARATOR . 'bin' . \DIRECTORY_SEPARATOR . 'oz',
					'project',
					'create',
					"--root-dir={$project_dir}",
					"--name={$name}",
					"--namespace={$namespace}",
					'--class-name=SampleApp',
					"--prefix={$prefix}",
				],
				$ozone_root,
			);
			$create->mustRun();
		}

		// -- Step 2: patch composer.json --------------------------------------
		$composer_file = $project_dir . \DIRECTORY_SEPARATOR . 'composer.json';
		$composer      = \json_decode(
			\file_get_contents($composer_file),
			true,
			512,
			\JSON_THROW_ON_ERROR,
		);

		// Path repository -> silassare/ozone resolved from local ozone root,
		// vendor/silassare/ozone will be a symlink to the ozone root.
		$composer['repositories'] = [[
			'type'    => 'path',
			'url'     => $ozone_root,
			'options' => ['symlink' => true],
		]];

		// Allow any version of ozone so the path-repo (which exports dev-main)
		// satisfies the constraint regardless of the exact version written in
		// the generated composer.json.
		$composer['require']['silassare/ozone'] = '*';

		// Composer requires PSR-4 namespace prefixes to end with '\'.
		// The project generator omits the trailing backslash, so add it here.
		if (isset($composer['autoload']['psr-4'])) {
			$psr4 = [];
			foreach ($composer['autoload']['psr-4'] as $prefix => $paths) {
				$key        = \str_ends_with($prefix, '\\') ? $prefix : $prefix . '\\';
				$psr4[$key] = $paths;
			}
			$composer['autoload']['psr-4'] = $psr4;
		}

		foreach ($deps as $pkg => $ver) {
			$composer['require'][$pkg] = $ver;
		}

		\ksort($composer['require']);

		// -- Step 3: compute vendor cache hash --------------------------------
		// Include a fingerprint of the ozone composer.lock so that any upstream
		// dependency update (e.g. kli, gobl, php-utils) that does not change the
		// project's require constraints still busts the cache and triggers a fresh
		// composer install.
		$lock_file  = $ozone_root . \DIRECTORY_SEPARATOR . 'composer.lock';
		$lock_hash  = \is_readable($lock_file) ? \md5_file($lock_file) : '';
		$hash_input = [
			'require' => $composer['require'] ?? [],
			'lock'    => $lock_hash,
		];
		if (!$shared) {
			$hash_input['project'] = $name;
		}
		$hash = \hash('sha256', \json_encode($hash_input, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES));

		$cache_dir      = self::vendorsCacheDir() . \DIRECTORY_SEPARATOR . $hash;
		$vendor_symlink = $project_dir . \DIRECTORY_SEPARATOR . 'vendor';

		\file_put_contents(
			$composer_file,
			\json_encode($composer, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES),
		);

		// -- Step 4: vendor caching -------------------------------------------
		if (!\is_link($vendor_symlink) && !\is_dir($vendor_symlink)) {
			if (\is_dir($cache_dir)) {
				\symlink($cache_dir, $vendor_symlink);
			} else {
				// First time this dep set is seen: install, cache, symlink.
				// Composer install can take several minutes on a cold network - use a generous timeout.
				// --no-dev: sub-projects only run oz CLI commands; dev packages are never needed.
				$install = new Process(
					['composer', 'install', '--no-dev', '--no-interaction', '--no-progress'],
					$project_dir,
					timeout: 300,
				);
				$install->mustRun();

				if (!\is_dir(\dirname($cache_dir))) {
					\mkdir(\dirname($cache_dir), 0o775, true);
				}
				\rename($vendor_symlink, $cache_dir);
				\symlink($cache_dir, $vendor_symlink);
			}
		}

		return new self($project_dir);
	}

	/**
	 * Build a Process that runs an oz CLI command inside the test project.
	 *
	 * The process is returned but NOT started - call mustRun() or run() on it.
	 *
	 * @param string ...$args e.g. 'migrations', 'run'
	 */
	public function oz(string ...$args): Process
	{
		return new Process(
			[
				\PHP_BINARY,
				self::ozoneRoot() . \DIRECTORY_SEPARATOR . 'bin' . \DIRECTORY_SEPARATOR . 'oz',
				...$args,
			],
			$this->dir,
		);
	}

	/**
	 * Write (or overwrite) key-value pairs in the project .env file.
	 *
	 * Existing keys are updated in-place; new keys are appended.
	 * This is the preferred way to inject DB credentials and similar
	 * runtime config - the generated oz.db.php already reads from .env.
	 *
	 * @param array<string, string> $values
	 */
	public function writeEnv(array $values): void
	{
		$env_file = $this->dir . \DIRECTORY_SEPARATOR . '.env';

		if (!\is_file($env_file)) {
			\file_put_contents($env_file, '');
		}

		(new Env($env_file))->patch($values);
	}

	/**
	 * Override a single settings key in the test project by writing
	 * (or updating) app/settings/{group}.php.
	 *
	 * Multiple calls for the same group are safe - the file is read, merged,
	 * and rewritten each time.
	 *
	 * For most tests, prefer {@see writeEnv()} to inject DB config instead.
	 */
	public function setSetting(string $group, string $key, mixed $value): void
	{
		$dir  = $this->dir . \DIRECTORY_SEPARATOR . 'app' . \DIRECTORY_SEPARATOR . 'settings' . \DIRECTORY_SEPARATOR;
		$file = $dir . $group . '.php';

		$current = \is_file($file) ? (require $file) : [];
		if (!\is_array($current)) {
			throw new RuntimeException(\sprintf('Settings file "%s" does not return an array.', $file));
		}
		$current[$key] = $value;

		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}

		$content = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
			. \var_export($current, true)
			. ";\n";
		\file_put_contents($file, $content);
	}

	/**
	 * Absolute path to the test project root directory.
	 */
	public function getPath(): string
	{
		return $this->dir;
	}

	/**
	 * Returns the PHP namespace derived from the project directory name.
	 *
	 * Mirrors the logic used by {@see self::create()} when invoking `oz project create`.
	 */
	public function getNamespace(): string
	{
		return self::toNamespace(\basename($this->dir));
	}

	/**
	 * Writes (or overwrites) a file inside the test project directory.
	 *
	 * The target path is relative to the project root. Parent directories are
	 * created automatically.
	 *
	 * @param string $relativePath relative path inside the project (e.g. 'app/MyClass.php')
	 * @param string $content      file contents to write
	 */
	public function writeFile(string $relativePath, string $content): void
	{
		$full = $this->dir
			. \DIRECTORY_SEPARATOR
			. \ltrim(\str_replace('/', \DIRECTORY_SEPARATOR, $relativePath), \DIRECTORY_SEPARATOR);
		$dir = \dirname($full);

		if (!\is_dir($dir)) {
			\mkdir($dir, 0o775, true);
		}
		\file_put_contents($full, $content);
	}

	/**
	 * Starts `oz project serve` for the given scope and waits until it accepts connections.
	 *
	 * @param string $scope the scope name (default 'api')
	 * @param string $host  the host to bind to (default '127.0.0.1')
	 *
	 * @return array{0: Process, 1: string, 2: int} [$server_process, $host, $port]
	 *
	 * @throws RuntimeException when the server does not start within the timeout
	 */
	public function startServer(string $scope = 'api', string $host = '127.0.0.1'): array
	{
		$server = $this->oz('project', 'serve', "--scope={$scope}", "--host={$host}");
		$server->start();

		// oz project serve writes server.json before binding the port.
		$server_json = $this->dir . "/.ozone/cache/scopes/{$scope}/server.json";
		$deadline    = \microtime(true) + 15.0;
		while (!\is_file($server_json) && \microtime(true) < $deadline) {
			\usleep(100_000);
		}

		if (!\is_file($server_json)) {
			$server->stop(0);

			throw new RuntimeException(
				"oz project serve (scope={$scope}) did not write server.json within 15 seconds."
			);
		}

		$info = \json_decode(\file_get_contents($server_json), true);
		$port = (int) ($info['port'] ?? 0);

		if (0 === $port) {
			$server->stop(0);

			throw new RuntimeException('server.json did not contain a valid port.');
		}

		// Poll until the port is actually accepting connections.
		$ready    = false;
		$deadline = \microtime(true) + 10.0;
		while (\microtime(true) < $deadline) {
			$sock = @\fsockopen($host, $port, $errno, $errstr, 0.1);
			if (\is_resource($sock)) {
				\fclose($sock);
				$ready = true;

				break;
			}
			\usleep(100_000);
		}

		if (!$ready) {
			$server->stop(0);

			throw new RuntimeException(
				"PHP built-in server (scope={$scope}) did not start within 10 seconds."
			);
		}

		return [$server, $host, $port];
	}

	/**
	 * Delete the test project directory.
	 *
	 * The vendor/ symlink is unlinked first so the cache directory is not
	 * touched by the recursive delete.
	 */
	public function destroy(): void
	{
		$vendor = $this->dir . \DIRECTORY_SEPARATOR . 'vendor';
		if (\is_link($vendor)) {
			\unlink($vendor);
		}
		(new Process(['rm', '-rf', $this->dir]))->run();
	}

	/**
	 * Drops all tables in the project's database (MySQL / PostgreSQL).
	 *
	 * Call this before running `oz migrations run` when the project reuses a
	 * shared server-side database.  MySQL enforces globally-unique FK
	 * constraint names within a database (e.g. `fk_oz_users_oz_usernames`),
	 * so leftover tables from a previous project with the same schema cause
	 * "Duplicate foreign key constraint name" errors.  Dropping everything
	 * first gives each test run a clean slate.
	 *
	 * No-op for SQLite (each SQLite test already uses a unique file).
	 */
	public function cleanDb(): void
	{
		$env_file = $this->dir . \DIRECTORY_SEPARATOR . '.env';
		if (!\is_file($env_file)) {
			return;
		}

		$env   = new Env($env_file);
		$rdbms = (string) ($env->get('OZ_DB_RDBMS') ?? 'sqlite');

		if ('sqlite' === $rdbms) {
			return; // SQLite uses a unique file per test class / run.
		}

		$host = (string) ($env->get('OZ_DB_HOST') ?? '127.0.0.1');
		$name = (string) ($env->get('OZ_DB_NAME') ?? '');
		$user = (string) ($env->get('OZ_DB_USER') ?? '');
		$pass = (string) ($env->get('OZ_DB_PASS') ?? '');

		if ('mysql' === $rdbms) {
			$port = (int) ($env->get('OZ_DB_PORT') ?? 3306);
			$pdo  = new PDO(
				"mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
				$user,
				$pass,
				[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
			);
			$pdo->exec('SET foreign_key_checks = 0');
			$tables = $pdo->query(
				'SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE()'
			)->fetchAll(PDO::FETCH_COLUMN);
			foreach ($tables as $table) {
				$pdo->exec("DROP TABLE IF EXISTS `{$table}`");
			}
			$pdo->exec('SET foreign_key_checks = 1');
		} elseif ('postgresql' === $rdbms) {
			$port = (int) ($env->get('OZ_DB_PORT') ?? 5432);
			$pdo  = new PDO(
				"pgsql:host={$host};port={$port};dbname={$name}",
				$user,
				$pass,
				[PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
			);
			// Drop each table individually with CASCADE to avoid exceeding
			// max_locks_per_transaction that DROP SCHEMA CASCADE would require.
			$tables = $pdo->query(
				"SELECT tablename FROM pg_tables WHERE schemaname = 'public'"
			)->fetchAll(PDO::FETCH_COLUMN);
			foreach ($tables as $table) {
				$quoted = '"' . \str_replace('"', '""', (string) $table) . '"';
				$pdo->exec("DROP TABLE IF EXISTS {$quoted} CASCADE");
			}
		}
	}

	/**
	 * Absolute path to the OZone repository root.
	 * tests/Integration/Support/ -> tests/Integration/ -> tests/ -> ozone root.
	 */
	private static function ozoneRoot(): string
	{
		return \dirname(__DIR__, 3);
	}

	/**
	 * /tmp/_oz_tests_/_vendors_cache_/.
	 */
	private static function vendorsCacheDir(): string
	{
		return '/tmp/_oz_tests_/_vendors_cache_';
	}

	/**
	 * /tmp/_oz_tests_/projects/.
	 */
	private static function projectsDir(): string
	{
		return '/tmp/_oz_tests_/projects';
	}

	/**
	 * Derives a PHP namespace from a project slug (e.g. "my-test" -> "MyTest").
	 */
	private static function toNamespace(string $name): string
	{
		return \preg_replace('/[^a-zA-Z0-9]/', '', \ucwords(\str_replace(['-', '_'], ' ', $name)));
	}

	/**
	 * Derives a 2-char uppercase project prefix from a project slug.
	 */
	private static function toPrefix(string $name): string
	{
		$alpha = \preg_replace('/[^a-zA-Z]/', '', $name);

		return \strtoupper(\substr($alpha ?: 'OZ', 0, 2));
	}
}
