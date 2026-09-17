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
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Templates;
use OZONE\Core\Scopes\StateLayout;

/**
 * Class ScopesCmd.
 */
final class ScopesCmd extends Command
{
	/**
	 * Adds new scope.
	 *
	 * @param Kli          $cli
	 * @param FilesManager $fm
	 * @param array        $options keys: api (bool), name, origin, project_name, namespace, app_class
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

		// The project root, captured before anything walks $fm: cd() moves a FilesManager in place.
		$project_root = $fm->getRoot();

		$private_abs_folder = $fm->resolve('scopes' . DS . $scope_name);
		$public_abs_folder  = $fm->resolve('public' . DS . $scope_name);

		// An existing folder is only acceptable when it is an empty directory: the scope is otherwise
		// already there, and scaffolding over it would overwrite its settings and entry point.
		// The resolved paths, not the bare scope name: asserting `$scope_name` used to look for
		// `{project}/{scope}`, a path that never exists, so the guard reported a missing directory
		// instead of an existing scope -- and never actually checked that it was empty.
		foreach ([$private_abs_folder, $public_abs_folder] as $folder) {
			if (!\file_exists($folder)) {
				continue;
			}

			if (!\is_dir($folder) || !self::isEmptyDir($folder)) {
				$cli->error(\sprintf(
					'The scope "%s" already exists: "%s" is not an empty directory.'
						. ' Remove it first, or choose another name.',
					$scope_name,
					$folder
				));

				return;
			}
		}

		$settings_inject = Settings::genExportInfo('oz.request', [
			'OZ_DEFAULT_ORIGIN' => $origin,
		]);

		$inject = [
			'oz_project_namespace'      => $namespace,
			'oz_project_app_class_name' => $app_class,
			'oz_scope_name'             => $scope_name,
			'oz_use_api_context'        => $use_api_context,
		];

		$oz_request  = Templates::compile('oz://~core~/gen/settings.info.blate', $settings_inject);
		$scope_index = Templates::compile('oz://~core~/gen/scope.index.blate', $inject);

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

		// The scope's state directories, and the symlink its web root follows to the public files.
		$project    = FS::from($project_root);
		StateLayout::ensureAt($project, $scope_name);
		$link_state = StateLayout::linkAt($project, $scope_name, $public_abs_folder);

		$cli
			->success(\sprintf('Scope "%s" added to project "%s".', $scope_name, $project_name))
			->info(\sprintf('- Private folder: %s', $private_abs_folder))
			->info(\sprintf('- Public folder: %s', $public_abs_folder));

		if ('blocked' === $link_state) {
			$cli->error(\sprintf(
				'- Could not link %s%sstatic: something already exists there.',
				$public_abs_folder,
				DS
			), true, null);
		} else {
			$cli->info(\sprintf(
				'- Public files: %s (linked from %s%sstatic)',
				StateLayout::dirAt($project, StateLayout::PUBLIC_FILES, $scope_name)->getRoot(),
				$public_abs_folder,
				DS
			));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage your ozone project scopes.');
		// https://stackoverflow.com/questions/37232382/what-is-protocol-and-host-combined-called
		$host_label = '(?:[a-zA-Z0-9]|[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9])';
		$origin_reg = '~^https?://' . $host_label . '(?:\.' . $host_label . ')*(?::\d+)?$~';

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

		$this->getCli()->withJsonSupport($add);

		$add->handler($this->add(...));
	}

	/**
	 * Whether a directory holds nothing.
	 *
	 * @param string $dir
	 *
	 * @return bool
	 */
	private static function isEmptyDir(string $dir): bool
	{
		foreach (\scandir($dir) ?: [] as $entry) {
			if ('.' !== $entry && '..' !== $entry) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Adds new scope.
	 *
	 * @param KliArgs $args
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

		if ($args->get('json')) {
			// a fresh files manager: addScope() moved $fm
			$project = app()->getProjectDir();

			$this->getCli()->writeJson([
				'scope'   => $scope_name,
				'origin'  => $origin,
				'api'     => (bool) $use_api_context,
				'private' => $project->resolve('scopes' . DS . $scope_name),
				'public'  => $project->resolve('public' . DS . $scope_name),
			]);
		}
	}
}
