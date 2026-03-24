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
use Gobl\DBAL\Types\Interfaces\ValidationSubjectInterface;
use Gobl\DBAL\Types\Type;
use Gobl\DBAL\Types\TypeString;
use Gobl\ORM\ORMTypeHint;
use Gobl\ORM\ORMUniversalType;
use JsonException;
use OLIUP\CG\PHPType;
use Override;
use OZONE\Core\App\Settings;
use OZONE\Core\Columns\ValidatedFile;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\TempFS;
use OZONE\Core\Http\UploadedFile;
use Throwable;

/**
 * Class TypeFile.
 *
 * Handles file upload validation and persistence for OZone column types.
 *
 * Accepted inputs for the write (validation) path:
 *  - {@see UploadedFile} - a fresh HTTP upload (single or multiple)
 *  - {@see OZFile} - an already-persisted DB file entity (re-assignment,
 *    only valid for non-temporary columns)
 *  - {@see ValidatedFile} - a value that previously passed through this
 *    type's validation pipeline (e.g. returned by {@see dbToPhp()} during
 *    an entity re-save)
 *
 * The clean value produced by validation is always:
 *  - `null` - when the column is nullable and no value was provided
 *  - `ValidatedFile` - for a single-file column
 *  - `ValidatedFile[]` - for a multiple-file column
 *
 * DB storage format:
 *  - Single persisted file: the numeric OZFile primary key as a plain string
 *  - Multiple persisted files: a JSON array of numeric IDs
 *  - Temporary file: the absolute TempFS path (single or JSON array)
 *
 * @extends Type<mixed, null|ValidatedFile|ValidatedFile[]>
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
	#[Override]
	public static function getInstance(array $options): static
	{
		return (new self())->configure($options);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getName(): string
	{
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
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
	 * Converts the stored DB string back to a {@see ValidatedFile} or {@see ValidatedFile}[] instance.
	 *
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 */
	#[Override]
	public function dbToPhp(mixed $value, RDBMSInterface $rdbms): array|ValidatedFile|null
	{
		if (null === $value) {
			return null;
		}

		$wrap = $this->isTemporary()
			? static fn (string $v): ValidatedFile => ValidatedFile::forTempPath($v)
			: static fn (string $v): ValidatedFile => ValidatedFile::forFileID($v);

		if ($this->isMultiple()) {
			/** @var string[] $ids */
			$ids = \json_decode($value, true, 512, \JSON_THROW_ON_ERROR);

			return \array_map($wrap, $ids);
		}

		return $wrap($value);
	}

	/**
	 * Serializes a {@see ValidatedFile} or {@see ValidatedFile}[] to the DB storage string.
	 *
	 * {@inheritDoc}
	 *
	 * @throws JsonException
	 */
	#[Override]
	public function phpToDb(mixed $value, RDBMSInterface $rdbms): ?string
	{
		if (null === $value) {
			return null;
		}

		if ($this->isMultiple()) {
			/** @var ValidatedFile[] $value */
			return \json_encode($value, \JSON_THROW_ON_ERROR);
		}

		/** @var ValidatedFile $value */
		return (string) $value;
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public function getWriteTypeHint(): ORMTypeHint
	{
		if ($this->isMultiple()) {
			return ORMTypeHint::list(ORMUniversalType::STRING)->setPHPType(new PHPType('\\' . ValidatedFile::class . '[]'));
		}

		return ORMTypeHint::string()
			->setPHPType(new PHPType('\\' . ValidatedFile::class));
	}

	/**
	 * Returns the read (getter) type hint for ORM code generation.
	 *
	 * {@inheritDoc}
	 */
	#[Override]
	public function getReadTypeHint(): ORMTypeHint
	{
		if ($this->isMultiple()) {
			return ORMTypeHint::list(ORMUniversalType::STRING)->setPHPType(new PHPType('\\' . ValidatedFile::class . '[]'));
		}

		return ORMTypeHint::string()
			->setPHPType(new PHPType('\\' . ValidatedFile::class));
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	#[Override]
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
	 * Validates the incoming file value(s) and accepts a `ValidatedFile`
	 * (single) or `ValidatedFile[]` (multiple) as the clean result.
	 *
	 * Accepted inputs:
	 *  - {@see UploadedFile} - a fresh HTTP upload
	 *  - {@see OZFile} - an already-persisted DB file entity (non-temp only)
	 *  - {@see ValidatedFile} - a value that already passed this pipeline
	 *    (e.g. the result of `dbToPhp()` being written back via entity save)
	 *
	 * Bare strings, integers, or any other type are rejected with
	 * `OZ_FILE_INVALID` to prevent IDOR attacks where a caller could pass an
	 * arbitrary file ID without proving ownership.
	 *
	 * {@inheritDoc}
	 *
	 * @throws TypesInvalidValueException
	 */
	#[Override]
	protected function runValidation(ValidationSubjectInterface $subject): void
	{
		$value = $subject->getUnsafeValue();
		$debug = [
			'value' => $value,
		];

		if (null === $value) {
			$value = $this->getDefault();

			if (null === $value && $this->isNullable()) {
				$subject->accept(null);

				return;
			}
		}

		if (!$value) {
			$subject->reject(new TypesInvalidValueException('OZ_FILE_INVALID', $debug));

			return;
		}

		$value = \is_array($value) ? $value : [$value];
		$total = \count($value);

		if (!$this->checkFileCount($total)) {
			$subject->reject(new TypesInvalidValueException('OZ_FILE_COUNT_OUT_OF_RANGE', [
				'min' => $this->getOption('file_min_count'),
				'max' => $this->getOption('file_max_count'),
			]));

			return;
		}

		try {
			$results = $this->computeUploadedFiles($value, $debug);
		} catch (TypesInvalidValueException $e) {
			$subject->reject($e);

			return;
		}

		$subject->accept($this->isMultiple() ? $results : $results[0]);
	}

	/**
	 * Validates and processes a list of file inputs, returning a `ValidatedFile[]`.
	 *
	 * Each item in `$uploaded_files` must be one of:
	 *  - {@see UploadedFile} - passes {@see checkUploadedFile()}, then uploaded to
	 *    storage (or moved to TempFS for temp columns)
	 *  - {@see OZFile} - passes {@see checkOZFile()}, then reused (non-temp only)
	 *  - {@see ValidatedFile} - already validated by a previous pipeline run;
	 *    accepted as-is and its size is not re-checked
	 *
	 * Bare strings and any other types are **rejected** to prevent IDOR: a raw
	 * file ID supplied by the user would bypass authorization checks.
	 *
	 * @param array<OZFile|UploadedFile|ValidatedFile> $uploaded_files
	 * @param array                                    $debug
	 *
	 * @return ValidatedFile[] the validated file references
	 *
	 * @throws TypesInvalidValueException
	 */
	protected function computeUploadedFiles(array $uploaded_files, array $debug): array
	{
		$total_size      = 0;
		$validated_items = [];

		foreach ($uploaded_files as $k => $item) {
			$debug['index'] = $k;

			if ($item instanceof ValidatedFile) {
				// Already passed through this pipeline -- trust it as-is.
				// Size is not rechecked: it was validated on initial submission.
				$validated_items[$k] = $item;

				continue;
			}

			if ($item instanceof UploadedFile) {
				$this->checkUploadedFile($item);

				$total_size += $item->getSize();
			} elseif ($item instanceof OZFile && !$this->isTemporary()) {
				$this->checkOZFile($item);

				$total_size += $item->getSize();
			} else {
				// Bare strings, integers, or any other type are rejected.
				// Accepting a raw file ID from user-supplied input would be an
				// IDOR vulnerability -- use ValidatedFile::forFileID() for trusted
				// internal assignments instead.
				throw new TypesInvalidValueException('OZ_FILE_INVALID', $debug + [
					'_advice' => 'Each item must be an UploadedFile, OZFile (non-temp), or ValidatedFile instance.'
						. ' Bare strings and other types are rejected to prevent IDOR vulnerabilities.'
						. ' Use ValidatedFile::forFileID() for trusted internal assignments.',
				]);
			}
		}

		$max_total_size         = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE');
		$file_upload_total_size = $this->getOption('file_upload_total_size', $max_total_size);

		if (null !== $file_upload_total_size && $total_size > $file_upload_total_size) {
			throw new TypesInvalidValueException('OZ_FILE_TOTAL_SIZE_EXCEED_LIMIT', $debug);
		}

		// Fast path: every item was already a ValidatedFile pass-through.
		// No upload, DB transaction, or storage provider access needed.
		if (\count($validated_items) === \count($uploaded_files)) {
			return \array_values($validated_items);
		}

		if ($this->isTemporary()) {
			// Only UploadedFile instances are uploaded to TempFS; ValidatedFile
			// pass-throughs (from a previous temp validation) are already paths.
			$new_uploads = \array_filter(
				$uploaded_files,
				static fn ($item) => $item instanceof UploadedFile
			);
			$temp_results = $this->computeTemporaryUploadedFiles(\array_values($new_uploads));

			// Merge: replace UploadedFile slots with the resulting ValidatedFile,
			// keep already-ValidatedFile slots in place.
			$merged    = [];
			$tempIndex = 0;

			foreach ($uploaded_files as $k => $item) {
				if ($item instanceof UploadedFile) {
					$merged[] = $temp_results[$tempIndex++];
				} else {
					$merged[] = $validated_items[$k];
				}
			}

			return $merged;
		}

		$label        = $this->getOption('file_label', '');
		$storage_name = $this->getOption('storage', FS::DEFAULT_STORAGE);
		$storage      = FS::getStorage($storage_name);

		/** @var OZFile[] $new_file_list */
		$new_file_list = [];

		/** @var ValidatedFile[] $data */
		$data = [];
		$db   = db();

		try {
			$db->beginTransaction();

			foreach ($uploaded_files as $k => $item) {
				if (isset($validated_items[$k])) {
					// Already-validated file -- reuse without re-uploading.
					$data[] = $validated_items[$k];

					continue;
				}

				if ($item instanceof OZFile) {
					/** @var string $fid */
					$fid    = $item->getID();
					$data[] = ValidatedFile::forFileID($fid);
				} else {
					/** @var UploadedFile $item */
					$fo = $storage->upload($item);
					$fo->setForLabel($label)
						->save();

					$new_file_list[] = $fo;

					/** @var string $fid */
					$fid    = $fo->getID();
					$data[] = ValidatedFile::forFileID($fid);
				}
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
	 * Moves freshly-uploaded files into TempFS and returns a `ValidatedFile[]`.
	 *
	 * Each item is moved to a file named after its cleaned filename inside the
	 * TempFS upload directory. The returned `ValidatedFile::forTempPath()` instances
	 * hold the absolute path of the moved file.
	 *
	 * @param UploadedFile[] $uploaded_files freshly-uploaded files to move
	 *
	 * @return ValidatedFile[] one `ValidatedFile::forTempPath()` per input
	 */
	protected function computeTemporaryUploadedFiles(array $uploaded_files): array
	{
		$lifetime = $this->getOption('temp_lifetime', self::TEMP_FILE_LIFETIME);

		$tmp_fs_dir = TempFS::get($lifetime, 'upload')->dir();

		/** @var ValidatedFile[] $list */
		$list = [];

		foreach ($uploaded_files as $upload) {
			$name = $upload->getCleanFileName();
			$path = $tmp_fs_dir->resolve($name);

			$upload->moveTo($path);

			$list[] = ValidatedFile::forTempPath($path);
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
