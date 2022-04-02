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

namespace OZONE\OZ\Users\Services;

use Exception;
use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\Service;
use OZONE\OZ\Exceptions\UnauthorizedActionException;
use OZONE\OZ\FS\PPicUtils;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

/**
 * Class UserPicEdit.
 */
class UserPicEdit extends Service
{
	public const PIC_EDIT_ROUTE = 'oz:user-pic-edit';

	/**
	 * @throws Exception
	 */
	public function actionPicEdit(RouteInfo $ri): void
	{
		$context = $ri->getContext();
		$request = $context->getRequest();
		$params  = $request->getFormData();
		$um      = $context->getUsersManager();
		$for_id  = $ri->getParam('user_id');

		$um->assertUserVerified();

		$label = $params['label'] ?? 'file';

		if (!\in_array($label, ['file', 'file_id', 'def'], true)) {
			throw new UnauthorizedActionException();
		}

		$user       = $um->getCurrentUserObject();
		$uid        = $user->getID();
		$file_label = 'OZ_FILE_LABEL_USER_PPIC';
		$msg        = 'OZ_PROFILE_PIC_CHANGED';

		if ($uid !== $for_id) {
			throw new UnauthorizedActionException();
		}

		$pu = new PPicUtils($uid);

		if ('file_id' === $label) {
			Assert::assertForm($params, ['file_id', 'file_key']);
			$pic_id = $pu->fromFileID($params['file_id'], $params['file_key'], $params, $file_label);
		} elseif ('file' === $label) {
			$files = $request->getUploadedFiles();
			Assert::assertForm($files, ['photo']);
			$pic_id = $pu->fromUploadedFile($files['photo'], $params, $file_label);
		} else {// def
			$pic_id = null;
			$msg    = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
		}

		$user->setPic($pic_id)
			->save();

		$this->getJSONResponse()
			->setDone($msg)
			->setData($user->toArray());
	}

	/**
	 * {@inheritDoc}
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->patch('/users/:user_id/pic/edit', function (RouteInfo $r) {
			$context = $r->getContext();
			$s       = new self($context);
			$s->actionPicEdit($r);

			return $s->respond();
		})
			->name(self::PIC_EDIT_ROUTE)
			->param('user_id', '\d+');
	}
}
