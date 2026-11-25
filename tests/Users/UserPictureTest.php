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

namespace OZONE\Tests\Users;

use Gobl\ORM\ORMOptions;
use Override;
use OZONE\Core\Access\AccessRights;
use OZONE\Core\Access\Interfaces\AccessRightsInterface;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\AuthenticationMethodInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Columns\ValidatedFile;
use OZONE\Core\Db\OZCountriesQuery;
use OZONE\Core\Db\OZCountry;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Views\GetFilesView;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Users\Picture\Enums\UserPictureFallbackReason;
use OZONE\Core\Users\Picture\Interfaces\UserPictureAccessInterface;
use OZONE\Core\Users\Picture\Interfaces\UserPictureFallbackInterface;
use OZONE\Core\Users\Picture\PublicPictureAccess;
use OZONE\Core\Users\Picture\SelfOrAdminPictureAccess;
use OZONE\Core\Users\Picture\SignedInPictureAccess;
use OZONE\Core\Users\Picture\UserPictureImage;
use OZONE\Core\Users\Views\UserPictureView;
use OZONE\Tests\Support\ImageBytes;
use OZONE\Tests\Support\TestAuthUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Class UserPictureTest.
 *
 * A user's picture served by `oz:user-picture`: to whom the rule allows, through the same image
 * chain as `oz:files`, the forced tokens first, and what a project answers in its place.
 *
 * @internal
 */
#[CoversClass(UserPictureView::class)]
#[CoversClass(SelfOrAdminPictureAccess::class)]
#[CoversClass(SignedInPictureAccess::class)]
#[CoversClass(PublicPictureAccess::class)]
#[CoversClass(UserPictureImage::class)]
final class UserPictureTest extends TestCase
{
	private const array KEYS = ['OZ_USER_PICTURE_ACCESS', 'OZ_USER_PICTURE_TOKENS', 'OZ_USER_PICTURE_FALLBACK'];

	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());

		if (null === new OZCountriesQuery()->whereCc2Is('BJ')->find(ORMOptions::makePaginated(1))->fetchClass()) {
			new OZCountriesQuery()->insertMulti([[
				OZCountry::COL_CC2          => 'BJ',
				OZCountry::COL_NAME         => 'Benin',
				OZCountry::COL_NAME_REAL    => 'Benin',
				OZCountry::COL_CALLING_CODE => '229',
			]])->execute();
		}
	}

	#[Override]
	protected function tearDown(): void
	{
		foreach (self::KEYS as $key) {
			Settings::unset('oz.users', $key);
		}

		UserPictureTestFallback::$image = null;

		parent::tearDown();
	}

	public function testTheDefaultRuleServesTheUserAndAdministratorsOnly(): void
	{
		$owner = self::userWithPicture();

		self::assertSame([200, 100], ImageBytes::size(self::body(self::serve($owner, '', self::viewer($owner)))));
		self::assertSame(
			[200, 100],
			ImageBytes::size(self::body(self::serve($owner, '', self::viewerWith(Role::ADMIN))))
		);

		self::assertRefused(static fn () => self::serve($owner, '', self::viewerWith(null)));
		self::assertRefused(static fn () => self::serve($owner, '', null));
	}

	public function testSignedInLetsEveryoneSignedInAndNobodyElse(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_ACCESS', SignedInPictureAccess::class);

		$owner = self::userWithPicture();

		self::assertSame([200, 100], ImageBytes::size(self::body(self::serve($owner, '', self::viewerWith(null)))));
		self::assertRefused(static fn () => self::serve($owner, '', null));
	}

	public function testPublicLetsAnyone(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_ACCESS', PublicPictureAccess::class);

		$owner = self::userWithPicture();

		self::assertSame([200, 100], ImageBytes::size(self::body(self::serve($owner, '', null))));
	}

	public function testAProjectsOwnRuleDecides(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_ACCESS', UserPictureTestEditorsOnly::class);

		$owner = self::userWithPicture();

		self::assertSame(
			[200, 100],
			ImageBytes::size(self::body(self::serve($owner, '', self::viewerWith(Role::EDITOR))))
		);
		// Not even the user themself: the project's rule is the whole rule.
		self::assertRefused(static fn () => self::serve($owner, '', self::viewer($owner)));

		Settings::set('oz.users', 'OZ_USER_PICTURE_ACCESS', self::class);

		$this->expectException(RuntimeException::class);
		self::serve($owner, '', self::viewer($owner));
	}

	public function testNoPictureIsNotFoundOnlyToAViewerTheRuleAllows(): void
	{
		$owner = self::user();

		try {
			self::serve($owner, '', self::viewer($owner));
			self::fail('A user with no picture was answered.');
		} catch (NotFoundException) {
		}

		// Whoever the rule refuses learns nothing of whether there is a picture.
		self::assertRefused(static fn () => self::serve($owner, '', self::viewerWith(null)));
	}

	public function testAnIdNoUserHasIsARefusal(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_ACCESS', PublicPictureAccess::class);

		self::assertRefused(static fn () => self::serveId('999999999', '', self::viewerWith(Role::ADMIN)));
	}

	public function testTheOriginalIsNeverServed(): void
	{
		$owner = self::userWithPicture();

		// To the user themself, and among other tokens too.
		self::assertRefused(static fn () => self::serve($owner, GetFilesView::ORIGINAL_TOKEN, self::viewer($owner)));
		self::assertRefused(static fn () => self::serve($owner, 'w64~original', self::viewerWith(Role::ADMIN)));
	}

	public function testTheBytesAreThoseOfOzFilesForTheSameTokens(): void
	{
		$owner = self::userWithPicture();
		$file  = $owner->getPic()?->loadFile();

		self::assertInstanceOf(OZFile::class, $file);

		foreach (['', 'w64', 'thumb48~grayscale'] as $tokens) {
			$picture = self::body(self::serve($owner, $tokens, self::viewer($owner)));

			self::assertSame(self::body(self::serveFile($file, $tokens)), $picture, $tokens);
		}

		// The plain picture carries no metadata.
		self::assertStringNotContainsString('OZoneCamera', self::body(self::serve($owner, '', self::viewer($owner))));
	}

	public function testTheAnswerIsPrivateAndItsETagFollowsTheFileAndTheTokens(): void
	{
		$owner  = self::userWithPicture();
		$viewer = self::viewer($owner);
		$plain  = self::serve($owner, '', $viewer);
		$small  = self::serve($owner, 'w64', $viewer);

		self::assertSame('private, no-cache', $plain->getHeaderLine('Cache-Control'));
		self::assertMatchesRegularExpression('~^"[0-9a-f]{16}"$~', $plain->getHeaderLine('ETag'));
		self::assertSame($plain->getHeaderLine('ETag'), self::serve($owner, '', $viewer)->getHeaderLine('ETag'));
		self::assertNotSame($plain->getHeaderLine('ETag'), $small->getHeaderLine('ETag'));

		// Another picture of the same size: another validator.
		$other = self::userWithPicture();

		self::assertNotSame(
			$plain->getHeaderLine('ETag'),
			self::serve($other, '', self::viewer($other))->getHeaderLine('ETag')
		);

		// Nothing of the file's key reaches the answer.
		$file = $owner->getPic()?->loadFile();

		self::assertInstanceOf(OZFile::class, $file);
		self::assertStringNotContainsString($file->getKey(), \implode("\n", \array_map(
			static fn (array $v): string => \implode(',', $v),
			$plain->getHeaders()
		)));
	}

	public function testTheForcedTokensComeFirstWhateverTheLinkAsks(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_TOKENS', ['w64']);

		$owner  = self::userWithPicture();
		$viewer = self::viewer($owner);

		// A size cap: a link asking for more gets the cap, one asking for less gets less.
		self::assertSame([64, 32], ImageBytes::size(self::body(self::serve($owner, '', $viewer))));
		self::assertSame([64, 32], ImageBytes::size(self::body(self::serve($owner, 'w640', $viewer))));
		self::assertSame([32, 16], ImageBytes::size(self::body(self::serve($owner, 'w32', $viewer))));
	}

	public function testAnImageFileAnswersInPlaceOfAPictureAndIsRenderedAsOne(): void
	{
		$path = \sys_get_temp_dir() . '/oz-user-picture-fallback-' . \bin2hex(\random_bytes(4)) . '.png';

		\file_put_contents($path, ImageBytes::filled(100, 100, 0, 0, 255));
		Settings::set('oz.users', 'OZ_USER_PICTURE_FALLBACK', ['refused' => $path]);

		try {
			$owner    = self::userWithPicture();
			$response = self::serve($owner, 'w64', self::viewerWith(null));
			$body     = self::body($response);

			self::assertSame([64, 64], ImageBytes::size($body));
			self::assertSame([0, 0, 255], \array_slice(ImageBytes::rgbAt($body, 32, 32), 0, 3));
			self::assertSame('private, no-cache', $response->getHeaderLine('Cache-Control'));

			// Only what the map names: a missing picture still answers its status.
			$bare = self::user();

			$this->expectException(NotFoundException::class);
			self::serve($bare, '', self::viewer($bare));
		} finally {
			\unlink($path);
		}
	}

	public function testAClassDrawsTheImageOrLeavesTheStatus(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_FALLBACK', UserPictureTestFallback::class);
		Settings::set('oz.users', 'OZ_USER_PICTURE_TOKENS', ['w32']);

		UserPictureTestFallback::$image = UserPictureImage::fromBytes(ImageBytes::filled(80, 80, 255, 0, 0), 'image/png');

		$bare = self::user();

		// The forced tokens apply to it as to a picture.
		self::assertSame([32, 32], ImageBytes::size(self::body(self::serve($bare, '', self::viewer($bare)))));
		self::assertSame(
			[UserPictureFallbackReason::MISSING, $bare->getID()],
			[UserPictureTestFallback::$reason, UserPictureTestFallback::$owner]
		);

		// Refused, for an id no user has: the class is asked with no owner.
		self::assertSame([32, 32], ImageBytes::size(self::body(self::serveId('999999998', '', null))));
		self::assertSame(
			[UserPictureFallbackReason::REFUSED, null],
			[UserPictureTestFallback::$reason, UserPictureTestFallback::$owner]
		);

		UserPictureTestFallback::$image = null;

		self::assertRefused(static fn () => self::serve($bare, '', null));
	}

	public function testAFallbackThatIsNeitherAPathNorAFallbackClassIsAnError(): void
	{
		Settings::set('oz.users', 'OZ_USER_PICTURE_FALLBACK', self::class);

		$this->expectException(RuntimeException::class);
		self::serve(self::user(), '', null);
	}

	public function testAFallbackMustBeAnImage(): void
	{
		$this->expectException(RuntimeException::class);
		UserPictureImage::fromBytes('not an image', 'text/plain');
	}

	public function testTheRouteIsDocumented(): void
	{
		try {
			$paths = \json_decode(\json_encode(ApiDoc::get()->toArray()), true)['spec']['paths'];
			$ids   = [];

			foreach ($paths as $operations) {
				foreach ($operations as $operation) {
					if (\is_array($operation) && isset($operation['operationId'])) {
						$ids[] = $operation['operationId'];
					}
				}
			}

			self::assertContains('Users.picture', $ids);
		} finally {
			ApiDoc::release();
		}
	}

	/**
	 * Asserts that an answer is a refusal (403).
	 *
	 * @param callable(): Response $serve
	 */
	private static function assertRefused(callable $serve): void
	{
		try {
			$serve();
			self::fail('The picture was served.');
		} catch (ForbiddenException $e) {
			self::assertSame('OZ_ERROR_NOT_ALLOWED', $e->getMessage());
		}
	}

	private static function serve(OZUser $owner, string $tokens, ?AuthUserInterface $viewer): Response
	{
		return self::serveId($owner->getAuthIdentifier(), $tokens, $viewer);
	}

	/**
	 * Serves `oz:user-picture` for an id, to a viewer (null: nobody signed in).
	 */
	private static function serveId(string $id, string $tokens, ?AuthUserInterface $viewer): Response
	{
		UserPictureTestAuth::$user = $viewer;

		$router = new Router();

		UserPictureView::registerRoutes($router);

		$route = $router->requireRoute(UserPictureView::MAIN_ROUTE);
		$route->getOptions()->withAuthentication(UserPictureTestAuth::class);

		$context = self::context();
		$params  = ['oz_user_id' => $id] + ('' === $tokens ? [] : ['oz_picture_token' => $tokens]);
		$ri      = new RouteInfo($context, $route, $params, static function (RouteInfo $ri) use ($context): void {
			$context->authState()->authenticate($ri);
		});

		// The request being handled, as `Context::handle()` makes it: a role is read under its auth.
		$current  = new ReflectionProperty(Context::class, 'current_context');
		$previous = $current->getValue();

		$current->setValue(null, $context);

		try {
			return UserPictureView::handle($ri);
		} finally {
			$current->setValue(null, $previous);
		}
	}

	/**
	 * Serves a file as `oz:files` does, with its key.
	 */
	private static function serveFile(OZFile $file, string $tokens): Response
	{
		$router = new Router();

		GetFilesView::registerRoutes($router);

		$params = ['oz_file_id' => $file->getID(), 'oz_file_auth_key' => $file->getKey()]
			+ ('' === $tokens ? [] : ['oz_file_filters' => $tokens]);

		return GetFilesView::handle(new RouteInfo(
			self::context(),
			$router->requireRoute(GetFilesView::MAIN_ROUTE),
			$params
		));
	}

	private static function body(Response $response): string
	{
		$body = $response->getBody();
		$body->rewind();

		return $body->getContents();
	}

	private static function context(): Context
	{
		return new Context(
			HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/1/picture']),
			null,
			Context::root(),
			false
		);
	}

	private static function user(): OZUser
	{
		$user = new OZUser();

		$user->setCivility('Mx')
			->setDisplayName('Pic Ture')
			->setFirstName('Pic')
			->setLastName('Ture')
			->setEmail('pic.' . \bin2hex(\random_bytes(6)) . '@example.com')
			->setGender('None')
			->setBirthDate('1990-01-01')
			->setPass('a-strong-password')
			->setCc2('BJ')
			->save();

		return $user;
	}

	/**
	 * A user whose picture is a photo of 200 x 100 carrying camera metadata.
	 */
	private static function userWithPicture(): OZUser
	{
		$user = self::user();
		$file = FS::getStorage(FS::PRIVATE_STORAGE)->saveRaw(
			ImageBytes::withExif(ImageBytes::jpeg(200, 100), 1),
			'image/jpeg',
			'me.jpg'
		);

		$file->save();
		$user->setPic(ValidatedFile::forFile($file))->save();

		return $user;
	}

	/**
	 * The user themself, as the request's authentication names them.
	 */
	private static function viewer(OZUser $user): AuthUserInterface
	{
		return new TestAuthUser($user->getAuthUserType(), $user->getAuthIdentifier());
	}

	/**
	 * Another signed-in user, with a role or none.
	 */
	private static function viewerWith(?Role $role): AuthUserInterface
	{
		$viewer = new TestAuthUser('user', (string) \random_int(100_000_000, 900_000_000));

		if (null !== $role) {
			Roles::assign($viewer, $role);
		}

		RolesUtils::roles($viewer, true);

		return $viewer;
	}
}

/**
 * The authentication method of the route under test: who is asking, as a test sets it.
 *
 * @internal
 */
final class UserPictureTestAuth implements AuthenticationMethodInterface
{
	public static ?AuthUserInterface $user = null;

	#[Override]
	public static function get(RouteInfo $ri, string $realm): static
	{
		return new self();
	}

	#[Override]
	public function satisfied(): bool
	{
		return true;
	}

	#[Override]
	public function authenticate(): void {}

	#[Override]
	public function user(): AuthUserInterface
	{
		return self::$user ?? throw new UnauthenticatedException();
	}

	#[Override]
	public function getAccessRights(): AccessRightsInterface
	{
		return new AccessRights();
	}

	#[Override]
	public function isScoped(): bool
	{
		return false;
	}

	#[Override]
	public function ask(): void {}
}

/**
 * A project's own rule: editors only.
 *
 * @internal
 */
final class UserPictureTestEditorsOnly implements UserPictureAccessInterface
{
	#[Override]
	public function allows(OZUser $owner, Context $context): bool
	{
		return $context->hasAuthenticatedUser() && Roles::isEditor($context->auth()->user());
	}
}

/**
 * A project's own fallback: the image a test sets, recording what it was asked.
 *
 * @internal
 */
final class UserPictureTestFallback implements UserPictureFallbackInterface
{
	public static ?UserPictureImage $image = null;

	public static ?UserPictureFallbackReason $reason = null;

	public static ?string $owner = null;

	#[Override]
	public function fallback(?OZUser $owner, Context $context, UserPictureFallbackReason $reason): ?UserPictureImage
	{
		self::$reason = $reason;
		self::$owner  = $owner?->getID();

		return self::$image;
	}
}
