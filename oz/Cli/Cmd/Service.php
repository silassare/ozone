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

namespace OZONE\OZ\Cli\Cmd;

use Gobl\DBAL\Table;
use Kli\KliArgs;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Utils\ServiceGenerator;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\FS\FilesManager;

/**
 * Class Service.
 */
final class Service extends Command
{
	/**
	 * {@inheritDoc}
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your project service.');

		// action: generate service for a table
		$generate = $this->action('generate', 'Generate service for a table in the database.');
		$generate->option('service-name', 'n', [], 1)
			->required()
			->prompt(true, 'The service name')
			->description('The service name.')
			->string()
			->pattern('#^[a-zA-Z0-9_-]+$#', 'The service name is invalid.');
		$generate->option('table-name', 't', [], 2)
			->required()
			->prompt(true, 'The table name')
			->description('The table name.')
			->string()
			->pattern(Table::NAME_REG, 'The table name is invalid.');
		$generate->option('service-class', 'c', [], 3)
			->prompt(true, 'The service class name')
			->description('The service class name.')
			->string()
			->def('')
			->pattern('#^[a-zA-Z_][a-zA-Z0-9_]*$#', 'The service class name is invalid.');
		$generate->option('override', 'o', [], 4)
			->description('To force override if a service with the same class name exists.')
			->bool()
			->def(false);
		$generate->handler($this->generate(...));
	}

	/**
	 * Generate service for a table in the database.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function generate(KliArgs $args): void
	{
		Utils::assertProjectFolder();

		$table_name    = $args->get('table-name');
		$service_name  = $args->get('service-name');
		$service_class = $args->get('service-class');
		$override      = $args->get('override');

		$db = DbManager::getDb();

		$db->assertHasTable($table_name);

		/** @var Table $table */
		$table       = $db->getTable($table_name);
		$fm          = new FilesManager(OZ_APP_DIR);
		$service_dir = $fm->cd('Services', true)
			->getRoot();

		$config            = Configs::load('oz.config');
		$service_namespace = $config['OZ_PROJECT_NAMESPACE'] . '\\Services';

		$generator = new ServiceGenerator($db, false, false);
		$info      = $generator->generateServiceClass(
			$table,
			$service_namespace,
			$service_dir,
			$service_name,
			$service_class,
			'',
			(bool) $override
		);

		Configs::set('oz.routes.api', $info['provider'], true);

		$this->getCli()
			->success(\sprintf('service "%s" generated.', $service_name));
	}
}
