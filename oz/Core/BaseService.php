<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use Exception;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Router\RouteProviderInterface;

\defined('OZ_SELF_SECURITY_CHECK') || die;

abstract class BaseService implements RouteProviderInterface
{
	/**
	 * @var \OZONE\OZ\Core\ResponseHolder
	 */
	private $response_holder;

	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * Converts Gobl exceptions unto O'Zone exceptions.
	 *
	 * @param \Exception $error the exception to convert
	 *
	 * @throws \OZONE\OZ\Exceptions\ForbiddenException
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \Exception
	 */
	public static function tryConvertException(Exception $error)
	{
		if ($error instanceof ORMQueryException) {
			throw new InvalidFormException($error->getMessage(), $error->getData(), $error);
		}

		if ($error instanceof TypesInvalidValueException) {
			$msg = $error->getMessage();

			if ($msg === \strtoupper($msg)) {
				throw new InvalidFormException($msg, $error->getData(), $error);
			}

			throw new InvalidFormException(null, [
				'type' => $msg,
				'data' => $error->getData(),
			], $error);
		}

		if ($error instanceof CRUDException) {
			throw new ForbiddenException($error->getMessage(), $error->getData(), $error);
		}

		throw $error;
	}

	/**
	 * BaseService constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function __construct(Context $context)
	{
		$this->context         = $context;
		$this->response_holder = new ResponseHolder(static::class);
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * Gets the service responses holder object.
	 *
	 * @return \OZONE\OZ\Core\ResponseHolder
	 */
	public function getResponseHolder()
	{
		return $this->response_holder;
	}

	/**
	 * Return service response.
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function respond()
	{
		$response_holder = $this->getResponseHolder();
		$data            = $response_holder->getResponse();
		$now             = \time();
		$data['utime']   = $now;
		$um              = $this->context->getUsersManager();

		if ($um->userVerified()) {
			$lifetime      = 1 * $this->context->getClient()
											   ->getSessionLifeTime();
			$data['stime'] = $now + $lifetime;

			if (SettingsManager::get('oz.sessions', 'OZ_SESSION_TOKEN_HEADER_ENABLED')) {
				$data['stoken'] = $um->getCurrentSessionToken();
			}
		}

		return $this->context->getResponse()
							 ->withJson($data);
	}
}
