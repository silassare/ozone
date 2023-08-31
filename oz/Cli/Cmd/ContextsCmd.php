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
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\Templates;

/**
 * Class ContextsCmd.
 */
final class ContextsCmd extends Command
{
	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your ozone project contexts.');
		// https://stackoverflow.com/questions/37232382/what-is-protocol-and-host-combined-called
		$origin_reg = '~^https?://(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])(?:\.(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]))*(?::\d+)?$~';

		// action: add web client
		$add = $this->action('add', 'Add new context to your project.');
		$add->option('origin', 'o', [], 1)
			->required()
			->prompt(true, 'The context url origin')
			->description('The context url origin.')
			->string()
			->pattern($origin_reg, '"%s" is not a valid origin.')
			->def('http://localhost');
		$add->option('folder', 'f', [], 2)
			->required()
			->prompt(true, 'The context folder name')
			->description('The context folder name.')
			->string()
			->pattern('#^[^\\\/?%*:|"<>]+$#', '"%s" is not a valid folder name.');
		$add->option('api', 'a')
			->required()
			->prompt(true, 'Is api context?')
			->description('Define if the context is an api context or not.')
			->bool()
			->def(false);
		$add->handler($this->add(...));
	}

	/**
	 * Adds new context.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$origin         = $args->get('origin');
		$folder_name    = $args->get('folder');
		$is_api_context = $args->get('api');
		$project_folder = \getcwd();

		$project_name = Settings::get('oz.config', 'OZ_PROJECT_NAME');
		$namespace    = Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE');
		$class_name   = Settings::get('oz.config', 'OZ_PROJECT_APP_CLASS_NAME');

		$fm                 = new FilesManager($project_folder);
		$private_abs_folder = $fm->resolve($folder_name);
		$public_abs_folder  = $fm->resolve('public' . DS . $folder_name);

		if (\file_exists($private_abs_folder)) {
			$fm->filter()
				->isDir()
				->isEmpty()
				->assert($folder_name);
		}

		if (\file_exists($public_abs_folder)) {
			$fm->filter()
				->isDir()
				->isEmpty()
				->assert($folder_name);
		}

		$settings_inject = Settings::genExportInfo('oz.request', [
			'OZ_DEFAULT_ORIGIN' => $origin,
		]);

		$inject = [
			'oz_version_name'           => OZ_OZONE_VERSION_NAME,
			'oz_time'                   => \time(),
			'oz_project_namespace'      => $namespace,
			'oz_project_app_class_name' => $class_name,
			'oz_folder_name'            => $folder_name,
			'oz_is_api_context'         => $is_api_context,
		];

		$oz_request    = Templates::compile('oz://gen/settings.info.otpl', $settings_inject);
		$context_index = Templates::compile('oz://gen/context.index.otpl', $inject);

		$tpl_folder = OZ_OZONE_DIR . 'oz_templates' . DS;

		$private_structure = [
			'oz_settings'  => [
				'type'     => 'dir',
				'children' => [
					'oz.request.php' => [
						'type'    => 'file',
						'content' => $oz_request,
					],
				],
			],
			'oz_templates' => [
				'type' => 'dir',
			],
			'.htaccess'    => [
				'type'    => 'file',
				'content' => 'deny from all',
			],
		];
		$public_structures = [
			'index.php'   => [
				'type'    => 'file',
				'content' => $context_index,
			],
			'robots.txt'  => [
				'type'    => 'file',
				'content' => $tpl_folder . 'gen/robots.txt',
			],
			'favicon.ico' => [
				'type' => 'file',
				'cp'   => $tpl_folder . 'gen/favicon.ico',
			],
			'.htaccess'   => [
				'type' => 'file',
				'cp'   => $tpl_folder . ($is_api_context ? 'gen/api.htaccess' : 'gen/web.htaccess'),
			],
		];
		$public_assets     = [
			'assets' => [
				'type'     => 'dir',
				'children' => [
					'js'     => [
						'type' => 'dir',
					],
					'styles' => [
						'type' => 'dir',
					],
					'images' => [
						'type' => 'dir',
					],
				],
			],
		];

		$fm->cd($private_abs_folder, true)
			->apply($private_structure);

		$fm->cd($public_abs_folder, true)
			->apply($public_structures)
			->apply($public_assets);

		$this->getCli()
			->success(\sprintf('Context added to project "%s".', $project_name))
			->info(\sprintf('- Private folder: %s', $private_abs_folder))
			->info(\sprintf('- Public folder: %s', $public_abs_folder));
	}
}
