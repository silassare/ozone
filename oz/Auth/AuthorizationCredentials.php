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

namespace OZONE\Core\Auth;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Keys;
use OZONE\Core\Auth\Interfaces\AuthorizationCredentialsInterface;
use OZONE\Core\Auth\Views\AuthLinkView;
use OZONE\Core\Http\Uri;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AuthCredentials.
 */
class AuthorizationCredentials implements AuthorizationCredentialsInterface
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
		$this->reference   = Keys::newAuthReference();
		$this->refresh_key = Keys::newAuthRefreshKey($this->reference);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function newCode(): string
	{
		$this->code = Keys::newAuthCode($this->auth_code_length, $this->auth_code_alpha_num);

		return $this->code;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function newToken(): string
	{
		$this->token = Keys::newAuthToken();

		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getCode(): string
	{
		return $this->code;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setCode(string $code): AuthorizationCredentialsInterface
	{
		$this->code = $code;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getToken(): string
	{
		return $this->token;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setToken(string $token): AuthorizationCredentialsInterface
	{
		$this->token = $token;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getRefreshKey(): string
	{
		return $this->refresh_key;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setRefreshKey(string $refresh_key): self
	{
		$this->refresh_key = $refresh_key;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getReference(): string
	{
		return $this->reference;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function setReference(string $reference): self
	{
		$this->reference = $reference;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	#[Override]
	public function toArray(): array
	{
		return [
			'auth_link'  => $this->getLink(),
			'auth_code'  => $this->code,
			'auth_token' => $this->token,
		];
	}
}
