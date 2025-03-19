<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\Core\REST;

use OpenApi\Annotations as OA;
use OZONE\Core\App\Context;
use OZONE\Core\OZone;
use OZONE\Core\REST\Events\ApiDocReady;
use OZONE\Core\REST\Interfaces\ApiDocProviderInterface;
use OZONE\Core\REST\Services\ApiDocService;
use OZONE\Core\REST\Traits\ApiDocManipulationTrait;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class ApiDoc.
 */
class ApiDoc implements ArrayCapableInterface
{
	use ApiDocManipulationTrait;
	use ArrayCapableTrait;

	protected OA\OpenApi $openapi;
	protected OA\Info $api_info;
	private static ?self $instance = null;

	/**
	 * ApiDoc constructor.
	 */
	private function __construct(protected Context $context, string $title, string $version)
	{
		$this->api_info = new OA\Info([
			'title'   => $title,
			'version' => $version,
		]);
		$this->openapi = new OA\OpenApi([
			$this->api_info,
		]);

		$this->loadProviders();
	}

	/**
	 * Gets the ApiDoc instance.
	 *
	 * @param Context $context
	 *
	 * @return ApiDoc
	 */
	public static function get(Context $context): self
	{
		if (!isset(self::$instance)) {
			self::$instance = new self($context, 'API Documentation', '1.0.0');
		}

		return self::$instance;
	}

	/**
	 * Gets the OpenApi view render data.
	 *
	 * @return array
	 */
	public function viewInject(): array
	{
		return [
			'api_doc_title'    => $this->api_info->title,
			'api_doc_spec_url' => $this->context->buildRouteUri(ApiDocService::API_DOC_SPEC_ROUTE),
			'api_doc_spec'     => $this->openapi->toJson(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function toArray(): array
	{
		return [
			'spec' => $this->openapi->toJson(),
		];
	}

	/**
	 * Load the API documentation providers.
	 */
	private function loadProviders(): void
	{
		$api  = OZone::getApiRoutesProviders();
		$web  = OZone::getWebRoutesProviders();

		$providers = $api + $web;

		foreach ($providers as $provider => $enabled) {
			if ($enabled && \is_subclass_of($provider, ApiDocProviderInterface::class)) {
				$provider::apiDoc($this);
			}
		}

		(new ApiDocReady($this))->dispatch();
	}
}
