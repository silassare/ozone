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

use JsonException;
use Kli\Exceptions\KliInputException;
use Kli\KliArgs;
use OLIUP\CG\PHPClass;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Process;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\TemplatesUtils;
use PHPUtils\Str;

/**
 * Class Project.
 */
final class Project extends Command
{
	/**
	 * Serve project.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	public function serve(KliArgs $args): void
	{
		$host     = $args->get('host');
		$port     = $args->get('port');
		$doc_root = $args->get('doc-root');
		$cli      = $this->getCli();

		$cli->info("Serving project on {$host}:{$port} ...");
		$cli->info("Document root: {$doc_root}");
		$cli->info('Press Ctrl-C to quit.');

		$router = OZ_OZONE_DIR . 'server.php';

		$php = \PHP_BINARY;
		$cmd = "{$php} -S {$host}:{$port} -t {$doc_root} {$router}";

		$process = new Process($cmd);

		exit($process->open()
			->close());
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Kli\Exceptions\KliException
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
			->def('.')
			->dir()
			->writable();
		$create->option('name', 'n', [], 2)
			->description('Your new project name.')
			->prompt(true, 'Your new project name')
			->required()
			->string(1, 60);
		$create->option('class-name', 'c', [], 3)
			->description('Your new project main class name.')
			->prompt(true, 'Your new project main class name to use')
			->required()
			->string(2, 30)
			->def('SampleApp')
			->pattern(PHPClass::CLASS_NAME_PATTERN);
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
			->def('..')
			->dir()
			->writable();
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
			->def('127.0.0.1');
		$serve->option('port', 'p')
			->description('The port to use.')
			->prompt(true, 'The port to use')
			->number(0, 65535)
			->def(8080);
		$serve->option('doc-root', 'r')
			->description('The document root to use.')
			->prompt(true, 'The document root to use')
			->path()
			->def((new FilesManager())->resolve('./api'))
			->dir();
		$serve->handler($this->serve(...));
	}

	/**
	 * Creates project backup.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws \Kli\Exceptions\KliException
	 * @throws \Kli\Exceptions\KliInputException
	 */
	private function backup(KliArgs $args): void
	{
		$dir               = $args->get('dir');
		$full              = $args->get('full');
		$project_fm        = new FilesManager();
		$project_name      = Configs::get('oz.config', 'OZ_PROJECT_NAME');
		$project_name_slug = \strtolower(Str::stringToURLSlug($project_name));
		$backup_name       = Hasher::genFileName('backup-' . $project_name_slug);
		$cli               = $this->getCli();

		if ($project_fm->isSelf($dir) || $project_fm->isParentOf($dir)) {
			throw new KliInputException('You should not backup project to project directory or subdirectory.');
		}
		$cli->info('Copying the required files and directories may take some time ...');

		$target_fm = new FilesManager($dir);
		$filter    = $project_fm->filter()
			->notIn('./vendor')
			->notName('~^(?:\.git|\.idea|otpl_done|blate_cache|node_modules|debug\.log)$~');

		$target_fm->cd($backup_name, true)
			->cp($project_fm->getRoot(), null, $filter);

		$full && Db::ensureDBBackup($cli, $target_fm);

		$cli
			->success('A backup of your project was created.')
			->writeLn($target_fm->getRoot());
	}

	/**
	 * Creates new project.
	 *
	 * @param \Kli\KliArgs $args
	 *
	 * @throws JsonException
	 * @throws \Kli\Exceptions\KliException
	 */
	private function create(KliArgs $args): void
	{
		$name       = $args->get('name');
		$folder     = $args->get('root-dir');
		$class_name = $args->get('class-name');
		$prefix     = \strtoupper($args->get('prefix'));
		$config     = Utils::tryGetProjectConfig($folder);
		$cli        = $this->getCli();

		if (null !== $config) {
			$cli->error(\sprintf(
				'project "%s" created with "OZone %s" exists in "%s".',
				$config['OZ_PROJECT_NAME'],
				$config['OZ_OZONE_VERSION'],
				$folder
			));

			return;
		}

		$namespace = Str::removeSuffix(\strtoupper($class_name), 'APP');

		// when class_name is App
		if (empty($namespace)) {
			$namespace = \strtoupper($prefix);
		}

		$namespace      = \sprintf('%s\\App', $namespace);
		$app_class_file = \sprintf('%s.php', $class_name);

		$inject = Configs::genExportInfo('oz.config', [
			'OZ_OZONE_VERSION'                => OZ_OZONE_VERSION,
			'OZ_PROJECT_NAME'                 => $name,
			'OZ_PROJECT_NAMESPACE'            => $namespace,
			'OZ_PROJECT_CLASS'                => $class_name,
			'OZ_PROJECT_PREFIX'               => $prefix,
			'OZ_DEBUG_MODE'                   => 0,
			'OZ_API_MAIN_URL'                 => 'http://localhost',
			'OZ_API_SESSION_ID_NAME'          => 'OZONE_SID',
			'OZ_API_KEY_HEADER_NAME'          => 'x-ozone-api-key',
			'::comment::1'                    => 'For server that does not support HEAD, PATCH, PUT, DELETE...',
			'OZ_API_ALLOW_REAL_METHOD_HEADER' => true,
			'OZ_API_REAL_METHOD_HEADER_NAME'  => 'x-ozone-real-method',
		]);

		$oz_config = TemplatesUtils::compile('oz://gen/settings.info.otpl', $inject);

		$inject = Configs::genExportInfo('oz.db', [
			'OZ_DB_TABLE_PREFIX' => Hasher::randomString(Hasher::randomInt(3, 6), Hasher::CHARS_ALPHA),
			'::comment::1'       => 'we use and support MySQL RDBMS by default',
			'OZ_DB_RDBMS'        => 'mysql',
			'OZ_DB_HOST'         => '__db_host__',
			'OZ_DB_NAME'         => '__db_name__',
			'OZ_DB_USER'         => '__db_user__',
			'OZ_DB_PASS'         => '__db_pass__',
			'::comment::2'       => 'changing charset may lead to data corruption and many more nightmares',
			'OZ_DB_CHARSET'      => 'utf8mb4',
			'OZ_DB_COLLATE'      => 'utf8mb4_unicode_ci',
		]);

		$oz_db = TemplatesUtils::compile('oz://gen/settings.info.otpl', $inject);

		$inject = Configs::genExportInfo('oz.keygen.salt', [
			'::comment::0'    => 'salt for any usage purpose',
			'OZ_DEFAULT_SALT' => Hasher::randomString(Hasher::randomInt(12, 64)),
			'::comment::1'    => 'salt used to generate files keys',
			'OZ_FILE_SALT'    => Hasher::randomString(Hasher::randomInt(12, 64)),
			'::comment::2'    => 'salt used to generate session id/token',
			'OZ_SESSION_SALT' => Hasher::randomString(Hasher::randomInt(12, 64)),
			'::comment::3'    => 'salt used to generate authorization tokens',
			'OZ_AUTH_SALT'    => Hasher::randomString(Hasher::randomInt(12, 64)),
		]);

		$oz_key_gen_salt = TemplatesUtils::compile('oz://gen/settings.warn.otpl', $inject);

		$inject = [
			'oz_version'           => OZ_OZONE_VERSION,
			'oz_version_name'      => OZ_OZONE_VERSION_NAME,
			'oz_time'              => \time(),
			'oz_project_namespace' => $namespace,
			'oz_project_class'     => $class_name,
			'oz_install_path'      => \dirname(OZ_OZONE_DIR),
		];

		$app_class        = TemplatesUtils::compile('oz://gen/app.otpl', $inject);
		$api_index        = TemplatesUtils::compile('oz://gen/index.api.otpl', $inject);
		$project_composer = TemplatesUtils::compile('oz://gen/composer.json.otpl', $inject);

		$tpl_folder = OZ_OZONE_DIR . 'oz_templates' . DS;

		$fm   = new FilesManager($folder);
		$root = $fm->getRoot();
		$fm->cd('api', true)
			->cd('app', true)
			->cp($tpl_folder . 'gen/htaccess.deny.txt', '.htaccess')
			->cd('oz_settings', true)
			->wf('oz.config.php', $oz_config)
			->wf('oz.db.php', $oz_db)
			->wf('oz.keygen.salt.php', $oz_key_gen_salt)
			->cd('..')
			->cd('oz_templates', true)
			->wf('.keep')
			->cd('../oz_files', true)
			->wf('.keep')
			->cd('../oz_cache', true)
			->wf('.keep')
			->cd('..')
			->wf($app_class_file, $app_class)
			->cd('..')
			->wf('index.php', $api_index)
			->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
			->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
			->cp($tpl_folder . 'gen/htaccess.api.txt', '.htaccess');

		$fm->cd('..');

		$composer_config_path = $fm->resolve('composer.json');

		if ($fm->filter()
			->exists()
			->check($composer_config_path)) {
			$content         = \file_get_contents($composer_config_path);
			$composer_config = \json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

			$composer_config['require'][OZ_OZONE_PACKAGE_NAME] = '^' . OZ_OZONE_VERSION;

			$fm->wf($composer_config_path, \json_encode(
				$composer_config,
				\JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES
			));
		} else {
			$fm->wf('composer.json', $project_composer);
		}

		$cli->success(\sprintf('project "%s" created in "%s".', $name, $root))
			->info('You need to run:')
			->writeLn("\tcomposer update");
	}
}
