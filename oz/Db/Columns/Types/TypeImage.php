<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Columns\Types;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;

	final class TypeImage extends TypeFile
	{
		private $image_min_width  = null;
		private $image_max_width  = null;
		private $image_min_height = null;
		private $image_max_height = null;

		/**
		 * TypeImage constructor.
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
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
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
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function imageWidthRange($min, $max)
		{
			self::assertSafeIntRange($min, $max, 1);

			$this->image_min_width = $min;
			$this->image_max_width = $max;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 */
		public function validate($value, $column_name, $table_name)
		{
			$debug = [
				"value" => $value
			];

			if (!isset($_FILES[$value])) {
				throw new TypesInvalidValueException("OZ_FILE_FIELD_NOT_FOUND", $debug);
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			$instance->imageWidthRange(
				self::getOptionKey($options, "image_min_width", 1),
				self::getOptionKey($options, "image_max_width", PHP_INT_MAX)
			);

			$instance->imageHeightRange(
				self::getOptionKey($options, "image_min_height", 1),
				self::getOptionKey($options, "image_max_height", PHP_INT_MAX)
			);

			if (self::getOptionKey($options, 'null', false))
				$instance->nullAble();

			if (array_key_exists('default', $options))
				$instance->setDefault($options['default']);

			return $instance;
		}

		/**
		 * {@inheritdoc}
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
	}
