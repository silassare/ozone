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

namespace OZONE\Core\Auth\Interfaces;

use OZONE\Core\App\Context;

/**
 * Interface TNetSectionInterface.
 *
 * A named part of `tnet`'s answer, registered by OZone, a plugin or a project in
 * `oz.tnet.sections`. `tnet` is the heartbeat a page already calls, so what a page needs to know
 * about its session and its app rides it instead of costing a request of its own.
 */
interface TNetSectionInterface
{
	/**
	 * Whether the section is answered only to a signed-in user.
	 *
	 * Read before the section is built, so a section about the reader never runs for nobody.
	 */
	public static function requiresAuthenticatedUser(): bool;

	/**
	 * The section's answer, or `null` for nothing this time.
	 *
	 * May throw: `tnet` leaves a failing section out and logs it, and never fails the heartbeat for
	 * one -- a page asking whether its session is alive must get its answer whatever a section did.
	 *
	 * @return null|array<string, mixed>
	 */
	public function answer(Context $context): ?array;
}
