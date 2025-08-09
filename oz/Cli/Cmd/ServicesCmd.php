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

use Gobl\DBAL\Table;
use Kli\KliArgs;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\ServiceGenerator;
use OZONE\Core\Cli\Utils\Utils;
use PHPUtils\Str;

/**
 * Class ServicesCmd.
 */
final class ServicesCmd extends Command
{
	/**
	 * {@inheritDoc}
	 */
	protected function describe(): void
	{
		$this->description('Manage your project service.');

		$class_name_reg = '~^[a-zA-Z_][a-zA-Z0-9_]*$~';
		$path_reg       = '~^/[a-zA-Z0-9_-]+(/[a-zA-Z0-9_-]+)*$~';
		// action: generate service for a table
		$generate = $this->action('generate', 'Generate service for a table in the database.');
		$generate->option('service-path', 'p', [], 1)
			->required()
			->prompt(true, 'The service path')
			->description('The service path.')
			->string()
			->pattern($path_reg, \sprintf('The service path is invalid, required pattern: "%s"', \trim($path_reg, $path_reg[0])));

		$generate->option('table-name', 't', [], 2)
			->required()
			->prompt(true, 'The table name')
			->description('The table name.')
			->string()
			->pattern(Table::NAME_REG, \sprintf('The table name is invalid, required pattern: "%s"', Table::NAME_PATTERN));
		$generate->option('service-class', 'c', [], 3)
			->prompt(true, 'The service class name')
			->description('The service class name.')
			->string()
			->pattern($class_name_reg, \sprintf('The service class name is invalid, required pattern: "%s"', \trim($class_name_reg, $class_name_reg[0])))
			->def('');
		$generate->option('override', 'o', [], 4)
			->description('To force override if a service with the same class name exists.')
			->bool()
			->def(false);

		$generate->option('service-dir', 'd', [], 5)
			->description('The service directory.')
			->path()
			->dir();

		$generate->handler($this->generate(...));
	}

	/**
	 * Generate service for a table in the database.
	 *
	 * @param KliArgs $args
	 */
	private function generate(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$table_name    = $args->get('table-name');
		$service_path  = $args->get('service-path');
		$service_class = $args->get('service-class');
		$service_dir   = $args->get('service-dir');
		$override      = $args->get('override');

		if (!$service_dir) {
			$service_dir = app()
				->getSourcesDir()
				->cd('Services', true)
				->getRoot();
		}

		$db = db();

		$db->assertHasTable($table_name);

		/** @var Table $table */
		$table = $db->getTable($table_name);

		if (empty($service_class)) {
			$service_class = Str::toClassName($table->getName() . '_service');
		}

		$p_ns              = Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE');
		$service_namespace = \sprintf('%s\Services', $p_ns);

		$generator = new ServiceGenerator($db);
		$generator->ignorePrivateTables(false);
		$generator->ignorePrivateColumns(false);
		$info      = $generator->generateServiceClass(
			$table,
			$service_namespace,
			$service_dir,
			$service_path,
			$service_class,
			'',
			(bool) $override
		);

		Settings::set('oz.routes.api', $info['provider'], true);

		$this->getCli()
			->success(\sprintf('service "%s" generated for "%s => %s".', $service_class, $service_path, $table_name));
	}
}
