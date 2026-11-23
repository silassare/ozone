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
use OZONE\Core\Cli\Deploy\DeployInitializer;
use OZONE\Core\Scopes\Interfaces\ScopeInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The `Public files` row of `oz doctor check`: what base direct links use, and a failure when direct
 * access is on without a usable one.
 *
 * @internal
 */
#[CoversClass(DoctorCmd::class)]
#[CoversClass(DeployInitializer::class)]
final class DoctorPublicFilesTest extends TestCase
{
	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED');
		Settings::unset('oz.files', 'OZ_PUBLIC_FILES_BASE_URL');
	}

	public function testWithDirectAccessOffTheFilesGoThroughTheRoute(): void
	{
		self::assertSame(
			[self::row('ok', 'served through the file route (direct access off)', '')],
			DoctorCmd::publicFilesChecks()
		);
	}

	public function testWithABaseTheRowShowsIt(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', 'https://cdn.example.com/');

		self::assertSame(
			[self::row('ok', 'linked at https://cdn.example.com/', '')],
			DoctorCmd::publicFilesChecks()
		);
	}

	public function testDirectAccessWithNoBaseFails(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);

		self::assertFileDoesNotExist(self::rootVhost());

		$rows = DoctorCmd::publicFilesChecks();

		self::assertCount(1, $rows);
		self::assertSame(['Public files', 'fail'], [$rows[0]['name'], $rows[0]['status']]);
		self::assertSame('direct access is on and OZ_PUBLIC_FILES_BASE_URL (oz.files) is not set', $rows[0]['detail']);
		self::assertStringStartsWith('Set OZ_PUBLIC_FILES_BASE_URL (oz.files) to the URL', $rows[0]['fix']);
		// With no vhost generated, there is no URL to suggest.
		self::assertStringNotContainsString('https://', $rows[0]['fix']);
	}

	public function testABadBaseFailsWithTheRefusal(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', '/static');

		$rows = DoctorCmd::publicFilesChecks();

		self::assertCount(1, $rows);
		self::assertSame(['Public files', 'fail'], [$rows[0]['name'], $rows[0]['status']]);
		self::assertStringStartsWith('OZ_PUBLIC_FILES_BASE_URL (oz.files) should be', $rows[0]['detail']);
		self::assertStringContainsString('"/static" is not.', $rows[0]['detail']);
	}

	public function testTheRootVhostOfABareDeployIsSuggested(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);
		Settings::set('oz.request', 'OZ_DEFAULT_ORIGIN', 'https://files.example.test');

		$vhost = self::rootVhost();

		try {
			self::assertSame('files.example.test', DeployInitializer::serverName(ScopeInterface::ROOT_SCOPE));

			if (!\is_dir(\dirname($vhost))) {
				\mkdir(\dirname($vhost), 0o775, true);
			}

			\file_put_contents($vhost, "server {}\n");

			$rows = DoctorCmd::publicFilesChecks();

			self::assertSame('fail', $rows[0]['status']);
			self::assertStringEndsWith(
				' The root vhost of "oz deploy init" serves it: https://files.example.test/static/',
				$rows[0]['fix']
			);
		} finally {
			if (\is_file($vhost)) {
				\unlink($vhost);
			}

			Settings::unset('oz.request', 'OZ_DEFAULT_ORIGIN');
		}
	}

	/**
	 * Where `oz deploy init --target=bare` writes the root scope's vhost in the sandbox project.
	 */
	private static function rootVhost(): string
	{
		return app()->getProjectDir()->resolve(DeployInitializer::nginxVhostPath(ScopeInterface::ROOT_SCOPE));
	}

	/**
	 * @return array{name: string, status: string, detail: string, fix: string}
	 */
	private static function row(string $status, string $detail, string $fix): array
	{
		return ['name' => 'Public files', 'status' => $status, 'detail' => $detail, 'fix' => $fix];
	}
}
