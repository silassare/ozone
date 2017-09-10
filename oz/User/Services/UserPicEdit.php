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

	use OZONE\OZ\Core\OZoneAssert;
	use OZONE\OZ\Core\OZoneService;
	use OZONE\OZ\Core\OZoneDb;
	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\FS\OZonePPicUtils;
	use OZONE\OZ\User\OZoneUserUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class UserPicEdit
	 *
	 * @package OZONE\OZ\User\Services
	 */
	class UserPicEdit extends OZoneService
	{

		/**
		 * UserPicEdit constructor.
		 */
		public function __construct()
		{
			parent::__construct();
		}

		/**
		 * {@inheritdoc}
		 *
		 * @throws \Exception
		 * @throws \OZONE\OZ\Exceptions\OZoneInvalidFormException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnauthorizedActionException
		 * @throws \OZONE\OZ\Exceptions\OZoneUnverifiedUserException
		 */
		public function execute($request = [])
		{
			OZoneAssert::assertUserVerified();
			OZoneAssert::assertForm($request, ['forid']);

			$label = 'file';

			if (isset($request['label'])) {
				$label = $request['label'];
			}

			OZoneAssert::assertAuthorizeAction(in_array($label, ['file', 'fid', 'def']));

			$forid = $request['forid'];

			$uid        = OZoneSessions::get('ozone_user:data:user_id');
			$file_label = 'OZ_FILE_LABEL_USER_PPIC';
			$msg        = 'OZ_PROFILE_PIC_CHANGED';

			OZoneAssert::assertAuthorizeAction($uid === $forid);

			$user_obj = OZoneUserUtils::getUserObject($uid);

			$ppic_obj = new OZonePPicUtils($uid);

			if ($label === 'fid') {
				OZoneAssert::assertForm($request, ['fid', 'fkey']);
				$picid = $ppic_obj->fromFid($request, $request['fid'], $request['fkey'], $file_label);
			} elseif ($label === 'file') {
				OZoneAssert::assertForm($_FILES, ['photo']);
				$picid = $ppic_obj->fromUploadedFile($request, $_FILES['photo'], $file_label);
			} else {//def
				$picid = $ppic_obj->toDefault();
				$msg   = 'OZ_PROFILE_PIC_SET_TO_DEFAULT';
			}

			$user_obj->updateUserData('user_picid', $picid);

			self::$resp->setDone($msg)
					   ->setData(OZoneDb::maskColumnsName(['user_picid' => $picid], ['user_picid']));
		}
	}