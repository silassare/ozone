<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator;

	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SettingsManager;
	use OZONE\OZ\Exceptions\NotFoundException;
	use OZONE\OZ\Utils\StringUtils;

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
		 * Gets qr-code image uri for authentication
		 *
		 * @return array the qr-code info
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \Exception
		 */
		public function getQrCode()
		{
			$auth        = $this->auth;
			$generated   = $auth->generate(1)
								->getGenerated();
			$label       = $auth->getLabel();
			$for_value   = $auth->getForValue();
			$qr_code_key = md5($label . $for_value . microtime());

			$f_name      = SettingsManager::get('oz.files', 'OZ_QR_CODE_FILE_NAME');
			$qr_code_src = str_replace(['{oz_qr_code_key}'], [$qr_code_key], $f_name);

			SessionsData::set('_qr_code_cfg_:' . $qr_code_key, $generated);

			return ['qr_code_src' => $qr_code_src, 'qr_code_key' => $qr_code_key];
		}

		/**
		 * @throws \Exception
		 */
		private static function cleanExpired()
		{
			$list = SessionsData::get('_qr_code_cfg_');
			$now  = time();

			if (is_array($list)) {
				foreach ($list as $key => $value) {
					if ($value['auth_expire'] <= $now) {
						SessionsData::remove('_qr_code_cfg_:' . $key);
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
		 * @throws \Exception
		 */
		public static function serveQrCodeImage($qr_code_key)
		{
			self::cleanExpired();

			$data = null;

			if (is_string($qr_code_key)) {
				$data = SessionsData::get('_qr_code_cfg_:' . $qr_code_key);
			}

			if (empty($data)) {
				throw new NotFoundException();
			}

			$content = json_encode([
				"auth_label"     => $data['auth_label'],
				"auth_expire"    => $data['auth_expire'],
				"auth_token"     => $data['auth_token'],
				"auth_for_value" => $data['auth_for_value']
			]);

			// \QRcode::png($content, 'php://output', QR_ECLEVEL_H, 20);
		}

		/**
		 * Generate regexp used to match QRCode file URI.
		 *
		 * @param array &$fields
		 *
		 * @return string
		 * @throws \OZONE\OZ\Exceptions\InternalErrorException
		 * @throws \OZONE\OZ\Exceptions\RuntimeException
		 */
		public static function genQRCodeURIRegExp(array &$fields)
		{
			$format = SettingsManager::get("oz.files", "OZ_QR_CODE_URI_EXTRA_FORMAT");

			$parts = [
				"oz_qr_code_key" => "([a-z0-9]{32})"
			];

			return StringUtils::stringFormatToRegExp($format, $parts, $fields);
		}
	}