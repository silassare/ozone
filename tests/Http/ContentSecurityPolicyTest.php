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

namespace OZONE\Tests\Http;

use OZONE\Core\Http\ContentSecurityPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class ContentSecurityPolicyTest.
 *
 * @internal
 */
#[CoversClass(ContentSecurityPolicy::class)]
final class ContentSecurityPolicyTest extends TestCase
{
	public function testDirectivesAreSentInTheOrderTheyWereDeclared(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives([
			'default-src' => ["'self'"],
			'img-src'     => ["'self'", 'data:'],
			'object-src'  => ["'none'"],
		]);

		self::assertSame("default-src 'self'; img-src 'self' data:; object-src 'none'", $policy->header());
	}

	public function testAnEmptyPolicyHasNothingToSend(): void
	{
		self::assertTrue(ContentSecurityPolicy::fromDirectives([])->isEmpty());
		self::assertSame('', ContentSecurityPolicy::fromDirectives([])->header());
	}

	public function testADirectiveWithNoSourceIsSentAsItsNameAlone(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives([
			'default-src'               => ["'self'"],
			'upgrade-insecure-requests' => true,
		]);

		self::assertSame("default-src 'self'; upgrade-insecure-requests", $policy->header());
	}

	public function testANameIsLowercasedAndWrittenTwiceKeepsTheFirst(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives([
			'Script-Src' => ["'self'"],
			'script-src' => ['https://cdn.example'],
		]);

		self::assertSame(["'self'"], $policy->sourcesOf('script-src'));
		self::assertSame("script-src 'self'", $policy->header());
	}

	public function testASourceWrittenTwiceIsSentOnce(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['img-src' => ["'self'", 'data:', "'self'"]]);

		self::assertSame("img-src 'self' data:", $policy->header());
	}

	public function testAPolicyIsReadBackFromTheHeaderItWasSentAs(): void
	{
		$header = "default-src 'self'; img-src 'self' data:; upgrade-insecure-requests";
		$policy = ContentSecurityPolicy::fromHeader($header);

		self::assertSame($header, $policy->header());
		self::assertSame(["'self'", 'data:'], $policy->sourcesOf('img-src'));
	}

	public function testAHeaderIsReadWhateverItIsSpacedWith(): void
	{
		$policy = ContentSecurityPolicy::fromHeader("  DEFAULT-SRC   'self'  ;;  img-src\tdata: ; ");

		self::assertSame(["'self'"], $policy->sourcesOf('default-src'));
		self::assertSame(['data:'], $policy->sourcesOf('img-src'));
		self::assertSame("default-src 'self'; img-src data:", $policy->header());
	}

	public function testADirectiveNotWrittenIsAnsweredByTheOneItFallsBackTo(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['default-src' => ["'self'"]]);

		self::assertSame(["'self'"], $policy->sourcesOf('style-src'));
		// style-src-attr is answered by style-src, which is answered by default-src.
		self::assertSame(["'self'"], $policy->sourcesOf('style-src-attr'));
		// worker-src is answered by child-src before default-src.
		self::assertSame(["'self'"], $policy->sourcesOf('worker-src'));
		// Nothing answers for these, which is a policy that does not restrict them.
		self::assertNull($policy->sourcesOf('frame-ancestors'));
		self::assertNull($policy->sourcesOf('form-action'));
	}

	public function testADirectiveNothingAnswersForAllowsEverything(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['script-src' => ["'self'"]]);

		self::assertTrue($policy->allows('script-src', "'self'"));
		self::assertFalse($policy->allows('script-src', "'unsafe-eval'"));
		self::assertTrue($policy->allows('frame-ancestors', 'https://anywhere.example'));
	}

	public function testAGrantKeepsWhatTheFallbackAlreadyAllowed(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['default-src' => ["'self'"]])
			->with('style-src', "'unsafe-inline'");

		self::assertSame("default-src 'self'; style-src 'self' 'unsafe-inline'", $policy->header());
	}

	public function testAGrantOnAWrittenDirectiveKeepsItsPlaceInTheHeader(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives([
			'default-src' => ["'self'"],
			'script-src'  => ["'self'"],
			'object-src'  => ["'none'"],
		])->with('script-src', "'unsafe-eval'");

		self::assertSame(
			"default-src 'self'; script-src 'self' 'unsafe-eval'; object-src 'none'",
			$policy->header()
		);
	}

	public function testAGrantMakesNoneMakeWay(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['script-src' => ["'none'"]])
			->with('script-src', "'self'");

		self::assertSame("script-src 'self'", $policy->header());
	}

	public function testNoneStaysWhenThereIsNothingToAdd(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(['object-src' => ["'none'"]])
			->with('object-src', "'none'");

		self::assertSame("object-src 'none'", $policy->header());
	}

	public function testADirectiveNothingAnswersForIsLeftAlone(): void
	{
		// Styles are unrestricted here, so writing the grant out would be the one way a grant
		// ends up restricting the page it was granted to.
		$policy = ContentSecurityPolicy::fromDirectives(['script-src' => ["'self'"]])
			->with('style-src', "'unsafe-inline'");

		self::assertSame("script-src 'self'", $policy->header());
	}

	public function testAGrantAnswersANewPolicyAndLeavesTheOneItWasGrantedOn(): void
	{
		$policy  = ContentSecurityPolicy::fromDirectives(['default-src' => ["'self'"]]);
		$granted = $policy->with('style-src', "'unsafe-inline'");

		self::assertSame("default-src 'self'", $policy->header());
		self::assertNotSame($policy, $granted);
	}

	public function testAGrantThatAddsNothingAnswersTheSamePolicy(): void
	{
		$policy  = ContentSecurityPolicy::fromDirectives(['style-src' => ["'self'", "'unsafe-inline'"]]);
		$granted = $policy->with('style-src', "'unsafe-inline'");

		self::assertSame($policy, $granted);
	}

	public function testNothingIsSentUntilAProjectWritesAPolicy(): void
	{
		self::assertTrue(ContentSecurityPolicy::fromSettings()->isEmpty());
	}

	public function testWhatTheStrictBaselineAllows(): void
	{
		$policy = ContentSecurityPolicy::fromDirectives(ContentSecurityPolicy::STRICT);

		// Script is what an injected value needs in order to run: the two sources that would let
		// one run are what this baseline is for.
		self::assertFalse($policy->allows('script-src', "'unsafe-inline'"));
		self::assertFalse($policy->allows('script-src', "'unsafe-eval'"));
		self::assertTrue($policy->allows('script-src', "'self'"));
		// Every HTML page OZone serves inlines its stylesheet into the layout.
		self::assertTrue($policy->allows('style-src', "'unsafe-inline'"));
		self::assertSame(["'none'"], $policy->sourcesOf('object-src'));
		self::assertSame(["'self'"], $policy->sourcesOf('base-uri'));
		self::assertSame(["'self'"], $policy->sourcesOf('frame-ancestors'));
	}
}
