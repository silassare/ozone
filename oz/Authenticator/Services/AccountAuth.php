<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of OZone (O'Zone) package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Authenticator\Services;

	use Gobl\DBAL\Rule;
	use OZONE\OZ\Core\Assert;
	use OZONE\OZ\Core\BaseService;
	use OZONE\OZ\Core\RequestHandler;
	use OZONE\OZ\Core\SessionsData;
	use OZONE\OZ\Core\SessionsHandler;
	use OZONE\OZ\Db\OZSessionsQuery;
	use OZONE\OZ\Db\OZUsersQuery;
	use OZONE\OZ\Exceptions\ForbiddenException;
	use OZONE\OZ\User\UsersUtils;

	defined('OZ_SELF_SECURITY_CHECK') or die;

	/**
	 * Class AccountAuth
	 *
	 * @package OZONE\OZ\User\Services
	 */
	final class AccountAuth extends BaseService
	{
		/**
		 * {@inheritdoc}
		 * @throws \Exception
		 */
		public function execute(array $request = [])
		{
			if (isset($request["account"])) {
				$user = self::check($request["account"]);
				$this->getResponseHolder()
					 ->setDone('OZ_USER_ONLINE')
					 ->setData($user->asArray());
			} else {
				$this->getResponseHolder()
					 ->setDone()
					 ->setData([
						 'account' => self::create()
					 ]);
			}
		}

		/**
		 * @throws \Exception
		 */
		public static function create()
		{
			Assert::assertUserVerified();

			$data = [
				"apiKey" => RequestHandler::getCurrentClient(true)->getApiKey(),
				"token"  => UsersUtils::getCurrentSessionToken(),
				"uid"    => UsersUtils::getCurrentUserId()
			];

			return self::encode($data);
		}

		/**
		 * @param string $str
		 *
		 * @return \OZONE\OZ\Db\OZUser
		 * @throws \Exception
		 */
		public static function check($str)
		{
			// And yes! user sent us a form
			// so we check that the form is valid.
			UsersUtils::logUserOut();

			$data = self::decode($str);

			Assert::assertForm($data, ['token', 'apiKey', 'uid'], new ForbiddenException('OZ_ERROR_INVALID_ACCOUNT_AUTH'));

			$verified = false;
			$user     = null;
			$apiKey   = $data['apiKey'];
			$token    = $data['token'];
			$uid      = $data['uid'];

			$s_table = new OZSessionsQuery();
			$session = $s_table->filterByClientApiKey($apiKey)
							   ->filterByToken($token)
							   ->filterByUserId($uid)
							   ->filterByExpire(time(), Rule::OP_GT)
							   ->find(1)
							   ->fetchClass();
			$u_table = new OZUsersQuery();

			if ($session AND $user = $u_table->filterById($uid)->find(1)->fetchClass()) {

				$session_data = SessionsHandler::decodeSessionString($session->getData());
				$verified     = SessionsData::get("ozone_user:verified", $session_data);
			}

			if (!$verified) {
				throw new ForbiddenException('OZ_ERROR_INVALID_ACCOUNT_AUTH');
			}

			UsersUtils::logUserIn($user);

			return $user;
		}

		/**
		 * @param array $data
		 *
		 * @return string
		 */
		static private function encode(array $data)
		{
			return base64_encode(json_encode($data));
		}

		/**
		 * @param $str
		 *
		 * @return array|false
		 */
		static private function decode($str)
		{
			$data = false;

			try {
				$data = json_decode(base64_decode($str), true);
			} catch (\Exception $e) {
				oz_logger($e);
			}

			return $data;
		}
	}