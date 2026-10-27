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

namespace OZONE\Core\Columns\Types;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Override;
use OZONE\Core\Http\Uri;

/**
 * Class TypeUrl.
 *
 * @extends Type<mixed, null|string>
 */
class TypeUrl extends Type
{
	public const string NAME = 'url';

	/**
	 * Regex for validating absolute URL paths (starting with a single slash, no scheme or host).
	 * Allows optional query string and fragment. Disallows control characters and spaces.
	 * This is used when allow_absolute_path option is enabled, to validate URLs that are not
	 * full URLs with scheme and host, but just paths (e.g. "/foo/bar?baz=qux#fragment").
	 */
	private const string ABSOLUTE_URL_PATH_REG = '~^\/[^\s?#]*(\?[^\s#]*)?(#[^\s]*)?$~u';

	/**
	 * TypeUrl constructor.
	 *
	 * @throws TypesException
	 */
	public function __construct()
	{
		parent::__construct(new TypeString(1, 2000));
	}

	/**
	 * Allow URL without scheme and host (absolute path + query + fragment).
	 *
	 * @return $this
	 */
	public function allowAbsolutePath(bool $allow_absolute_path = true): static
	{
		return $this->setOption('allow_absolute_path', $allow_absolute_path);
	}

	/**
	 * Restricts full URLs to http(s) on the given hosts (compared case-insensitively).
	 *
	 * Absolute paths, when allowed, are unaffected. An empty list lifts the restriction.
	 * Use it for user-supplied redirect targets, which would otherwise be open redirects.
	 *
	 * @param list<string> $hosts
	 *
	 * @return $this
	 */
	public function allowedHosts(array $hosts): static
	{
		$hosts = \array_map(static fn ($host) => \strtolower(\trim((string) $host)), $hosts);

		return $this->setOption('allowed_hosts', \array_values(\array_unique(\array_filter($hosts))));
	}

	#[Override]
	public static function getInstance(array $options): static
	{
		return (new static())->configure($options);
	}

	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	#[Override]
	public function default($default): static
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	#[Override]
	public function configure(array $options): static
	{
		if (isset($options['allow_absolute_path'])) {
			if ($options['allow_absolute_path']) {
				$this->allowAbsolutePath();
			}
		}

		if (isset($options['allowed_hosts']) && \is_array($options['allowed_hosts'])) {
			$this->allowedHosts($options['allowed_hosts']);
		}

		return parent::configure($options);
	}

	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();

		try {
			$value = $this->base_type->validate($value)->getCleanValue();
		} catch (TypesInvalidValueException $e) {
			$subject->reject(new TypesInvalidValueException('OZ_FIELD_URL_INVALID', null, $e));

			return;
		}

		$debug = [
			'value' => $value,
		];

		if (null !== $value && '' !== $value) {
			$allow_absolute_path = (bool) $this->getOption('allow_absolute_path', false);

			if ($allow_absolute_path && \str_starts_with($value, '/') && !\str_starts_with($value, '//')) {
				// Validate as an absolute path: only path characters, optional query string
				// and fragment. No scheme, no host, no control characters, no spaces.
				if (!\preg_match(self::ABSOLUTE_URL_PATH_REG, $value)) {
					$subject->reject(new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug));

					return;
				}
				$value = (string) Uri::createFromString($value);
			} elseif (!Uri::isStrictUrl($value)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_URL_INVALID', $debug));

				return;
			} elseif (!$this->isAllowedHost($value)) {
				$subject->reject(new TypesInvalidValueException('OZ_FIELD_URL_HOST_NOT_ALLOWED', $debug));

				return;
			}
		}

		$subject->accept($value);
	}

	/**
	 * Whether a full URL passes the {@see self::allowedHosts()} restriction.
	 *
	 * The scheme is not asked about here: {@see Uri::isStrictUrl()} has already refused anything
	 * but `http` and `https`, for a value with a host list and for one without. It used to be
	 * asked only when a list was configured, so a column with no list accepted whatever
	 * `FILTER_VALIDATE_URL` did -- `javascript://comment%0Aalert(1)` among it.
	 */
	private function isAllowedHost(string $url): bool
	{
		$hosts = (array) $this->getOption('allowed_hosts', []);

		if (empty($hosts)) {
			return true;
		}

		return \in_array(\strtolower(Uri::createFromString($url)->getHost()), $hosts, true);
	}
}
