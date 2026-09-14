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

namespace OZONE\Tests\Scopes;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\Scopes\AbstractScope;
use OZONE\Core\Scopes\StateLayout;
use PHPUnit\Framework\TestCase;

/**
 * Class AbstractScopeTest.
 *
 * The scope directory is left behind on purpose: it stays registered as a settings source
 * for the rest of the process.
 *
 * @internal
 *
 * @covers \OZONE\Core\Scopes\AbstractScope
 */
final class AbstractScopeTest extends TestCase
{
	private static StubScope $scope;

	private static string $root;

	/** @var list<string> every directory this class created, removed in tearDownAfterClass() */
	private static array $roots = [];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		self::$root  = self::newRoot('scope');
		self::$scope = new StubScope(self::$root);
	}

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$roots as $root) {
			self::rmdir($root);
		}

		self::$roots = [];

		parent::tearDownAfterClass();
	}

	public function testDirectoriesDeriveFromTheScopeRoots(): void
	{
		$scope = self::$scope;
		$root  = self::$root;

		self::assertSame('stub', $scope->getName());
		self::assertSame(self::path("{$root}/sources/settings"), self::path($scope->getSettingsDir()->getRoot()));
		self::assertSame(self::path("{$root}/sources/templates"), self::path($scope->getTemplatesDir()->getRoot()));
		// data/{kind}/{scope}: kind first, because that is the level a backup rule and a container
		// volume are written at.
		self::assertSame(self::path("{$root}/data/settings/stub"), self::path($scope->getStatefulSettingsDir()->getRoot()));
		self::assertSame(self::path("{$root}/data/files/stub"), self::path($scope->getPrivateFilesDir()->getRoot()));
		self::assertSame(self::path("{$root}/data/static/stub"), self::path($scope->getPublicFilesDir()->getRoot()));
		self::assertSame(self::path("{$root}/data/tmp-fs/stub"), self::path($scope->getTempDir()->getRoot()));
		self::assertSame(self::path("{$root}/data/state/stub"), self::path($scope->getStateStoreDir()->getRoot()));

		// The document root holds the entry point and the symlink, never the files themselves.
		self::assertSame(self::path("{$root}/public"), self::path($scope->getDocumentRootDir()->getRoot()));
		self::assertSame(self::path("{$root}/public"), self::path($scope->getPublicDir()->getRoot()));
		self::assertSame(
			self::path(\rtrim(app()->getProjectDir()->getRoot(), '/\\') . '/.ozone/logs'),
			self::path($scope->getLogsDir()->getRoot())
		);
	}

	public function testThePublicLinkPointsFromTheDocumentRootToTheFiles(): void
	{
		$scope = self::$scope;

		self::assertSame(
			self::path($scope->getDocumentRootDir()->getRoot() . '/static'),
			self::path(StateLayout::publicLinkPath($scope))
		);

		self::assertSame('created', StateLayout::link($scope));
		self::assertSame('ok', StateLayout::link($scope), 'Linking twice must be a no-op.');

		$link = StateLayout::publicLinkPath($scope);

		self::assertTrue(\is_link($link));
		self::assertSame(
			\realpath($scope->getPublicFilesDir()->getRoot()),
			\realpath((string) \readlink($link))
		);
	}

	public function testAPublicLinkBlockedByRealContentIsReportedNotDeleted(): void
	{
		$scope = new StubScope(self::newRoot('blocked'));
		$path  = StateLayout::publicLinkPath($scope);

		\mkdir($path, 0o775, true);
		\file_put_contents($path . '/keep.txt', 'do not delete me');

		self::assertSame('blocked', StateLayout::link($scope));
		self::assertFileExists($path . '/keep.txt');
	}

	public function testEnsureCreatesEveryKind(): void
	{
		$scope = new StubScope(self::newRoot('ensure'));

		$created = StateLayout::ensure($scope);

		self::assertCount(\count(StateLayout::kinds()), $created);

		foreach ($created as $dir) {
			self::assertDirectoryExists($dir);
		}
	}

	public function testStatefulSettingsDirIsASettingsSource(): void
	{
		\file_put_contents(
			self::$scope->getStatefulSettingsDir()->getRoot() . '/oz.stub.scope.php',
			"<?php\n\nreturn ['OZ_STUB_SCOPE_KEY' => 'from-scope'];\n"
		);

		self::assertSame('from-scope', Settings::get('oz.stub.scope', 'OZ_STUB_SCOPE_KEY'));
	}

	private static function path(string $path): string
	{
		return \rtrim(\str_replace('\\', '/', $path), '/');
	}

	/**
	 * A fresh directory for a scope, remembered so tearDownAfterClass() removes it.
	 */
	private static function newRoot(string $label): string
	{
		$root = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_scope_' . $label . '_' . \bin2hex(\random_bytes(6));

		\mkdir($root, 0o775, true);

		self::$roots[] = $root;

		return $root;
	}

	private static function rmdir(string $dir): void
	{
		if (!\is_dir($dir)) {
			return;
		}

		foreach (\scandir($dir) ?: [] as $entry) {
			if ('.' === $entry || '..' === $entry) {
				continue;
			}

			$path = $dir . \DIRECTORY_SEPARATOR . $entry;

			\is_dir($path) && !\is_link($path) ? self::rmdir($path) : @\unlink($path);
		}

		@\rmdir($dir);
	}
}

/**
 * A scope rooted in a test directory.
 *
 * @internal
 */
final class StubScope extends AbstractScope
{
	public function __construct(private readonly string $root)
	{
		parent::__construct();
	}

	#[Override]
	public function getName(): string
	{
		return 'stub';
	}

	#[Override]
	public function getSourcesDir(): FilesManager
	{
		return FS::from($this->root)->cd('sources', true);
	}

	#[Override]
	public function getDataDir(): FilesManager
	{
		return FS::from($this->root)->cd('data', true);
	}

	#[Override]
	public function getDocumentRootDir(): FilesManager
	{
		return FS::from($this->root)->cd('public', true);
	}

	#[Override]
	public function getCacheDir(): FilesManager
	{
		return FS::from($this->root)->cd('cache', true);
	}
}
