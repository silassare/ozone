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

namespace OZONE\OZ\Auth\Providers;

use OZONE\OZ\Auth\Interfaces\AuthScopeInterface;
use OZONE\OZ\Core\Context;

/**
 * Class AuthFile.
 */
class AuthFile extends AuthProvider
{
	public const NAME = 'auth:provider:file';

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(Context $context, ?AuthScopeInterface $scope = null): self
	{
		return new self($context, $scope);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getName(): string
	{
		return self::NAME;
	}
}
