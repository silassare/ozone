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

use JsonException;
use Kli\Exceptions\KliException;
use Kli\Exceptions\KliInputException;
use Kli\KliArgs;
use OLIUP\CG\PHPClass;
use OLIUP\CG\PHPNamespace;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Process;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Templates;
use OZONE\Core\Utils\Random;
use PHPUtils\Str;

/**
 * Class ProjectCmd.
 */
final class ProjectCmd extends Command
{
	private const NAMESPACE_PLACEHOLDER = '_Default_';

	/**
	 * {@inheritDoc}
	 *
	 * @throws KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your ozone project.');

		$project_prefix_reg = '#^[a-zA-Z][a-zA-Z0-9]$#';

		// action: project create
		$create = $this->action('create', 'Create new project.');
		$create->option('root-dir', 'r', [], 1)
			->prompt(true, 'The project root folder path')
			->description('The project root folder path.')
			->path()
			->dir()
			->writable()
			->def('.');
		$create->option('name', 'n', [], 2)
			->description('Your new project name.')
			->prompt(true, 'Project name')
			->required()
			->string(1, 60);
		$create->option('namespace')
			->description('The project namespace.')
			->prompt(true, 'Project namespace')
			->required()
			->string()
			->pattern(PHPNamespace::NAMESPACE_PATTERN)
			->def(self::NAMESPACE_PLACEHOLDER);
		$create->option('class-name', 'c', [], 3)
			->description('App class name.')
			->prompt(true, 'Project app class name')
			->required()
			->string(2, 30)
			->pattern(PHPClass::CLASS_NAME_PATTERN)
			->def('SampleApp');
		$create->option('prefix', 'p', [], 4)
			->description('Your new project prefix.')
			->prompt(true, 'Your new project prefix')
			->required()
			->string(2, 2)
			->pattern($project_prefix_reg)
			->def('SA');
		$create->handler($this->create(...));

		// action: project backup
		$backup = $this->action('backup', 'Backup your project.');
		$backup->option('dir', 'd', [], 1)
			->description('The backup directory path.')
			->prompt(true, 'The backup directory path')
			->path()
			->dir()
			->writable()
			->def('..');
		$backup->option('full', 'f', [], 2)
			->description('Enable or disable full backup.')
			->prompt(true, 'Full backup? yes/no')
			->bool()
			->def(true);
		$backup->handler($this->backup(...));

		// action: project serve
		$serve = $this->action('serve', 'Serve your project using php built in server.');
		$serve->option('host', 'h')
			->description('The host to use.')
			->prompt(true, 'The host to use')
			->string(1, 255)
			->def('localhost');
		$serve->option('port', 'p')
			->description('The port to use.')
			->prompt(true, 'The port to use')
			->number(0, 65535);
		$serve->option('doc-root', 'r')
			->description('The document root to use.')
			->path()
			->dir();
		$serve->option('scope', 's')
			->description('The scope to use.')
			->prompt(true, 'The scope to use')
			->string(1, 255)
			->def('api');
		$serve->handler($this->serve(...));
	}

	/**
	 * Serve project.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 */
	private function serve(
		KliArgs $args
	): void {
		Utils::assertProjectLoaded();

		$host     = $args->get('host');
		$port     = $args->get('port');
		$scope    = $args->get('scope');
		$doc_root = $args->get('doc-root');
		$cli      = $this->getCli();

		if (null === $port) {
			$port = Utils::getOpenPort([
				8080,
				8888,
				9000,
				2227,
			], $host);
		}

		if (empty($doc_root)) {
			$doc_root = app()->getScope($scope)->getPublicDir()->getRoot();
		}

		$cli->info("Serving project on {$host}:{$port} ...");
		$cli->info("Document root: {$doc_root}");
		$cli->info('Press Ctrl-C to quit.');
		$cli->writeLn();

		$router = OZ_OZONE_DIR . 'server.php';

		$cmd = [
			\PHP_BINARY,
			'-S',
			"{$host}:{$port}",
			'-t',
			$doc_root,
			$router,
		];

		$process = new Process($cmd);

		$process->setTty(true);

		$exit_code = $process->run(static function ($type, $data) use ($cli) {
			$cli->write($data);
		});

		exit($exit_code);
	}

	/**
	 * Creates project backup.
	 *
	 * @param KliArgs $args
	 *
	 * @throws KliException
	 * @throws KliInputException
	 */
	private function backup(KliArgs $args): void
	{
		$dir               = $args->get('dir');
		$full              = $args->get('full');
		$project_fm        = FS::fromRoot();
		$project_name      = Settings::get('oz.config', 'OZ_PROJECT_NAME');
		$project_name_slug = \strtolower(Str::stringToURLSlug($project_name));
		$backup_name       = Random::fileName('backup-' . $project_name_slug);
		$cli               = $this->getCli();

		if ($project_fm->isSelf($dir) || $project_fm->isParentOf($dir)) {
			throw new KliInputException('You should not backup project to project directory or subdirectory.');
		}
		$cli->info('Copying the required files and directories may take some time ...');

		$target_fm = FS::from($dir);
		$filter    = $project_fm->filter()
			->notIn('./vendor')
			->notName('~^(?:\.git|\.idea|otpl_done|blate_cache|node_modules|debug\.log)$~');

		$target_fm->cd($backup_name, true)
			->cp($project_fm->getRoot(), null, $filter);

		$full && DbCmd::ensureDBBackup($cli, $target_fm);

		$cli
			->success('A backup of your project was created.')
			->writeLn($target_fm->getRoot());
	}

	/**
	 * Creates new project.
	 *
	 * @param KliArgs $args
	 *
	 * @throws JsonException
	 * @throws KliException
	 */
	private function create(KliArgs $args): void
	{
		$name       = $args->get('name');
		$folder     = $args->get('root-dir');
		$namespace  = $args->get('namespace');
		$class_name = $args->get('class-name');
		$prefix     = \strtoupper($args->get('prefix'));
		$cli        = $this->getCli();
		$origin_url = 'http://localhost';

		if (Utils::isProjectFolder($folder)) {
			$cli->error(
				\sprintf(
					'Folder "%s" already contains an O\'Zone project.',
					$folder
				)
			);

			return;
		}

		if (self::NAMESPACE_PLACEHOLDER === $namespace) {
			$namespace = Str::removeSuffix(\strtoupper($class_name), 'APP');

			// when class_name is App
			if (empty($namespace)) {
				$namespace = \strtoupper($prefix);
			}
		}

		$app_class_file = \sprintf('%s.php', $class_name);

		$oz_config  = Templates::compile(
			'oz://~core~/gen/settings.info.otpl',
			Settings::genExportInfo('oz.config', [
				'OZ_OZONE_VERSION'          => OZ_OZONE_VERSION,
				'OZ_PROJECT_NAME'           => $name,
				'OZ_PROJECT_NAMESPACE'      => $namespace,
				'OZ_PROJECT_APP_CLASS_NAME' => $class_name,
				'OZ_PROJECT_PREFIX'         => $prefix,
			])
		);
		$oz_request = Templates::compile(
			'oz://~core~/gen/settings.info.otpl',
			Settings::genExportInfo('oz.request', [
				'OZ_DEFAULT_ORIGIN' => $origin_url,
			])
		);

		$oz_db = Templates::compile('oz://~core~/gen/project.db.configs.otpl', [
			'oz_version'         => OZ_OZONE_VERSION,
			'oz_version_name'    => OZ_OZONE_VERSION_NAME,
			'oz_time'            => \time(),
			'oz_db_table_prefix' => Random::alpha(Random::int(3, 6)),
		]);

		$dot_env_file         = Templates::compile('oz://~core~/gen/project.env.otpl', [
			'OZ_APP_SALT'   => \base64_encode(Keys::newSalt()),
			'OZ_APP_SECRET' => \base64_encode(Keys::newSecret()),
		]);
		$dot_env_example_file = Templates::compile('oz://~core~/gen/project.env.otpl', [
			'OZ_APP_SALT'   => \base64_encode(Keys::newSalt()),
			'OZ_APP_SECRET' => \base64_encode(Keys::newSecret()),
		]);

		$inject = [
			'oz_version'                   => OZ_OZONE_VERSION,
			'oz_version_name'              => OZ_OZONE_VERSION_NAME,
			'oz_time'                      => \time(),
			'oz_project_namespace'         => $namespace,
			'oz_project_namespace_escaped' => \str_replace('\\', '\\\\', $namespace),
			'oz_project_app_class_name'    => $class_name,
			'oz_install_path'              => \dirname(OZ_OZONE_DIR),
		];

		$app_class        = Templates::compile('oz://~core~/gen/app_class.otpl', $inject);
		$app_instance     = Templates::compile('oz://~core~/gen/app.otpl', $inject);
		$boot_content     = Templates::compile('oz://~core~/gen/boot.otpl', $inject);
		$project_composer = Templates::compile('oz://~core~/gen/composer.json.otpl', $inject);

		$tpl_folder = Templates::OZ_TEMPLATE_DIR;

		$fm   = FS::from($folder);
		$root = $fm->getRoot();

		$structures = [
			'app'          => [
				'type'     => 'dir',
				'children' => [
					'.htaccess'     => [
						'type'    => 'file',
						'content' => 'deny from all',
					],
					'settings'      => [
						'type'     => 'dir',
						'children' => [
							'oz.config.php'  => [
								'type'    => 'file',
								'content' => $oz_config,
							],
							'oz.request.php' => [
								'type'    => 'file',
								'content' => $oz_request,
							],
							'oz.db.php'      => [
								'type'    => 'file',
								'content' => $oz_db,
							],
						],
					],
					'templates'     => [
						'type'     => 'dir',
						'children' => [
							'.keep' => [
								'type' => 'file',
							],
						],
					],
					'files'         => [
						'type'     => 'dir',
						'children' => [
							'.keep' => [
								'type' => 'file',
							],
						],
					],
					$app_class_file => [
						'type'    => 'file',
						'content' => $app_class,
					],
					'app.php'       => [
						'type'    => 'file',
						'content' => $app_instance,
					],
					'boot.php'      => [
						'type'    => 'file',
						'content' => $boot_content,
					],
				],
			],
			'.env'         => [
				'type'    => 'file',
				'content' => $dot_env_file,
			],
			'.env.example' => [
				'type'    => 'file',
				'content' => $dot_env_example_file,
			],
			'.gitignore'   => [
				'type' => 'file',
				'copy' => $tpl_folder . 'gen/project.gitignore',
			],
		];

		$fm->apply($structures);

		$composer_config_path = $fm->resolve('composer.json');

		if ($fm->filter()
			->exists()
			->check($composer_config_path)) {
			$content         = \file_get_contents($composer_config_path);
			$composer_config = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

			$oz_composer_file = \dirname(OZ_OZONE_DIR) . DS . 'composer.json';
			$oz_composer      = \json_decode(\file_get_contents($oz_composer_file), true, 512, \JSON_THROW_ON_ERROR);
			$oz_package_name  = $oz_composer['name'];

			$composer_config['require'][$oz_package_name] = '^' . OZ_OZONE_VERSION;

			$fm->wf(
				$composer_config_path,
				\json_encode(
					$composer_config,
					\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
				)
			);
		} else {
			$fm->wf('composer.json', $project_composer);
		}

		$cli->success(\sprintf('project "%s" created in "%s".', $name, $root));

		ScopesCmd::addScope($cli, $fm, [
			'api'          => true,
			'name'         => 'api',
			'origin'       => $origin_url,
			'project_name' => $name,
			'namespace'    => $namespace,
			'app_class'    => $class_name,
		]);

		$cli->info('You need to run:')
			->writeLn("\tcomposer update");
	}
}
