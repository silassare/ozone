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

namespace OZONE\Tests\Integration\Cli;

use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verifies `oz lang export` in a real project: the catalogs of the enabled languages, merged as the
 * server reads them, for a client that translates the keys the API sends.
 *
 * @internal
 *
 * @coversNothing
 */
final class LangCmdTest extends TestCase
{
	private static ?OZTestProject $proj = null;

	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();

		$proj = OZTestProject::create('lang-export', shared: false);

		$proj->writeEnv([
			'OZ_DB_RDBMS' => 'sqlite',
			'OZ_DB_HOST'  => $proj->getPath() . '/lang_export_test.sqlite',
		]);

		// A text of OZone's the project rewrites, a key of its own, and a language enabled before its
		// catalog is written.
		$proj->setSetting('lang/oz.en', 'OZ_ERROR_INVALID_FORM', 'Please check the form.');
		$proj->setSetting('lang/oz.en', 'MY_APP_WELCOME', 'Welcome, {name}.');
		$proj->setSetting('lang/oz.lang.list', 'ar', true);

		self::$proj = $proj;
	}

	public static function tearDownAfterClass(): void
	{
		self::$proj?->destroy();
		self::$proj = null;

		parent::tearDownAfterClass();
	}

	public function testExportsTheCatalogsMergedAsTheServerReadsThem(): void
	{
		$out  = $this->export();
		$data = $out['data'];

		self::assertSame(0, $out['error']);
		self::assertSame('en', $data['default']);
		self::assertSame(['en', 'fr', 'ar'], $data['languages']);

		// The project's text wins over OZone's, and its own key is there beside OZone's.
		self::assertSame('Please check the form.', $data['catalogs']['en']['OZ_ERROR_INVALID_FORM']);
		self::assertSame('Welcome, {name}.', $data['catalogs']['en']['MY_APP_WELCOME']);
		self::assertSame('Les données envoyées sont invalides.', $data['catalogs']['fr']['OZ_ERROR_INVALID_FORM']);

		// Texts are as written: placeholders are for the client to fill.
		self::assertSame(
			'The fields {field} and {field_confirm} must have the same value.',
			$data['catalogs']['en']['OZ_FIELD_SHOULD_HAVE_SAME_VALUE']
		);
	}

	public function testGivesALanguageWithoutACatalogAnEmptyOne(): void
	{
		$catalogs = $this->export()['data']['catalogs'];

		// Every text of it falls back to the default language, as the server's would.
		self::assertSame([], $catalogs['ar']);
	}

	public function testSaysWhichFiltersTheTextsMayUse(): void
	{
		// OZone declares none; a project's must each have a twin in the client.
		self::assertSame([], $this->export()['data']['filters']);
	}

	public function testSummarisesWithoutJson(): void
	{
		$proc = self::requireProject()->oz('lang', 'export');
		$proc->run();

		self::assertSame(0, $proc->getExitCode(), self::outputOf($proc));

		$text = $proc->getOutput() . $proc->getErrorOutput();

		self::assertStringContainsString('Default language: en', $text);
		self::assertStringContainsString('ar: enabled, but no catalog', $text);
	}

	public function testRefusesATextThatDoesNotFollowTheSyntax(): void
	{
		$proj = self::requireProject();

		$proj->setSetting('lang/oz.en', 'MY_APP_BROKEN', 'Hello {name');

		try {
			$proc = $proj->oz('lang', 'export', '--json');
			$proc->run();

			$out = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

			self::assertSame(1, $proc->getExitCode(), self::outputOf($proc));
			self::assertSame(1, $out['error']);
			self::assertSame('OZ_LANG_CATALOG_INVALID', $out['msg']);
			self::assertSame(
				[['lang' => 'en', 'key' => 'MY_APP_BROKEN']],
				\array_map(
					static fn (array $e): array => ['lang' => $e['lang'], 'key' => $e['key']],
					$out['data']['errors']
				)
			);

			// The doctor says so too.
			$doctor = $proj->oz('doctor', 'check', '--json');
			$doctor->run();

			$checks = \array_column(
				\json_decode($doctor->getOutput(), true, 512, \JSON_THROW_ON_ERROR)['data']['checks'],
				'status',
				'name'
			);

			self::assertSame('fail', $checks['Translations']);
		} finally {
			$proj->setSetting('lang/oz.en', 'MY_APP_BROKEN', 'Hello {name}');
		}
	}

	/**
	 * @return array<string, mixed>
	 */
	private function export(): array
	{
		$proc = self::requireProject()->oz('lang', 'export', '--json');
		$proc->run();

		self::assertSame(0, $proc->getExitCode(), self::outputOf($proc));
		self::assertJson($proc->getOutput(), self::outputOf($proc));

		$out = \json_decode($proc->getOutput(), true, 512, \JSON_THROW_ON_ERROR);

		self::assertIsArray($out);

		return $out;
	}

	private static function requireProject(): OZTestProject
	{
		return self::$proj ?? throw new \LogicException('The project was not created.');
	}

	private static function outputOf(Process $proc): string
	{
		return \sprintf("stdout:\n%s\nstderr:\n%s", $proc->getOutput(), $proc->getErrorOutput());
	}
}
