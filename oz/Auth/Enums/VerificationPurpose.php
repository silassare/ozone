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

namespace OZONE\Core\Auth\Enums;

use OZONE\Core\Exceptions\RuntimeException;

/**
 * What an email or phone ownership verification is for.
 *
 * The owner is told it before confirming, and a verification only serves its own purpose: one
 * started to check an address does not create or recover an account.
 */
enum VerificationPurpose: string
{
	/** A plain proof of ownership, for a project's own use. */
	case CHECK = 'check';

	/** Creating an account with the email or phone (`POST /signup`). */
	case SIGN_UP = 'sign-up';

	/** Setting a new password for the account of the email or phone (`POST /account-recovery`). */
	case ACCOUNT_RECOVERY = 'account-recovery';

	/**
	 * The lang key of each text a verification shows, by purpose. A recovery link opens a form, with
	 * texts of its own, so a recovery has no `LINK` text.
	 */
	private const array TEXTS = [
		'check' => [
			'EMAIL_SUBJECT' => 'OZ_AUTH_VERIFY_EMAIL_SUBJECT_CHECK',
			'EMAIL_TITLE'   => 'OZ_AUTH_VERIFY_EMAIL_TITLE_CHECK',
			'EMAIL_BODY'    => 'OZ_AUTH_VERIFY_EMAIL_BODY_CHECK',
			'EMAIL_BTN'     => 'OZ_AUTH_VERIFY_EMAIL_BTN_CHECK',
			'EMAIL_IGNORE'  => 'OZ_AUTH_VERIFY_EMAIL_IGNORE_CHECK',
			'PHONE_SMS'     => 'OZ_AUTH_VERIFY_PHONE_SMS_CHECK',
			'LINK'          => 'OZ_AUTH_VERIFY_LINK_CHECK',
		],
		'sign-up' => [
			'EMAIL_SUBJECT' => 'OZ_AUTH_VERIFY_EMAIL_SUBJECT_SIGN_UP',
			'EMAIL_TITLE'   => 'OZ_AUTH_VERIFY_EMAIL_TITLE_SIGN_UP',
			'EMAIL_BODY'    => 'OZ_AUTH_VERIFY_EMAIL_BODY_SIGN_UP',
			'EMAIL_BTN'     => 'OZ_AUTH_VERIFY_EMAIL_BTN_SIGN_UP',
			'EMAIL_IGNORE'  => 'OZ_AUTH_VERIFY_EMAIL_IGNORE_SIGN_UP',
			'PHONE_SMS'     => 'OZ_AUTH_VERIFY_PHONE_SMS_SIGN_UP',
			'LINK'          => 'OZ_AUTH_VERIFY_LINK_SIGN_UP',
		],
		'account-recovery' => [
			'EMAIL_SUBJECT' => 'OZ_AUTH_VERIFY_EMAIL_SUBJECT_ACCOUNT_RECOVERY',
			'EMAIL_TITLE'   => 'OZ_AUTH_VERIFY_EMAIL_TITLE_ACCOUNT_RECOVERY',
			'EMAIL_BODY'    => 'OZ_AUTH_VERIFY_EMAIL_BODY_ACCOUNT_RECOVERY',
			'EMAIL_BTN'     => 'OZ_AUTH_VERIFY_EMAIL_BTN_ACCOUNT_RECOVERY',
			'EMAIL_IGNORE'  => 'OZ_AUTH_VERIFY_EMAIL_IGNORE_ACCOUNT_RECOVERY',
			'PHONE_SMS'     => 'OZ_AUTH_VERIFY_PHONE_SMS_ACCOUNT_RECOVERY',
		],
	];

	/**
	 * The lang key of one of the texts a verification shows for this purpose: `EMAIL_SUBJECT`,
	 * `EMAIL_TITLE`, `EMAIL_BODY`, `EMAIL_BTN`, `EMAIL_IGNORE`, `PHONE_SMS` or `LINK`.
	 */
	public function text(string $part): string
	{
		return self::TEXTS[$this->value][$part]
			?? throw new RuntimeException(\sprintf('No "%s" text for the purpose "%s".', $part, $this->value));
	}
}
