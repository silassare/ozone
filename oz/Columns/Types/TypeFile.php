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

namespace OZONE\OZ\Columns\Types;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\Utils\ORMTypeHint;
use JsonException;
use OZONE\OZ\Core\DbManager;
use OZONE\OZ\Db\OZFile;
use OZONE\OZ\FS\FilesUtils;
use OZONE\OZ\Http\UploadedFile;
use Throwable;

/**
 * Class TypeFile.
 */
class TypeFile extends Type
{
	public const NAME = 'file';

	/**
	 * TypeFile constructor.
	 */
	public function __construct()
	{
		parent::__construct(new TypeString());
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getInstance(array $options): self
	{
		return (new static())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function default($default): self
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * Sets file driver.
	 *
	 * @return $this
	 */
	public function driver(string $driver): self
	{
		return $this->setOption('driver', $driver);
	}

	/**
	 * Enable/disable multiple file.
	 *
	 * @return $this
	 */
	public function multiple(bool $multiple = true): self
	{
		return $this->setOption('multiple', $multiple);
	}

	/**
	 * Checks if multiple file is allowed.
	 *
	 * @return bool
	 */
	public function isMultiple(): bool
	{
		return (bool) $this->getOption('multiple', false);
	}

	/**
	 * Sets allowed mime types.
	 *
	 * @param string[] $mime_types
	 *
	 * @return $this
	 */
	public function mimeTypes(array $mime_types): self
	{
		$mime_types = \array_unique($mime_types);

		return $this->setOption('mime_types', $mime_types);
	}

	/**
	 * Sets upload file label.
	 *
	 * @param string $label
	 *
	 * @return $this
	 */
	public function fileLabel(string $label): self
	{
		return $this->setOption('file_label', $label);
	}

	/**
	 * Sets minimum file size.
	 *
	 * @param int $min
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function fileMinSize(int $min): self
	{
		$max = $this->getOption('file_max_size', \INF);

		self::assertSafeIntRange($min, $max, 1);

		return $this->setOption('file_min_size', $min);
	}

	/**
	 * Sets maximum file size.
	 *
	 * @param int $max
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function fileMaxSize(int $max): self
	{
		$min = $this->getOption('file_min_size', 1);

		self::assertSafeIntRange($min, $max, 1);

		return $this->setOption('file_max_size', $max);
	}

	/**
	 * Sets minimum files count.
	 *
	 * @param int $min
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function fileMinCount(int $min): self
	{
		$max = $this->getOption('file_max_count', \INF);

		self::assertSafeIntRange($min, $max, 1);

		return $this->setOption('file_min_count', $min);
	}

	/**
	 * Sets maximum files count.
	 *
	 * @param int $max
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function fileMaxCount(int $max): self
	{
		$min = $this->getOption('file_min_count', 1);

		self::assertSafeIntRange($min, $max, 1);

		return $this->setOption('file_max_count', $max);
	}

	/**
	 * Sets file upload total size.
	 *
	 * @param int $total total upload size in bytes
	 *
	 * @return $this
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function fileUploadTotalSize(int $total): self
	{
		if ($total <= 0) {
			throw new TypesException(\sprintf('total=%s is not greater than 0.', $total));
		}

		return $this->setOption('file_upload_total_size', $total);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return null|string|string[]
	 */
	public function validate($value): string|array|null
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullAble()) {
				return null;
			}
		}

		if ($value) {
			$value = \is_array($value) ? $value : [$value];
			$total = \count($value);

			if (!$this->checkFileCount($total)) {
				throw new TypesInvalidValueException('OZ_FILE_COUNT_OUT_OF_RANGE', [
					'min' => $this->getOption('file_min_count'),
					'max' => $this->getOption('file_max_count'),
				]);
			}

			$results = $this->computeUploadedFiles($value, $debug);

			return $this->isMultiple() ? $results : $results[0];
		}

		throw new TypesInvalidValueException('OZ_FILE_INVALID', $debug);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 */
	public function dbToPhp($value, RDBMSInterface $rdbms): string|array|null
	{
		if (null === $value) {
			return null;
		}

		return $this->isMultiple() ? \json_decode($value, false, 512, \JSON_THROW_ON_ERROR) : $value;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 */
	public function phpToDb($value, RDBMSInterface $rdbms): ?string
	{
		if (null === $value) {
			return null;
		}

		return $this->isMultiple() ? \json_encode($value, \JSON_THROW_ON_ERROR) : $value;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getWriteTypeHint(): array
	{
		return [$this->isMultiple() ? ORMTypeHint::ARRAY : ORMTypeHint::STRING];
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): array
	{
		return [$this->isMultiple() ? ORMTypeHint::ARRAY : ORMTypeHint::STRING];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesException
	 */
	public function configure(array $options): self
	{
		if (isset($options['multiple'])) {
			$this->multiple((bool) $options['multiple']);
		}

		if (isset($options['driver'])) {
			$this->driver((string) $options['driver']);
		}

		if (isset($options['mime_types'])) {
			$this->mimeTypes((array) $options['mime_types']);
		}

		if (isset($options['file_label'])) {
			$this->fileLabel((string) $options['file_label']);
		}

		if (isset($options['file_min_size'])) {
			$this->fileMinSize((int) $options['file_min_size']);
		}

		if (isset($options['file_max_size'])) {
			$this->fileMaxSize((int) $options['file_max_size']);
		}

		if (isset($options['file_min_count'])) {
			$this->fileMinCount((int) $options['file_min_count']);
		}

		if (isset($options['file_max_count'])) {
			$this->fileMaxCount((int) $options['file_max_count']);
		}

		if (isset($this->file_upload_total_size)) {
			$this->fileUploadTotalSize((int) $options['file_upload_total_size']);
		}

		return parent::configure($options);
	}

	/**
	 * @param \OZONE\OZ\Http\UploadedFile[] $uploaded_files
	 * @param array                         $debug
	 *
	 * @return string[]
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	protected function computeUploadedFiles(array $uploaded_files, array $debug): array
	{
		$total_size = 0;
		$file_label = $this->getOption('file_label', '');

		foreach ($uploaded_files as $k => $item) {
			$debug['index'] = $k;

			if ($item instanceof UploadedFile) {
				$this->checkUploadedFile($item);

				$total_size += $item->getSize();
			} elseif ($item instanceof OZFile) {
				$this->checkOZFile($item);

				$total_size += $item->getSize();
			} else {
				throw new TypesInvalidValueException('OZ_FILE_INVALID', $debug);
			}
		}

		$file_upload_total_size = $this->getOption('file_upload_total_size');

		if (null !== $file_upload_total_size && $total_size > $file_upload_total_size) {
			throw new TypesInvalidValueException('OZ_FILE_TOTAL_SIZE_EXCEED_LIMIT', $debug);
		}

		$driver_name = $this->getOption('driver', 'default');
		$driver      = FilesUtils::getFileDriver($driver_name);

		/** @var \OZONE\OZ\Db\OZFile[] $new_file_list */
		$new_file_list = [];
		$data          = [];
		$db            = DbManager::getDb();

		try {
			$db->beginTransaction();

			foreach ($uploaded_files as $file) {
				if ($file instanceof OZFile) {
					/** @var string $fid */
					$fid = $file->getID();
				} else {
					$fo = $driver->upload($file);
					$fo->setDriver($driver_name)
						->setLabel($file_label)
						->save();

					$new_file_list[] = $fo;

					/** @var string $fid */
					$fid = $fo->getID();
				}

				$data[] = $fid;
			}
		} catch (Throwable $t) {
			$db->rollBack();

			foreach ($new_file_list as $f) {
				$driver->delete($f);
			}

			throw new TypesInvalidValueException('OZ_FILE_UPLOAD_FAILS', null, $t);
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
	protected function checkFileCount(int $total): bool
	{
		if (!$this->isMultiple()) {
			return 1 === $total;
		}

		$min = $this->getOption('file_min_count', 1);
		$max = $this->getOption('file_max_count', 1);

		return $total >= $min && $total <= $max;
	}

	/**
	 * Checks file size.
	 *
	 * @param int $size
	 *
	 * @return bool
	 */
	protected function checkFileSize(int $size): bool
	{
		$min = $this->getOption('file_min_size', 1);
		$max = $this->getOption('file_max_size', \PHP_INT_MAX);

		return $size >= $min && $size <= $max;
	}

	/**
	 * Checks file mime.
	 *
	 * @param string $mime
	 *
	 * @return bool
	 */
	protected function checkFileMime(string $mime): bool
	{
		$mime_types = $this->getOption('mime_types', []);

		return !\count($mime_types) || \in_array($mime, $mime_types, true);
	}

	/**
	 * Checks uploaded file.
	 *
	 * @param \OZONE\OZ\Http\UploadedFile $upload
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	protected function checkUploadedFile(UploadedFile $upload): void
	{
		$error              = $upload->getError();
		$debug['file_name'] = $upload->getClientFilename();

		if (\UPLOAD_ERR_OK !== $error) {
			$info             = FilesUtils::uploadErrorInfo($error);
			$debug['_reason'] = $info['reason'];

			throw new TypesInvalidValueException($info['message'], $debug);
		}

		if (!$this->checkFileSize($upload->getSize())) {
			$debug['min'] = $this->getOption('file_min_size');
			$debug['max'] = $this->getOption('file_max_size');

			throw new TypesInvalidValueException('OZ_FILE_SIZE_OUT_OF_RANGE', $debug);
		}

		if (!$this->checkFileMime($upload->getClientMediaType())) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}
	}

	/**
	 * Checks ozone file.
	 *
	 * @param \OZONE\OZ\Db\OZFile $file
	 *
	 * @throws \Gobl\DBAL\Types\Exceptions\TypesInvalidValueException
	 */
	protected function checkOZFile(OZFile $file): void
	{
		$debug['file_name'] = $file->getName();

		if (!$this->checkFileSize($file->getSize())) {
			$debug['min'] = $this->getOption('file_min_size');
			$debug['max'] = $this->getOption('file_max_size');

			throw new TypesInvalidValueException('OZ_FILE_SIZE_OUT_OF_RANGE', $debug);
		}

		if (!$this->checkFileMime($file->getMimeType())) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}
	}
}
