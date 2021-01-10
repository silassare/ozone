<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\Authenticator;

use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Exceptions\NotFoundException;

/**
 * Class QRCodeHelper
 */
final class QRCodeHelper
{
	/**
	 * Gets qr-code image uri for authentication
	 *
	 * @param \OZONE\OZ\Core\Context                $context
	 * @param \OZONE\OZ\Authenticator\Authenticator $auth
	 *
	 * @throws \Exception
	 *
	 * @return array the qr-code info
	 */
	public static function getQrCode(Context $context, Authenticator $auth)
	{
		$generated   = $auth->generate(1)
							->getGenerated();
		$qr_code_key = \md5($auth->getRef() . \microtime());

		$f_name      = SettingsManager::get('oz.files', 'OZ_QR_CODE_FILE_NAME');
		$qr_code_src = \str_replace(['{oz_qr_code_key}'], [$qr_code_key], $f_name);

		$context->getSession()
				->set('qr_code_cfg.' . $qr_code_key, $generated);

		return ['qr_code_src' => $qr_code_src, 'qr_code_key' => $qr_code_key];
	}

	/**
	 * create the qrcode image
	 *
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $qr_code_key
	 *
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public static function generateQrCodeImage(Context $context, $qr_code_key)
	{
		self::cleanExpired($context);

		$data     = null;
		$response = $context->getResponse();

		if (\is_string($qr_code_key)) {
			$data = $context->getSession()
							->get('qr_code_cfg.' . $qr_code_key);
		}

		if (empty($data)) {
			throw new NotFoundException();
		}

		// TODO QRcode
		// The qrcode content should be a link
		// then the scanner makes a request back to the qrcode to get action done OR retrieve data
		/*$content = json_encode([
			"auth_label"     => $data['auth_label'],
			"auth_expire"    => $data['auth_expire'],
			"auth_token"     => $data['auth_token'],
			"auth_for" => $data['auth_for']
		]);*/

		// \QRcode::png($content, 'php://output', QR_ECLEVEL_H, 20);

		return $response;
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\BaseException when the qrcode was not found or is not valid
	 */
	private static function cleanExpired(Context $context)
	{
		$session = $context->getSession();
		$list    = $session->get('qr_code_cfg');
		$now     = \time();

		if (\is_array($list)) {
			foreach ($list as $key => $value) {
				if ($value['auth_expire'] <= $now) {
					$session->remove('qr_code_cfg.' . $key);
				}
			}
		}
	}
}
