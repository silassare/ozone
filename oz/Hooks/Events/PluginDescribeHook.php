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

namespace OZONE\Core\Hooks\Events;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Plugins\Interfaces\PluginInterface;
use OZONE\Core\Plugins\Plugins;
use PHPUtils\Events\Event;

/**
 * Class PluginDescribeHook.
 *
 * Triggered while a plugin is described ({@see Plugins::describe()}), so another plugin can say
 * what this one adds that only it knows how to read: a plugin that defines a kind of file other
 * plugins ship lists the ones the described plugin ships. Each listener adds a section of its own,
 * read without booting the described plugin, as the rest of the description is.
 */
final class PluginDescribeHook extends Event
{
	/** @var array<string, array<string, list<string>>> */
	private array $sections = [];

	/**
	 * PluginDescribeHook constructor.
	 *
	 * @param PluginInterface $plugin the plugin being described
	 */
	public function __construct(public readonly PluginInterface $plugin) {}

	/**
	 * Adds a section to the description: a list of names under each label, such as the names of
	 * each kind of file the plugin ships.
	 *
	 * @param string                      $section the section's name, one per listener
	 * @param array<string, list<string>> $entries the names, by label
	 *
	 * @throws RuntimeException when another listener already added a section of that name
	 */
	public function addSection(string $section, array $entries): static
	{
		if (isset($this->sections[$section])) {
			throw new RuntimeException(
				\sprintf('The description of plugin "%s" already has a section "%s".', $this->plugin::class, $section)
			);
		}

		$this->sections[$section] = $entries;

		return $this;
	}

	/**
	 * The sections added, by name.
	 *
	 * @return array<string, array<string, list<string>>>
	 */
	public function getSections(): array
	{
		return $this->sections;
	}
}
