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

namespace OZONE\OZ\Auth;

use OZONE\OZ\Auth\Interfaces\AuthCredentialsInterface;
use OZONE\OZ\Auth\Views\AuthLinkView;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Http\Uri;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AuthCredentials.
 */
class AuthCredentials implements AuthCredentialsInterface
{
	use ArrayCapableTrait;

	protected string $reference;
	protected string $refresh_key;
	protected string $code  = '';
	protected string $token = '';

	/**
	 * @param int  $auth_code_length    fixed auth code length
	 * @param bool $auth_code_alpha_num whether to use num only or alpha
	 *                                  numeric for code
	 */
	public function __construct(
		protected Context $context,
		protected int $auth_code_length = 6,
		protected bool $auth_code_alpha_num = false
	) {
		$this->reference   = Hasher::genAuthReference();
		$this->refresh_key = Hasher::genAuthRefreshKey($this->reference);
	}

	/**
	 * {@inheritDoc}
	 */
	public function newCode(): string
	{
		$this->code = Hasher::genAuthCode($this->auth_code_length, $this->auth_code_alpha_num);

		return $this->code;
	}

	/**
	 * {@inheritDoc}
	 */
	public function newToken(): string
	{
		$this->token = Hasher::genAuthToken();

		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setCode(string $code): AuthCredentialsInterface
	{
		$this->code = $code;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getToken(): string
	{
		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setToken(string $token): AuthCredentialsInterface
	{
		$this->token = $token;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRefreshKey(): string
	{
		return $this->refresh_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setRefreshKey(string $refresh_key): self
	{
		$this->refresh_key = $refresh_key;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReference(): string
	{
		return $this->reference;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setReference(string $reference): self
	{
		$this->reference = $reference;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getLink(): Uri
	{
		return $this->context->buildRouteUri(AuthLinkView::AUTH_LINK_ROUTE, [
			'ref'   => $this->reference,
			'token' => $this->token,
		]);
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'auth_link'  => $this->getLink(),
			'auth_code'  => $this->code,
			'auth_token' => $this->token,
		];
	}
}
