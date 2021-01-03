<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Core;

use Gobl\DBAL\Table;
use Gobl\ORM\ORMRequestBase;

final class ORMRequest extends ORMRequestBase
{
	/**
	 * @var \OZONE\OZ\Core\Context
	 */
	private $context;

	/**
	 * ORMRequest constructor.
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param array                  $form
	 */
	public function __construct(Context $context, array $form)
	{
		parent::__construct($form);

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
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * @param \Gobl\DBAL\Table $table
	 *
	 * @return \OZONE\OZ\Core\ORMRequest
	 */
	public function createScopedInstance(Table $table)
	{
		$request = $this->getParsedRequest($table);

		return new self($this->context, $request);
	}
}
