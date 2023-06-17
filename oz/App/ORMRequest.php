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

namespace OZONE\Core\App;

use Gobl\ORM\ORMRequest as GoblORMRequest;
use OZONE\Core\Forms\FormData;

/**
 * Class ORMRequest.
 */
final class ORMRequest extends GoblORMRequest
{
	/**
	 * @var \OZONE\Core\App\Context
	 */
	private Context $context;

	/**
	 * ORMRequest constructor.
	 *
	 * @param \OZONE\Core\App\Context          $context
	 * @param array|\OZONE\Core\Forms\FormData $form
	 * @param null|string                      $scope
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
	 * @return \OZONE\Core\App\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return \OZONE\Core\App\ORMRequest
	 */
	public function createScopedInstance(string $scope): self
	{
		return new self($this->context, $this->payload, $scope);
	}
}
