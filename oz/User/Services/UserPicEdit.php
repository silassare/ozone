<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\User\Services;

	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\FS\PPicUtils;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class UserPicEdit
	 *
	 * @package OZONE\OZ\User\Services
	 */
	class UserPicEdit extends BaseService
	{
		/**
		 * {@inheritdoc}
		 */
		public function execute(array $request = [])
		{
			Assert::assertUserVerified();
			Assert::assertForm($request, ['for_id']);

			$label = 'file';

			if (isset($request['label'])) {
				$label = $request['label'];
			}

			Assert::assertAuthorizeAction(in_array($label, ['file', 'file_id', 'def']));

			$for_id = $request['for_id'];

			$user_obj   = UsersUtils::getCurrentUserObject();
			$uid        = $user_obj->getId();
			$file_label = 'OZ_FILE_LABEL_USER_PPIC';
			$msg        = 'OZ_PROFILE_PIC_CHANGED';

			Assert::assertAuthorizeAction($uid === $for_id);

			$ppic_obj = new PPicUtils($uid);

			if ($label === 'file_id') {
				Assert::assertForm($request, ['file_id', 'file_key']);
				$picid = $ppic_obj->fromFileId($request, $request['file_id'], $request['file_key'], $file_label);
			} elseif ($label === 'file') {
				Assert::assertForm($_FILES, ['photo']);
				$picid = $ppic_obj->fromUploadedFile($request, $_FILES['photo'], $file_label);
			} else {//def
				$picid = PPicUtils::getDefault();
				$msg   = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
			}

			$user_obj->setPicid($picid)
					 ->save();

			$this->getResponseHolder()->setDone($msg)
					   ->setData($user_obj->asArray());
		}
	}