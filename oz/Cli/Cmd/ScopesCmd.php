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

use Kli\Kli;
use Kli\KliArgs;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\Templates;

/**
 * Class ScopesCmd.
 */
final class ScopesCmd extends Command
{
	/**
	 * Adds new scope.
	 *
	 * @param Kli                                                                                                  $cli
	 * @param FilesManager                                                                                         $fm
	 * @param array{api:bool, name:string, origin:string, project_name:string, namespace:string, app_class:string} $options
	 */
	public static function addScope(
		Kli $cli,
		FilesManager $fm,
		array $options
	): void {
		$scope_name      = $options['name'];
		$origin          = $options['origin'];
		$use_api_context = $options['api'];
		$project_name    = $options['project_name'];
		$namespace       = $options['namespace'];
		$app_class       = $options['app_class'];

		$private_abs_folder = $fm->resolve('scopes' . DS . $scope_name);
		$public_abs_folder  = $fm->resolve('public' . DS . $scope_name);

		if (\file_exists($private_abs_folder)) {
			$fm->filter()
				->isDir()
				->isEmpty()
				->assert($scope_name);
		}

		if (\file_exists($public_abs_folder)) {
			$fm->filter()
				->isDir()
				->isEmpty()
				->assert($scope_name);
		}

		$settings_inject = Settings::genExportInfo('oz.request', [
			'OZ_DEFAULT_ORIGIN' => $origin,
		]);

		$inject = [
			'oz_version_name'           => OZ_OZONE_VERSION_NAME,
			'oz_time'                   => \time(),
			'oz_project_namespace'      => $namespace,
			'oz_project_app_class_name' => $app_class,
			'oz_scope_name'             => $scope_name,
			'oz_use_api_context'        => $use_api_context,
		];

		$oz_request  = Templates::compile('oz://gen/settings.info.otpl', $settings_inject);
		$scope_index = Templates::compile('oz://gen/scope.index.otpl', $inject);

		$tpl_folder = Templates::OZ_TEMPLATE_DIR;

		$private_structure = [
			'settings'  => [
				'type'     => 'dir',
				'children' => [
					'oz.request.php' => [
						'type'    => 'file',
						'content' => $oz_request,
					],
				],
			],
			'templates' => [
				'type'     => 'dir',
				'children' => [
					'.keep' => [
						'type' => 'file',
					],
				],
			],
			'.htaccess' => [
				'type'    => 'file',
				'content' => 'deny from all',
			],
		];
		$public_structures = [
			'index.php'   => [
				'type'    => 'file',
				'content' => $scope_index,
			],
			'robots.txt'  => [
				'type' => 'file',
				'copy' => $tpl_folder . 'gen/robots.txt',
			],
			'favicon.ico' => [
				'type' => 'file',
				'copy' => $tpl_folder . 'gen/favicon.ico',
			],
			'.htaccess'   => [
				'type' => 'file',
				'copy' => $tpl_folder . ($use_api_context ? 'gen/api.htaccess' : 'gen/web.htaccess'),
			],
		];

		$fm->cd($private_abs_folder, true)
			->apply($private_structure);

		$fm->cd($public_abs_folder, true)
			->apply($public_structures);

		$cli
			->success(\sprintf('Scope "%s" added to project "%s".', $scope_name, $project_name))
			->info(\sprintf('- Private folder: %s', $private_abs_folder))
			->info(\sprintf('- Public folder: %s', $public_abs_folder));
	}

	/**
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your ozone project scopes.');
		// https://stackoverflow.com/questions/37232382/what-is-protocol-and-host-combined-called
		$origin_reg = '~^https?://(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])(?:\.(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]))*(?::\d+)?$~';

		// action: add web client
		$add = $this->action('add', 'Add new scope to your project.');
		$add->option('name', 'n', [], 1)
			->required()
			->prompt(true, 'The scope name')
			->description('The scope name.')
			->string()
			->pattern('#^[^\\\/?%*:|"<>]+$#', '"%s" is not a valid scope name.');
		$add->option('origin', 'o', [], 2)
			->required()
			->prompt(true, 'The scope url origin')
			->description('The scope url origin.')
			->string()
			->pattern($origin_reg, '"%s" is not a valid origin.')
			->def('http://localhost');
		$add->option('api', 'a')
			->required()
			->prompt(true, 'Use api context?')
			->description('Define if the scope should run in api context or not.')
			->bool()
			->def(false);
		$add->handler($this->add(...));
	}

	/**
	 * Adds new scope.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	private function add(KliArgs $args): void
	{
		Utils::assertProjectLoaded();

		$origin          = $args->get('origin');
		$scope_name      = $args->get('name');
		$use_api_context = $args->get('api');

		$project_name = Settings::get('oz.config', 'OZ_PROJECT_NAME');
		$namespace    = Settings::get('oz.config', 'OZ_PROJECT_NAMESPACE');
		$class_name   = Settings::get('oz.config', 'OZ_PROJECT_APP_CLASS_NAME');

		$fm = app()->getProjectDir();

		self::addScope(
			$this->getCli(),
			$fm,
			[
				'api'          => $use_api_context,
				'name'         => $scope_name,
				'origin'       => $origin,
				'project_name' => $project_name,
				'namespace'    => $namespace,
				'app_class'    => $class_name,
			]
		);
	}
}
