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

namespace OZONE\OZ\FS;

use Exception;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Db\OZFile;
use OZONE\OZ\Exceptions\RuntimeException;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use OZONE\OZ\Http\UploadedFile;

// TODO remove this dependency, I don't think we need it anymore
// Image manipulation should be made on the client side or on dedicated server,
// there are many useful tool to tod that

/**
 * Class PPicUtils.
 *
 * @deprecated
 */
class PPicUtils
{
	/** @var string */
	private string $uid;

	/**
	 * PPicUtils constructor.
	 *
	 * @param string $uid the user id
	 */
	public function __construct(string $uid)
	{
		$this->uid = $uid;
	}

	/**
	 * Sets a profile picture with a given file id and key of an existing file.
	 *
	 * @param string $file_id    the file id
	 * @param string $file_key   the file key
	 * @param array  $coordinate the crop zone coordinate
	 * @param string $file_label the file log label
	 *
	 * @return string the profile pic_id
	 *
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws Exception
	 */
	public function fromFileID(
		string $file_id,
		string $file_key,
		array $coordinate,
		string $file_label = 'OZ_FILE_LABEL_USER_PIC'
	): string {
		$f = FilesUtils::getFileWithId($file_id);

		if (!$f || $f->getKey() !== $file_key) {
			throw new UnauthorizedActionException();
		}

		$clone = $f->cloneFile();
		$clone->setUserID($this->uid)
			->setLabel($file_label);

		// each file clone should have its own thumbnail
		// because crop zone coordinates may be different from a clone to another

		$user_dir          = FilesUtils::getUserRootDirectory($this->uid);
		$gen_info          = FilesUtils::genNewFileInfo($user_dir, $clone->getName(), $clone->getMimeType());
		$thumb_destination = $gen_info['thumbnail'];

		$this->makeProfilePic($clone, $thumb_destination, $coordinate);

		$clone->save();

		return $clone->getID();
	}

	/**
	 * Sets a profile picture from uploaded file.
	 *
	 * @param \OZONE\OZ\Http\UploadedFile $uploaded_file the uploaded file
	 * @param array                       $coordinate    the crop zone coordinate
	 * @param string                      $file_label    the file log label
	 *
	 * @return string the profile picid
	 *
	 * @throws Exception
	 */
	public function fromUploadedFile(
		UploadedFile $uploaded_file,
		array $coordinate,
		string $file_label = 'OZ_FILE_LABEL_USER_PIC'
	): string {
		$user_dir   = FilesUtils::getUserRootDirectory($this->uid);
		$upload_obj = new FilesUploadHandler();

		$f = $upload_obj->moveUploadedFile($uploaded_file, $user_dir);

		if (!$f) {
			throw new UnauthorizedActionException($upload_obj->lastErrorMessage());
		}

		$f->setUserID($this->uid)
			->setLabel($file_label);

		if ($f->getCloneID()) {
			// the uploaded file is an alias file
			// we shouldn't overwrite existing thumbnail
			$user_dir          = FilesUtils::getUserRootDirectory($this->uid);
			$gen_info          = FilesUtils::genNewFileInfo($user_dir, $f->getName(), $f->getMimeType());
			$thumb_destination = $gen_info['thumbnail'];

			$this->makeProfilePic($f->getPath(), $thumb_destination, $coordinate);
			$f->setThumb($thumb_destination);
		} else {
			// overwrite existing thumbnail
			$this->makeProfilePic($f->getPath(), $f->getThumb(), $coordinate);
		}

		// don't forget to save
		$f->save();

		return $f->getID() . '_' . $f->getKey();
	}

	/**
	 * Make a thumbnail of the current file with a given crop zone coordinates, for profile pic.
	 *
	 * @param \OZONE\OZ\Db\OZFile $source      the source file
	 * @param string              $destination the profile pic destination
	 * @param array               $coordinates the crop zone coordinates
	 *
	 * @throws Exception
	 */
	private function makeProfilePic(OZFile $source, string $destination, array $coordinates): void
	{
		$img_utils_obj     = new ImagesUtils($source);
		$size_x            = $size_y = Configs::get('oz.users', 'OZ_USER_PIC_MIN_SIZE');
		$quality           = 100; // jpeg image quality: 0 to 100
		$clean_coordinates = null;

		if (!empty($coordinates) && isset($coordinates['x'], $coordinates['y'], $coordinates['w'], $coordinates['h'])) {
			$clean_coordinates = [
				'x' => (int) $coordinates['x'],
				'y' => (int) $coordinates['y'],
				'w' => (int) $coordinates['w'],
				'h' => (int) $coordinates['h'],
			];
		}

		if ($img_utils_obj->load()) {
			if (null === $clean_coordinates) {
				$img_utils_obj->cropAndSave($destination, $quality, $size_x, $size_y);
			} else {
				$img_utils_obj->cropAndSave($destination, $quality, $size_x, $size_y, $coordinates, false);
			}
		} else { /* this file is not a valid image */
			throw new RuntimeException('OZ_IMAGE_NOT_VALID');
		}
	}
}
