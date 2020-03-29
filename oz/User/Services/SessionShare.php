<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\User\Services;

use Exception;
use Gobl\DBAL\Rule;
use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\BaseService;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Session;
use OZONE\OZ\Core\SessionDataStore;
use OZONE\OZ\Db\OZSessionsQuery;
use OZONE\OZ\Db\OZUsersQuery;
use OZONE\OZ\Exceptions\ForbiddenException;
use OZONE\OZ\Router\RouteInfo;
use OZONE\OZ\Router\Router;

\defined('OZ_SELF_SECURITY_CHECK') || die;

/**
 * Class SessionShare
 */
final class SessionShare extends BaseService
{
	/**
	 * {@inheritdoc}
	 */
	public static function registerRoutes(Router $router)
	{
		$router->get('/oz-session-share', function (RouteInfo $r) {
			$context  = $r->getContext();
			$token  = $context->getRequest()
								->getFormField('token', null);

			$instance = new static($context);

			if (null !== $token) {
				$instance->actionCheck($context, $token);
			} else {
				$instance->actionCreate($context);
			}

			return $instance->respond();
		});
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 *
	 * @throws \OZONE\OZ\Exceptions\UnverifiedUserException
	 */
	public function actionCreate(Context $context)
	{
		$users_manager = $context->getUsersManager();

		$users_manager->assertUserVerified();

		$data = [
			'token' => $users_manager->getCurrentSessionToken(),
			'uid'   => $users_manager->getCurrentUserId(),
		];

		$this->getResponseHolder()
			 ->setDone()
			 ->setData(['token' => self::encode($data)]);
	}

	/**
	 * @param \OZONE\OZ\Core\Context $context
	 * @param string                 $data
	 *
	 * @throws \Exception
	 */
	public function actionCheck(Context $context, $data)
	{
		// And yes! user sent us a form
		// so we check that the form is valid.
		$context->getUsersManager()
				->logUserOut();

		$data = self::decode($data);

		Assert::assertForm($data, ['token', 'uid'], new ForbiddenException('OZ_ERROR_INVALID_ACCOUNT_AUTH'));

		$verified = false;
		$user     = null;
		$token    = $data['token'];
		$uid      = $data['uid'];

		$sq      = new OZSessionsQuery();
		$session = $sq->filterByToken($token)
					  ->filterByUserId($uid)
					  ->filterByExpire(\time(), Rule::OP_GT)
					  ->find(1)
					  ->fetchClass();

		$uq = new OZUsersQuery();

		if (
			$session && $user = $uq->filterById($uid)
									->find(1)
									->fetchClass()
		) {
			$decoded = Session::decode($session->getData());

			if (\is_array($decoded)) {
				$data_store = new SessionDataStore($decoded);
				$verified   = $data_store->get('ozone_user:verified');
			}
		}

		if (!$verified) {
			throw new ForbiddenException('OZ_ERROR_INVALID_ACCOUNT_AUTH');
		}

		$context->getUsersManager()
				->logUserIn($user);

		$this->getResponseHolder()
			 ->setDone('OZ_USER_ONLINE')
			 ->setData($user->asArray());
	}

	/**
	 * @param array $data
	 *
	 * @return string
	 */
	private static function encode(array $data)
	{
		// TODO Not safe find a way to send a hash
		return \base64_encode(\json_encode($data));
	}

	/**
	 * @param $str
	 *
	 * @return array|false
	 */
	private static function decode($str)
	{
		$data = false;

		try {
			$data = \json_decode(\base64_decode($str), true);
		} catch (Exception $e) {
		}

		return $data;
	}
}
