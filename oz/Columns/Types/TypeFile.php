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

namespace OZONE\Core\Columns\Types;

use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\ORMTypeHint;
use JsonException;
use OLIUP\CG\PHPType;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\TempFS;
use OZONE\Core\Http\UploadedFile;
use Throwable;

/**
 * Class TypeFile.
 */
class TypeFile extends Type
{
	public const NAME               = 'file';
	public const TEMP_FILE_LIFETIME = 3600;

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
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
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
	public function default($default): static
	{
		$this->base_type->default($default);

		return parent::default($default);
	}

	/**
	 * Sets file storage.
	 *
	 * @return $this
	 */
	public function storage(string $storage): static
	{
		return $this->setOption('storage', $storage);
	}

	/**
	 * Sets upload as temporary.
	 *
	 * @param bool     $temp     true to enable temporary upload
	 * @param null|int $lifetime the file lifetime in seconds
	 *
	 * @return $this
	 */
	public function temp(bool $temp = true, ?int $lifetime = null): static
	{
		if ($lifetime) {
			$this->setOption('temp_lifetime', $lifetime);
		}

		return $this->setOption('temp', $temp);
	}

	/**
	 * Checks if upload is temporary.
	 *
	 * @return bool
	 */
	public function isTemporary(): bool
	{
		return (bool) $this->getOption('temp');
	}

	/**
	 * Enable/disable multiple file.
	 *
	 * @return $this
	 */
	public function multiple(bool $multiple = true): static
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
	public function mimeTypes(array $mime_types): static
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
	public function fileLabel(string $label): static
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
	 * @throws TypesException
	 */
	public function fileMinSize(int $min): static
	{
		$max = $this->getOption('file_max_size', \PHP_INT_MAX);

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
	 * @throws TypesException
	 */
	public function fileMaxSize(int $max): static
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
	 * @throws TypesException
	 */
	public function fileMinCount(int $min): static
	{
		$max = $this->getOption('file_max_count', \PHP_INT_MAX);

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
	 * @throws TypesException
	 */
	public function fileMaxCount(int $max): static
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
	 * @throws TypesException
	 */
	public function fileUploadTotalSize(int $total): static
	{
		if ($total <= 0) {
			throw new TypesException(\sprintf('total=%s is not greater than 0.', $total));
		}

		return $this->setOption('file_upload_total_size', $total);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return null|string|string[] the file(s) id(s) or path(s)
	 */
	public function validate($value): array|string|null
	{
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
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
	public function dbToPhp($value, RDBMSInterface $rdbms): array|string|null
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
	public function getWriteTypeHint(): ORMTypeHint
	{
		return $this->isMultiple() ? ORMTypeHint::array()
			->setPHPType(new PHPType('string[]')) : ORMTypeHint::string();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getReadTypeHint(): ORMTypeHint
	{
		return $this->isMultiple() ? ORMTypeHint::array()
			->setPHPType(new PHPType('string[]')) : ORMTypeHint::string();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	public function configure(array $options): static
	{
		if (isset($options['multiple'])) {
			$this->multiple((bool) $options['multiple']);
		}

		if (isset($options['temp'])) {
			$lifetime = null;

			if (isset($options['temp_lifetime'])) {
				$lifetime = (int) $options['temp_lifetime'];
			}

			$this->temp((bool) $options['temp'], $lifetime);
		}

		if (isset($options['storage'])) {
			$this->storage((string) $options['storage']);
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

		if (isset($options['file_upload_total_size'])) {
			$this->fileUploadTotalSize((int) $options['file_upload_total_size']);
		}

		return parent::configure($options);
	}

	/**
	 * Computes uploaded files.
	 *
	 * @param array<OZFile|string|UploadedFile> $uploaded_files
	 * @param array                             $debug
	 *
	 * @return string[] the list of file ids or paths
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function computeUploadedFiles(array $uploaded_files, array $debug): array
	{
		$total_size = 0;

		foreach ($uploaded_files as $k => $item) {
			$debug['index'] = $k;

			if (\is_numeric($item)) {
				// dangerous should be fixed, but we assume the string is a file ID or path
				// we should not allow this in the first place,
				// as the any user may send a random file id that may be have an access restriction
				// but we need to handle it as we currently
				// have a bug in the validation process when saving
				// issue explanation:
				// table foo has image_file_id column that reference oz_file.id
				// now when the form is uploaded the file is validated and saved into the database
				// then when entity of type foo is being saved image_file_id value receive the oz_file.id instead of
				// OZFile or UploadedFile and this is validated
				// Solution: make sure validate always return UploadedFile | OZFile and a TempFile
				// then when saving check inside phpToDb save and use the oz_file.id
				// when reading dbToPhp load the file from database or find a better solution
				oz_logger()->warning(
					'TypeFile: received a string as file, this should not happen, please fix your code.',
					$debug
				);

				$item = FS::getFileByID($item);

				$uploaded_files[$k] = $item;
			}

			// in case of temporary upload
			// only UploadedFile instances are allowed
			if ($item instanceof UploadedFile) {
				$this->checkUploadedFile($item);

				$total_size += $item->getSize();
			} elseif ($item instanceof OZFile && !$this->isTemporary()) {
				$this->checkOZFile($item);

				$total_size += $item->getSize();
			} else {
				throw new TypesInvalidValueException('OZ_FILE_INVALID', $debug);
			}
		}

		$max_total_size         = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE');
		$file_upload_total_size = $this->getOption('file_upload_total_size', $max_total_size);

		if (null !== $file_upload_total_size && $total_size > $file_upload_total_size) {
			throw new TypesInvalidValueException('OZ_FILE_TOTAL_SIZE_EXCEED_LIMIT', $debug);
		}

		if ($this->isTemporary()) {
			/** @var UploadedFile[] $uploaded_files */
			return $this->computeTemporaryUploadedFiles($uploaded_files);
		}

		$label        = $this->getOption('file_label', '');
		$storage_name = $this->getOption('storage', FS::DEFAULT_STORAGE);
		$storage      = FS::getStorage($storage_name);

		/** @var OZFile[] $new_file_list */
		$new_file_list = [];
		$data          = [];
		$db            = db();

		try {
			$db->beginTransaction();

			foreach ($uploaded_files as $file) {
				if ($file instanceof OZFile) {
					/** @var string $fid */
					$fid = $file->getID();
				} else {
					$fo = $storage->upload($file);
					$fo->setForLabel($label)
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
				$storage->delete($f);
			}

			throw new TypesInvalidValueException('OZ_FILE_UPLOAD_FAILS', null, $t);
		}

		$db->commit();

		return $data;
	}

	/**
	 * Computes temporary uploaded files.
	 *
	 * @param array<UploadedFile> $uploaded_files
	 *
	 * @return string[] the list of file paths
	 */
	protected function computeTemporaryUploadedFiles(array $uploaded_files): array
	{
		$lifetime = $this->getOption('temp_lifetime', self::TEMP_FILE_LIFETIME);

		$tmp_fs_dir = TempFS::get($lifetime, 'upload')->dir();

		/** @var string[] $list */
		$list = [];

		foreach ($uploaded_files as $upload) {
			$name = $upload->getCleanFileName();
			$path = $tmp_fs_dir->resolve($name);

			$upload->moveTo($path);

			$list[] = $path;
		}

		return $list;
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

		$max_file_count = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_COUNT');
		$min            = $this->getOption('file_min_count', 1);
		$max            = $this->getOption('file_max_count', $max_file_count);

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
		$max_file_size = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_SIZE');
		$min           = $this->getOption('file_min_size', 1);
		$max           = $this->getOption('file_max_size', $max_file_size);

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
	 * @param UploadedFile $upload
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function checkUploadedFile(UploadedFile $upload): void
	{
		$error              = $upload->getError();
		$debug['file_name'] = $upload->getClientFilename();

		if (\UPLOAD_ERR_OK !== $error) {
			$info             = FS::uploadErrorInfo($error);
			$debug['_reason'] = $info['reason'];

			throw new TypesInvalidValueException($info['message'], $debug);
		}

		if (!$this->checkFileSize($upload->getSize())) {
			$debug['min'] = $this->getOption('file_min_size');
			$debug['max'] = $this->getOption('file_max_size');

			throw new TypesInvalidValueException('OZ_FILE_SIZE_OUT_OF_RANGE', $debug);
		}

		$client_media = $upload->getClientMediaType();
		$clean_media  = $upload->getCleanMediaType();

		if (!$this->checkFileMime($client_media)) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}

		if ($client_media !== $clean_media && !$this->checkFileMime($clean_media)) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}
	}

	/**
	 * Checks ozone file.
	 *
	 * @param OZFile $file
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function checkOZFile(OZFile $file): void
	{
		$debug['file_real_name'] = $file->getRealName();
		$debug['file_name']      = $file->getName();

		if (!$this->checkFileSize($file->getSize())) {
			$debug['min'] = $this->getOption('file_min_size');
			$debug['max'] = $this->getOption('file_max_size');

			throw new TypesInvalidValueException('OZ_FILE_SIZE_OUT_OF_RANGE', $debug);
		}

		if (!$this->checkFileMime($file->getMime())) {
			throw new TypesInvalidValueException('OZ_FILE_MIME_INVALID', $debug);
		}
	}
}
