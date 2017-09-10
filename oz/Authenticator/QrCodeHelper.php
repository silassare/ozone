<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\OZoneSessions;
	use OZONE\OZ\Core\OZoneSettings;
	use OZONE\OZ\Exceptions\OZoneNotFoundException;

	require_once OZ_OZONE_DIR . 'oz_vendors' . DS . 'phpqrcode' . DS . 'qrlib.php';

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class QrCodeHelper
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class QrCodeHelper implements AuthenticatorHelper
	{

		private $auth = null;

		/**
		 * CaptchaCodeHelper constructor.
		 *
		 * @param \OZONE\OZ\Authenticator\Authenticator $auth
		 */
		public function __construct(Authenticator $auth)
		{
			$this->auth = $auth;
		}

		/**
		 * get captcha image uri for authentication
		 *
		 * @return array the qrcode info
		 */
		public function getQrCode()
		{
			$auth   = $this->auth;
			$expire = 3600 * 24 * 7;

			$generated = $auth->generate(1, $expire)
							  ->getGenerated();
			$label     = $auth->getLabel();
			$forValue  = $auth->getForValue();
			$qrCodeKey = md5($label . $forValue . microtime());

			$fname     = OZoneSettings::get('oz.files', 'OZ_QRCODE_FILE_NAME');
			$qrCodeSrc = str_replace(['{oz_qrcode_key}'], [$qrCodeKey], $fname);

			OZoneSessions::set('_qrcode_cfg_:' . $qrCodeKey, $generated);

			return ['qrCodeSrc' => $qrCodeSrc, 'qrCodeKey' => $qrCodeKey];
		}

		private static function cleanExpired()
		{
			$list = OZoneSessions::get('_qrcode_cfg_');
			$now  = time();

			if (is_array($list)) {
				foreach ($list as $key => $value) {
					if ($value['authExpire'] <= $now) {
						OZoneSessions::remove('_qrcode_cfg_:' . $key);
					}
				}
			}
		}

		/**
		 * draw the qrcode image
		 *
		 * @param string $qrCodeKey the captcha image key
		 *
		 * @throws \OZONE\OZ\Exceptions\OZoneNotFoundException when captcha image key is not valid
		 */
		public static function serveQrCodeImage($qrCodeKey)
		{
			self::cleanExpired();

			$data = null;

			if (is_string($qrCodeKey)) {
				$data = OZoneSessions::get('_qrcode_cfg_:' . $qrCodeKey);
			}

			if (empty($data)) {
				throw new OZoneNotFoundException();
			}

			$label    = $data['authLabel'];
			$token    = $data['authToken'];
			$forValue = $data['authForValue'];
			$content  = "$forValue:$label:$token";
			oz_logger($content);

			\QRcode::png($content, 'php://output', QR_ECLEVEL_H, 20);
		}
	}