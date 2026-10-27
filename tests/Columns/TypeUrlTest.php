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

namespace OZONE\Tests\Columns;

use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use OZONE\Core\Columns\Types\TypeUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Class TypeUrlTest.
 *
 * @internal
 */
#[CoversClass(TypeUrl::class)]
final class TypeUrlTest extends TestCase
{
	/**
	 * A column with no host list refuses a scheme that is not http(s).
	 *
	 * The scheme was only ever looked at inside the host-list check, so a column without a list
	 * accepted whatever `FILTER_VALIDATE_URL` accepted -- and it accepts
	 * `javascript://comment%0Aalert(1)`, because the newline ends the comment and what follows
	 * runs wherever the stored value is put in an `href`.
	 */
	#[DataProvider('provideASchemeThatIsNotHttpIsRefusedWithNoHostListCases')]
	public function testASchemeThatIsNotHttpIsRefusedWithNoHostList(string $url): void
	{
		$type = new TypeUrl();

		$this->expectException(TypesInvalidValueException::class);

		$type->validate($url);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideASchemeThatIsNotHttpIsRefusedWithNoHostListCases(): iterable
	{
		return [
			'a javascript url behind a comment' => ['javascript://comment%0Aalert(1)'],
			'a data url'                        => ['data://text/html;base64,PHNjcmlwdD4='],
			'a file url'                        => ['file:///etc/passwd'],
			'another scheme'                    => ['ftp://example.com/f'],
		];
	}

	/**
	 * A stored URL means one thing to every reader: what only some parsers accept is refused.
	 */
	#[DataProvider('provideAnAmbiguousUrlIsRefusedCases')]
	public function testAnAmbiguousUrlIsRefused(string $url): void
	{
		$type = new TypeUrl();

		$this->expectException(TypesInvalidValueException::class);

		$type->validate($url);
	}

	/**
	 * @return iterable<string, array{0: string}>
	 */
	public static function provideAnAmbiguousUrlIsRefusedCases(): iterable
	{
		return [
			'an authority a browser reads differently' => ['http://evil.com\@good.com/'],
			'a per cent that is not an escape'         => ['http://example.com/%zz'],
			'a host that is not yet in ASCII'          => ['http://例え.jp/p'],
		];
	}

	public function testAcceptsHttpUrl(): void
	{
		$type  = new TypeUrl();
		$clean = $type->validate('http://example.com/path?q=1#frag')->getCleanValue();
		self::assertSame('http://example.com/path?q=1#frag', $clean);
	}

	public function testAcceptsHttpsUrl(): void
	{
		$type  = new TypeUrl();
		$clean = $type->validate('https://example.com/')->getCleanValue();
		self::assertSame('https://example.com/', $clean);
	}

	public function testNullableAcceptsNull(): void
	{
		$type  = new TypeUrl()->nullable();
		$clean = $type->validate(null)->getCleanValue();
		self::assertNull($clean);
	}

	public function testAbsolutePathAcceptedWhenAllowed(): void
	{
		$type  = new TypeUrl()->allowAbsolutePath();
		$clean = $type->validate('/some/path')->getCleanValue();
		self::assertSame('/some/path', $clean);
	}

	public function testAbsolutePathWithQueryAndFragment(): void
	{
		$type  = new TypeUrl()->allowAbsolutePath();
		$clean = $type->validate('/search?q=hello#results')->getCleanValue();
		self::assertSame('/search?q=hello#results', $clean);
	}

	public function testAbsolutePathRejectedWhenNotAllowed(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->validate('/absolute/path');
	}

	public function testProtocolRelativeUrlRejectedAsAbsolutePath(): void
	{
		// //example.com starts with // -> not treated as absolute path and fails FILTER_VALIDATE_URL
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->allowAbsolutePath()->validate('//example.com');
	}

	public function testRejectsPlainWord(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->validate('notaurl');
	}

	public function testRejectsMissingScheme(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->validate('example.com/path');
	}

	public function testRejectsEmptyStringWhenNotNullable(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->validate('');
	}

	public function testRejectsAbsolutePathWithSpaces(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->allowAbsolutePath()->validate('/path with spaces');
	}

	public function testAllowedHostsAcceptsListedHostsAndLocalPaths(): void
	{
		$type = new TypeUrl()->allowAbsolutePath()->allowedHosts(['App.Example.com']);

		self::assertSame('https://APP.example.com/home', $type->validate('https://APP.example.com/home')->getCleanValue());
		self::assertSame('/local', $type->validate('/local')->getCleanValue());
	}

	public function testAllowedHostsRejectsOtherHosts(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		$this->expectExceptionMessage('OZ_FIELD_URL_HOST_NOT_ALLOWED');

		new TypeUrl()->allowAbsolutePath()->allowedHosts(['app.example.com'])->validate('https://evil.example/');
	}

	public function testAllowedHostsRejectsNonHttpSchemes(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		new TypeUrl()->allowedHosts(['app.example.com'])->validate('javascript://app.example.com/%0Aalert(1)');
	}

	public function testProtocolRelativeUrlIsNotALocalPath(): void
	{
		$this->expectException(TypesInvalidValueException::class);

		new TypeUrl()->allowAbsolutePath()->allowedHosts(['app.example.com'])->validate('//evil.example/');
	}

	/** PHP counts "0" as empty, which used to skip every check of this type: it is checked now. */
	public function testUrlChecksTheStringZero(): void
	{
		$this->expectException(TypesInvalidValueException::class);
		new TypeUrl()->validate('0');
	}
}
