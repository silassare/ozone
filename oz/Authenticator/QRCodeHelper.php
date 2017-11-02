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

	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Exceptions\NotFoundException;

	require_once OZ_OZONE_DIR . 'oz_vendors' . DS . 'phpqrcode' . DS . 'qrlib.php';

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class QRCodeHelper
	 *
	 * @package OZONE\OZ\Authenticator
	 */
	final class QRCodeHelper implements AuthenticatorHelper
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
		 * Gets captcha image uri for authentication
		 *
		 * @return array the qrcode info
		 */
		public function getQrCode()
		{
			$auth   = $this->auth;
			$expire = 3600 * 24 * 7;

			$generated   = $auth->generate(1, $expire)
								->getGenerated();
			$label       = $auth->getLabel();
			$for_value   = $auth->getForValue();
			$qr_code_key = md5($label . $for_value . microtime());

			$f_name      = SettingsManager::get('oz.files', 'OZ_QR_CODE_FILE_NAME');
			$qr_code_src = str_replace(['{oz_qrcode_key}'], [$qr_code_key], $f_name);

			SessionsData::set('_qrcode_cfg_:' . $qr_code_key, $generated);

			return ['qrCodeSrc' => $qr_code_src, 'qrCodeKey' => $qr_code_key];
		}

		private static function cleanExpired()
		{
			$list = SessionsData::get('_qrcode_cfg_');
			$now  = time();

			if (is_array($list)) {
				foreach ($list as $key => $value) {
					if ($value['authExpire'] <= $now) {
						SessionsData::remove('_qrcode_cfg_:' . $key);
					}
				}
			}
		}

		/**
		 * draw the qrcode image
		 *
		 * @param string $qr_code_key the captcha image key
		 *
		 * @throws \OZONE\OZ\Exceptions\NotFoundException when captcha image key is not valid
		 */
		public static function serveQrCodeImage($qr_code_key)
		{
			self::cleanExpired();

			$data = null;

			if (is_string($qr_code_key)) {
				$data = SessionsData::get('_qrcode_cfg_:' . $qr_code_key);
			}

			if (empty($data)) {
				throw new NotFoundException();
			}

			$label     = $data['authLabel'];
			$token     = $data['authToken'];
			$for_value = $data['authForValue'];
			$content   = "$for_value:$label:$token";
			oz_logger($content);

			\QRcode::png($content, 'php://output', QR_ECLEVEL_H, 20);
		}
	}