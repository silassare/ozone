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

namespace OZONE\Core\Senders\Interfaces;

/**
 * Interface MessageInterface.
 */
interface MessageInterface
{
	/**
	 * Inject data.
	 *
	 * @param array $inject
	 *
	 * @return $this
	 */
	public function inject(array $inject): static;

	/**
	 * @return string
	 */
	public function getContent(): string;

	/**
	 * Gets attributes.
	 *
	 * @return array
	 */
	public function getAttributes(): array;

	/**
	 * Gets attribute with a given key.
	 *
	 * @param string $name
	 * @param null   $default
	 *
	 * @return mixed
	 */
	public function getAttribute(string $name, $default = null): mixed;

	/**
	 * Send message.
	 *
	 * @param string $to
	 *
	 * @return $this
	 */
	public function send(string $to): static;
}
