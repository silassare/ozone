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

namespace OZONE\Tests\Plugins;

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Hooks\Events\DbSchemaCollectHook;
use OZONE\Core\Plugins\AbstractPlugin;
use OZONE\Core\Plugins\CorePlugin;
use OZONE\Core\Plugins\Plugins;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Class PluginSourcesTest.
 *
 * What a plugin ships -- its settings and its tables -- named rather than added from code, so its
 * boot adds them and `Plugins::describe()` reads them without booting it.
 *
 * @internal
 */
#[CoversClass(AbstractPlugin::class)]
#[CoversClass(Plugins::class)]
final class PluginSourcesTest extends TestCase
{
	private static string $dir;

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$dir = \sys_get_temp_dir() . '/oz_plugin_sources_' . \bin2hex(\random_bytes(6));

		\mkdir(self::$dir . '/settings', 0o775, true);
		\mkdir(self::$dir . '/listed', 0o775, true);

		\file_put_contents(self::$dir . '/composer.json', \json_encode([
			'name'    => 'acme/notes',
			'version' => '2.0.1',
			'require' => ['php' => '>=8.4', 'silassare/ozone' => '*'],
		]));
		\file_put_contents(
			self::$dir . '/settings/oz.notes.php',
			"<?php return ['OZ_NOTES_LIMIT' => 10, 'OZ_NOTES_SHARED' => true];"
		);
		\file_put_contents(self::$dir . '/schema.php', <<<'PHP'
			<?php

			use Gobl\DBAL\Builders\NamespaceBuilder;

			return static function (NamespaceBuilder $ns): void {
				$ns->schema([
					'acme_notes' => [
						'singular_name' => 'acme_note',
						'plural_name'   => 'acme_notes',
						'column_prefix' => 'note',
						'columns'       => [
							'id'   => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
							'text' => ['type' => 'string', 'max' => 200],
						],
						'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
					],
				]);
			};
			PHP);
		// What a project lists: OZone, and this plugin enabled.
		\file_put_contents(
			self::$dir . '/listed/oz.plugins.php',
			'<?php return [' . \var_export(NotesPlugin::class, true) . ' => true];'
		);

		NotesPlugin::$root = self::$dir;
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (['settings/oz.notes.php', 'listed/oz.plugins.php', 'schema.php', 'composer.json'] as $file) {
			\unlink(self::$dir . '/' . $file);
		}

		\rmdir(self::$dir . '/settings');
		\rmdir(self::$dir . '/listed');
		\rmdir(self::$dir);

		parent::tearDownAfterClass();
	}

	/**
	 * In a process of its own: a boot registers a schema listener for the rest of the process, and
	 * every other test collecting a schema would be handed this plugin's table.
	 */
	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testAnEnabledPluginsBootAddsItsSettingsAndItsTables(): void
	{
		Settings::addSource(self::$dir . '/listed');
		Settings::load('oz.plugins', true);

		NotesPlugin::instance()->boot();

		self::assertSame(10, Settings::get('oz.notes', 'OZ_NOTES_LIMIT'));

		$db = self::memoryDb();

		new DbSchemaCollectHook($db)->dispatch();

		// In the plugin's own namespace, as its ORM classes are generated in.
		self::assertSame(['acme_notes'], \array_keys($db->getTables(NotesPlugin::instance()->getDbNamespace())));
	}

	public function testAPluginIsDescribedWithoutBootingIt(): void
	{
		$described = Plugins::describe(
			NotesPlugin::instance(),
			[CorePlugin::class => true, NotesPlugin::class => false]
		);

		self::assertSame([
			'class'       => NotesPlugin::class,
			'name'        => 'notes',
			'package'     => 'acme/notes',
			'version'     => '2.0.1',
			'description' => '',
			'author'      => '',
			'enabled'     => false,
			// OZone, required through Composer; `php` is Composer's alone.
			'requires'    => [['plugin' => CorePlugin::class, 'package' => 'silassare/ozone', 'enabled' => true]],
			'settings'    => ['oz.notes' => ['OZ_NOTES_LIMIT', 'OZ_NOTES_SHARED']],
			'tables'      => ['acme_notes'],
		], $described);

		// And nothing was booted: its settings are not read.
		self::assertFalse(Settings::has('oz.notes'));
	}

	public function testOZoneIsDescribedAsAnyPlugin(): void
	{
		$described = Plugins::describe(Plugins::ozone(), [CorePlugin::class => true]);

		self::assertSame('silassare/ozone', $described['package']);
		self::assertContains('oz_users', $described['tables']);
		self::assertContains('oz_files', $described['tables']);
		self::assertArrayHasKey('oz.routes.api', $described['settings']);
		self::assertSame([], $described['requires']);
	}

	private static function memoryDb(): Db
	{
		return Db::newInstanceOf(SQLite::NAME, new DbConfig([
			'db_host'    => ':memory:',
			'db_port'    => '',
			'db_name'    => '',
			'db_user'    => '',
			'db_pass'    => '',
			'db_charset' => 'utf8',
			'db_collate' => 'utf8',
		]));
	}
}

/**
 * A plugin shipping settings and a table.
 *
 * @internal
 */
final class NotesPlugin extends AbstractPlugin
{
	public static string $root = '';

	public function __construct()
	{
		parent::__construct('notes', 'Acme\Notes', self::$root);
	}

	#[Override]
	public static function instance(): static
	{
		return new self();
	}

	#[Override]
	public function getSettingsSource(): string
	{
		return self::$root . '/settings';
	}

	#[Override]
	public function getSchemaFile(): string
	{
		return self::$root . '/schema.php';
	}
}
