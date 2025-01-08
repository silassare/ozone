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

namespace OZONE\Core\Http;

use InvalidArgumentException;
use JsonException;
use OZONE\Core\Exceptions\RuntimeException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class Response.
 */
class Response extends Message implements ResponseInterface
{
	/**
	 * EOL characters used for HTTP response.
	 */
	public const EOL = "\r\n";

	/**
	 * Status codes and reason phrases.
	 */
	protected static array $messages = [
		// Informational 1xx
		100 => 'Continue',
		101 => 'Switching Protocols',
		102 => 'Processing',
		// Successful 2xx
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		207 => 'Multi-Status',
		208 => 'Already Reported',
		226 => 'IM Used',
		// Redirection 3xx
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		306 => '(Unused)',
		307 => 'Temporary Redirect',
		308 => 'Permanent Redirect',
		// Client Error 4xx
		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		418 => 'I\'m a teapot',
		421 => 'Misdirected Request',
		422 => 'Unprocessable Entity',
		423 => 'Locked',
		424 => 'Failed Dependency',
		426 => 'Upgrade Required',
		428 => 'Precondition Required',
		429 => 'Too Many Requests',
		431 => 'Request Header Fields Too Large',
		444 => 'Connection Closed Without Response',
		451 => 'Unavailable For Legal Reasons',
		499 => 'Client Closed Request',
		// Server Error 5xx
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
		506 => 'Variant Also Negotiates',
		507 => 'Insufficient Storage',
		508 => 'Loop Detected',
		510 => 'Not Extended',
		511 => 'Network Authentication Required',
		// custom error same as the CloudFlare's Unknown Error
		520 => 'Unknown Error',
		599 => 'Network Connect Timeout Error',
	];

	/**
	 * Status code.
	 */
	protected int $status = 200;

	/**
	 * Reason phrase.
	 */
	protected string $reasonPhrase = '';

	/**
	 * Creates new HTTP response.
	 *
	 * @param int                  $status  the response status code
	 * @param null|Headers         $headers the response headers
	 * @param null|StreamInterface $body    the response body
	 */
	public function __construct(int $status = 200, ?Headers $headers = null, ?StreamInterface $body = null)
	{
		$this->status  = $this->filterStatus($status);
		$this->headers = $headers ?: new Headers();
		$this->body    = $body ?: Body::create();
	}

	/**
	 * This method is applied to the cloned object
	 * after PHP performs an initial shallow-copy. This
	 * method completes a deep-copy by creating new objects
	 * for the cloned object's internal reference pointers.
	 */
	public function __clone()
	{
		$this->headers = clone $this->headers;
	}

	/**
	 * Converts response to string.
	 *
	 * @return string
	 */
	public function __toString(): string
	{
		$output = \sprintf(
			'HTTP/%s %s %s',
			$this->getProtocolVersion(),
			$this->getStatusCode(),
			$this->getReasonPhrase()
		);
		$output .= self::EOL;

		foreach ($this->getHeaders() as $name => $values) {
			$output .= \sprintf('%s: %s', $name, $this->getHeaderLine($name)) . self::EOL;
		}
		$output .= self::EOL;
		$output .= $this->getBody();

		return $output;
	}

	/**
	 * Write data to the response body.
	 *
	 * Proxies to the underlying stream and writes the provided data to it.
	 *
	 * @param string $data
	 *
	 * @return $this
	 */
	public function write(string $data): static
	{
		$this->getBody()
			->write($data);

		return $this;
	}

	/**
	 * Redirect.
	 *
	 * This method prepares the response object to return an HTTP Redirect
	 * response to the client.
	 *
	 * @param string|UriInterface $url    the redirect destination
	 * @param null|int            $status the redirect HTTP status code
	 *
	 * @return $this
	 */
	public function withRedirect(string|UriInterface $url, ?int $status = null): static
	{
		$responseWithRedirect = $this->withHeader('Location', (string) $url);

		if (null === $status && 200 === $this->getStatusCode()) {
			$status = 302;
		}

		if (null !== $status) {
			/** @psalm-suppress UndefinedMethod */
			return $responseWithRedirect->withStatus($status);
		}

		return $responseWithRedirect;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getStatusCode(): int
	{
		return $this->status;
	}

	/**
	 * {@inheritDoc}
	 */
	public function withStatus(int $code, string $reasonPhrase = ''): static
	{
		$code = $this->filterStatus($code);

		$clone         = clone $this;
		$clone->status = $code;

		if ('' === $reasonPhrase && isset(static::$messages[$code])) {
			$reasonPhrase = static::$messages[$code];
		}

		if ('' === $reasonPhrase) {
			throw new InvalidArgumentException('Reason phrase must be supplied for this code');
		}

		$clone->reasonPhrase = (string) $reasonPhrase;

		return $clone;
	}

	/**
	 * Json.
	 *
	 * This method prepares the response object to return an HTTP Json
	 * response to the client.
	 *
	 * @param mixed    $data                The data
	 * @param null|int $status              the HTTP status code
	 * @param int      $jsonEncodingOptions Json encoding options
	 *
	 * @return $this
	 */
	public function withJson(mixed $data, ?int $status = null, int $jsonEncodingOptions = 0): static
	{
		try {
			$json = \json_encode($data, \JSON_THROW_ON_ERROR | $jsonEncodingOptions);
		} catch (JsonException $j) {
			throw new RuntimeException('Unable to encode data.', null, $j);
		}

		$body     = Body::fromString($json);
		$response = $this->withBody($body);

		$responseWithJson = $response->withHeader('Content-Type', 'application/json;charset=utf-8');

		if (isset($status)) {
			/** @psalm-suppress UndefinedMethod */
			return $responseWithJson->withStatus($status);
		}

		return $responseWithJson;
	}

	/**
	 * Is this response empty?
	 *
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return \in_array($this->getStatusCode(), [204, 205, 304], true);
	}

	/**
	 * Is this response informational?
	 *
	 * @return bool
	 */
	public function isInformational(): bool
	{
		return $this->getStatusCode() >= 100 && $this->getStatusCode() < 200;
	}

	/**
	 * Is this response OK?
	 *
	 * @return bool
	 */
	public function isOk(): bool
	{
		return 200 === $this->getStatusCode();
	}

	/**
	 * Is this response successful?
	 *
	 * @return bool
	 */
	public function isSuccessful(): bool
	{
		return $this->getStatusCode() >= 200 && $this->getStatusCode() < 300;
	}

	/**
	 * Is this response a redirect?
	 *
	 * @return bool
	 */
	public function isRedirect(): bool
	{
		return \in_array($this->getStatusCode(), [301, 302, 303, 307], true);
	}

	/**
	 * Is this response a redirection?
	 *
	 * @return bool
	 */
	public function isRedirection(): bool
	{
		return $this->getStatusCode() >= 300 && $this->getStatusCode() < 400;
	}

	/**
	 * Is this response forbidden?
	 *
	 * @return bool
	 */
	public function isForbidden(): bool
	{
		return 403 === $this->getStatusCode();
	}

	/**
	 * Is this response not Found?
	 *
	 * @return bool
	 */
	public function isNotFound(): bool
	{
		return 404 === $this->getStatusCode();
	}

	/**
	 * Is this response a client error?
	 *
	 * @return bool
	 */
	public function isClientError(): bool
	{
		return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
	}

	/**
	 * Is this response a server error?
	 *
	 * @return bool
	 */
	public function isServerError(): bool
	{
		return $this->getStatusCode() >= 500 && $this->getStatusCode() < 600;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReasonPhrase(): string
	{
		if ($this->reasonPhrase) {
			return $this->reasonPhrase;
		}

		return static::$messages[$this->status] ?? '';
	}

	/**
	 * Converts HTTP status code to reason phrase.
	 *
	 * @param int $status
	 *
	 * @return null|string
	 */
	public static function statusToReasonPhrase(int $status): ?string
	{
		return static::$messages[$status] ?? null;
	}

	/**
	 * Filters HTTP status code.
	 *
	 * @param int $status HTTP status code
	 *
	 * @return int
	 *
	 * @throws InvalidArgumentException if an invalid HTTP status code is provided
	 */
	protected function filterStatus(int $status): int
	{
		if ($status < 100 || $status > 599) {
			throw new InvalidArgumentException('Invalid HTTP status code');
		}

		return $status;
	}
}
