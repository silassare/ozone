<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Exceptions;

use Exception;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\ResponseHolder;
use Throwable;

/**
 * Class BaseException
 */
abstract class BaseException extends Exception
{
	const BAD_REQUEST = 400;

	const UNAUTHORIZED_ACTION = 401;

	const FORBIDDEN = 403;

	const NOT_FOUND = 404;

	const METHOD_NOT_ALLOWED = 405;

	const INTERNAL_ERROR = 500;

	const UNKNOWN_ERROR = 520;

	// ozone custom error codes
	const UNVERIFIED_USER = 10001;

	const INVALID_FORM = 10002;

	const MESSAGE_INTERNAL_ERROR = 'An "Error" occurred. If you are an admin, please review the log file and correct it!';

	const MESSAGE_ERROR_UNHANDLED = 'An "unhandled error" occurred. If you are an admin, please review the log file and correct it!';

	const MESSAGE_ERROR_HANDLING_ERROR = 'An "Error handling error" occurred. If you are an admin, please review the log file and correct it!';

	const SENSITIVE_DATA_PREFIX = '_';

	/**
	 * @var bool
	 */
	protected static $just_die = false;

	private static $ERRORS_HEADER_MAP = [
		self::BAD_REQUEST         => 'HTTP/1.1 400 Bad Request',
		self::UNAUTHORIZED_ACTION => 'HTTP/1.1 401 Unauthorized',
		self::FORBIDDEN           => 'HTTP/1.1 403 Forbidden',
		self::NOT_FOUND           => 'HTTP/1.1 404 Not Found',
		self::METHOD_NOT_ALLOWED  => 'HTTP/1.1 405 Method Not Allowed',
		self::INTERNAL_ERROR      => 'HTTP/1.1 500 Internal Server Error',
		// default error same as the CloudFlare's Unknown Error
		self::UNKNOWN_ERROR       => 'HTTP/1.1 520 Unknown Error',
		// ozone custom error codes
		self::UNVERIFIED_USER     => 'HTTP/1.1 403 Forbidden',
		self::INVALID_FORM        => 'HTTP/1.1 400 Bad Request',
	];

	private static $ERRORS_STATUS_CODE_MAP = [
		self::BAD_REQUEST         => 400,
		self::UNAUTHORIZED_ACTION => 401,
		self::FORBIDDEN           => 403,
		self::NOT_FOUND           => 404,
		self::METHOD_NOT_ALLOWED  => 405,
		self::INTERNAL_ERROR      => 500,
		// default error same as the CloudFlare's Unknown Error
		self::UNKNOWN_ERROR       => 520,
		// ozone custom error codes
		self::UNVERIFIED_USER     => 403,
		self::INVALID_FORM        => 400,
	];

	private static $force_json = false;

	/**
	 * @var array
	 */
	protected $data;

	/**
	 * BaseException constructor.
	 *
	 * @param string          $message  the exception message
	 * @param int             $code     the exception code
	 * @param null|array      $data     additional error data
	 * @param null|\Throwable $previous previous throwable used for the exception chaining
	 */
	public function __construct($message, $code, array $data = null, $previous = null)
	{
		parent::__construct($message, $code, $previous);

		$this->data = \is_array($data) ? $data : [];// prevent null value
	}

	/**
	 * Gets data.
	 *
	 * We shouldn't expose all debug data to client, may contains sensitive data
	 * like table structure, table name etc.
	 * all sensitive data should be set with the sensitive data prefix
	 *
	 * @param bool $show_sensitive
	 *
	 * @return array
	 */
	public function getData($show_sensitive = false)
	{
		if (!$show_sensitive) {
			$data = [];

			foreach ($this->data as $key => $value) {
				if (\is_int($key) || $key[0] !== self::SENSITIVE_DATA_PREFIX) {
					$data[$key] = $value;
				}
			}

			return $data;
		}

		return $this->data;
	}

	/**
	 * Gets a http header string that corresponds to this exception
	 *
	 * @return string
	 */
	public function getHeaderString()
	{
		$code = $this->getCode();

		if (isset(self::$ERRORS_HEADER_MAP[$code])) {
			return self::$ERRORS_HEADER_MAP[$code];
		}

		return self::$ERRORS_HEADER_MAP[self::UNKNOWN_ERROR];
	}

	/**
	 * Gets a http header string that corresponds to this exception
	 *
	 * @return string
	 */
	public function getResponseStatusCode()
	{
		$code = $this->getCode();

		if (isset(self::$ERRORS_STATUS_CODE_MAP[$code])) {
			return self::$ERRORS_STATUS_CODE_MAP[$code];
		}

		return self::$ERRORS_STATUS_CODE_MAP[self::UNKNOWN_ERROR];
	}

	/**
	 * Show exception according to accept header name or request type
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function informClient(Context $context)
	{
		$request = $context->getRequest();

		$this->data['_url']    = (string) $request->getUri();
		$this->data['_method'] = $request->getMethod();

		oz_logger($this);

		self::clearOutPutBuffer();

		// We must avoid any other error, if there is one after this,
		// then we are in a critical situation: 'Error handling error'
		if (self::$just_die === true) {
			$this->errorHandlingError();
		}

		// Any other error or exception should not be tolerated
		self::$just_die = true;

		try {
			if (OZ_OZONE_IS_CLI) {
				die(\PHP_EOL . $this->getMessage() . \PHP_EOL);
			}
			$accept = $request->getHeaderLine('HTTP_ACCEPT');

			if (!self::$force_json && (\is_int(\strpos($accept, 'text/html')) || $context->isWebContext())) {
				$this->showCustomErrorPage($context);
			} else {
				$this->showJson($context);
			}
		} catch (Exception $e) {
			oz_logger($e);
			$this->errorHandlingError();
		} catch (Throwable $t) {
			oz_logger($t);
			$this->errorHandlingError();
		}
	}

	/**
	 * Called when an error / exception occurs while we
	 * want to show another error / exception
	 */
	private function errorHandlingError()
	{
		self::criticalDie(self::MESSAGE_ERROR_HANDLING_ERROR);
	}

	/**
	 * Show exception as json
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \Exception
	 */
	private function showJson(Context $context)
	{
		if (!\headers_sent()) {
			\header($this->getHeaderString());
		}

		$r = new ResponseHolder(\get_class($this));
		$r->setError($this->getMessage())
		  ->setData($this->getData());

		$response = $context->getResponse()
							->withStatus($this->getResponseStatusCode())
							->withJson($r->getResponse());
		$context->respond($response);
	}

	/**
	 * Show exception in a custom error page.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \Throwable
	 */
	private function showCustomErrorPage(Context $context)
	{
		$context->subRequestRoute('oz:error', [
			'exception' => $this,
			'context'   => $context,
		]);
	}

	/**
	 * Sets whether json response should be forced for error.
	 *
	 * @param bool $force_json
	 */
	public static function setForceJson($force_json)
	{
		self::$force_json = (bool) $force_json;
	}

	/**
	 * Try to send error message to the client.
	 *
	 * @param string $err_msg
	 */
	public static function criticalDie($err_msg)
	{
		if (OZ_OZONE_IS_CLI) {
			die(\PHP_EOL . $err_msg . \PHP_EOL);
		}

		if (!\headers_sent()) {
			\header(self::$ERRORS_HEADER_MAP[self::INTERNAL_ERROR]);
		}

		self::clearOutPutBuffer();

		$err_msg = \preg_replace('~\"([^\"]+)\"~', '<span>$1</span>', $err_msg);

		$err_html = <<<ERROR_PAGE
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Error</title>
		<meta
			name="viewport"
			content="width=device-width, height=device-height, initial-scale=1.0, maximum-scale=1.0, user-scalable=0"
		/>
		<style>
			body{
				margin: 0;
				font-family: 'Ubuntu', sans-serif;
			}
			div.oz-error{
				margin: 50px auto;
				width: 80%;
				padding: 10px 20px;
				border-left: 3px solid #ff5064;
				background-color: whitesmoke;
			}
			span{
				color: #ff5064
			}
		</style>
	</head>
	<body>
		<div class="oz-error">
			<p>$err_msg</p>
		</div>
	</body>
</html>
ERROR_PAGE;
		die($err_html);
	}

	/**
	 * Convert a given throwable to string.
	 *
	 * @param \Throwable $throwable
	 *
	 * @return string
	 */
	public static function throwableToString($throwable)
	{
		$describe = self::throwableDescribe($throwable);
		$data     = \json_encode($describe['data']);

		return <<<STRING
\tFile    : {$describe['file']},
\tLine    : {$describe['line']},
\tCode    : {$describe['code']},
\tMessage : {$describe['message']},
\tData    : {$data},
\tClass   : {$describe['class']},
\tTrace   : {$describe['trace']}
STRING;
	}

	/**
	 * Describe a given throwable.
	 *
	 * @param \Throwable $throwable
	 *
	 * @return array
	 */
	public static function throwableDescribe($throwable)
	{
		$data = [];

		if (\method_exists($throwable, 'getData')) {
			$data = $throwable->getData(true);
		}

		return [
			'file'    => $throwable->getFile(),
			'line'    => $throwable->getLine(),
			'code'    => $throwable->getCode(),
			'message' => $throwable->getMessage(),
			'data'    => $data,
			'class'   => \get_class($throwable),
			'trace'   => $throwable->getTraceAsString(),
		];
	}

	/**
	 * Converts Gobl exceptions unto OZone exceptions.
	 *
	 * @param \Throwable $throwable the throwable to convert
	 *
	 * @return \OZONE\OZ\Exceptions\BaseException
	 */
	public static function tryConvert($throwable)
	{
		if ($throwable instanceof self) {
			return $throwable;
		}

		if ($throwable instanceof TypesInvalidValueException || $throwable instanceof ORMQueryException) {
			$msg = $throwable->getMessage();

			if ($msg === \strtoupper($msg)) {
				return new InvalidFormException($msg, $throwable->getData(), $throwable);
			}

			return new InvalidFormException(null, [
				'type' => $msg,
				'data' => $throwable->getData(),
			], $throwable);
		}

		if ($throwable instanceof CRUDException) {
			return new ForbiddenException($throwable->getMessage(), $throwable->getData(), $throwable);
		}

		return new InternalErrorException(null, null, $throwable);
	}

	/**
	 * Try clear output buffer.
	 */
	private static function clearOutPutBuffer()
	{
		// clear the output buffer, 'Dirty linen should be washed as a family.'
		// loop until we get the top buffer level
		while (\ob_get_level()) {
			@\ob_end_clean();
		}
	}

	/**
	 * OZone exception to string formatter
	 *
	 * @return string
	 */
	public function __toString()
	{
		return self::throwableToString($this);
	}
}
