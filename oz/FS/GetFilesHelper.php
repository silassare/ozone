<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS;

use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Db\OZUsersQuery;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Router\RouteInfo;

\defined('OZ_SELF_SECURITY_CHECK') || die;

class GetFilesHelper
{
	/**
	 * Process the 'get file' request
	 *
	 * @param \OZONE\OZ\Router\RouteInfo $r
	 *
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public static function process(RouteInfo $r)
	{
		$params_required = ['oz_file_id', 'oz_file_key', 'oz_file_quality'];
		$args            = $r->getArgs();

		Assert::assertForm($args, $params_required, new NotFoundException());

		$file_id       = $args['oz_file_id'];
		$file_key      = $args['oz_file_key'];
		$file_quality  = (int) ($args['oz_file_quality']);
		$file_src      = null;
		$access        = SettingsManager::get('oz.files', 'OZ_GET_FILE_ACCESS_LEVEL');
		$users_manager = $r->getContext()
						   ->getUsersManager();

		if ($access === 'users') {
			$picid = $file_id . '_' . $file_key;

			if (!$users_manager->userVerified() && !self::isLastUserPic($r->getContext(), $picid)) {
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

		if (empty($file_src) || !\file_exists($file_src) || !\is_file($file_src) || !\is_readable($file_src)) {
			// NOTE keep this log, helpful for debugging
			$real_error = new InternalErrorException('The file is in the database but not at specified path.', [
				'file_id'  => $file_id,
				'file_src' => $file_src,
			]);

			throw new NotFoundException(null, null, $real_error);
		}

		$path_parts          = \pathinfo($file_src);
		$real_file_extension = \strtolower($path_parts['extension']);
		$file_mime           = FilesUtils::extensionToMimeType($real_file_extension);
		$file_name           = SettingsManager::get('oz.files', 'OZ_GET_FILE_NAME');

		// when the request provide an extension and the extension
		// does not match the file extension
		// we just return a not found
		if (isset($args['oz_file_extension'])) {
			$request_file_extension = \strtolower($args['oz_file_extension']);

			if ($request_file_extension !== $real_file_extension) {
				throw new NotFoundException();
			}
		}

		if (\strlen($file_name)) {
			$file_name = \str_replace([
				'{oz_file_id}',
				'{oz_file_quality}',
				'{oz_file_extension}',
			], [
				$file_id,
				$file_quality,
				$real_file_extension,
			], $file_name);
		} else {
			$file_name = $result->getName();
		}

		$fis = new FilesServer();

		return $fis->serve($r->getContext(), [
			'file_src'     => $file_src,
			'file_quality' => $file_quality,
			'file_name'    => $file_name,
			'file_mime'    => $file_mime,
		]);
	}

	/**
	 * Checks if a given picid is the current user profile picid
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $picid
	 *
	 * @throws \Gobl\DBAL\Exceptions\DBALException
	 * @throws \Gobl\ORM\Exceptions\ORMException
	 *
	 * @return bool
	 */
	private static function isLastUserPic(Context $context, $picid)
	{
		$uid = $context->getSession()
					   ->get('ozone_user:id');

		if (!empty($uid)) {
			$uq   = new OZUsersQuery();
			$user = $uq->filterById($uid)
					   ->find(1)
					   ->fetchClass();

			if ($user) {
				return $user->getPicid() === $picid;
			}
		}

		return false;
	}
}
