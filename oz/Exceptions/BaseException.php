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

namespace OZONE\OZ\Exceptions;

use Exception;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\JSONResponse;
use OZONE\OZ\Exceptions\Traits\ExceptionCustomSuspectTrait;
use OZONE\OZ\Exceptions\Traits\ExceptionWithCustomResponseTrait;
use OZONE\OZ\Exceptions\Views\ErrorView;
use OZONE\OZ\Http\Response;
use OZONE\OZ\Lang\I18nMessage;
use OZONE\OZ\Logger\Logger;
use OZONE\OZ\OZone;
use OZONE\OZ\Utils\Utils;
use PHPUtils\Interfaces\RichExceptionInterface;
use PHPUtils\Traits\RichExceptionTrait;
use Throwable;

/**
 * Class BaseException.
 */
abstract class BaseException extends Exception implements RichExceptionInterface
{
	use ExceptionCustomSuspectTrait;
	use ExceptionWithCustomResponseTrait;
	use RichExceptionTrait;

	public const BAD_REQUEST = 400;

	public const UNAUTHORIZED_ACTION = 401;

	public const FORBIDDEN = 403;

	public const NOT_FOUND = 404;

	public const METHOD_NOT_ALLOWED = 405;

	public const INTERNAL_ERROR = 500;

	public const UNKNOWN_ERROR = 520;

	// ozone custom error codes
	public const UNVERIFIED_USER = 10001;

	public const INVALID_FORM = 10002;

	private const ERRORS_STATUS_CODE_MAP = [
		self::BAD_REQUEST         => 400,
		self::UNAUTHORIZED_ACTION => 401,
		self::FORBIDDEN           => 403,
		self::NOT_FOUND           => 404,
		self::METHOD_NOT_ALLOWED  => 405,
		self::INTERNAL_ERROR      => 500,
		// default error same as the CloudFlare's Unknown Error
		self::UNKNOWN_ERROR       => 520,
		// ozone custom error codes
		self::UNVERIFIED_USER     => 401,
		self::INVALID_FORM        => 400,
	];

	/**
	 * @var bool
	 */
	protected static bool $just_die = false;

	/**
	 * BaseException constructor.
	 *
	 * @param I18nMessage|string $message  the exception message
	 * @param null|array         $data     additional error data
	 * @param null|Throwable     $previous previous throwable used for the exception chaining
	 * @param int                $code     the exception code
	 */
	public function __construct(string|I18nMessage $message, ?array $data = null, Throwable $previous = null, int $code = 0)
	{
		$this->data = $data ?? [];
		if ($message instanceof I18nMessage) {
			$this->data = \array_merge($message->getInject(), $this->data);
			$message    = $message->getText();
		}

		parent::__construct($message, $code, $previous);
	}

	/**
	 * OZone exception to string magic helper.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		return self::throwableToString($this);
	}

	/**
	 * Gets an http header reason phrase that matches this exception.
	 *
	 * @return string
	 */
	public function getHTTPReasonPhrase(): string
	{
		return Response::statusToReasonPhrase($this->getHTTPStatusCode()) ?? '';
	}

	/**
	 * Gets an http header status code that matches this exception.
	 *
	 * @return int
	 */
	public function getHTTPStatusCode(): int
	{
		$code = $this->getCode();

		return self::ERRORS_STATUS_CODE_MAP[$code] ?? self::ERRORS_STATUS_CODE_MAP[self::UNKNOWN_ERROR];
	}

	/**
	 * Show exception according to accept header name or request type.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 */
	public function informClient(Context $context): void
	{
		$request = $context->getRequest();

		$this->data['_url']    = (string) $request->getUri();
		$this->data['_method'] = $request->getMethod();

		Logger::log($this);

		self::clearOutPutBuffer();

		// We are in a critical situation: 'Error handling error'
		if (true === self::$just_die) {
			self::dieWithAnErrorHandlingErrorOccurred();
		}

		// Any other error or exception should not be tolerated
		// We must avoid any other error, if there is another error we must die
		self::$just_die = true;

		try {
			if (OZone::isCliMode()) {
				exit(\PHP_EOL . $this->getMessage() . \PHP_EOL);
			}

			$response = $this->getCustomResponse();

			if (null === $response) {
				if ($context->shouldReturnJSON(true)) {
					$response = $this->getJSONResponse($context);
				} else {
					$response = $this->getDefaultErrorPage($context);
				}
			}

			$context->setResponse($response->withStatus($this->getHTTPStatusCode()));
		} catch (Throwable $t) {
			Logger::log($t);
			self::dieWithAnErrorHandlingErrorOccurred();
		}
	}

	/**
	 * Returns error occurred message.
	 *
	 * @param string $type
	 *
	 * @return string
	 */
	public static function anErrorOccurredMessage(string $type = 'error'): string
	{
		return \sprintf('An "%s" occurred. If you are an admin, please review the log file and correct it!', $type);
	}

	/**
	 * Die with an `unhandled error` message.
	 */
	public static function dieWithAnUnhandledErrorOccurred(): void
	{
		self::criticalDie(self::anErrorOccurredMessage('unhandled error'));
	}

	/**
	 * Die with an `error unhandled error` message.
	 *
	 * Called when an error / exception occurs while we
	 * want to show/handle another error / exception
	 */
	public static function dieWithAnErrorHandlingErrorOccurred(): void
	{
		self::criticalDie(self::anErrorOccurredMessage('error handling error'));
	}

	/**
	 * Try to send error message to the client.
	 *
	 * @param string $err_msg
	 */
	public static function criticalDie(string $err_msg): void
	{
		if (OZone::isCliMode()) {
			exit(\PHP_EOL . $err_msg . \PHP_EOL);
		}

		if (!\headers_sent()) {
			\header('HTTP/1.1 500 Internal Server Error');
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
			<p>{$err_msg}</p>
		</div>
	</body>
</html>
ERROR_PAGE;

		exit($err_html);
	}

	/**
	 * Convert a given throwable to string.
	 *
	 * @param Throwable $throwable
	 *
	 * @return string
	 */
	public static function throwableToString(Throwable $throwable): string
	{
		$describe = self::throwableDescribe($throwable);

		try {
			$data = \json_encode($describe['data'], \JSON_THROW_ON_ERROR);
		} catch (Throwable $t) {
			/** @noinspection JsonEncodingApiUsageInspection */
			$data = \json_encode(['_error_will_encoding_data_' => $t->getMessage()]);
		}

		return <<<STRING
\tFile    : {$describe['file']}
\tLine    : {$describe['line']}
\tCode    : {$describe['code']}
\tMessage : {$describe['message']}
\tData    : {$data}
\tClass   : {$describe['class']}
\tTrace   : {$describe['trace']}
STRING;
	}

	/**
	 * Describe a given throwable.
	 *
	 * @param Throwable $throwable
	 *
	 * @return array
	 */
	public static function throwableDescribe(Throwable $throwable): array
	{
		$data = [];

		if (\method_exists($throwable, 'getData')) {
			try {
				$data = $throwable->getData(true);
			} catch (Throwable $t) {
				$data = ['_error_will_calling_get_data_' => $t->getMessage()];
			}
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
	 * Converts Gobl exceptions into OZone exceptions.
	 *
	 * @param Throwable $throwable the throwable to convert
	 */
	public static function tryConvert(Throwable $throwable): self
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
	 * Returns json response.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function getJSONResponse(Context $context): Response
	{
		$json = new JSONResponse();
		$json->setError($this->getMessage())
			->setData($this->getData());

		return $context->getResponse()
			->withJson($json);
	}

	/**
	 * Returns default error page response.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	private function getDefaultErrorPage(Context $context): Response
	{
		return (new ErrorView($context))->renderError($this);
	}

	/**
	 * Try clear output buffer.
	 */
	private static function clearOutPutBuffer(): void
	{
		Utils::closeOutputBuffers(1, false);
	}
}
