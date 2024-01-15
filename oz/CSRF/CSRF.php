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

namespace OZONE\Core\CSRF;

use OZONE\Core\App\Context;
use OZONE\Core\App\Keys;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Random;

/**
 * Class CSRF.
 */
class CSRF
{
	public const TOKEN_SEP    = '.';
	public const TOKEN_PARAM  = '_csrf';
	public const TOKEN_HEADER = 'X-XSRF-TOKEN';
	private string $scope_ref;

	/**
	 * CSRF constructor.
	 */
	public function __construct(private Context $context, private CSRFScope $scope)
	{
		switch ($this->scope) {
			case CSRFScope::SESSION:
				$this->scope_ref = $this->context->session()
					->id();

				break;

			case CSRFScope::ACTIVE_USER:
				$this->scope_ref = $this->context->user()
					->getID();

				break;

			case CSRFScope::USER_IP:
				$this->scope_ref = $this->context->getUserIP(true, true);

				break;

			case CSRFScope::HOST:
				$this->scope_ref = $this->context->getHost(true);

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
	 * @param FormData $fd
	 *
	 * @return bool
	 */
	public function check(FormData $fd): bool
	{
		if ($token = $fd->get(self::TOKEN_PARAM)) {
			$parts = \explode(self::TOKEN_SEP, $token);
			$id    = $parts[0] ?? null;
			$key   = $parts[1] ?? null;
		} elseif ($header = $this->context->getRequest()
			->getHeaderLine(self::TOKEN_HEADER)) {
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
		$id  = Random::num();
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
		// obfuscation is not security keep it simple
		return Hasher::shorten($this->scope_ref . $human_readable_str . Keys::salt());
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
		return Hasher::hash64($id . $this->scope_ref . Keys::salt());
	}
}
