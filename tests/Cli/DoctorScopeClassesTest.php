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

namespace OZONE\Tests\Cli;

use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Cmd\DoctorCmd;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class DoctorScopeClassesTest.
 *
 * A class declared by two scopes in their own directories: a process calling from one into the
 * other runs whichever it loaded first, so `oz doctor check` says which class and which scopes.
 *
 * @internal
 */
#[CoversClass(DoctorCmd::class)]
final class DoctorScopeClassesTest extends TestCase
{
	/** @var list<string> the scope directories this test made, removed after it */
	private array $scopes = [];

	#[Override]
	protected function tearDown(): void
	{
		foreach ($this->scopes as $dir) {
			foreach (\glob($dir . '/{,settings/}*.php', \GLOB_BRACE) ?: [] as $file) {
				\unlink($file);
			}

			@\rmdir($dir . '/settings');
			\rmdir($dir);
		}

		$this->scopes = [];

		parent::tearDown();
	}

	public function testAClassTwoScopesDeclareIsReported(): void
	{
		$class = self::scope('dup-one', ['Shared', 'OnlyOne']);

		self::scope('dup-two', ['Shared']);

		$rows = \array_values(\array_filter(
			DoctorCmd::scopeClassesChecks(),
			static fn (array $row): bool => \str_contains($row['detail'], 'Shared')
		));

		self::assertCount(1, $rows);
		self::assertSame('warn', $rows[0]['status']);
		self::assertSame($class . 'Shared is declared by the scopes dup-one, dup-two', $rows[0]['detail']);
	}

	public function testClassesOfOneScopeEachAndSettingsFilesAreNot(): void
	{
		self::scope('own-one', ['Alone']);
		self::scope('own-two', ['Lonely']);

		// The scopes' settings files, the same in both: groups, not classes.
		foreach ($this->scopes as $dir) {
			\mkdir($dir . '/settings');
			\file_put_contents($dir . '/settings/sameGroup.php', "<?php\n\nreturn [];\n");
		}

		$rows = DoctorCmd::scopeClassesChecks();

		self::assertSame(
			[['name' => 'Scope classes', 'status' => 'ok', 'detail' => 'no class declared by two scopes', 'fix' => '']],
			$rows
		);
	}

	/**
	 * A scope of the sandbox holding a file per class name.
	 *
	 * @param list<string> $classes
	 *
	 * @return string the project namespace, with its trailing backslash
	 */
	private function scope(string $name, array $classes): string
	{
		$dir = app()->getProjectDir()->resolve('scopes') . '/' . $name;

		\mkdir($dir, 0o775, true);

		$this->scopes[] = $dir;

		foreach ($classes as $class) {
			\file_put_contents($dir . '/' . $class . '.php', "<?php\n");
		}

		return \trim((string) Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE'), '\\') . '\\';
	}
}
