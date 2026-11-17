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

use OZONE\Core\OZone;
use OZONE\Core\REST\Enums\RESTFulAction;

/**
 * Class ServiceExport.
 *
 * What a client needs of the API's REST services, and the one thing {@see RouteExport} cannot say:
 * **what each service answers**.
 *
 * A route named `events.get_all` already travels with its path and its parameters, so a client can
 * call it. Nothing in that says which table is behind it, and so nothing above the call can say what
 * comes back: rows with no columns, a column name that is any string, and an id written for the
 * wrong table found by a reader looking at an empty page. With the morph type of the entity beside
 * the service name, the shapes Gobl writes for that table join to it, and a client's build is
 * working with real columns.
 *
 * **Read off the declarations and never off the routes.** A service declares its name, its table and
 * its key column as constants and may turn actions off ({@see RESTFulService}), which is the answer;
 * reading it back out of the registered routes would be guessing at the declaration from what it
 * produced, and would stop being able to tell a service with no listing from one whose listing is
 * named differently.
 *
 * @psalm-type ExportedService = array{
 *     name: string,
 *     entity: string,
 *     table: string,
 *     key: string,
 *     actions: list<string>,
 * }
 */
final class ServiceExport
{
	/**
	 * The REST services the API router's providers declare, sorted by name.
	 *
	 * Sorted so that two runs over one project write the same bytes, which is what lets the file a
	 * client builds from be committed and compared.
	 *
	 * @return list<ExportedService>
	 */
	public static function of(): array
	{
		$out = [];

		foreach (OZone::getApiRoutesProviders() as $provider => $enabled) {
			if (!$enabled || !\is_subclass_of($provider, RESTFulService::class)) {
				continue;
			}

			$out[] = self::ofService($provider);
		}

		\usort($out, static fn (array $a, array $b): int => \strcmp($a['name'], $b['name']));

		return $out;
	}

	/**
	 * One service: its name, the entity it answers, and the actions it really serves.
	 *
	 * @param class-string<RESTFulService> $service
	 *
	 * @return ExportedService
	 */
	private static function ofService(string $service): array
	{
		$service::assertDeclared();

		$table   = db()->getTableOrFail($service::TABLE_NAME);
		$actions = [];

		foreach (RESTFulAction::cases() as $action) {
			if ($service::isActionEnabled($action)) {
				$actions[] = $action->value;
			}
		}

		return [
			'name' => $service::SERVICE_NAME,
			// The morph type, which Gobl keeps unique across the database and keys its generated
			// shapes by: the one word that joins a service to the shape of what it answers.
			'entity' => $table->getMorphType(),
			'table'  => $table->getName(),
			// The full name, not the short one the service declares (`id`): a row carries
			// `event_id`, and reading an id off a row is the whole of what a client does with it.
			'key' => $table->getColumnOrFail($service::KEY_COLUMN)
				->getFullName(),
			'actions' => $actions,
		];
	}
}
