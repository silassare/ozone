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

namespace OZONE\Core\Cli\Utils;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use Gobl\Gobl;
use Gobl\ORM\Generators\CSGeneratorORM;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Templates;

/**
 * Class ServiceGenerator.
 */
class ServiceGenerator extends CSGeneratorORM
{
	public const SERVICE_TEMPLATE_NAME = 'service.class';

	private static bool $templates_registered = false;

	/**
	 * ServiceGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(
		RDBMSInterface $db,
	) {
		parent::__construct($db);

		if (!self::$templates_registered) {
			Gobl::addTemplate(
				self::SERVICE_TEMPLATE_NAME,
				Templates::localize('oz://~core~/gen/gobl/php/MyService.php'),
				[
					'MY_SERVICE_NS' => '<%$.service.namespace%>',
					'MyService'     => '<%$.service.class%>',
					'my_path'       => '<%$.service.path%>',
				]
			);

			self::$templates_registered = true;
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(array $tables, string $path, string $header = ''): static
	{
		foreach ($tables as $table) {
			$this->generateServiceClass(
				$table,
				$table->getNamespace() . '\Services',
				$path,
				'',
				'',
				$header,
				true
			);
		}

		return $this;
	}

	/**
	 * Generate OZone service class for a given table.
	 *
	 * @param Table  $table             the table
	 * @param string $service_namespace the service class namespace
	 * @param string $service_dir       the destination folder path
	 * @param string $service_path      the service path
	 * @param string $service_class     the service class name to use
	 * @param string $header            the source header to use
	 *
	 * @return array{provider:string} the generated service info
	 */
	public function generateServiceClass(
		Table $table,
		string $service_namespace,
		string $service_dir,
		string $service_path,
		string $service_class,
		string $header = '',
		bool $override = false
	): array {
		if (!$table->hasPrimaryKeyConstraint()) {
			throw new RuntimeException(\sprintf('There is no primary key in the table "%s".', $table->getName()));
		}

		$fm = FS::fromRoot();

		$fm->filter()
			->isDir()
			->assert($service_dir);

		if ($table->hasSinglePKColumn()) {
			throw new \PHPUtils\Exceptions\RuntimeException(
				\sprintf(
					'Table "%s" has more than one column in primary key while expecting 1.'
					 . 'You can generate service only for tables with one column as primary key.',
					$table->getName()
				)
			);
		}

		$pk_column    = $table->getSinglePKColumnOrFail();
		$service_path = \trim($service_path, '/');

		$inject                         = $this->describeTable($table);
		$inject['oz_header']            = $header;
		$inject['oz_version_name']      = OZ_OZONE_VERSION_NAME;
		$inject['oz_time']              = \time();
		$inject['service']['path']      = $service_path;
		$inject['service']['namespace'] = $service_namespace;
		$inject['service']['class']     = $service_class;
		$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];

		$class_path = $service_dir . \DIRECTORY_SEPARATOR . $service_class . '.php';

		if ($override && \file_exists($class_path)) {
			\rename($class_path, $class_path . '.backup');
		}

		// we check if the class file is empty/not exists etc...
		$fm->filter()
			->isEmpty()
			->assert($class_path);

		$replaces = [
			'my_id' => $pk_column->getFullName(),
		];

		$search      = \array_keys($replaces);
		$replacement = \array_values($replaces);

		$content = \str_replace($search, $replacement, Gobl::runTemplate(self::SERVICE_TEMPLATE_NAME, $inject));

		\file_put_contents($class_path, $content);

		return [
			'provider' => $qualified_class,
		];
	}
}
