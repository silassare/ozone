<?php

/**
 * Auto generated file,
 *
 * INFO: you are free to edit it,
 * but make sure to know what you are doing.
 *
 * Proudly With: gobl v1.5.0
 * Time: 1617030519
 */

namespace OZONE\OZ\Db;

use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Db\Base\OZFile as BaseOZFile;
use OZONE\OZ\FS\FilesUtils;
use OZONE\OZ\FS\PathUtils;
use OZONE\OZ\Utils\StringUtils;

/**
 * Class OZFile
 */
class OZFile extends BaseOZFile
{
	/**
	 * @inheritdoc
	 */
	public function getPath()
	{
		return $this->toAbsolutePath(parent::getPath());
	}

	/**
	 * @inheritdoc
	 */
	public function setPath($path)
	{
		return parent::setPath($this->toRelativePath($path));
	}

	/**
	 * @inheritdoc
	 */
	public function getThumb()
	{
		return $this->toAbsolutePath(parent::getThumb());
	}

	/**
	 * @inheritdoc
	 */
	public function setThumb($thumb)
	{
		return parent::setThumb($this->toRelativePath($thumb));
	}

	/**
	 * @inheritdoc
	 * @throws \Exception
	 */
	public function save()
	{
		if (!$this->getId()) {
			$key = Hasher::genFileKey($this->getPath());
			$this->setKey($key);
		}

		return parent::save();
	}

	/**
	 * Clone the current ozone file
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 * @throws \Exception when trying to clone unsaved file
	 */
	public function cloneFile()
	{
		if (!$this->isSaved()) {
			throw new Exception('You cannot clone unsaved file.');
		}

		$f = new static();
		$f->hydrate($this->asArray(false));

		$f->setId(null);// force new file id
		$f->setClone($this->getId());

		if (!$this->getOrigin()) {// first level clone
			$f->setOrigin($this->getId());
		}

		return $f;
	}

	/**
	 * Clone helper.
	 *
	 * @return \OZONE\OZ\Db\OZFile
	 * @throws \Exception
	 */
	public function __clone()
	{
		return $this->cloneFile();
	}

	/**
	 * Returns file relative path: relative to the current users files directory
	 *
	 * @param string $path the absolute file path
	 *
	 * @return string
	 */
	private function toRelativePath($path)
	{
		$root = FilesUtils::getUsersFilesRootDirectory();

		return StringUtils::removePrefix(PathUtils::normalize($path), $root);
	}

	/**
	 * Returns file absolute path
	 *
	 * @param string $path the relative file path
	 *
	 * @return string
	 */
	private function toAbsolutePath($path)
	{
		$path = PathUtils::normalize($path);
		$root = FilesUtils::getUsersFilesRootDirectory();

		if (!empty($path) and !is_int(strpos($path, $root))) {
			return $root . $path;
		}

		return $path;
	}
}
