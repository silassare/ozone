<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\FS\Services\FilesServer;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class GetFilesHelper
	{
		/**
		 * Process the 'get file' request
		 *
		 * @param $request
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 * @throws \Exception
		 */
		public static function serveFile($request)
		{
			$params_required = ['oz_file_id', 'oz_file_key', 'oz_file_quality'];

			Assert::assertForm($request, $params_required, new NotFoundException());

			$file_id      = $request['oz_file_id'];
			$file_key     = $request['oz_file_key'];
			$file_quality = intval($request['oz_file_quality']);
			$file_src     = null;

			$access = SettingsManager::get('oz.files', 'OZ_GET_FILE_ACCESS_LEVEL');

			// we directly set the behavior in oz.services.list
			// for 'session' and 'any' access level
			if ($access === 'users') {
				$picid = $file_id . '_' . $file_key;
				if (!UsersUtils::userVerified() AND !self::isLastUserPic($picid)) {
					throw new ForbiddenException();
				}
			}

			$result = FilesUtils::getFileWithId($file_id, $file_key);

			if (!$result) {
				throw new NotFoundException();
			}

			if ($file_quality > 0) {
				// if the client wants a thumbnails and we do not have it
				// then we notify it; the client should be able to handle this situation
				if (empty($result->getThumb())) {
					throw new NotFoundException();
				}

				$file_src = $result->getThumb();
			} else {
				$file_src = $result->getPath();
			}

			if (empty($file_src) || !file_exists($file_src) || !is_file($file_src) || !is_readable($file_src)) {
				// NOTE keep this log, helpful for debugging
				oz_logger(sprintf('The file_id: "%s" is in the database but not at "%s".', $file_id, $file_src));

				throw new NotFoundException();
			}

			$path_parts          = pathinfo($file_src);
			$real_file_extension = strtolower($path_parts['extension']);
			$file_mime           = FilesUtils::extensionToMimeType($real_file_extension);
			$file_name           = SettingsManager::get('oz.files', 'OZ_GET_FILE_NAME');

			// when the request provide an extension and the extension
			// does not match the file extension
			// we just return a not found
			if (isset($request['oz_file_extension'])) {
				$request_file_extension = strtolower($request['oz_file_extension']);

				if ($request_file_extension !== $real_file_extension) {
					throw new NotFoundException();
				}
			}

			if (strlen($file_name)) {
				$file_name = str_replace([
					'{oz_file_id}',
					'{oz_file_quality}',
					'{oz_file_extension}'
				], [
					$file_id,
					$file_quality,
					$real_file_extension
				], $file_name);
			} else {
				$file_name = $result->getName();
			}

			(new FilesServer())->execute([
				'file_src'     => $file_src,
				'file_quality' => $file_quality,
				'file_name'    => $file_name,
				'file_mime'    => $file_mime
			]);
		}

		/**
		 * Checks if a given picid is the current user profile picid
		 *
		 * @param string $picid the file picture file id
		 *
		 * @return bool
		 * @throws \Exception
		 */
		private static function isLastUserPic($picid)
		{
			$uid = SessionsData::get('ozone_user:id');

			if (!empty($uid)) {
				$u_table = new OZUsersQuery();
				$u       = $u_table->filterById($uid)
								   ->find(1)
								   ->fetchClass();
				if ($u) {
					return ($u->getPicid() === $picid);
				}
			}

			return false;
		}
	}