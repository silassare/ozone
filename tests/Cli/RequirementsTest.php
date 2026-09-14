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

use OZONE\Core\Cli\Utils\Requirements;
use PHPUnit\Framework\TestCase;

/**
 * Class RequirementsTest.
 *
 * @internal
 *
 * @covers \OZONE\Core\Cli\Utils\Requirements
 */
final class RequirementsTest extends TestCase
{
	public function testOzoneComposerFileIsReadable(): void
	{
		$file = Requirements::ozoneComposerFile();

		self::assertFileExists($file);
		self::assertSame('composer.json', \basename($file));
	}

	public function testPhpConstraintComesFromComposerJson(): void
	{
		$data = \json_decode((string) \file_get_contents(Requirements::ozoneComposerFile()), true);

		self::assertSame($data['require']['php'], Requirements::phpConstraint());
	}

	public function testMinPhpVersionIsExtractedFromTheConstraint(): void
	{
		self::assertMatchesRegularExpression('~^\d+\.\d+(\.\d+)?$~', Requirements::minPhpVersion());
	}

	public function testPhpVersionIsSatisfiedByTheRunningPhp(): void
	{
		// The suite could not have booted otherwise.
		self::assertTrue(Requirements::phpVersionSatisfied());
	}

	public function testExtensionsAreReadFromComposerJson(): void
	{
		$data     = \json_decode((string) \file_get_contents(Requirements::ozoneComposerFile()), true);
		$expected = [];

		foreach (\array_keys($data['require']) as $package) {
			if (\str_starts_with((string) $package, 'ext-')) {
				$expected[] = \substr((string) $package, 4);
			}
		}

		\sort($expected);

		$found = Requirements::extensions();

		self::assertNotEmpty($found);
		self::assertSame($expected, \array_values(\array_intersect($found, $expected)));
		self::assertNotContains('ext-pdo', $found, 'The ext- prefix must be stripped.');
		self::assertContains('pdo', $found);
	}

	public function testExtensionsHaveNoDuplicates(): void
	{
		$found = Requirements::extensions();

		self::assertSame(\array_values(\array_unique($found)), $found);
	}

	public function testNoRequiredExtensionIsMissingHere(): void
	{
		// The suite runs OZone, so every required extension has to be loaded.
		self::assertSame([], Requirements::missingExtensions());
	}
}
