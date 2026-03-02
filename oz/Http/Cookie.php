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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use PHPUtils\Str;

/**
 * Class Cookie.
 */
class Cookie
{
	/**
	 * The name of the cookie.
	 *
	 * @var string
	 */
	public string $name;

	/**
	 * The value of the cookie.
	 *
	 * @var string
	 */
	public string $value = '';

	/**
	 * The domain of the cookie.
	 *
	 * @var null|string
	 */
	public ?string $domain = null;

	/**
	 * The path of the cookie.
	 *
	 * @var null|string
	 */
	public ?string $path = null;

	/**
	 * The expiration time of the cookie in seconds.
	 *
	 * @var null|int
	 */
	public ?int $expires = null;

	/**
	 * The maximum age of the cookie in seconds.
	 *
	 * @var null|int
	 */
	public ?int $max_age = null;

	/**
	 * The secure flag of the cookie.
	 *
	 * @var bool
	 */
	public bool $secure = false;

	/**
	 * The httponly flag of the cookie.
	 *
	 * Prevent access from javascript
	 *
	 * @var bool
	 */
	public bool $httponly = false;

	/**
	 * The hostonly flag of the cookie.
	 *
	 * @var bool
	 */
	public bool $hostonly = false;

	/**
	 * The samesite flag of the cookie.
	 *
	 * `None`, `Lax` or `Strict`
	 *
	 * @var null|string
	 */
	public ?string $samesite = null;

	/**
	 * The partitioned flag of the cookie.
	 *
	 * @var bool
	 */
	public bool $partitioned = false;

	/**
	 * Cookie constructor.
	 *
	 * @param string $name  The name of the cookie
	 * @param string $value The value of the cookie
	 */
	public function __construct(
		string $name,
		string $value
	) {
		$this->name  = $name;
		$this->value = $value;
	}

	/**
	 * Returns the cookie as a `Set-Cookie` header string.
	 */
	public function __toString(): string
	{
		$result = \urlencode($this->name) . '=' . \urlencode($this->value);

		// __Host- and __Secure- prefixes
		// https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#cookie_prefixes
		if (Str::hasPrefix($this->name, '__Host-')) {
			$this->secure = true;
			$this->path   = '/';
			$this->domain = null;
		}
		if (Str::hasPrefix($this->name, '__Secure-')) {
			$this->secure = true;
		}

		// don't set domain for localhost
		// https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#attributes
		if (isset($this->domain) && !empty($this->domain) && 'localhost' !== $this->domain) {
			$result .= '; Domain=' . $this->domain;
		}

		if (isset($this->path)) {
			$result .= '; Path=' . $this->path;
		}

		if (isset($this->expires) && 0 !== $this->expires) {
			$result .= '; Expires=' . \gmdate('D, d-M-Y H:i:s', $this->expires) . ' GMT';
		}

		if (isset($this->max_age)) {
			$result .= '; Max-Age=' . $this->max_age;
		}

		if ($this->secure) {
			$result .= '; Secure';
		}

		if ($this->partitioned) {
			$result .= '; Partitioned';
		}

		if ($this->hostonly) {
			$result .= '; HostOnly';
		}

		if ($this->httponly) {
			$result .= '; HttpOnly';
		}

		if (
			$this->samesite && \in_array(\strtolower($this->samesite), [
				'none',
				'lax',
				'strict',
			], true)
		) {
			$result .= '; SameSite=' . $this->samesite;
		}

		return $result;
	}

	/**
	 * Create a new cookie with default settings.
	 *
	 * @param Context $context The context
	 * @param string  $name    The name of the cookie
	 * @param string  $value   The value of the cookie
	 *
	 * @return self
	 */
	public static function create(Context $context, string $name, string $value = ''): self
	{
		$cfg_domain      = Settings::get('oz.cookie', 'OZ_COOKIE_DOMAIN');
		$cfg_path        = Settings::get('oz.cookie', 'OZ_COOKIE_PATH');
		$cfg_lifetime    = Settings::get('oz.cookie', 'OZ_COOKIE_LIFETIME');
		$cfg_samesite    = Settings::get('oz.cookie', 'OZ_COOKIE_SAMESITE');
		$cfg_partitioned = Settings::get('oz.cookie', 'OZ_COOKIE_PARTITIONED');

		$request = $context->getRequest();
		$uri     = $request->getUri();
		$domain  = (empty($cfg_domain) || 'self' === $cfg_domain) ? $context->getHost() : $cfg_domain;
		$path    = (empty($cfg_path) || 'self' === $cfg_path) ? $uri->getBasePath() : $cfg_path;
		$path    = empty($path) ? '/' : $path;

		$cookie = new self($name, $value);

		$cookie->expires     = \time() + $cfg_lifetime;
		$cookie->path        = $path;
		$cookie->domain      = $domain;
		$cookie->httponly    = true;
		$cookie->secure      = 'https' === $uri->getScheme();
		$cookie->partitioned = $cfg_partitioned;
		$cookie->samesite    = $cfg_samesite;

		return $cookie;
	}

	/**
	 * Drop the cookie.
	 *
	 * @return $this
	 */
	public function drop(): self
	{
		$this->expires = \time() - 86400;
		$this->max_age = 0;
		$this->value   = '';

		return $this;
	}
}
