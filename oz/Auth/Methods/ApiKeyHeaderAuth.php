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

namespace OZONE\Core\Auth\Methods;

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthMethodType;
use OZONE\Core\Auth\Interfaces\AuthMethodInterface;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Router\RouteInfo;

/**
 * Class ApiKeyHeaderAuth.
 */
class ApiKeyHeaderAuth implements AuthMethodInterface
{
	use UserAuthMethodTrait;

	protected AuthMethodType $type    = AuthMethodType::API_KEY_HEADER;
	protected string         $api_key = '';

	/**
	 * ApiKeyHeaderAuth constructor.
	 */
	protected function __construct(protected RouteInfo $ri, protected string $realm)
	{
	}

	/**
	 * ApiKeyHeaderAuth destructor.
	 */
	public function __destruct()
	{
		unset($this->ri);
	}

	/**
	 * Returns the api key.
	 *
	 * @return string
	 */
	public function getApiKey(): string
	{
		return $this->api_key;
	}

	/**
	 * {@inheritDoc}
	 */
	public function satisfied(): bool
	{
		$api_key_name = Settings::get('oz.config', 'OZ_API_KEY_HEADER_NAME');
		$header_name  = \sprintf('HTTP_%s', \strtoupper(\str_replace('-', '_', $api_key_name)));

		$context = $this->ri->getContext();
		$request = $context->getRequest();
		$api_key = $request->getHeaderLine($header_name);

		if (empty($api_key)) {
			return false;
		}

		$this->api_key = $api_key;

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function get(RouteInfo $ri, string $realm): self
	{
		return new self($ri, $realm);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function authenticate(): void
	{
		$this->authenticateWithToken($this->api_key);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 */
	public function ask(): void
	{
		if (empty($this->api_key)) {
			throw new ForbiddenException('OZ_MISSING_API_KEY', [
				'url' => (string) $this->ri->uri(),
			]);
		}

		throw new ForbiddenException('OZ_YOUR_API_KEY_IS_NOT_VALID', [
			'url'     => (string) $this->ri->uri(),
			'api_key' => $this->api_key,
		]);
	}
}