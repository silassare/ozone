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

use Gobl\DBAL\Table;
use Gobl\ORM\ORMRequestBase;
use OZONE\OZ\Forms\FormData;

/**
 * Class ORMRequest.
 */
final class ORMRequest extends ORMRequestBase
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
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function __construct(Context $context, array|FormData $form)
	{
		parent::__construct(\is_array($form) ? $form : $form->getData());

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
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * @param \Gobl\DBAL\Table $table
	 *
	 * @return \OZONE\OZ\Core\ORMRequest
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 */
	public function createScopedInstance(Table $table): self
	{
		$request = $this->getParsedRequest($table);

		return new self($this->context, $request);
	}
}
