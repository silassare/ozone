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
use SensitiveParameter;

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

	#[Override]
	public function newCode(): string
	{
		$this->code = Keys::newAuthCode($this->auth_code_length, $this->auth_code_alpha_num);

		return $this->code;
	}

	#[Override]
	public function newToken(): string
	{
		$this->token = Keys::newAuthToken();

		return $this->token;
	}

	#[Override]
	public function getCode(): string
	{
		return $this->code;
	}

	#[Override]
	public function setCode(#[SensitiveParameter] string $code): static
	{
		$this->code = $code;

		return $this;
	}

	#[Override]
	public function getToken(): string
	{
		return $this->token;
	}

	#[Override]
	public function setToken(#[SensitiveParameter] string $token): static
	{
		$this->token = $token;

		return $this;
	}

	#[Override]
	public function getRefreshKey(): string
	{
		return $this->refresh_key;
	}

	#[Override]
	public function setRefreshKey(string $refresh_key): static
	{
		$this->refresh_key = $refresh_key;

		return $this;
	}

	#[Override]
	public function getReference(): string
	{
		return $this->reference;
	}

	#[Override]
	public function setReference(string $reference): static
	{
		$this->reference = $reference;

		return $this;
	}

	#[Override]
	public function getLink(): Uri
	{
		return $this->context->buildRouteUri(AuthLinkView::AUTH_LINK_ROUTE, [
			'ref'   => $this->reference,
			'token' => $this->token,
		]);
	}

	#[Override]
	public function toArray(): array
	{
		return [
			// A string: the array goes into a message's data, which is serialized to JSON when queued.
			'auth_link'  => (string) $this->getLink(),
			'auth_code'  => $this->code,
			'auth_token' => $this->token,
		];
	}
}
