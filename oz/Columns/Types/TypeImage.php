<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Columns\Types;

final class TypeImage extends TypeFile
{
	private $image_min_width;

	private $image_max_width;

	private $image_min_height;

	private $image_max_height;

	/**
	 * TypeImage constructor.
	 *
	 * @inheritdoc
	 */
	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Sets image height range.
	 *
	 * @param int $min the minimum height
	 * @param int $max the maximum height
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function imageHeightRange($min, $max)
	{
		self::assertSafeIntRange($min, $max, 1);
		$this->image_min_height = $min;
		$this->image_max_height = $max;

		return $this;
	}

	/**
	 * Sets image width range.
	 *
	 * @param int $min the minimum width
	 * @param int $max the maximum width
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function imageWidthRange($min, $max)
	{
		self::assertSafeIntRange($min, $max, 1);

		$this->image_min_width = $min;
		$this->image_max_width = $max;

		return $this;
	}

	/**
	 * @inheritdoc
	 */
	public function validate($value, $column_name, $table_name)
	{
		return parent::validate($value, $column_name, $table_name);
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		$options                     = parent::getCleanOptions();
		$options['image_min_width']  = $this->image_min_width;
		$options['image_max_width']  = $this->image_max_width;
		$options['image_min_height'] = $this->image_min_height;
		$options['image_max_height'] = $this->image_max_height;

		return $options;
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		$instance->mimeTypes(['image/jpeg']);

		$instance->imageWidthRange(
			self::getOptionKey($options, 'image_min_width', 1),
			self::getOptionKey($options, 'image_max_width', \PHP_INT_MAX)
		);

		$instance->imageHeightRange(
			self::getOptionKey($options, 'image_min_height', 1),
			self::getOptionKey($options, 'image_max_height', \PHP_INT_MAX)
		);

		if (self::getOptionKey($options, 'null', false)) {
			$instance->nullAble();
		}

		if (\array_key_exists('default', $options)) {
			$instance->setDefault($options['default']);
		}

		return $instance;
	}
}
