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
use Gobl\ORM\Generators\CSGeneratorORM;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Templates;

/**
 * Class ServiceGenerator.
 */
class ServiceGenerator extends CSGeneratorORM
{
	/**
	 * ServiceGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(
		RDBMSInterface $db,
	) {
		parent::__construct($db);
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(array $tables, ?string $path = null, string $header = ''): static
	{
		foreach ($tables as $table) {
			$this->generateServiceClass(
				$table,
				$table->getNamespace() . '\Services',
				'',
				'',
				$header,
				$path,
				true
			);
		}

		return $this;
	}

	/**
	 * Generate OZone service class for a given table.
	 *
	 * @param Table       $table             the table
	 * @param string      $service_namespace the service class namespace
	 * @param string      $service_path      the service path
	 * @param string      $service_class     the service class name to use
	 * @param string      $header            the source header to use
	 * @param null|string $service_dir       the destination folder path
	 *
	 * @return array{provider:string} the generated service info
	 */
	public function generateServiceClass(
		Table $table,
		string $service_namespace,
		string $service_path,
		string $service_class,
		string $header = '',
		?string $service_dir = null,
		bool $override = false
	): array {
		if (!$table->hasPrimaryKeyConstraint()) {
			throw new RuntimeException(\sprintf('There is no primary key in the table "%s".', $table->getName()));
		}

		if (!$table->hasSinglePKColumn()) {
			throw new \PHPUtils\Exceptions\RuntimeException(
				\sprintf(
					'Table "%s" has more than one column in primary key while expecting 1.'
						. 'You can generate service only for tables with one column as primary key.',
					$table->getName()
				)
			);
		}

		$fs           = self::outputDirFS($service_dir);
		$class_path   = $fs->resolve($service_class . '.php');
		$service_path = \trim($service_path, '/');

		if ($override && \file_exists($class_path)) {
			\rename($class_path, $class_path . '.backup');
		}

		// we check if the class file is empty/not exists etc...
		$fs->filter()
			->isEmpty()
			->assert($class_path);

		$inject                         = $this->describeTable($table);
		$inject['oz_header']            = $header;
		$inject['service']['path']      = $service_path;
		$inject['service']['namespace'] = $service_namespace;
		$inject['service']['class']     = $service_class;
		$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];

		$content = Templates::compile('oz://~core~/gen/gobl/MyService.php.blate', $inject);

		\file_put_contents($class_path, $content);

		return [
			'provider' => $qualified_class,
		];
	}
}
