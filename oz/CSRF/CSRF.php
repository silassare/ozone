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

namespace OZONE\OZ\CSRF;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Forms\FormData;

/**
 * Class CSRF.
 */
class CSRF
{
	public const TOKEN_SEP      = '.';
	private string $csrf_token  = '_csrf';
	private string $csrf_header = 'X-XSRF-TOKEN';
	private string $scope_ref;

	/**
	 * CSRF constructor.
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function __construct(private Context $context, private CSRFScope $scope)
	{
		switch ($this->scope) {
			case CSRFScope::SESSION:
				$this->scope_ref = $this->context->getSession()
					->getDataStore()
					->getToken();

				break;

			case CSRFScope::USER_IP:
				$this->scope_ref = $this->context->getUserIP(true, true, true);

				break;

			case CSRFScope::HOST:
				$this->scope_ref = $this->context->getHost(true);

				break;

			case CSRFScope::ACTIVE_USER:
				$this->context->getUsersManager()
					->assertUserVerified();
				$this->scope_ref = $this->context->getSession()
					->getDataStore()
					->getUserID();

				break;
		}
	}

	/**
	 * CSRF destructor.
	 */
	public function __destruct()
	{
		unset($this->scope, $this->context);
	}

	/**
	 * Check a csrf token validity in a given form.
	 *
	 * @param \OZONE\OZ\Forms\FormData $fd
	 *
	 * @return bool
	 */
	public function check(FormData $fd): bool
	{
		if ($token = $fd->get($this->csrf_token)) {
			$parts = \explode(self::TOKEN_SEP, $token);
			$id    = $parts[0] ?? null;
			$key   = $parts[1] ?? null;
		} elseif ($header = $this->context->getRequest()
			->getHeaderLine($this->csrf_header)) {
			$parts = \explode(self::TOKEN_SEP, $header);
			$id    = $parts[0] ?? null;
			$key   = $parts[1] ?? null;
		}

		if (empty($id) || empty($key)) {
			return false;
		}

		$known = $this->buildKey($id);

		return \hash_equals($known, $key);
	}

	/**
	 * Generate a new CSRF Token.
	 *
	 * @return string
	 */
	public function genCsrfToken(): string
	{
		$id  = (string) Hasher::randomInt();
		$key = $this->buildKey($id);

		return $id . self::TOKEN_SEP . $key;
	}

	/**
	 * Obfuscate a human readable string (Field name, Select option value etc.).
	 *
	 * @param string $human_readable_str
	 *
	 * @return string
	 */
	public function obfuscate(string $human_readable_str): string
	{
		$salt = Hasher::getSalt('OZ_AUTH_TOKEN_SALT');

		// obfuscation is not security keep it simple
		return Hasher::shorten($this->scope_ref . $human_readable_str . $salt);
	}

	/**
	 * Builds csrf token key part.
	 *
	 * @param string $id
	 *
	 * @return string
	 */
	private function buildKey(string $id): string
	{
		$salt = Hasher::getSalt('OZ_DEFAULT_SALT');

		return Hasher::hash64($id . $this->scope_ref . $salt);
	}
}
