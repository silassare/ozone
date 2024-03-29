<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS;

use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Http\UploadedFile;

class PPicUtils
{
	/** @var int|string */
	private $uid;

	/**
	 * PPicUtils constructor.
	 *
	 * @param int|string $uid the user id
	 */
	public function __construct($uid)
	{
		$this->uid = $uid;
	}

	/**
	 * Sets a profile picture with a given file id and key of an existing file
	 *
	 * @param int|string $file_id    the file id
	 * @param string     $file_key   the file key
	 * @param array      $coordinate the crop zone coordinate
	 * @param string     $file_label the file log label
	 *
	 * @throws \Exception
	 *
	 * @return string the profile picid
	 */
	public function fromFileId($file_id, $file_key, array $coordinate, $file_label = 'OZ_FILE_LABEL_PPIC')
	{
		$f = FilesUtils::getFileWithId($file_id, $file_key);

		Assert::assertAuthorizeAction($f);

		$clone = $f->cloneFile();
		$clone->setUserId($this->uid)
			  ->setLabel($file_label);

		// each file clone should have its own thumbnail
		// because crop zone coordinates may be different from a clone to another

		$user_dir          = FilesUtils::getUserRootDirectory($this->uid);
		$gen_info          = FilesUtils::genNewFileInfo($user_dir, $clone->getName(), $clone->getType());
		$thumb_destination = $gen_info['thumbnail'];

		$this->makeProfilePic($clone->getPath(), $thumb_destination, $coordinate);

		$clone->setThumb($thumb_destination)
			  ->save();

		return $clone->getId() . '_' . $clone->getKey();
	}

	/**
	 * Sets a profile picture from uploaded file
	 *
	 * @param \OZONE\OZ\Http\UploadedFile $uploaded_file the uploaded file
	 * @param array                       $coordinate    the crop zone coordinate
	 * @param string                      $file_label    the file log label
	 *
	 * @throws \Exception
	 *
	 * @return string the profile picid
	 */
	public function fromUploadedFile(UploadedFile $uploaded_file, array $coordinate, $file_label = 'OZ_FILE_LABEL_PPIC')
	{
		$user_dir   = FilesUtils::getUserRootDirectory($this->uid);
		$upload_obj = new FilesUploadHandler();

		$f = $upload_obj->moveUploadedFile($uploaded_file, $user_dir);

		Assert::assertAuthorizeAction($f, $upload_obj->lastErrorMessage());

		$f->setUserId($this->uid)
		  ->setLabel($file_label);

		if ($f->getClone()) {
			// the uploaded file is an alias file
			// we shouldn't overwrite existing thumbnail
			$user_dir          = FilesUtils::getUserRootDirectory($this->uid);
			$gen_info          = FilesUtils::genNewFileInfo($user_dir, $f->getName(), $f->getType());
			$thumb_destination = $gen_info['thumbnail'];

			$this->makeProfilePic($f->getPath(), $thumb_destination, $coordinate);
			$f->setThumb($thumb_destination);
		} else {
			// overwrite existing thumbnail
			$this->makeProfilePic($f->getPath(), $f->getThumb(), $coordinate);
		}

		// don't forget to save
		$f->save();

		return $f->getId() . '_' . $f->getKey();
	}

	/**
	 * Make a thumbnail of the current file with a given crop zone coordinates, for profile pic
	 *
	 * @param string $source      the source file path
	 * @param string $destination the profile pic destination
	 * @param array  $coordinates the crop zone coordinates
	 *
	 * @throws \Exception
	 */
	private function makeProfilePic($source, $destination, array $coordinates)
	{
		$img_utils_obj     = new ImagesUtils($source);
		$size_x            = $size_y = SettingsManager::get('oz.users', 'OZ_PPIC_MIN_SIZE');
		$quality           = 100; // jpeg image quality: 0 to 100
		$clean_coordinates = null;

		if (!empty($coordinates) && isset($coordinates['x'], $coordinates['y'], $coordinates['w'], $coordinates['h'])) {
			$clean_coordinates = [
				'x' => (int) ($coordinates['x']),
				'y' => (int) ($coordinates['y']),
				'w' => (int) ($coordinates['w']),
				'h' => (int) ($coordinates['h']),
			];
		}

		if ($img_utils_obj->load()) {
			if (null === $clean_coordinates) {
				$img_utils_obj->cropAndSave($destination, $quality, $size_x, $size_y);
			} else {
				$img_utils_obj->cropAndSave($destination, $quality, $size_x, $size_y, $coordinates, false);
			}
		} else { /*this file is not a valid image*/
			throw new InternalErrorException('OZ_IMAGE_NOT_VALID');
		}
	}

	/**
	 * Gets the default profile picid
	 *
	 * @return string the profile picid
	 */
	public static function getDefault()
	{
		return '0_0';
	}
}
