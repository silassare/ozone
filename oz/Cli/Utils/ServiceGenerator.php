<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Cli\Utils;

use Gobl\DBAL\Table;
use Gobl\ORM\Exceptions\ORMException;
use Gobl\ORM\Generators\Generator;
use InvalidArgumentException;

class ServiceGenerator extends Generator
{
	/**
	 * @inheritDoc
	 */
	public function generate(array $tables, $path, $header = '')
	{
		foreach ($tables as $table) {
			$this->generateServiceClass($table, $table->getNamespace() . '\\Services', $path, '', '', $header);
		}
	}

	/**
	 * Generate OZone service class for a given table.
	 *
	 * @param \Gobl\DBAL\Table $table             the table
	 * @param string           $service_namespace the service class namespace
	 * @param string           $service_dir       the destination folder path
	 * @param string           $service_name      the service name
	 * @param string           $service_class     the service class name to use
	 * @param string           $header            the source header to use
	 *
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 * @throws \Exception
	 *
	 * @return array the OZone setting for the service
	 */
	public function generateServiceClass(
		Table $table,
		$service_namespace,
		$service_dir,
		$service_name,
		$service_class = '',
		$header = ''
	) {
		if (!\file_exists($service_dir) || !\is_dir($service_dir)) {
			throw new InvalidArgumentException(\sprintf('"%s" is not a valid directory path.', $service_dir));
		}

		if (!$table->hasPrimaryKeyConstraint()) {
			throw new ORMException(\sprintf('There is no primary key in the table "%s".', $table->getName()));
		}

		$pk           = $table->getPrimaryKeyConstraint();
		$columns      = $pk->getConstraintColumns();
		$pk_col_count = \count($columns);

		if ($pk_col_count !== 1) {
			throw new ORMException(\sprintf(
				'Table "%s" contains "%s" columns in primary key.'
				. 'You can generate service only for tables with one column as primary key.',
				$table->getName(),
				$pk_col_count
			));
		}

		if (empty($service_class)) {
			$service_class = \Gobl\DBAL\Utils::toClassName($table->getName() . '_service');
		}

		$service_class_tpl              = Generator::getTemplateCompiler('service.class');
		$inject                         = $this->describeTable($table);
		$inject['oz_header']            = $header;
		$inject['oz_version_name']      = OZ_OZONE_VERSION_NAME;
		$inject['oz_time']              = \time();
		$inject['service']['name']      = $service_name;
		$inject['service']['namespace'] = $service_namespace;
		$inject['service']['class']     = $service_class;
		$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];
		$class_path                     = $service_dir . \DIRECTORY_SEPARATOR . $service_class . '.php';

		if (\file_exists($class_path)) {
			\rename($class_path, $class_path . '.old');
		}

		\file_put_contents($class_path, $service_class_tpl->runGet($inject));

		return [
			'provider' => $qualified_class,
		];
	}
}
