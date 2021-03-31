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

use Exception;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\TypeString;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\FS\FilesUploadHandler;
use OZONE\OZ\FS\FilesUtils;
use OZONE\OZ\Http\UploadedFile;
use Throwable;

class TypeFile extends TypeString
{
	private $multiple = false;

	private $mime_types = [];

	private $file_label = 'OZ_FILE_UPLOAD_LABEL';

	private $file_min_count = 1;

	private $file_max_count = 1;

	private $file_min_size = 1;// size in bytes per file

	private $file_max_size = \PHP_INT_MAX;// size in bytes per file

	private $file_upload_total_size = \PHP_INT_MAX;// size in bytes

	/**
	 * TypeFile constructor.
	 *
	 * @inheritdoc
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
	 * Sets allowed mime types.
	 *
	 * @param $mime_type
	 *
	 * @return $this
	 */
	public function mimeTypes($mime_type)
	{
		if (\is_array($mime_type)) {
			$this->mime_types = $mime_type;
		} elseif (\is_string($mime_type)) {
			$this->mime_types = [$mime_type];
		}

		return $this;
	}

	/**
	 * Sets upload file label.
	 *
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
	 * Sets file size range.
	 *
	 * @param int $min the minimum file size in bytes
	 * @param int $max the maximum file size in bytes
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function fileSizeRange($min, $max)
	{
		self::assertSafeIntRange($min, $max, 1);

		$this->file_min_size = (int) $min;
		$this->file_max_size = (int) $max;

		return $this;
	}

	/**
	 * Sets file upload total size.
	 *
	 * @param int $total total upload size in bytes
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function fileUploadTotalSize($total)
	{
		if (!\is_int($total)) {
			throw new TypesException(\sprintf('total=%s is not a valid integer.', $total));
		}

		if ($total <= 0) {
			throw new TypesException(\sprintf('total=%s is not greater than 0.', $total));
		}

		$this->file_upload_total_size = $total;

		return $this;
	}

	/**
	 * Sets file count range.
	 *
	 * @param int $min the minimum file count
	 * @param int $max the maximum file count
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 *
	 * @return $this
	 */
	public function fileCountRange($min, $max)
	{
		self::assertSafeIntRange($min, $max, 1);

		$this->file_min_count = (int) $min;
		$this->file_max_count = (int) $max;

		return $this;
	}

	/**
	 * @inheritdoc
	 *
	 * @throws \Exception
	 */
	public function validate($value, $column_name, $table_name)
	{
		$debug = [
			'value' => $value,
		];

		$upload = $value;
		// TODO find a way to set the real user id
		$uid = '1';

		if ($this->isMultiple()) {
			$uploaded_files = !\is_array($value) ? [$value] : $value;
			$total          = \count($uploaded_files);

			if (!$this->checkFileCount($total)) {
				throw new TypesInvalidValueException('OZ_FILE_COUNT_OUT_OF_LIMIT', [
					'min' => $this->file_min_count,
					'max' => $this->file_max_count,
				]);
			}

			$files = $this->computeUploadedFiles($uploaded_files, $uid, $this->file_label, $debug);
			$value = \json_encode($files);
		} else {
			if ($upload) {
				$results = $this->computeUploadedFiles([$upload], $uid, $this->file_label, $debug);
				$value   = $results[0];
			} elseif ($default = $this->getDefault()) {
				$value = $default;
			} else {
				throw new TypesInvalidValueException('OZ_FILE_FIELD_EMPTY', $debug);
			}
		}

		return $value;
	}

	/**
	 * @inheritdoc
	 */
	public function getCleanOptions()
	{
		$options                           = parent::getCleanOptions();
		$options['multiple']               = $this->multiple;
		$options['mime_types']             = $this->mime_types;
		$options['file_label']             = $this->file_label;
		$options['file_min_size']          = $this->file_min_size;
		$options['file_max_size']          = $this->file_max_size;
		$options['file_min_count']         = $this->file_min_count;
		$options['file_max_count']         = $this->file_max_count;
		$options['file_upload_total_size'] = $this->file_upload_total_size;

		return $options;
	}

	/**
	 * @param \OZONE\OZ\Http\UploadedFile[] $uploaded_files
	 * @param string                        $uid
	 * @param string                        $file_label
	 * @param array                         $debug
	 *
	 * @throws \Exception
	 *
	 * @return string[]
	 */
	protected function computeUploadedFiles(array $uploaded_files, $uid, $file_label, $debug)
	{
		$total_size = 0;

		foreach ($uploaded_files as $k => $item) {
			if ($item instanceof UploadedFile) {
				$this->checkUploadedFile($item);
				$total_size += $item->getSize();
			} elseif ($this->isMultiple()) {
				$debug['index'] = $k;

				throw new TypesInvalidValueException('invalid_file_upload', $debug);
			} else {
				throw new TypesInvalidValueException('invalid_file_upload', $debug);
			}
		}

		if ($total_size > $this->file_upload_total_size) {
			throw new TypesInvalidValueException('OZ_FILE_UPLOAD_TOTAL_SIZE_EXCEED_LIMIT', $debug);
		}

		$user_dir = FilesUtils::getUserRootDirectory($uid);
		$fuh      = new FilesUploadHandler();
		$error    = false;

		/* @var \OZONE\OZ\Db\OZFile[] $file_list */
		$file_list = [];

		foreach ($uploaded_files as $k => $file) {
			$fo = $fuh->moveUploadedFile($file, $user_dir);

			if (!$fo) {
				$error = true;

				break;
			}

			$file_list[$k] = $fo;
		}

		if ($error) {
			foreach ($file_list as $f) {
				$fuh->safeDelete($f);
			}

			throw new TypesInvalidValueException($fuh->lastErrorMessage(), $debug);
		}

		$data = [];
		$db   = DbManager::getDb();

		try {
			$db->beginTransaction();

			foreach ($file_list as $f) {
				$f->setUserId($uid)
				  ->setLabel($file_label)
				  ->save();

				$data[] = $f->getId() . '_' . $f->getKey();
			}
		} catch (Exception $e) {
			// php 5.6 and earlier

			$db->rollBack();

			foreach ($file_list as $f) {
				$fuh->safeDelete($f);
			}

			throw new InternalErrorException('Unable to save uploaded files to database.', $debug, $e);
		} catch (Throwable $t) {
			$db->rollBack();

			foreach ($file_list as $f) {
				$fuh->safeDelete($f);
			}

			throw new InternalErrorException('Unable to save uploaded files to database.', $debug, $t);
		}

		$db->commit();

		return $data;
	}

	/**
	 * Checks file count.
	 *
	 * @param int $total
	 *
	 * @return bool
	 */
	protected function checkFileCount($total)
	{
		return $total >= $this->file_min_count && $total <= $this->file_max_count;
	}

	/**
	 * Checks file size.
	 *
	 * @param int $size
	 *
	 * @return bool
	 */
	protected function checkFileSize($size)
	{
		return $size >= $this->file_min_size && $size <= $this->file_max_size;
	}

	/**
	 * Checks file mime.
	 *
	 * @param string $mime
	 *
	 * @return bool
	 */
	protected function checkFileMime($mime)
	{
		return !\count($this->mime_types) || \in_array($mime, $this->mime_types);
	}

	/**
	 * Checks uploaded file.
	 *
	 * @param \OZONE\OZ\Http\UploadedFile $upload
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	protected function checkUploadedFile(UploadedFile $upload)
	{
		$error              = $upload->getError();
		$debug['file_name'] = $upload->getClientFilename();

		if ($error != \UPLOAD_ERR_OK) {
			throw new TypesInvalidValueException(FilesUploadHandler::uploadErrorMessage($error), $debug);
		}

		if (!$this->checkFileSize($upload->getSize())) {
			$debug['min'] = $this->file_min_size;
			$debug['max'] = $this->file_max_size;

			throw new TypesInvalidValueException('OZ_FILE_SIZE_OUT_OF_LIMIT', $debug);
		}

		if (!$this->checkFileMime($upload->getClientMediaType())) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}
	}

	/**
	 * @inheritdoc
	 */
	public static function getInstance(array $options)
	{
		$instance = new self();

		if (isset($options['multiple']) && $options['multiple']) {
			$instance->multiple();
		}

		if (isset($options['mime_types'])) {
			$instance->mimeTypes($options['mime_types']);
		}

		if (isset($options['file_label'])) {
			$instance->fileLabel($options['file_label']);
		}

		if (isset($options['file_upload_total_size'])) {
			$instance->fileUploadTotalSize($options['file_upload_total_size']);
		}
		$instance->fileCountRange(
			self::getOptionKey($options, 'file_min_count', 1),
			self::getOptionKey($options, 'file_max_count', \PHP_INT_MAX)
		);
		$instance->fileSizeRange(
			self::getOptionKey($options, 'file_min_size', 1),
			self::getOptionKey($options, 'file_max_size', \PHP_INT_MAX)
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
