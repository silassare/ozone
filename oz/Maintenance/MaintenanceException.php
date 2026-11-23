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

namespace OZONE\Core\Maintenance;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\BaseException;
use OZONE\Core\Http\Response;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\Maintenance\Views\MaintenanceView;
use OZONE\Core\Utils\Utils;
use Throwable;

/**
 * Class MaintenanceException.
 *
 * What a request a maintenance holds is answered: 503, with `Retry-After` when the maintenance gave
 * one and `Cache-Control: no-store` so no cache keeps serving it after `off`. An API request, and a
 * web one that asks for JSON only, gets the envelope of every OZone answer with the message code
 * {@see self::MESSAGE}; a web page gets the maintenance page (`oz.maintenance.blate`).
 */
final class MaintenanceException extends BaseException
{
	/**
	 * The message code of the answer.
	 */
	public const string MESSAGE = 'OZ_MAINTENANCE';

	/**
	 * The maintenance that holds the request, when it is known.
	 */
	private ?MaintenanceMode $mode = null;

	/**
	 * MaintenanceException constructor.
	 *
	 * @param null|I18nMessage|string $message  the exception message
	 * @param null|array              $data     additional exception data
	 * @param null|Throwable          $previous previous throwable used for the exception chaining
	 */
	public function __construct(
		I18nMessage|string|null $message = null,
		?array $data = null,
		?Throwable $previous = null
	) {
		parent::__construct(
			empty($message) ? self::MESSAGE : $message,
			$data,
			$previous,
			BaseException::SERVICE_UNAVAILABLE,
		);
	}

	/**
	 * The answer to a request a maintenance holds.
	 */
	public static function of(MaintenanceMode $mode): self
	{
		$e       = new self();
		$e->mode = $mode;

		return $e;
	}

	/**
	 * The maintenance that holds the request, when it is known.
	 */
	public function getMode(): ?MaintenanceMode
	{
		return $this->mode;
	}

	/**
	 * Sets the answer on the context.
	 *
	 * Not logged, unlike other errors: a request held by a maintenance is the maintenance working,
	 * and a line per held request would bury whatever does go wrong while it lasts. Should the
	 * answer itself fail -- a page that does not render -- that is logged, and the request still
	 * gets the generic error page with this status.
	 */
	#[Override]
	public function informClient(Context $context): void
	{
		Utils::closeOutputBuffers($context->outputBufferLevel(), false);

		try {
			$context->setResponse($this->answer($context));
		} catch (Throwable $t) {
			oz_logger($t);

			parent::informClient($context);
		}
	}

	/**
	 * The answer to a request held by the maintenance.
	 */
	public function answer(Context $context): Response
	{
		if ($context->isApiContext() || $context->shouldReturnJSON()) {
			$json = new JSONResponse();
			$json->setError(self::MESSAGE)
				->setData([
					'retry_after' => $this->mode?->retryAfter,
					'message'     => $this->mode?->message,
				]);

			$response = $context->getResponse()->withJson($json->toEnvelope($context));
		} else {
			$response = new MaintenanceView($context)->renderMaintenance($this->mode?->message);
		}

		$response = $response->withStatus($this->getHTTPStatusCode())
			->withHeader('Cache-Control', 'no-store');

		$retry_after = $this->mode?->retryAfter;

		if (null !== $retry_after) {
			$response = $response->withHeader('Retry-After', (string) $retry_after);
		}

		return $response;
	}
}
