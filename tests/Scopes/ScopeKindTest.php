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
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Scopes\ScopeKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class ScopeKindTest.
 *
 * Whether a scope serves the API or the web, as its record says, for what needs to know it without
 * serving it.
 *
 * @internal
 */
#[CoversClass(ScopeKind::class)]
final class ScopeKindTest extends TestCase
{
	private string $project = '';

	#[Override]
	protected function setUp(): void
	{
		parent::setUp();

		$this->project = \sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'oz_scope_kind_' . \bin2hex(\random_bytes(6));

		foreach (['scopes/site', 'scopes/broken', 'scopes/bare', 'public/site', 'public/service'] as $dir) {
			\mkdir($this->project . '/' . $dir, 0o775, true);
		}
	}

	#[Override]
	protected function tearDown(): void
	{
		$files = \glob($this->project . '/{scopes,public}/*/*', \GLOB_BRACE) ?: [];

		foreach ($files as $file) {
			\unlink($file);
		}

		foreach (\glob($this->project . '/{scopes,public}/*', \GLOB_BRACE | \GLOB_ONLYDIR) ?: [] as $dir) {
			\rmdir($dir);
		}

		@\rmdir($this->project . '/scopes');
		@\rmdir($this->project . '/public');
		@\rmdir($this->project);

		parent::tearDown();
	}

	public function testAWrittenRecordIsReadBack(): void
	{
		ScopeKind::WEB->write($this->project, 'site');

		self::assertSame(ScopeKind::WEB, ScopeKind::read($this->project, 'site'));
		self::assertSame(
			['kind' => 'web'],
			\json_decode((string) \file_get_contents($this->project . '/scopes/site/scope.json'), true)
		);
	}

	public function testAScopeWithoutARecordIsRefusedNamingTheCommandThatWritesIt(): void
	{
		self::assertNull(ScopeKind::find($this->project, 'bare'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageMatches('~"bare".*oz project link~s');

		ScopeKind::read($this->project, 'bare');
	}

	public function testARecordThatSaysNoKindIsNone(): void
	{
		\file_put_contents($this->project . '/scopes/broken/scope.json', '{"kind": "desktop"}');

		self::assertNull(ScopeKind::find($this->project, 'broken'));

		\file_put_contents($this->project . '/scopes/broken/scope.json', 'not json');

		self::assertNull(ScopeKind::find($this->project, 'broken'));
	}

	/**
	 * What `oz project link` records for a scope made before the record: what its entry point says,
	 * which defines `OZ_OZONE_IS_WEB_CONTEXT` for a web scope only.
	 */
	public function testTheEntryPointTellsTheKindOfAScopeMadeBeforeTheRecord(): void
	{
		\file_put_contents(
			$this->project . '/public/site/index.php',
			"<?php\n\\define('OZ_OZONE_IS_WEB_CONTEXT', true);\n"
		);
		\file_put_contents($this->project . '/public/service/index.php', "<?php\n");

		self::assertSame(ScopeKind::WEB, ScopeKind::fromEntryPoint($this->project, 'site'));
		self::assertSame(ScopeKind::API, ScopeKind::fromEntryPoint($this->project, 'service'));
		self::assertNull(ScopeKind::fromEntryPoint($this->project, 'bare'));
	}
}
