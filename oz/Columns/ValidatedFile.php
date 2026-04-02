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

namespace OZONE\Core\Columns;

use InvalidArgumentException;
use JsonSerializable;
use LogicException;
use Override;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FS;

/**
 * Class ValidatedFile.
 *
 * Represents a file reference that has already passed through the TypeFile
 * validation pipeline. There are two variants:
 *
 * - **Persisted**: backed by an `OZFile` DB record. Created from a
 *   successful upload or when reading a column value from the database.
 *   The raw value is the numeric file ID stored in the column.
 *
 * - **Temporary**: backed by a path in TempFS. Created when `TypeFile` is
 *   configured as `->temp(true)`. The raw value is the absolute filesystem
 *   path of the uploaded file in the temp directory.
 *
 * Usage:
 *
 * ```php
 * // Force-assign a known persisted file ID (internal/trusted code only):
 * $entity->image_file_id = ValidatedFile::forFileID('42');
 *
 * // Assign a validated temp path:
 * $entity->tmp_avatar = ValidatedFile::forTempPath('/data/tmp/abc.jpg');
 *
 * // After loading from DB, lazily fetch the OZFile record:
 * $vf = $entity->getAvatarFileId(); // returns ValidatedFile
 * $oz = $vf->loadFile();            // returns OZFile|null (cached)
 * ```
 */
final class ValidatedFile implements JsonSerializable
{
	/** @var null|OZFile lazily loaded OZFile record (null for temp files or before first load) */
	private ?OZFile $_loaded_file = null;

	/**
	 * Private constructor - use the named factories {@see forFileID()} and {@see forTempPath()}.
	 *
	 * @param string $value     the raw value: a numeric file ID or an absolute TempFS path
	 * @param bool   $temporary true when the value is a TempFS path, false when it is a file ID
	 */
	private function __construct(
		private readonly string $value,
		private readonly bool $temporary
	) {}

	/**
	 * Returns the raw value (file ID or TempFS path) as a string.
	 *
	 * This is what {@see Types\TypeFile::phpToDb()} stores in the database column
	 * via `(string) $validatedFile`.
	 *
	 * {@inheritDoc}
	 */
	public function __toString(): string
	{
		return $this->value;
	}

	/**
	 * Serializes the file reference to its raw string value for JSON encoding.
	 *
	 * Without this, `json_encode($validatedFile)` would silently produce `{}`
	 * because all properties are private. This ensures the correct raw value
	 * (file ID or TempFS path) is used whenever a `ValidatedFile` ends up
	 * inside a JSON payload.
	 */
	#[Override]
	public function jsonSerialize(): string
	{
		return $this->value;
	}

	/**
	 * Creates a ValidatedFile representing a persisted OZFile record.
	 *
	 * Use this inside trusted internal code when you need to assign a known
	 * file ID to an entity column without going through a file upload:
	 *
	 * ```php
	 * $entity->image_file_id = ValidatedFile::forFileID($oz_file->getID());
	 * ```
	 *
	 * **Security**: never build this from raw user-supplied input unless
	 * you have verified that the user is authorized to access the file.
	 *
	 * @param string $id the numeric file ID (value of `OZFile->getID()`)
	 */
	public static function forFileID(string $id): static
	{
		return new self($id, false);
	}

	/**
	 * Creates a ValidatedFile from an already-loaded {@see OZFile} entity.
	 *
	 * Use this when you already hold an `OZFile` instance and need to assign it
	 * to a file column without going through an upload. The entity is cached on
	 * the returned instance so the first call to {@see loadFile()} is free.
	 *
	 * ```php
	 * $oz = OZFilesQuery::find('42'); // however you loaded it
	 * $entity->image_file_id = ValidatedFile::forFile($oz);
	 * ```
	 *
	 * **Security**: the caller is responsible for verifying that the current
	 * user is authorized to access `$file` before using this factory.
	 *
	 * @param OZFile $file a saved OZFile entity (`isSaved()` must be true)
	 *
	 * @throws InvalidArgumentException when `$file` has not yet been saved to the database
	 */
	public static function forFile(OZFile $file): static
	{
		if (!$file->isSaved()) {
			throw new InvalidArgumentException('Cannot create ValidatedFile for an OZFile that has not been saved to the database.');
		}

		/** @var string $id */
		$id = $file->getID();
		$s  = new static($id, false);

		$s->_loaded_file = $file;

		return $s;
	}

	/**
	 * Creates a ValidatedFile representing a file in TempFS.
	 *
	 * Called by {@see TypeFile::computeTemporaryUploadedFiles()} after moving
	 * an uploaded file into the temporary directory. Consumer code reads the
	 * path via {@see getPath()} to perform further processing (e.g. re-upload
	 * to permanent storage).
	 *
	 * @param string $path the absolute TempFS path of the uploaded file
	 */
	public static function forTempPath(string $path): static
	{
		return new self($path, true);
	}

	/**
	 * Returns true when this represents a temporary TempFS file (not yet saved to permanent storage).
	 *
	 * @return bool
	 */
	public function isTemporary(): bool
	{
		return $this->temporary;
	}

	/**
	 * Returns true when this represents a file that has been persisted in the OZFile DB table.
	 *
	 * @return bool
	 */
	public function isPersisted(): bool
	{
		return !$this->temporary;
	}

	/**
	 * Returns the numeric file ID for persisted files.
	 *
	 * @return string the OZFile primary key (same value as `OZFile->getID()`)
	 *
	 * @throws LogicException when called on a temporary file
	 */
	public function getId(): string
	{
		if ($this->temporary) {
			throw new LogicException(\sprintf('Cannot call %s on a temporary ValidatedFile; use getPath() instead.', __METHOD__));
		}

		return $this->value;
	}

	/**
	 * Returns the absolute TempFS path for temporary files.
	 *
	 * @return string the absolute path of the uploaded file in TempFS
	 *
	 * @throws LogicException when called on a persisted file
	 */
	public function getPath(): string
	{
		if (!$this->temporary) {
			throw new LogicException(\sprintf('Cannot call %s on a persisted ValidatedFile; use getId() instead.', __METHOD__));
		}

		return $this->value;
	}

	/**
	 * Lazily loads and caches the corresponding OZFile DB record.
	 *
	 * Returns `null` for temporary files (they have no DB record yet) or when
	 * no record with the stored ID exists.
	 *
	 * The result is cached on the instance after the first call, so repeated
	 * calls do not issue additional DB queries.
	 *
	 * ```php
	 * $oz_file = $validatedFile->loadFile(); // DB query on first call
	 * $oz_file = $validatedFile->loadFile(); // returns cached result
	 * ```
	 *
	 * @return null|OZFile the OZFile entity, or null for temp files / missing records
	 */
	public function loadFile(): ?OZFile
	{
		if ($this->temporary) {
			return null;
		}

		if (null === $this->_loaded_file) {
			$this->_loaded_file = FS::getFileByID($this->value);
		}

		return $this->_loaded_file;
	}
}
