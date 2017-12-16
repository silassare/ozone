<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\FS\Services;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Core\URIHelper;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\FS\FilesUtils;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	class GetFiles extends BaseService
	{
		/**
		 * GetFiles constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \OZONE\OZ\Exceptions\ForbiddenException
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\InvalidFormException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		public function execute(array $request = [])
		{
			$src          = null;
			$result       = null;
			$params       = ['id', 'key', 't'];
			$file_uri_reg = SettingsManager::get('oz.files', 'OZ_FILE_URI_EXTRA_REG');

			$extra_ok = URIHelper::parseUriExtra($file_uri_reg, $params, $request);

			if (!$extra_ok) {
				throw new NotFoundException();
			}

			Assert::assertForm($request, $params, new NotFoundException());
			$access = SettingsManager::get('oz.files', 'OZ_FILE_ACCESS_LEVEL');

			// we directly set the behavior in oz.services.list
			// for 'session' and 'any' access level

			if ($access === 'users') {
				$picid = $request['id'] . '_' . $request['key'];
				if (!UsersUtils::userVerified() AND !$this->isLastUserPic($picid)) {
					throw new ForbiddenException();
				}
			}

			$this->labelGetFile($request);
		}

		/**
		 * process the 'get file' request
		 *
		 * @param $request
		 *
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\NotFoundException
		 */
		private function labelGetFile($request)
		{
			$file_id  = $request['id'];
			$file_key = $request['key'];
			$thumb    = intval($request['t']);
			$src      = null;

			$result = FilesUtils::getFileWithId($file_id, $file_key);

			if (!$result) {
				throw new NotFoundException();
			}

			if ($thumb > 0) {
				// if the client wants a thumbnails and we do not have it
				// then we notify it; the client should be able to handle this situation
				if (empty($result->getThumb())) {
					throw new NotFoundException();
				}

				$src = $result->getThumb();
			} else {
				$src = $result->getPath();
			}

			if (empty($src) || !file_exists($src) || !is_file($src) || !is_readable($src)) {
				// NOTE keep this log, helpful for debugging
				oz_logger(sprintf('The file_id: "%s" exist in the database but not at "%s".', $file_id, $src));

				throw new NotFoundException();
			}

			$path_parts = pathinfo($src);
			$ext        = strtolower($path_parts['extension']);

			$file_mime = FilesUtils::extensionToMimeType($ext);
			$file_name = SettingsManager::get('oz.files', 'OZ_FILE_DOWNLOAD_NAME');

			if (strlen($file_name)) {
				$file_name = str_replace(['{oz_file_id}', '{oz_thumbnail}', '{oz_file_extension}'], [
					$file_id,
					$thumb,
					$ext
				], $file_name);
			} else {
				$file_name = $result->getName();
			}

			(new FilesServer())->execute([
				'src'       => $src,
				'thumb'     => $thumb,
				'file_name' => $file_name,
				'file_mime' => $file_mime
			]);
		}

		/**
		 * Checks if a given picid is the current user profile picid
		 *
		 * @param string $picid the file picture file id
		 *
		 * @return bool
		 */
		private function isLastUserPic($picid)
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