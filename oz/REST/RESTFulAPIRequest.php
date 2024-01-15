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

namespace OZONE\Core\REST;

use Gobl\ORM\Exceptions\ORMQueryException;
use Gobl\ORM\ORMRequest as GoblORMRequest;
use OZONE\Core\App\Context;
use OZONE\Core\Forms\FormData;

/**
 * Class RESTFulAPIRequest.
 */
class RESTFulAPIRequest extends GoblORMRequest
{
	private Context $context;

	/**
	 * RESTFulAPIRequest constructor.
	 *
	 * @param Context                          $context
	 * @param array|\OZONE\Core\Forms\FormData $form
	 * @param string                           $scope
	 *
	 * @throws ORMQueryException
	 */
	public function __construct(Context $context, array|FormData $form, string $scope = '')
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
	 * @return Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return static
	 */
	public function createScopedInstance(string $scope): static
	{
		return new self($this->context, $this->payload, $scope);
	}
}
