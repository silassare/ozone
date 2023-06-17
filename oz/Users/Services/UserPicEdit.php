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

namespace OZONE\Core\Users\Services;

use Exception;
use OZONE\Core\App\Service;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\FS\PPicUtils;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class UserPicEdit.
 */
class UserPicEdit extends Service
{
	public const ROUTE_PIC_EDIT = 'oz:user-pic-edit';

	/**
	 * @throws Exception
	 */
	public function actionPicEdit(RouteInfo $ri): void
	{
		$context = $ri->getContext();
		$request = $context->getRequest();
		$params  = $request->getUnsafeFormData();
		$um      = $context->getUsers();
		$for_id  = $ri->getParam('user_id');

		$um->assertUserVerified();

		$kind = $params['kind'] ?? 'file';

		if (!\in_array($kind, ['file', 'file_id', 'def'], true)) {
			throw new UnauthorizedActionException();
		}

		$user       = $context->user();
		$uid        = $user->getID();
		$file_label = 'OZ_FILE_LABEL_USER_PPIC';
		$msg        = 'OZ_PROFILE_PIC_CHANGED';

		if ($uid !== $for_id) {
			throw new UnauthorizedActionException();
		}

		$pu = new PPicUtils($uid);

		if ('file_id' === $kind) {
			if (empty($params['file_id']) || empty($params['file_key'])) {
				new InvalidFormException();
			}
			$pic_id = $pu->fromFileID($params['file_id'], $params['file_key'], $params->getData(), $file_label);
		} elseif ('file' === $kind) {
			$files = $request->getUploadedFiles();
			if (empty($files['photo'])) {
				new InvalidFormException();
			}
			$pic_id = $pu->fromUploadedFile($files['photo'], $params->getData(), $file_label);
		} else {// def
			$pic_id = null;
			$msg    = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
		}

		$user->setPic($pic_id)
			->save();

		$this->getJSONResponse()
			->setDone($msg)
			->setData($user);
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->patch('/users/:user_id/pic/edit', function (RouteInfo $ri) {
			$s = new self($ri);
			$s->actionPicEdit($ri);

			return $s->respond();
		})
			->name(self::ROUTE_PIC_EDIT)
			->param('user_id', '\d+');
	}
}
