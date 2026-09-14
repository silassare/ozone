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
use OZONE\Core\FS\TempFS;
use PHPUtils\FS\PathUtils;
use PHPUtils\Str;

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
 * - **Temporary**: backed by a file in TempFS. Created when `TypeFile` is
 *   configured as `->temp(true)`. The raw value is the portable
 *   `{tmpfs_ref}/{name}` reference, resolved to a path only when
 *   {@see self::getPath()} is called -- an absolute path would not survive a
 *   deploy that changes the project directory, nor reach another instance.
 *   A value written before this became the rule is an absolute path and is
 *   still read as one.
 *
 * Usage:
 *
 * ```php
 * // Force-assign a known persisted file ID (internal/trusted code only):
 * $entity->image_file_id = ValidatedFile::forFileID('42');
 *
 * // Assign a validated temp file:
 * $entity->tmp_avatar = ValidatedFile::forTempFile($tmp_fs->getRef(), 'abc.jpg');
 *
 * // After loading from DB, lazily fetch the OZFile record:
 * $vf = $entity->getAvatarFileId(); // returns ValidatedFile
 * $oz = $vf->loadFile();            // returns OZFile|null (cached)
 * ```
 */
final class ValidatedFile implements JsonSerializable
{
	/** @var null|OZFile lazily loaded OZFile record (null for temp files or before first load) */
	private ?OZFile $loaded_file = null;

	/**
	 * Private constructor - use the named factories {@see forFileID()} and {@see forTempPath()}.
	 *
	 * @param string $value     the raw value: a numeric file ID or a TempFS reference
	 * @param bool   $temporary true when the value is a TempFS reference, false when it is a file ID
	 */
	private function __construct(
		private readonly string $value,
		private readonly bool $temporary
	) {}

	/**
	 * Returns the raw value (file ID or TempFS path) as a string.
	 *
	 * This is what {@see Types\TypeFile::phpToDb()} stores in the database column
	 * via `(string) $validatedFile`: a file ID, or a `{tmpfs_ref}/{name}` reference.
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
	 * (file ID or TempFS reference) is used whenever a `ValidatedFile` ends up
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
			throw new InvalidArgumentException(\sprintf(
				'Cannot create a %s for an %s that is not saved yet.',
				self::class,
				OZFile::class
			));
		}

		/** @var string $id */
		$id = $file->getID();
		$s  = new self($id, false);

		$s->loaded_file = $file;

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
	 * The stored value is the `{tmpfs_ref}/{name}` reference, not a path: it must
	 * stay valid after a deploy that moves the project directory, and on any
	 * instance that can reach the temp directory.
	 *
	 * @param string $tmp_ref the TempFS ref ({@see TempFS::getRef()})
	 * @param string $name    the name of the file in the ref directory
	 */
	public static function forTempFile(string $tmp_ref, string $name): static
	{
		TempFS::assertSegment($tmp_ref);
		TempFS::assertSegment($name);

		return new self($tmp_ref . '/' . $name, true);
	}

	/**
	 * Creates a ValidatedFile from a stored temporary value.
	 *
	 * Used when reading a `->temp()` column back from the database. A value
	 * written before references replaced paths is an absolute path, and is kept
	 * as such.
	 *
	 * @param string $value a `{tmpfs_ref}/{name}` reference, or a legacy absolute path
	 */
	public static function forTempValue(string $value): static
	{
		return new self($value, true);
	}

	/**
	 * Creates a ValidatedFile from an absolute TempFS path.
	 *
	 * The path is converted to a `{tmpfs_ref}/{name}` reference when it points
	 * inside the temp directory of the current scope; prefer
	 * {@see self::forTempFile()}, which needs no conversion. A path outside the
	 * temp directory is stored as is: nothing can resolve it later but the
	 * filesystem it was written on.
	 *
	 * @param string $path the absolute TempFS path of the uploaded file
	 */
	public static function forTempPath(string $path): static
	{
		$path = PathUtils::normalize($path);
		$root = PathUtils::normalize(TempFS::root()->getRoot()) . DS;

		if (\str_starts_with($path, $root)) {
			$value = \str_replace(DS, '/', \substr($path, \strlen($root)));
			$pos   = \strpos($value, '/');

			if (false !== $pos) {
				return self::forTempFile(\substr($value, 0, $pos), \substr($value, $pos + 1));
			}
		}

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
			throw new LogicException(\sprintf(
				'Cannot call %s on a temporary %s; use %s instead.',
				__METHOD__,
				self::class,
				Str::callableName([$this, 'getPath'])
			));
		}

		return $this->value;
	}

	/**
	 * Returns the absolute TempFS path for temporary files.
	 *
	 * The path is resolved from the stored reference at each call, so it follows
	 * the project directory instead of being frozen at validation time. It is
	 * returned whether or not the file is still there: {@see self::isAvailable()}
	 * answers that.
	 *
	 * @return string the absolute path of the uploaded file in TempFS
	 *
	 * @throws LogicException when called on a persisted file
	 */
	public function getPath(): string
	{
		if (!$this->temporary) {
			throw new LogicException(\sprintf(
				'Cannot call %s on a persisted %s; use %s instead.',
				__METHOD__,
				self::class,
				Str::callableName([$this, 'getId'])
			));
		}

		if ($this->isLegacyPath()) {
			return $this->value;
		}

		[$ref, $name] = $this->splitTempValue();

		return TempFS::path($ref, $name);
	}

	/**
	 * Checks that the file this references still exists.
	 *
	 * A temporary file must also still be within the lifetime of its TempFS ref:
	 * the garbage collector may have taken the directory since, and a reference
	 * replayed from a resume entry can outlive it.
	 *
	 * @return bool
	 */
	public function isAvailable(): bool
	{
		if (!$this->temporary) {
			return null !== $this->loadFile();
		}

		if ($this->isLegacyPath()) {
			return \is_file($this->value);
		}

		[$ref, $name] = $this->splitTempValue();

		return TempFS::canUse($ref) && \is_file(TempFS::path($ref, $name));
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

		if (null === $this->loaded_file) {
			$this->loaded_file = FS::getFileByID($this->value);
		}

		return $this->loaded_file;
	}

	/**
	 * Checks whether a temporary value is an absolute path.
	 *
	 * Only values stored before references replaced paths are: a TempFS ref never
	 * starts with a separator or a drive letter.
	 *
	 * @return bool
	 */
	private function isLegacyPath(): bool
	{
		return \str_starts_with($this->value, '/')
			|| \str_starts_with($this->value, '\\')
			|| 1 === \preg_match('~^[A-Za-z]:[\\\/]~', $this->value);
	}

	/**
	 * Splits a temporary value into its TempFS ref and file name.
	 *
	 * @return array{0: string, 1: string}
	 */
	private function splitTempValue(): array
	{
		$pos = \strpos($this->value, '/');

		if (false === $pos) {
			throw new InvalidArgumentException(\sprintf(
				'Malformed temporary %s value: "%s", expected "{tmpfs_ref}/{name}".',
				self::class,
				$this->value
			));
		}

		return [\substr($this->value, 0, $pos), \substr($this->value, $pos + 1)];
	}
}
