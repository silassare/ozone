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
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Templates;

/**
 * Class ServiceGenerator.
 */
class ServiceGenerator
{
	/**
	 * ServiceGenerator constructor.
	 *
	 * @param RDBMSInterface $db
	 */
	public function __construct(
		private RDBMSInterface $db,
	) {}

	/**
	 * Generate OZone service class for a given table.
	 *
	 * @param Table       $table             the table
	 * @param string      $service_namespace the service class namespace
	 * @param string      $service_class     the service class name to use
	 * @param string      $base_path         the service url base path
	 * @param string      $header            the header to add at the top of the generated class file
	 * @param null|string $output_dir        the output directory for the generated class file, if null it will be generated in the default services directory
	 * @param bool        $override          whether to override the service class if it already exists,
	 *                                       if false and the class file already exists an exception will be thrown,
	 *                                       if true and the class file already exists it will be renamed
	 *                                       with a ".backup" suffix before generating the new class file
	 *
	 * @return array{provider:string} the generated service info
	 */
	public function generateClass(
		Table $table,
		string $service_namespace,
		string $service_class,
		string $base_path,
		string $header = '',
		?string $output_dir = null,
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

		$fm = FS::fromRoot();

		$fm->filter()
			->isDir()
			->isWritable()
			->assert($output_dir);

		$class_path = $fm->cd($output_dir)->resolve($service_class . '.php');
		$base_path  = \trim($base_path, '/');

		if ($override && \file_exists($class_path)) {
			\rename($class_path, $class_path . '.backup');
		}

		// we check if the class file is empty/not exists etc...
		$fm->filter()
			->isEmpty()
			->assert($class_path);

		$og = new CSGeneratorORM($this->db);

		$og->ignorePrivateTables(false);
		$og->ignorePrivateColumns(false);

		$inject                         = $og->describeTable($table);
		$inject['oz_header']            = $header;
		$inject['service']['path']      = $base_path;
		$inject['service']['namespace'] = $service_namespace;
		$inject['service']['class']     = $service_class;
		$qualified_class                = $service_namespace . '\\' . $inject['service']['class'];

		$content = Templates::compile('oz://~core~/gen/gobl/php/MyService.php.blate', $inject);

		\file_put_contents($class_path, $content);

		return [
			'provider' => $qualified_class,
		];
	}
}
