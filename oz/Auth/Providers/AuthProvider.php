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

namespace OZONE\OZ\Auth\Providers;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\DBAL\Exceptions\DBALException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\OZ\Auth\Auth;
use OZONE\OZ\Auth\AuthCredentials;
use OZONE\OZ\Auth\AuthScope;
use OZONE\OZ\Auth\AuthSecretType;
use OZONE\OZ\Auth\AuthState;
use OZONE\OZ\Auth\Interfaces\AuthCredentialsInterface;
use OZONE\OZ\Auth\Interfaces\AuthProviderInterface;
use OZONE\OZ\Auth\Interfaces\AuthScopeInterface;
use OZONE\OZ\Auth\Traits\AuthProviderEventTrait;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\Hasher;
use OZONE\OZ\Core\JSONResponse;
use OZONE\OZ\Db\OZAuth;
use OZONE\OZ\Exceptions\InvalidFormException;
use OZONE\OZ\Exceptions\RuntimeException;
use Throwable;

/**
 * Class AuthProvider.
 */
abstract class AuthProvider implements AuthProviderInterface
{
	use AuthProviderEventTrait;

	protected JSONResponse $json_response;
	protected AuthScopeInterface $scope;

	protected AuthCredentialsInterface $credentials;

	/**
	 * AuthProvider constructor.
	 *
	 * @param \OZONE\OZ\Core\Context                            $context
	 * @param null|\OZONE\OZ\Auth\Interfaces\AuthScopeInterface $scope
	 */
	public function __construct(protected Context $context, ?AuthScopeInterface $scope = null)
	{
		$code_length        = (int) Configs::get('oz.auth', 'OZ_AUTH_CODE_LENGTH');
		$code_use_alpha_num = (bool) Configs::get('oz.auth', 'OZ_AUTH_CODE_USE_ALPHA_NUM');

		$this->scope         = $scope ?? new AuthScope();
		$this->json_response = new JSONResponse();
		$this->credentials   = new AuthCredentials($this->context, $code_length, $code_use_alpha_num);
	}

	/**
	 * AuthProvider destructor.
	 */
	public function __destruct()
	{
		unset($this->context);
	}

	/**
	 * Gets context.
	 *
	 * @return \OZONE\OZ\Core\Context
	 */
	public function getContext(): Context
	{
		return $this->context;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCredentials(): AuthCredentialsInterface
	{
		return $this->credentials;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getScope(): AuthScopeInterface
	{
		return $this->scope;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getJSONResponse(): JSONResponse
	{
		return $this->json_response;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function authorize(AuthSecretType $type): void
	{
		$ref    = $this->credentials->getReference();
		$secret = match ($type) {
			AuthSecretType::CODE  => $this->credentials->getCode(),
			AuthSecretType::TOKEN => $this->credentials->getToken(),
		};

		if (empty($ref)) {
			throw new InvalidFormException('OZ_AUTH_MISSING_REF');
		}

		if (empty($secret)) {
			throw new InvalidFormException('OZ_AUTH_MISSING_SECRET');
		}

		$auth = Auth::getRequiredByRef($ref);

		$this->scope = $this->scope::from($auth);

		$try_max    = $auth->try_max; // 0 means unlimited try
		$count      = $auth->try_count + 1;
		$remainder  = $try_max - $count;
		$is_code    = AuthSecretType::CODE === $type;
		$known_hash = $is_code ? $auth->code_hash : $auth->token_hash;

		// checks if auth process has expired
		if ($auth->expire <= \time()) {
			$this->save($auth->setState(AuthState::REFUSED->value));
			$this->onExpired($auth);
		} elseif ((0 === $try_max || $remainder >= 0) && \hash_equals($known_hash, Hasher::hash64($secret))) {
			// we don't exceed the auth_try_max and the token/code is valid
			$this->save($auth->setState(AuthState::AUTHORIZED->value));
			$this->onAuthorized($auth);
		} elseif (0 === $try_max || $remainder <= 0) {
			// it is our last tentative or we already exceed auth_try_max
			$this->save($auth->setState(AuthState::REFUSED->value));
			$this->onTooMuchRetry($auth);
		} else {
			// we have another chance
			$this->save($auth->setTryCount($auth->try_count + 1));

			if (AuthSecretType::CODE === $type) {
				$this->onInvalidCode($auth);
			} else {
				$this->onInvalidToken($auth);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function getState(): AuthState
	{
		$ref = $this->credentials->getReference();

		return AuthState::from(Auth::getRequiredByRef($ref)
			->getState());
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(): self
	{
		$code_hash   = Hasher::hash64($this->credentials->newCode());
		$token_hash  = Hasher::hash64($this->credentials->newToken());
		$ref         = $this->credentials->getReference();
		$refresh_key = $this->credentials->getRefreshKey();
		$expire      = \time() + $this->scope->getLifetime();

		if (Auth::getByRef($ref)) {
			throw new RuntimeException('An auth ref conflict occurred, newly generated auth ref already in use.', [
				'auth_ref' => $ref,
			]);
		}

		try {
			$auth = new OZAuth();
			$auth->setRef($ref)
				->setRefreshKey($refresh_key)
				->setProvider(static::getName())
				->setLabel($this->scope->getLabel())
				->setFor($this->scope->getValue())
				->setTryMax($this->scope->getTryMax())
				->setLifetime($this->scope->getLifetime())
				->setCodeHash($code_hash)
				->setTokenHash($token_hash)
				->setTryCount(0)
				->setExpire((string) $expire)
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save authorization data.', null, $t);
		}

		$this->onInit($auth);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 */
	public function refresh(bool $re_authorize = true): self
	{
		$ref  = $this->credentials->getReference();
		$auth = Auth::getRequiredByRef($ref);

		$this->scope = $this->scope::from($auth);

		if (!\hash_equals($auth->getRefreshKey(), $this->credentials->getRefreshKey())) {
			$this->onInvalidRefreshKey($auth);

			return $this;
		}

		$expire = \time() + $this->scope->getLifetime();

		$code_hash  = Hasher::hash64($this->credentials->newCode());
		$token_hash = Hasher::hash64($this->credentials->newToken());

		$auth->setCodeHash($code_hash)
			->setTokenHash($token_hash)
			->setTryMax($this->scope->getTryMax())
			->setTryCount($this->scope->getLifetime())
			->setTryCount(0)
			->setExpire((string) $expire);

		if ($re_authorize) {
			$auth->setState(AuthState::PENDING->value);
		}

		$this->save($auth);

		$this->onRefresh($auth);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public function cancel(): self
	{
		$ref  = $this->credentials->getReference();
		$auth = Auth::getRequiredByRef($ref);

		$this->scope = $this->scope::from($auth);

		try {
			$auth->selfDelete();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to cancel authorization process.', null, $t);
		}

		$this->onCancel($auth);

		return $this;
	}

	/**
	 * Save authorisation process into the database.
	 *
	 * @param \OZONE\OZ\Db\OZAuth $auth
	 */
	protected function save(OZAuth $auth): void
	{
		try {
			$auth->setUpdatedAT((string) \time())
				->setData($this->scope->toArray())
				->save();
		} catch (DBALException|ORMException|CRUDException $e) {
			throw new RuntimeException('Unable to save authorization process data.', null, $e);
		}
	}
}
