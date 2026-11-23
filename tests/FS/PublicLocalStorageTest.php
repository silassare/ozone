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

namespace OZONE\Tests\FS;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Drivers\PublicLocalStorage;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\HTTPEnvironment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The direct link of a public file: `OZ_PUBLIC_FILES_BASE_URL` followed by the file's path in the
 * public files directory, and a refusal naming the setting when the base is missing or not one.
 *
 * @internal
 */
#[CoversClass(PublicLocalStorage::class)]
final class PublicLocalStorageTest extends TestCase
{
	private const string HOST = 'app.example.test';

	#[Override]
	protected function setUp(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', true);
	}

	#[Override]
	protected function tearDown(): void
	{
		Settings::unset('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED');
		Settings::unset('oz.files', 'OZ_PUBLIC_FILES_BASE_URL');
	}

	public function testAnAbsoluteBaseIsFollowedByThePathInThePublicFilesDirectory(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', 'https://cdn.example.com/media/');

		self::withFile(static function (PublicLocalStorage $storage, OZFile $file): void {
			self::assertSame(
				'https://cdn.example.com/media/uploads/' . $file->getRef(),
				(string) $storage->publicUri(self::context(), $file)
			);
		});
	}

	public function testAPathBaseIsReadOnTheHostBeingServed(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', '/static/');

		self::withFile(static function (PublicLocalStorage $storage, OZFile $file): void {
			self::assertSame(
				'http://' . self::HOST . '/static/uploads/' . $file->getRef(),
				(string) $storage->publicUri(self::context(), $file)
			);
		});
	}

	public function testAPathBaseIsReadFromTheRootOfTheHostNotUnderTheBasePathOfTheApp(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', '/static/');

		$context = self::context(['SCRIPT_NAME' => '/shop/index.php', 'REQUEST_URI' => '/shop/cart']);

		// The app is served under /shop: what it links itself is under it.
		self::assertSame('http://' . self::HOST . '/shop/cart', (string) $context->buildUri('/cart'));

		self::withFile(static function (PublicLocalStorage $storage, OZFile $file) use ($context): void {
			self::assertSame(
				'http://' . self::HOST . '/static/uploads/' . $file->getRef(),
				(string) $storage->publicUri($context, $file)
			);
		});
	}

	public function testDirectAccessWithNoBaseIsRefusedNamingBothSettings(): void
	{
		self::withFile(static function (PublicLocalStorage $storage, OZFile $file): void {
			try {
				$storage->publicUri(self::context(), $file);
				self::fail('A direct link was built with no base URL.');
			} catch (RuntimeException $e) {
				self::assertStringContainsString('OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', $e->getMessage());
				self::assertStringContainsString('OZ_PUBLIC_FILES_BASE_URL', $e->getMessage());
				self::assertSame(
					['type' => 'config', 'group' => 'oz.files', 'key' => 'OZ_PUBLIC_FILES_BASE_URL'],
					$e->getData(true)['_suspect'] ?? null
				);
			}
		});
	}

	public function testAnEmptyBaseIsNoBase(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', '');

		self::assertNull(PublicLocalStorage::baseUrl());
	}

	#[DataProvider('provideAGoodBaseIsTakenAsItIsCases')]
	public function testAGoodBaseIsTakenAsItIs(string $base): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', $base);

		self::assertSame($base, PublicLocalStorage::baseUrl());
	}

	public static function provideAGoodBaseIsTakenAsItIsCases(): iterable
	{
		yield 'the root of the host' => ['/'];

		yield 'a path' => ['/static/'];

		yield 'a deeper path' => ['/files/public-v2/'];

		yield 'a host with a port' => ['http://127.0.0.1:8080/static/'];

		yield 'a CDN' => ['https://cdn.example.com/'];
	}

	#[DataProvider('provideABadBaseIsRefusedNamingTheSettingCases')]
	public function testABadBaseIsRefusedNamingTheSetting(mixed $base): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', $base);

		self::withFile(static function (PublicLocalStorage $storage, OZFile $file): void {
			try {
				$storage->publicUri(self::context(), $file);
				self::fail('A direct link was built on a bad base URL.');
			} catch (RuntimeException $e) {
				self::assertStringContainsString('OZ_PUBLIC_FILES_BASE_URL (oz.files) should be', $e->getMessage());
				self::assertSame(
					['type' => 'config', 'group' => 'oz.files', 'key' => 'OZ_PUBLIC_FILES_BASE_URL'],
					$e->getData(true)['_suspect'] ?? null
				);
			}
		});
	}

	public static function provideABadBaseIsRefusedNamingTheSettingCases(): iterable
	{
		yield 'a path not starting with /' => ['static/'];

		yield 'a path not ending with /' => ['/static'];

		yield 'an empty segment' => ['/static//files/'];

		yield 'a dot segment' => ['/static/../'];

		yield 'a URL not ending with /' => ['https://cdn.example.com'];

		yield 'a URL with no scheme' => ['//cdn.example.com/'];

		yield 'a URL of another scheme' => ['ftp://cdn.example.com/'];

		yield 'a script' => ['javascript:alert(1)//'];

		yield 'a URL with credentials' => ['https://user:secret@cdn.example.com/'];

		yield 'a URL with a query' => ['https://cdn.example.com/?v=1/'];

		yield 'a URL with a fragment' => ['https://cdn.example.com/#top/'];

		yield 'a URL with a dot segment' => ['https://cdn.example.com/media/../'];

		yield 'not a string' => [8080];
	}

	public function testWithDirectAccessOffNoBaseIsRead(): void
	{
		Settings::set('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED', false);
		Settings::set('oz.files', 'OZ_PUBLIC_FILES_BASE_URL', 'not a base');

		self::withFile(static function (PublicLocalStorage $storage, OZFile $file): void {
			$file->save();

			self::assertSame(
				(string) FS::buildFileUri(self::context(), $file),
				(string) $storage->publicUri(self::context(), $file)
			);
		});
	}

	/**
	 * Runs a test on a stored public file, which it removes after.
	 *
	 * @param callable(PublicLocalStorage, OZFile): void $test
	 */
	private static function withFile(callable $test): void
	{
		$storage = PublicLocalStorage::get(FS::PUBLIC_STORAGE);
		$file    = $storage->saveRaw('a public note', 'text/plain', 'note.txt');

		try {
			$test($storage, $file);
		} finally {
			$storage->delete($file);
		}
	}

	private static function context(array $env = []): Context
	{
		return new Context(
			HTTPEnvironment::mock($env + ['HTTP_HOST' => self::HOST, 'SERVER_NAME' => self::HOST]),
			null,
			Context::root()
		);
	}
}
