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

namespace __PLH_NAMESPACE__;

use OZONE\Core\Auth\Events\SessionHijackingDetected;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;

/**
 * Writes down what a listener of SessionHijackingDetected is handed, for the integration test to
 * read back: a project's listener has to be able to say whose session it was and what changed
 * without reaching into the framework's own session object.
 */
final class SessionHijackingWitness implements BootHookReceiverInterface
{
	public static function boot(): void
	{
		SessionHijackingDetected::listen(static function (SessionHijackingDetected $ev): void {
			\file_put_contents('__PLH_WITNESS_FILE__', \json_encode([
				'auth_user_type'     => $ev->auth_user->getAuthUserType(),
				'auth_user_id'       => $ev->auth_user->getAuthIdentifier(),
				'session_id'         => $ev->session_id,
				'opened_source_key'  => $ev->opened_source_key,
				'request_source_key' => $ev->request_source_key,
				'user_ip'            => $ev->context->getUserIP(),
			], \JSON_THROW_ON_ERROR));
		});
	}
}
