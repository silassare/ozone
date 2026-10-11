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

namespace OZONE\Tests\Access;

use OZONE\Core\Access\AtomicAction;
use OZONE\Core\Access\AtomicActionsRegistry;
use OZONE\Core\Lang\I18nMessage;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @internal
 *
 * @coversNothing
 */
final class AtomicActionsRegistryTest extends TestCase
{
	public function testTheSameActionRegisteredAgainIsKept(): void
	{
		$first = self::action('registry_test_same.read', 'read');

		AtomicActionsRegistry::register($first);
		AtomicActionsRegistry::register(self::action('registry_test_same.read', 'read'));

		self::assertSame($first, AtomicActionsRegistry::get('registry_test_same.read'));
	}

	public function testAnotherDefinitionUnderARegisteredNameThrows(): void
	{
		AtomicActionsRegistry::register(self::action('registry_test_other.read', 'read'));

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage("Action 'registry_test_other.read' already registered with another definition.");

		AtomicActionsRegistry::register(self::action('registry_test_other.read', 'write'));
	}

	private static function action(string $name, string $what): AtomicAction
	{
		return new AtomicAction(
			$name,
			new I18nMessage('OZ_ACCESS_RIGHT_DESCRIPTION', ['action' => $what]),
			new I18nMessage('OZ_MISSING_ACCESS_RIGHT', ['action' => $what])
		);
	}
}
