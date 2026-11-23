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

namespace OZONE\Core\Exceptions;

use Exception;
use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\ORM\Exceptions\ORMQueryException;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\Exceptions\Traits\ExceptionCustomSuspectTrait;
use OZONE\Core\Exceptions\Traits\ExceptionWithCustomResponseTrait;
use OZONE\Core\Exceptions\Utils\ErrorUtils;
use OZONE\Core\Exceptions\Views\ErrorView;
use OZONE\Core\Hooks\Events\EndRequestHook;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Headers;
use OZONE\Core\Http\Response;
use OZONE\Core\Lang\I18nMessage;
use OZONE\Core\OZone;
use OZONE\Core\Runtime\Interfaces\ResponseSinkInterface;
use OZONE\Core\Runtime\Runtime;
use OZONE\Core\Utils\Utils;
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

	public const int BAD_REQUEST = 400;

	public const int UNAUTHORIZED = 401;

	public const int FORBIDDEN = 403;

	public const int NOT_FOUND = 404;

	public const int METHOD_NOT_ALLOWED = 405;

	public const int RATE_LIMIT_REACHED = 429;

	public const int GONE = 410;

	public const int INTERNAL_ERROR = 500;

	public const int SERVICE_UNAVAILABLE = 503;

	public const int UNKNOWN_ERROR = 520;

	// ozone custom error codes
	public const int UNAUTHENTICATED_USER = 10001;

	public const int INVALID_FORM = 10002;

	private const array ERRORS_STATUS_CODE_MAP = [
		self::BAD_REQUEST         => 400,
		self::UNAUTHORIZED        => 401,
		self::FORBIDDEN           => 403,
		self::GONE                => 410,
		self::NOT_FOUND           => 404,
		self::METHOD_NOT_ALLOWED  => 405,
		self::RATE_LIMIT_REACHED  => 429,
		self::INTERNAL_ERROR      => 500,
		self::SERVICE_UNAVAILABLE => 503,
		// default error same as the CloudFlare's Unknown Error
		self::UNKNOWN_ERROR       => 520,
		// ozone custom error codes
		self::UNAUTHENTICATED_USER     => 401,
		self::INVALID_FORM             => 400,
	];

	protected static bool $just_die = false;

	/**
	 * BaseException constructor.
	 *
	 * @param I18nMessage|string $message  the exception message
	 * @param null|array         $data     additional error data
	 * @param null|Throwable     $previous previous throwable used for the exception chaining
	 * @param int                $code     the exception code
	 */
	public function __construct(
		I18nMessage|string $message,
		?array $data = null,
		?Throwable $previous = null,
		int $code = 0
	) {
		$this->data = $data ?? [];
		if ($message instanceof I18nMessage) {
			$this->data = \array_merge($message->getData(), $this->data);
			$message    = $message->getText();
		}

		parent::__construct($message, $code, $previous);
	}

	/**
	 * OZone exception to string magic helper.
	 */
	public function __toString(): string
	{
		return self::throwableToString($this);
	}

	/**
	 * Forgets that an error has already been handled in this process.
	 *
	 * `$just_die` exists so a failure *while reporting a failure* cannot loop, and says only that a
	 * report is being made: {@see self::informClient()} clears it when its report ends. Released
	 * here too at the end of a request, so nothing a request did can outlive it in a worker.
	 *
	 * @internal on {@see EndRequestHook}
	 */
	public static function release(): void
	{
		self::$just_die = false;
	}

	/**
	 * Lets a request made in process report its own errors, until {@see self::leaveRequest()} is
	 * given what this returns: one made while an error is being reported -- an error page that calls
	 * a route -- would otherwise die on its first error, as a failure to report.
	 *
	 * @internal for {@see \OZONE\Core\App\Navigator::callAnonymously()}
	 *
	 * @return bool whether an error was being reported
	 */
	public static function enterRequest(): bool
	{
		$reporting      = self::$just_die;
		self::$just_die = false;

		return $reporting;
	}

	/**
	 * Puts back what {@see self::enterRequest()} replaced.
	 *
	 * @internal for {@see \OZONE\Core\App\Navigator::callAnonymously()}
	 *
	 * @param bool $reporting what {@see self::enterRequest()} returned
	 */
	public static function leaveRequest(bool $reporting): void
	{
		self::$just_die = $reporting;
	}

	/**
	 * Gets an http header reason phrase that matches this exception.
	 */
	public function getHTTPReasonPhrase(): string
	{
		return Response::statusToReasonPhrase($this->getHTTPStatusCode()) ?? '';
	}

	/**
	 * Gets an http header status code that matches this exception.
	 */
	public function getHTTPStatusCode(): int
	{
		$code = $this->getCode();

		return self::ERRORS_STATUS_CODE_MAP[$code] ?? self::ERRORS_STATUS_CODE_MAP[self::UNKNOWN_ERROR];
	}

	/**
	 * Show exception according to accept header name or request type.
	 *
	 * The response is built under the console too: the request being answered may be a sub-request
	 * a command or a job made, which reads the status of what it gets back, and printing the error
	 * and exiting there ended the whole process on the first refusal a sub-request met.
	 */
	public function informClient(Context $context): void
	{
		$request = $context->getRequest();

		$this->data['_url']    = (string) $request->getUri();
		$this->data['_method'] = $request->getMethod();

		oz_logger($this);

		self::clearOutPutBuffer($context);

		// We are in a critical situation: 'Error handling error'
		if (true === self::$just_die) {
			self::dieWithAnErrorHandlingErrorOccurred();
		}

		// Set while this report is being made, and only then: a failure from inside it is a failure
		// to report, which has nothing left to report it with. Once the response is built the report
		// is over, and a later error of the same request -- a second sub-request failing, the
		// request itself failing after one did -- is reported like the first.
		self::$just_die = true;

		try {
			$response = $this->getCustomResponse();

			if (null === $response) {
				if ($context->shouldReturnJSON()) {
					$response = $this->getJSONResponse($context);
				} else {
					$response = $this->getDefaultErrorPage($context);
				}
			}

			$context->setResponse($response->withStatus($this->getHTTPStatusCode()));
		} catch (Throwable $t) {
			oz_logger($t);
			self::dieWithAnErrorHandlingErrorOccurred();
		} finally {
			self::$just_die = false;
		}
	}

	/**
	 * Returns error occurred message.
	 */
	public static function anErrorOccurredMessage(string $type = 'error'): string
	{
		return \sprintf('An "%s" occurred. If you are an admin, please review the log file and correct it!', $type);
	}

	/**
	 * Die with an `unhandled error` message.
	 */
	public static function dieWithAnUnhandledErrorOccurred(): never
	{
		self::criticalDie(self::anErrorOccurredMessage('unhandled error'));
	}

	/**
	 * Die with an `error unhandled error` message.
	 *
	 * Called when an error / exception occurs while we
	 * want to show/handle another error / exception
	 */
	public static function dieWithAnErrorHandlingErrorOccurred(): never
	{
		self::criticalDie(self::anErrorOccurredMessage('error handling error'));
	}

	/**
	 * Try to send error message to the client.
	 */
	public static function criticalDie(string $err_msg): never
	{
		if (OZone::isCliMode()) {
			echo \PHP_EOL . $err_msg . \PHP_EOL;

			Runtime::current()->terminate(1);
		}

		$sink = self::responseSink();

		if (null === $sink && !\headers_sent()) {
			\header('HTTP/1.1 500 Internal Server Error');
		}

		// The process is going down: whatever it buffered goes, down to the level a server owns.
		Utils::closeOutputBuffers(1, false);

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

		if (null === $sink) {
			echo $err_html;
		} else {
			// Last resort, and still a response object: under RoadRunner the process output is the
			// protocol pipe, and bytes written to it would kill the worker rather than reach anyone.
			$sink->send(new Response(
				500,
				new Headers(['Content-Type' => 'text/html; charset=UTF-8']),
				Body::fromString($err_html)
			));
		}

		Runtime::current()->terminate(1);
	}

	/**
	 * Convert a given throwable to string.
	 */
	public static function throwableToString(Throwable $throwable): string
	{
		$describe = self::throwableDescribe($throwable)[0];

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
	 * @param Throwable $throwable the throwable to describe
	 * @param bool      $deep      if true, will describe all previous throwables
	 *
	 * @return array<array{file:string,line:int,code:int,message:string,data:array,class:string,trace:string}>
	 */
	public static function throwableDescribe(Throwable $throwable, bool $deep = false): array
	{
		$results = [];
		$current = $throwable;

		while ($current) {
			$data = [];

			if ($current instanceof RichExceptionInterface || \method_exists($current, 'getData')) {
				try {
					/** @var RichExceptionInterface $current */
					$data = ErrorUtils::redactSensitiveData($current->getData(true));
				} catch (Throwable $t) {
					$data = ['_error_will_calling_get_data_' => $t->getMessage()];
				}
			}

			$results[] = [
				'file'    => $current->getFile(),
				'line'    => $current->getLine(),
				'code'    => $current->getCode(),
				'message' => $current->getMessage(),
				'data'    => $data,
				'class'   => $current::class,
				'trace'   => $current->getTraceAsString(),
			];

			if (!$deep) {
				break;
			}
			$current = $current->getPrevious();
		}

		return $results;
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
	 * The worker's response sink of the current request, if there is a request and it has one.
	 */
	private static function responseSink(): ?ResponseSinkInterface
	{
		try {
			return Context::root()->getResponseSink();
		} catch (Throwable) {
			return null;
		}
	}

	/**
	 * Returns json response.
	 */
	private function getJSONResponse(Context $context): Response
	{
		$json = new JSONResponse();
		$json->setError($this->getMessage())
			->setData(ErrorUtils::redactSensitiveData($this->getData()));

		// The same envelope as a service's answer: a client reads one shape, whether the request
		// succeeded or failed.
		return $context->getResponse()
			->withJson($json->toEnvelope($context));
	}

	/**
	 * Returns default error page response.
	 */
	private function getDefaultErrorPage(Context $context): Response
	{
		return new ErrorView($context)->renderError($this);
	}

	/**
	 * Drops what the request being answered wrote: the buffers it opened, above the level its context
	 * recorded, and never one opened before it -- a sub-request's error used to close the buffers of
	 * the handler that made it.
	 */
	private static function clearOutPutBuffer(Context $context): void
	{
		Utils::closeOutputBuffers($context->outputBufferLevel(), false);
	}
}
