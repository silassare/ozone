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

use Kli\KliArgs;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\OZClient;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\TemplatesUtils;

/**
 * Class Client.
 */
final class Client extends Command
{
	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your ozone web client.');
		$host_reg = '~^https?\:\/\/(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])'
					. '(?:\.(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]))*$~';

		// action: add web client
		$add = $this->action('add', 'Add new web client to project.');
		$add->option('host', 'h', [], 1)
			->required()
			->prompt(true, 'The web client hostname')
			->description('The web client hostname.')
			->string()
			->pattern($host_reg, '"%s" is not a valid hostname.');
		$add->option('folder', 'f', [], 2)
			->required()
			->prompt(true, 'The web client folder name')
			->description('The web client folder name.')
			->string()
			->pattern('#^[^\\/?%*:|"<>]+$#', '"%s" is not a valid folder name.');
		$add->option('about', 'a', [], 3)
			->required()
			->prompt(true, 'Short text about the web client')
			->description('Short text about the web client.')
			->string();
		$add->handler($this->add(...));
	}

	/**
	 * Adds new web client.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Gobl\CRUD\Exceptions\CRUDException
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Gobl\ORM\Exceptions\ORMQueryException
	 * @throws \Kli\Exceptions\KliException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$host           = $args->get('host');
		$folder_name    = $args->get('folder');
		$about          = $args->get('about');
		$project_folder = \getcwd();
		$config         = Utils::tryGetProjectConfig($project_folder);

		$project_name = $config['OZ_PROJECT_NAME'];
		$namespace    = $config['OZ_PROJECT_NAMESPACE'];
		$class_name   = $config['OZ_PROJECT_CLASS'];
		$api_key      = Hasher::genClientID($host);

		if (!empty($folder_name)) {
			$fm         = new FilesManager($project_folder);
			$abs_folder = $fm->resolve($folder_name);

			if (\file_exists($abs_folder)) {
				$fm->filter()
					->isDir()
					->isEmpty()
					->assert($folder_name);
			}

			$inject = Configs::genExportInfo('oz.config', [
				'OZ_API_MAIN_URL' => $host,
			]);

			$oz_config = TemplatesUtils::compile('oz://gen/settings.info.otpl', $inject);

			$inject = [
				'oz_version_name'      => OZ_OZONE_VERSION_NAME,
				'oz_time'              => \time(),
				'oz_project_namespace' => $namespace,
				'oz_project_class'     => $class_name,
				'oz_default_api_key'   => $api_key,
			];

			$www_index = TemplatesUtils::compile('oz://gen/index.www.otpl', $inject);

			$tpl_folder = OZ_OZONE_DIR . 'oz_templates' . DS;

			$fm->cd($abs_folder, true)
				->mkdir('assets')
				->cd('assets')
				->mkdir('js')
				->mkdir('styles')
				->mkdir('images')
				->mkdir('vendor')
				->cd('..')
				->cd('oz_private', true)
				->cd('oz_settings', true)
				->wf('oz.config.php', $oz_config)
				->cd('..')
				->mkdir('oz_templates')
				->cp($tpl_folder . 'gen/htaccess.deny.txt', '.htaccess')
				->cd('..')
				->wf('index.php', $www_index)
				->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
				->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
				->cp($tpl_folder . 'gen/htaccess.www.txt', '.htaccess');
		}

		$wc = new OZClient();
		$wc->setApiKey($api_key)
			->setAbout($about)
			->setUrl($host)
			->save();

		$this->getCli()
			->success(\sprintf('web client added to project "%s".', $project_name))
			->info(\sprintf('Client Host  : %s', $host))
			->info(\sprintf('Client ApiKey: %s', $api_key));
	}
}
