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

use OZONE\Core\FS\S3\S3Signer;
use PHPUnit\Framework\TestCase;

/**
 * Class S3SignerTest.
 *
 * The expected signatures are the worked examples of the AWS Signature Version 4 documentation
 * for S3 (bucket `examplebucket`, 2013-05-24).
 *
 * @internal
 *
 * @covers \OZONE\Core\FS\S3\S3Signer
 */
final class S3SignerTest extends TestCase
{
	private const ACCESS_KEY = 'AKIAIOSFODNN7EXAMPLE';
	private const SECRET_KEY = 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY';
	private const HOST       = 'examplebucket.s3.amazonaws.com';

	public function testSignsTheGetObjectExample(): void
	{
		$headers = self::signer()->signHeaders(
			'GET',
			'/test.txt',
			[],
			['Host' => self::HOST, 'Range' => 'bytes=0-9'],
			S3Signer::EMPTY_PAYLOAD_HASH,
			self::time()
		);

		self::assertSame('20130524T000000Z', $headers['x-amz-date']);
		self::assertSame(
			'AWS4-HMAC-SHA256 Credential=AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request, '
				. 'SignedHeaders=host;range;x-amz-content-sha256;x-amz-date, '
				. 'Signature=f0e8bdb87c964420e857bd35b5d6ed310bd44f0170aba48dd91039c6036bdb41',
			$headers['authorization']
		);
	}

	public function testPresignsTheGetObjectExample(): void
	{
		$query = self::signer()->presignQuery('GET', self::HOST, '/test.txt', [], 86400, self::time());

		self::assertSame('AKIAIOSFODNN7EXAMPLE/20130524/us-east-1/s3/aws4_request', $query['X-Amz-Credential']);
		self::assertSame('aeeed9bbccd4d02ee5c0109b86d86835f995330da4c265957d157751f604d404', $query['X-Amz-Signature']);
	}

	public function testEncodesPathSegmentsAndSortsTheQuery(): void
	{
		self::assertSame('/photos/my%20cat%2B1.jpg', S3Signer::encodePath('/photos/my cat+1.jpg'));
		self::assertSame('a=1&b=x%2Fy&prefix=', S3Signer::canonicalQuery(['prefix' => '', 'b' => 'x/y', 'a' => '1']));
	}

	private static function signer(): S3Signer
	{
		return new S3Signer(self::ACCESS_KEY, self::SECRET_KEY, 'us-east-1');
	}

	private static function time(): int
	{
		return \gmmktime(0, 0, 0, 5, 24, 2013);
	}
}
