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

namespace OZONE\Tests\Auth;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\Auth\AuthUserDataStore;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Interfaces\TNetSectionInterface;
use OZONE\Core\Auth\Services\TNet;
use OZONE\Core\Auth\TNet\ReaderSection;
use OZONE\Core\Http\HTTPEnvironment;
use OZONE\Core\Roles\Enums\Role;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Roles\RolesUtils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Class TNetSectionsTest.
 *
 * What `tnet` answers beside its own fields, and the `reader` section a client decides with.
 *
 * @internal
 */
#[CoversClass(TNet::class)]
#[CoversClass(ReaderSection::class)]
final class TNetSectionsTest extends TestCase
{
	#[Override]
	public static function setUpBeforeClass(): void
	{
		parent::setUpBeforeClass();
		db()->executeMulti(db()->getGenerator()->buildDatabase());
	}

	public function testEverySectionIsAnsweredWhenNoneIsAskedFor(): void
	{
		$out = TNet::answers(self::context(), [
			'one' => TNetSectionsTestOne::class,
			'two' => TNetSectionsTestTwo::class,
		], null, true);

		self::assertSame(['one' => ['n' => 1], 'two' => ['n' => 2]], $out);
	}

	public function testOnlyTheSectionsAskedForAreAnswered(): void
	{
		$registered = ['one' => TNetSectionsTestOne::class, 'two' => TNetSectionsTestTwo::class];

		self::assertSame(['two' => ['n' => 2]], TNet::answers(self::context(), $registered, 'two', true));
		// Spaces around the names, an empty one, and a name nothing registered: the rest is answered.
		self::assertSame(
			['one' => ['n' => 1]],
			TNet::answers(self::context(), $registered, ' one , , nowhere ', true)
		);
	}

	public function testASectionAboutTheReaderIsNotBuiltForNobody(): void
	{
		$registered = ['one' => TNetSectionsTestOne::class, 'mine' => TNetSectionsTestMine::class];

		self::assertSame(['one' => ['n' => 1]], TNet::answers(self::context(), $registered, null, false));
		self::assertArrayHasKey('mine', TNet::answers(self::context(), $registered, null, true));
	}

	public function testAFailingSectionIsLeftOutAndTheRestAnswered(): void
	{
		// The heartbeat answers whatever a section did: a page asking whether its session is alive
		// must get that answer.
		$out = TNet::answers(self::context(), [
			'boom' => TNetSectionsTestBoom::class,
			'one'  => TNetSectionsTestOne::class,
			'odd'  => self::class,
		], null, true);

		self::assertSame(['one' => ['n' => 1]], $out);
	}

	public function testASectionRemovedByALaterSourceIsNotAnswered(): void
	{
		// `null` is what a project's settings write to remove a section OZone registered.
		$out = TNet::answers(self::context(), [
			'one'                 => TNetSectionsTestOne::class,
			ReaderSection::NAME   => null,
		], null, true);

		self::assertSame(['one' => ['n' => 1]], $out);
	}

	public function testASectionThatAnswersNothingIsLeftOut(): void
	{
		self::assertSame([], TNet::answers(self::context(), ['none' => TNetSectionsTestNone::class], null, true));
	}

	public function testTheReaderIsItsTypeIdRolesAndTheWeightsOfTheEnum(): void
	{
		$user = self::user('201', 'user');
		Roles::assign($user, Role::EDITOR);
		RolesUtils::roles($user, true);

		self::assertSame([
			'type'    => 'user',
			'id'      => '201',
			'roles'   => ['editor'],
			'weights' => ['super-admin' => 10_000, 'admin' => 8_000, 'editor' => 5_000],
		], ReaderSection::of($user, false));
	}

	public function testUnderAScopedAuthTheRolesAreSentEmpty(): void
	{
		// Under a scoped auth a role grants nothing, so a client must not draw what it would allow.
		$user = self::user('202', 'user');
		Roles::assign($user, Role::ADMIN);
		RolesUtils::roles($user, true);

		$section = ReaderSection::of($user, true);

		self::assertSame([], $section['roles']);
		self::assertSame('202', $section['id']);
	}

	public function testTheSameIdOfAnotherTypeIsAnotherReader(): void
	{
		// An id alone confuses user 1 with client 1: the roles are found by id **and** type.
		Roles::assign(self::user('203', 'user'), Role::ADMIN);
		RolesUtils::roles(self::user('203', 'user'), true);

		$client = self::user('203', 'client');

		self::assertSame([], ReaderSection::of($client, false)['roles']);
		self::assertSame('client', ReaderSection::of($client, false)['type']);
	}

	private static function context(): Context
	{
		return new Context(
			HTTPEnvironment::mock(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/tnet']),
			null,
			Context::root()
		);
	}

	private static function user(string $id, string $type): AuthUserInterface
	{
		return new class($id, $type) implements AuthUserInterface {
			public function __construct(private readonly string $id, private readonly string $type) {}

			#[Override]
			public function getAuthUserType(): string
			{
				return $this->type;
			}

			#[Override]
			public function getAuthIdentifier(): string
			{
				return $this->id;
			}

			#[Override]
			public function getAuthIdentifiers(): array
			{
				return [];
			}

			#[Override]
			public function getAuthPassword(): string
			{
				return '';
			}

			#[Override]
			public function setAuthPassword(string $password_hash): static
			{
				return $this;
			}

			#[Override]
			public function getAuthUserDataStore(): AuthUserDataStore
			{
				throw new RuntimeException('No data store on this stub.');
			}

			#[Override]
			public function setAuthUserDataStore(AuthUserDataStore $store): static
			{
				return $this;
			}

			#[Override]
			public function isAuthUserValid(): bool
			{
				return true;
			}

			#[Override]
			public function save(): bool
			{
				return true;
			}

			#[Override]
			public function toArray(): array
			{
				return ['type' => $this->type, 'id' => $this->id];
			}

			#[Override]
			public function jsonSerialize(): mixed
			{
				return $this->toArray();
			}
		};
	}
}

/**
 * @internal
 */
final class TNetSectionsTestOne implements TNetSectionInterface
{
	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return false;
	}

	#[Override]
	public function answer(Context $context): array
	{
		return ['n' => 1];
	}
}

/**
 * @internal
 */
final class TNetSectionsTestTwo implements TNetSectionInterface
{
	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return false;
	}

	#[Override]
	public function answer(Context $context): array
	{
		return ['n' => 2];
	}
}

/**
 * @internal
 */
final class TNetSectionsTestMine implements TNetSectionInterface
{
	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return true;
	}

	#[Override]
	public function answer(Context $context): array
	{
		return ['n' => 3];
	}
}

/**
 * @internal
 */
final class TNetSectionsTestBoom implements TNetSectionInterface
{
	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return false;
	}

	#[Override]
	public function answer(Context $context): array
	{
		throw new RuntimeException('A section that fails.');
	}
}

/**
 * @internal
 */
final class TNetSectionsTestNone implements TNetSectionInterface
{
	#[Override]
	public static function requiresAuthenticatedUser(): bool
	{
		return false;
	}

	#[Override]
	public function answer(Context $context): ?array
	{
		return null;
	}
}
