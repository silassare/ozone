<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Cli\Cmd;

use Kli\Exceptions\KliInputException;
use Kli\KliAction;
use Kli\KliOption;
use Kli\Types\KliTypeBool;
use Kli\Types\KliTypePath;
use Kli\Types\KliTypeString;
use OZONE\OZ\Cli\Command;
use OZONE\OZ\Cli\Utils\Utils;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\FS\FilesManager;
use OZONE\OZ\FS\TemplatesUtils;
use OZONE\OZ\Utils\StringUtils;

final class Project extends Command
{
	/**
	 * @inheritdoc
	 *
	 * @throws \Exception
	 */
	public function execute(KliAction $action, array $options, array $anonymous_options)
	{
		switch ($action->getName()) {
			case 'create':
				$this->create($options);

				break;
			case 'backup':
				$this->backup($options);

				break;
		}
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \Kli\Exceptions\KliException
	 */
	protected function describe()
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
	 * @throws \Exception
	 */
	private function backup(array $options)
	{
		Utils::assertDatabaseAccess();

		$dir               = $options['d'];
		$full              = $options['f'];
		$project_fs        = new FilesManager();
		$project_name      = SettingsManager::get('oz.config', 'OZ_PROJECT_NAME');
		$project_name_slug = \strtolower(StringUtils::stringToURLSlug($project_name));
		$backup_name       = Hasher::genRandomFileName('backup-' . $project_name_slug);

		if ($project_fs->isSelf($dir) || $project_fs->isParentOf($dir)) {
			throw new KliInputException('You should not backup project to project directory or subdirectory.');
		}
		$this->getCli()
			 ->info('Copying the required files and directories may take some time ...');

		$fm      = new FilesManager($dir);
		$exclude = function ($filename) {
			return \preg_match('~^\.git|\.idea|otpl_done|node_modules|debug\.log$~', $filename);
		};
		$fm->cd($backup_name, true)
		   ->cp($project_fs->getRoot(), null, ['exclude' => $exclude]);

		if ($full) {
			$this->getCli()
				 ->info('Generating database backup for your project ...');

			$db_backup_dir = \escapeshellarg($fm->getRoot());
			$this->getCli()
				 ->executeString("db backup -d={$db_backup_dir}");
		}

		$this->getCli()
			 ->success('A backup of your project was created.')
			 ->writeLn($fm->getRoot());
	}

	/**
	 * Creates new project.
	 *
	 * @param array $options
	 *
	 * @throws \Exception
	 */
	private function create(array $options)
	{
		$name       = $options['n'];
		$folder     = $options['r'];
		$class_name = $options['c'];
		$prefix     = \strtoupper($options['p']);
		$config     = Utils::loadProjectConfig($folder);

		if ($config !== null) {
			$this->getCli()
				 ->error(\sprintf(
					 'project "%s" created with "OZone %s" exists in "%s".',
					 $config['OZ_PROJECT_NAME'],
					 $config['OZ_OZONE_VERSION'],
					 $folder
				 ));

			return;
		}

		$namespace = StringUtils::removeSuffix(\strtoupper($class_name), 'APP');

		// when class_name is App
		if (empty($namespace)) {
			$namespace = \strtoupper($prefix);
		}

		$namespace      = \sprintf('%s\\App', $namespace);
		$app_class_file = \sprintf('%s.php', $class_name);

		$inject = SettingsManager::genExportInfo('oz.config', [
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

		$oz_config = TemplatesUtils::compute('oz://gen/settings.info.otpl', $inject);

		$inject = SettingsManager::genExportInfo('oz.db', [
			'OZ_DB_TABLE_PREFIX' => Hasher::genRandomString(\rand(3, 6), Hasher::CHARS_ALPHA),
			'::comment::1'       => 'we use and support MySQL RDBMS by default',
			'OZ_DB_RDBMS'        => 'mysql',
			'OZ_DB_HOST'         => '__db_host__',
			'OZ_DB_NAME'         => '__db_name__',
			'OZ_DB_USER'         => '__db_user__',
			'OZ_DB_PASS'         => '__db_pass__',
			'OZ_DB_CHARSET'      => 'utf8mb4',
			'OZ_DB_COLLATE'      => 'utf8mb4_unicode_ci',
		]);

		$oz_db = TemplatesUtils::compute('oz://gen/settings.info.otpl', $inject);

		$inject = SettingsManager::genExportInfo('oz.keygen.salt', [
			'::comment::1'           => 'salt used to generate files tokens/keys.',
			'OZ_FILE_KEY_GEN_SALT'   => Hasher::genRandomString(\rand(32, 64)),
			'::comment::2'           => 'salt used to generate session identifiers.',
			'OZ_SESSION_ID_GEN_SALT' => Hasher::genRandomString(\rand(32, 64)),
			'::comment::3'           => 'salt used to generate authentication tokens.',
			'OZ_AUTH_TOKEN_SALT'     => Hasher::genRandomString(\rand(32, 64)),
			'::comment::4'           => 'salt used to generate client id/api_key.',
			'OZ_CLIENT_ID_GEN_SALT'  => Hasher::genRandomString(\rand(32, 64)),
		]);

		$oz_key_gen_salt = TemplatesUtils::compute('oz://gen/settings.warn.otpl', $inject);

		$inject = [
			'oz_version_name'      => OZ_OZONE_VERSION_NAME,
			'oz_time'              => \time(),
			'oz_project_namespace' => $namespace,
			'oz_project_class'     => $class_name,
		];

		$app_class = TemplatesUtils::compute('oz://gen/app.otpl', $inject);
		$api_index = TemplatesUtils::compute('oz://gen/index.api.otpl', $inject);

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
		   ->mkdir('oz_templates')
		   ->mkdir('oz_users_files')
		   ->wf($app_class_file, $app_class)
		   ->cd('..')
		   ->ln(OZ_OZONE_DIR, 'oz')
		   ->wf('index.php', $api_index)
		   ->cp($tpl_folder . 'gen/robots.txt', 'robots.txt')
		   ->cp($tpl_folder . 'gen/favicon.ico', 'favicon.ico')
		   ->cp($tpl_folder . 'gen/htaccess.api.txt', '.htaccess')
		   ->cd('..');

		$this->getCli()
			 ->success(\sprintf('project "%s" created in "%s".', $name, $root));
	}
}
