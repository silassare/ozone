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

namespace OZONE\Core\Cli\Cmd;

use Kli\KliArgs;
use Kli\Table\KliTable;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\OZone;
use OZONE\Core\Router\RouteExport;

/**
 * Class RoutesCmd.
 *
 * A client calls routes by name, never by path: this command gives it the names, with what it needs
 * to build each request.
 */
final class RoutesCmd extends Command
{
	/**
	 * Exports the named routes of the API router.
	 *
	 * @param KliArgs $args
	 */
	public function export(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$routes = RouteExport::of(OZone::getApiRouter());
		$cli    = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson(['routes' => $routes]);
		}

		$table = new KliTable();

		$table->addHeader('Name', 'name')->alignLeft();
		$table->addHeader('Methods', 'methods')->alignLeft();
		$table->addHeader('Path', 'path')->alignLeft();
		$table->addHeader('Form', 'form')->alignLeft();
		$table->addRows(\array_map(static fn (array $route): array => [
			'name'    => $route['name'],
			'methods' => \implode(', ', $route['methods']),
			'path'    => $route['path'],
			'form'    => $route['form'] ? ($route['resumable'] ? 'resumable' : 'yes') : '',
		], $routes));

		$cli->writeLn((string) $table, false);
		$cli->info(\sprintf('%d named route(s).', \count($routes)));
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Inspect the routes of the project.');

		$export = $this->action('export', 'Export the named routes of the API, for clients to call them by name.');
		self::withJsonSupport($export);
		$export->handler($this->export(...));
	}
}
