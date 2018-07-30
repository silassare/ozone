<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Db\Columns\Types;

	use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
	use Gobl\DBAL\Types\TypeString;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\FS\FilesUploadHandler;
	use OZONE\OZ\FS\FilesUtils;

	class TypeFile extends TypeString
	{
		private $multiple       = false;
		private $mime_types     = [];
		private $file_label     = "OZ_FILE_UPLOAD_LABEL";
		private $file_min_count = 1;
		private $file_max_count = 1;
		private $file_min_size  = 1;// size in bytes per file
		private $file_max_size  = PHP_INT_MAX;// size in bytes per file

		/**
		 * TypeFile constructor.
		 *
		 * {@inheritdoc}
		 */
		public function __construct()
		{
			parent::__construct();
			// Force column to not be of type TEXT (couldn't have default value)
			$this->length(0, 6500);
		}

		/**
		 * @return $this
		 */
		public function multiple()
		{
			$this->multiple = true;

			return $this;
		}

		/**
		 * @return bool
		 */
		public function isMultiple()
		{
			return $this->multiple;
		}

		/**
		 * @param $label
		 *
		 * @return $this
		 */
		public function fileLabel($label)
		{
			$this->file_label = $label;

			return $this;
		}

		/**
		 * @param $mime_type
		 *
		 * @return $this
		 */
		public function mimeTypes($mime_type)
		{
			if (is_array($mime_type)) {
				$this->mime_types = $mime_type;
			} elseif (is_string($mime_type)) {
				$this->mime_types = [$mime_type];
			}

			return $this;
		}

		/**
		 * Sets file size range.
		 *
		 * @param int $min the minimum file size
		 * @param int $max the maximum file size
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function fileSizeRange($min, $max)
		{
			self::assertSafeIntRange($min, $max, 1);

			$this->file_min_size = $min;
			$this->file_max_size = $max;

			return $this;
		}

		/**
		 * Sets file count range.
		 *
		 * @param int $min the minimum file count
		 * @param int $max the maximum file count
		 *
		 * @return $this
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
		 */
		public function fileCountRange($min, $max)
		{
			self::assertSafeIntRange($min, $max, 1);

			$this->file_min_count = $min;
			$this->file_max_count = $max;

			return $this;
		}

		/**
		 * {@inheritdoc}
		 * @throws \Exception
		 */
		public function validate($value, $column_name, $table_name)
		{
			$debug = [
				"value" => $value
			];

			if (!isset($_FILES[$value])) {
				throw new TypesInvalidValueException("OZ_FILE_FIELD_NOT_FOUND", $debug);
			}

			$upload = $_FILES[$value];
			$uid    = SessionsData::get('ozone_user:id');
			$uid    = empty($uid) ? 1 : $uid;

			if ($this->isMultiple()) {
				if (!isset($upload["name"]) OR !is_array($upload["name"])) {
					// this is not a valid tree dimensional array
					// may be it is not a multiple file upload
					throw new TypesInvalidValueException("OZ_FILE_MULTIPLE_FILE_REQUIRED", $debug);
				}

				$total = count($upload["name"]);

				if ($total < $this->file_min_count OR $total > $this->file_max_count)
					throw new TypesInvalidValueException("OZ_FILE_COUNT_OUT_OF_LIMIT", $debug);

				foreach ($upload['size'] as $index => $size) {
					if (!$this->checkFileSize($size))
						throw new TypesInvalidValueException("OZ_FILE_SIZE_OUT_OF_LIMIT", $debug);
				}

				foreach ($upload['type'] as $index => $mime) {
					if (!$this->checkFileMime($mime))
						throw new TypesInvalidValueException("OZ_FILE_MIME_INVALID", $debug);
				}

				$files = self::computeMultipleFilesUploaded($upload, $uid, $this->file_label, $debug);
				$value = json_encode($files);
			} else {
				if (!empty($upload["tmp_name"])) {
					$size = $upload['size'];

					if (!$this->checkFileSize($size))
						throw new TypesInvalidValueException("OZ_FILE_SIZE_OUT_OF_LIMIT", $debug);

					$mime = $upload['type'];
					if (!$this->checkFileMime($mime))
						throw new TypesInvalidValueException("OZ_FILE_MIME_INVALID", $debug);

					$value = self::computeSingleFileUploaded($upload, $uid, $this->file_label, $debug);
				} elseif ($default = $this->getDefault()) {
					$value = $default;
				} else {
					throw new TypesInvalidValueException("OZ_FILE_FIELD_EMPTY", $debug);
				}
			}

			return $value;
		}

		/**
		 * {@inheritdoc}
		 */
		public static function getInstance(array $options)
		{
			$instance = new self;

			if (isset($options['multiple']) AND $options['multiple'])
				$instance->multiple();

			if (isset($options['mime_types']))
				$instance->mimeTypes($options['mime_types']);

			if (isset($options['file_label']))
				$instance->fileLabel($options['file_label']);

			$instance->fileCountRange(
				self::getOptionKey($options, "file_min_count", 1),
				self::getOptionKey($options, "file_max_count", PHP_INT_MAX)
			);
			$instance->fileSizeRange(
				self::getOptionKey($options, "file_min_size", 1),
				self::getOptionKey($options, "file_max_size", PHP_INT_MAX)
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
			$options                   = parent::getCleanOptions();
			$options['multiple']       = $this->multiple;
			$options['mime_types']     = $this->mime_types;
			$options['file_label']     = $this->file_label;
			$options['file_min_size']  = $this->file_min_size;
			$options['file_max_size']  = $this->file_max_size;
			$options['file_min_count'] = $this->file_min_count;
			$options['file_max_count'] = $this->file_max_count;

			return $options;
		}

		/**
		 * @param $uploaded_file
		 * @param $uid
		 * @param $file_label
		 * @param $debug
		 *
		 * @return string
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 */
		public static function computeSingleFileUploaded($uploaded_file, $uid, $file_label, $debug)
		{
			$user_dir   = FilesUtils::getUserRootDirectory($uid);
			$upload_obj = new FilesUploadHandler();

			$f = $upload_obj->moveUploadedFile($uploaded_file, $user_dir);

			if (!$f) {
				throw new TypesInvalidValueException($upload_obj->lastErrorMessage(), $debug);
			}

			$f->setUserId($uid)
			  ->setLabel($file_label)
				// don't forget to save
			  ->save();

			return $f->getId() . '_' . $f->getKey();
		}

		/**
		 * @param $uploaded_files
		 * @param $uid
		 * @param $file_label
		 * @param $debug
		 *
		 * @return array
		 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
		 * @throws \Gobl\ORM\Exceptions\ORMException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function computeMultipleFilesUploaded($uploaded_files, $uid, $file_label, $debug)
		{
			$user_dir   = FilesUtils::getUserRootDirectory($uid);
			$upload_obj = new FilesUploadHandler();

			$f_list = $upload_obj->moveMultipleFilesUpload($uploaded_files, $user_dir);

			if (!$f_list) {
				throw new TypesInvalidValueException($upload_obj->lastErrorMessage(), $debug);
			}

			$data = [];

			foreach ($f_list as $f) {
				$f->setUserId($uid)
				  ->setLabel($file_label);
				// don't forget to save
				$f->save();

				$data[] = $f->getId() . '_' . $f->getKey();
			}

			return $data;
		}

		/**
		 * Check file count.
		 *
		 * @param integer $total
		 *
		 * @return bool
		 */
		protected function checkFileCount($total)
		{
			return $total >= $this->file_min_count AND $total <= $this->file_max_count;
		}

		/**
		 * Check file size.
		 *
		 * @param integer $size
		 *
		 * @return bool
		 */
		protected function checkFileSize($size)
		{
			return $size >= $this->file_min_size AND $size <= $this->file_max_size;
		}

		/**
		 * Check file mime.
		 *
		 * @param string $mime
		 *
		 * @return bool
		 */
		protected function checkFileMime($mime)
		{
			return !count($this->mime_types) OR in_array($mime, $this->mime_types);
		}
	}
