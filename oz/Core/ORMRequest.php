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

namespace OZONE\OZ\Core;

use Gobl\ORM\ORMRequest as GoblORMRequest;
use OZONE\OZ\Forms\FormData;

/**
 * Class ORMRequest.
 */
final class ORMRequest extends GoblORMRequest
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private Context $context;

	/**
	 * ORMRequest constructor.
	 *
	 * @param \OZONE\OZ\Core\Context         $context
	 * @param array|\OZONE\OZ\Forms\FormData $form
	 * @param null|string                    $scope
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function __construct(Context $context, array|FormData $form, string $scope = null)
	{
		parent::__construct(\is_array($form) ? $form : $form->getData(), $scope);

		$this->context = $context;
	}

	/**
	 * ORMRequest destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * Gets the context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\OZ\Core\ORMRequest
	 */
	public function createScopedInstance(string $scope): self
	{
		return new self($this->context, $this->payload, $scope);
	}
}
