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

namespace OZONE\Core\Auth\Providers;

use Gobl\CRUD\Exceptions\CRUDException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthCredentials;
use OZONE\Core\Auth\AuthScope;
use OZONE\Core\Auth\AuthSecretType;
use OZONE\Core\Auth\AuthState;
use OZONE\Core\Auth\Interfaces\AuthCredentialsInterface;
use OZONE\Core\Auth\Interfaces\AuthProviderInterface;
use OZONE\Core\Auth\Interfaces\AuthScopeInterface;
use OZONE\Core\Auth\Traits\AuthProviderEventTrait;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Utils\Hasher;
use Throwable;

/**
 * Class AuthProvider.
 */
abstract class AuthProvider implements AuthProviderInterface
{
	use AuthProviderEventTrait;

	protected JSONResponse       $json_response;
	protected AuthScopeInterface $scope;

	protected AuthCredentialsInterface $credentials;

	/**
	 * AuthProvider constructor.
	 *
	 * @param \OZONE\Core\App\Context $context
	 */
	public function __construct(Context $context)
	{
		$code_length        = (int) Settings::get('oz.auth', 'OZ_AUTH_CODE_LENGTH');
		$code_use_alpha_num = (bool) Settings::get('oz.auth', 'OZ_AUTH_CODE_USE_ALPHA_NUM');

		$this->scope         = new AuthScope();
		$this->json_response = new JSONResponse();
		$this->credentials   = new AuthCredentials($context, $code_length, $code_use_alpha_num);
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
	public function setScope(AuthScopeInterface $scope): self
	{
		$this->scope = $scope;

		return $this;
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
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
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

		$auth = Auth::getRequired($ref);

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
		} elseif ((0 === $try_max || $remainder >= 0) && \hash_equals($known_hash, $this->hash($secret))) {
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
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function getState(): AuthState
	{
		$ref = $this->credentials->getReference();

		return Auth::getRequired($ref)
			->getState();
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(): self
	{
		$code_hash   = $this->hash($this->credentials->newCode());
		$token_hash  = $this->hash($this->credentials->newToken());
		$ref         = $this->credentials->getReference();
		$refresh_key = $this->credentials->getRefreshKey();
		$expire      = \time() + $this->scope->getLifetime();

		if (Auth::get($ref)) {
			throw new RuntimeException('An auth ref conflict occurred, newly generated auth ref already in use.', [
				OZAuth::COL_REF => $ref,
			]);
		}

		try {
			$auth = new OZAuth();
			$auth->setRef($ref)
				->setRefreshKey($refresh_key)
				->setProvider(static::getName())
				->setLabel($this->scope->getLabel())
				->setPayload($this->getPayload())
				->setTryMax($this->scope->getTryMax())
				->setLifetime($this->scope->getLifetime())
				->setCodeHash($code_hash)
				->setTokenHash($token_hash)
				->setTryCount(0)
				->setExpire((string) $expire)
				->setOptions($this->scope->getOptions())
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
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 */
	public function refresh(bool $re_authorize = true): self
	{
		$ref  = $this->credentials->getReference();
		$auth = Auth::getRequired($ref);

		$this->scope = $this->scope::from($auth);

		if (!\hash_equals($auth->getRefreshKey(), $this->credentials->getRefreshKey())) {
			$this->onInvalidRefreshKey($auth);
		} else {
			$expire = \time() + $this->scope->getLifetime();

			$code_hash  = $this->hash($this->credentials->newCode());
			$token_hash = $this->hash($this->credentials->newToken());

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
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\NotFoundException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function cancel(): self
	{
		$ref  = $this->credentials->getReference();
		$auth = Auth::getRequired($ref);

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
	 * Used to hash a code or a token.
	 *
	 * @param string $secret
	 *
	 * @return string
	 */
	protected function hash(string $secret): string
	{
		return Hasher::hash64($secret);
	}

	/**
	 * Save authorisation process into the database.
	 *
	 * @param \OZONE\Core\Db\OZAuth $auth
	 */
	protected function save(OZAuth $auth): void
	{
		try {
			$auth->setUpdatedAT((string) \time())
				->setOptions($this->scope->getOptions())
				->save();
		} catch (ORMException|CRUDException $e) {
			throw new RuntimeException('Unable to save authorization process data.', null, $e);
		}
	}
}
