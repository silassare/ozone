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

use Exception;
use Kli\Exceptions\KliInputException;
use Kli\KliAction;
use Kli\KliOption;
use Kli\Types\KliTypeBool;
use Kli\Types\KliTypePath;
use Kli\Types\KliTypeString;
use OZONE\OZ\Cli\Command;
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
	 * {@inheritDoc}
	 *
	 * @throws Exception
	 */
	public function execute(KliAction $action, array $options, array $anonymous_options): void
	{
		switch ($action->getName()) {
			case 'create':
				$this->create($options);

				break;

			case 'backup':
				$this->backup($options);

				break;

			case 'build':
				$this->build($options);

				break;
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe(): void
	{
		$this->description('Manage your ozone project.');

		// action: project create
		$create = new KliAction('create');
		$create->description('Create new project.');

		// action: project backup
		$backup = new KliAction('backup');
		$backup->description('Backup your project.');

		// option: -d alias --dir
		$bd = new KliOption('d');
		$bd->alias('dir')
			->offsets(1)
			->type((new KliTypePath())->dir()
			->writable())
			->def('..')
			->prompt(true, 'The backup directory path')
			->description('The backup directory path.');

		// option: -f alias --full
		$full = new KliOption('f');
		$full->alias('full')
			->type(new KliTypeBool(false))
			->def(true)
			->prompt(true, 'Full backup? yes/no')
			->description('Enable or disable full backup.');

		// option: -r alias --root-dir
		$cr = new KliOption('r');
		$cr->alias('root-dir')
			->offsets(1)
			->type((new KliTypePath())->dir()
			->writable())
			->def('.')
			->prompt(true, 'The project root folder path')
			->description('The project root folder path.');

		// option: -n alias --name
		$n = new KliOption('n');
		$n->alias('name')
			->offsets(2)
			->required()
			->type(new KliTypeString(1, 60))
			->prompt(true, 'Your new project name')
			->description('Your new project name.');

		// option: -c alias --class-name
		$c = new KliOption('c');
		$c->alias('class-name')
			->offsets(3)
			->required()
			// according to "http://php.net/manual/en/language.oop5.basic.php" visited on 1st Sept. 2017
			// php class name in regexp should be : ^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$
			->type((new KliTypeString(2, 30))->pattern('#^[a-zA-Z][a-zA-Z0-9_]+$#'))
			->def('SampleApp')
			->prompt(true, 'Your new project main class name to use')
			->description('Your new project main class name.');

		// option: -p alias --prefix
		$p = new KliOption('p');
		$p->alias('prefix')
			->offsets(4)
			->required()
			->type((new KliTypeString(2, 2))->pattern('#^[a-zA-Z][a-zA-Z0-9]$#', 'invalid project prefix.'))
			->def('SA')
			->prompt(true, 'Your new project prefix')
			->description('Your new project prefix.');

		$create->addOption($cr, $n, $c, $p);
		$backup->addOption($bd, $full);

		$this->addAction($create, $backup);
	}

	/**
	 * Creates project backup.
	 *
	 * @param array $options
	 *
	 * @throws \Kli\Exceptions\KliInputException
	 */
	private function backup(array $options): void
	{
		$dir               = $options['d'];
		$full              = $options['f'];
		$project_fm        = new FilesManager();
		$project_name      = Configs::get('oz.config', 'OZ_PROJECT_NAME');
		$project_name_slug = \strtolower(Str::stringToURLSlug($project_name));
		$backup_name       = Hasher::genFileName('backup-' . $project_name_slug);

		if ($project_fm->isSelf($dir) || $project_fm->isParentOf($dir)) {
			throw new KliInputException('You should not backup project to project directory or subdirectory.');
		}
		$this->getCli()
			->info('Copying the required files and directories may take some time ...');

		$target_fm = new FilesManager($dir);
		$filter    = $project_fm->filter()
			->notIn('./vendor')
			->notName('~^(?:\.git|\.idea|otpl_done|blate_cache|node_modules|debug\.log)$~');

		$target_fm->cd($backup_name, true)
			->cp($project_fm->getRoot(), null, $filter);

		if ($full) {
			$this->getCli()
				->info('Generating database backup for your project ...');

			$db_backup_dir = \escapeshellarg($target_fm->getRoot());
			$this->getCli()
				->executeString("db backup -d={$db_backup_dir}");
		}

		$this->getCli()
			->success('A backup of your project was created.')
			->writeLn($target_fm->getRoot());
	}

	/**
	 * Creates project build for production.
	 *
	 * @param array $options
	 *
	 * @throws \Kli\Exceptions\KliInputException
	 */
	private function build(array $options): void
	{
		$dir               = $options['d'];
		$full              = $options['f'];
		$project_fm        = new FilesManager();
		$project_name      = Configs::get('oz.config', 'OZ_PROJECT_NAME');
		$project_name_slug = \strtolower(Str::stringToURLSlug($project_name));
		$backup_name       = Hasher::genFileName('build-' . $project_name_slug);

		if ($project_fm->isSelf($dir) || $project_fm->isParentOf($dir)) {
			throw new KliInputException('You should not build project to project directory or subdirectory.');
		}
		$this->getCli()
			->info('Copying the required files and directories may take some time ...');

		$target_fm = new FilesManager($dir);
		$filter    = $project_fm->filter()
			->notIn('./vendor')
			->notName('~^(?:\.git|\.idea|otpl_done|blate_cache|node_modules|debug\.log)$~');

		$target_fm->cd($backup_name, true)
			->cp($project_fm->getRoot(), null, $filter);

		if ($full) {
			$this->getCli()
				->info('Generating database script for your project ...');

			$db_backup_dir = \escapeshellarg($target_fm->getRoot());
			$this->getCli()
				->executeString("db build -d={$db_backup_dir}");
		}

		$this->getCli()
			->success('A production build of your project was created.')
			->writeLn($target_fm->getRoot());
	}

	/**
	 * Creates new project.
	 *
	 * @param array $options
	 */
	private function create(array $options): void
	{
		$name       = $options['n'];
		$folder     = $options['r'];
		$class_name = $options['c'];
		$prefix     = \strtoupper($options['p']);
		$config     = Utils::loadProjectConfig($folder);

		if (null !== $config) {
			$this->getCli()
				->error(\sprintf(
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
			->wf('.keep', '')
			->cd('../oz_users_files', true)
			->wf('.keep', '')
			->cd('../oz_cache', true)
			->wf('.keep', '')
			->cd('..')
			->wf($app_class_file, $app_class)
			->cd('..')
			->wf('index.php', $api_index)
			->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
			->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
			->cp($tpl_folder . 'gen/htaccess.api.txt', '.htaccess')
			->cd('..')
			->wf('composer.json', $project_composer);

		$this->getCli()
			->success(\sprintf('project "%s" created in "%s".', $name, $root))
			->info('You need to run:')
			->writeLn("\tcomposer update");
	}
}
