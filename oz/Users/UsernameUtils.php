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

namespace OZONE\Core\Users;

use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Gobl\ORM\ORMOptions;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Db\OZUsername;

/**
 * Class UsernameUtils.
 *
 * `oz_usernames` is the one place a username is unique across every auth user repository a project
 * registers: the name is the table's primary key, and the row records which auth user holds it as a
 * morph, so an entity of any repository can hold one.
 */
final class UsernameUtils
{
	/**
	 * Whether this process has told the table's CRUD that the name may be written.
	 */
	private static bool $claims_allowed = false;

	/**
	 * Whether this process has registered the handler that allows the name to be written.
	 */
	private static bool $handler_registered = false;

	/**
	 * Check if a username exists.
	 */
	public static function exists(
		string $name,
	): bool {
		return null !== self::get($name);
	}

	/**
	 * Get a registered username entry.
	 */
	public static function get(
		string $name,
	): ?OZUsername {
		return OZUsername::qb()
			->whereNameIs($name)
			->find(ORMOptions::makePaginated(1))->fetchClass();
	}

	/**
	 * Claims a username for an auth user, and answers whether it got it.
	 *
	 * Nothing wrote to `oz_usernames` before this: the table was read and never filled, so the
	 * column pointing at it could hold nothing and no user could be given a username at all.
	 *
	 * The primary key is what decides, not the check above it: two requests claiming one name both
	 * read it as free, and the one the database refuses is told the name is taken, rather than
	 * raising for a race that has a right answer. The check is still there so the ordinary answer
	 * costs no exception.
	 *
	 * A name that was held and released is **kept**, soft-deleted rather than removed, and is not
	 * handed to anyone else here: a project that wants to recycle a freed name decides that for
	 * itself, knowing it lets one user be addressed by what another was known by.
	 *
	 * Setting the name on the user's own entity is the caller's: OZone knows what a username is and
	 * deliberately not what holds it ({@see AuthUserInterface}).
	 */
	public static function claim(string $name, AuthUserInterface $user): bool
	{
		if (self::exists($name)) {
			return false;
		}

		self::allowWritingTheName();

		try {
			new OZUsername()
				->setName($name)
				->setUserType($user->getAuthUserType())
				->setUserID($user->getAuthIdentifier())
				->save();
		} catch (DBALUniqueViolationException) {
			return false;
		} finally {
			self::$claims_allowed = false;
		}

		return true;
	}

	/**
	 * Allows the name, which is the table's primary key, to be written for the claim in progress.
	 *
	 * A primary key is refused by CRUD unless the table says otherwise, and a name is the one thing
	 * a row here is: the row cannot be created without writing it. So the permission is given for
	 * the one write that needs it and taken back as soon as it is made -- the handler is registered
	 * once per process and answers for the claim running, not for the process -- and a row written
	 * here by anything else is refused as it was before any of this existed.
	 *
	 * Registered from here rather than from a boot hook, so a request that claims no username never
	 * loads the generated class.
	 */
	private static function allowWritingTheName(): void
	{
		self::$claims_allowed = true;

		if (self::$handler_registered) {
			return;
		}

		self::$handler_registered = true;

		OZUsername::crud()->onBeforePKColumnWrite(static fn (): bool => self::$claims_allowed);
	}
}
