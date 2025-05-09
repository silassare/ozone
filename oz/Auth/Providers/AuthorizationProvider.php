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
use Gobl\Exceptions\GoblException;
use Gobl\ORM\Exceptions\ORMException;
use OZONE\Core\App\Context;
use OZONE\Core\App\JSONResponse;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Auth;
use OZONE\Core\Auth\AuthorizationCredentials;
use OZONE\Core\Auth\AuthorizationScope;
use OZONE\Core\Auth\Enums\AuthorizationSecretType;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Auth\Interfaces\AuthorizationCredentialsInterface;
use OZONE\Core\Auth\Interfaces\AuthorizationProviderInterface;
use OZONE\Core\Auth\Interfaces\AuthorizationScopeInterface;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Traits\AuthProviderEventsTrait;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Exceptions\UnauthorizedException;
use OZONE\Core\Utils\Hasher;
use Throwable;

/**
 * Class AuthorizationProvider.
 */
abstract class AuthorizationProvider implements AuthorizationProviderInterface
{
	use AuthProviderEventsTrait;

	protected JSONResponse $json_response;
	protected AuthorizationScopeInterface $scope;

	protected AuthorizationCredentialsInterface $credentials;

	/**
	 * AuthProvider constructor.
	 */
	public function __construct(Context $context)
	{
		$code_length        = (int) Settings::get('oz.auth', 'OZ_AUTH_CODE_LENGTH');
		$code_use_alpha_num = (bool) Settings::get('oz.auth', 'OZ_AUTH_CODE_USE_ALPHA_NUM');

		$this->scope         = new AuthorizationScope();
		$this->json_response = new JSONResponse();
		$this->credentials   = new AuthorizationCredentials($context, $code_length, $code_use_alpha_num);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getCredentials(): AuthorizationCredentialsInterface
	{
		return $this->credentials;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getScope(): AuthorizationScopeInterface
	{
		return $this->scope;
	}

	/**
	 * {@inheritDoc}
	 */
	public function setScope(AuthorizationScopeInterface $scope): self
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
	 * @throws InvalidFormException
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 * @throws GoblException
	 */
	public function authorize(AuthorizationSecretType $type): void
	{
		$ref    = $this->credentials->getReference();
		$secret = match ($type) {
			AuthorizationSecretType::CODE  => $this->credentials->getCode(),
			AuthorizationSecretType::TOKEN => $this->credentials->getToken(),
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
		$is_code    = AuthorizationSecretType::CODE === $type;
		$known_hash = $is_code ? $auth->code_hash : $auth->token_hash;

		// checks if auth process has expired
		if ($auth->expire <= \time()) {
			$this->save($auth->setState(AuthorizationState::REFUSED->value));
			$this->onExpired($auth);
		} elseif ((0 === $try_max || $remainder >= 0) && \hash_equals($known_hash, $this->hash($secret))) {
			// we don't exceed the auth_try_max and the token/code is valid
			$this->save($auth->setState(AuthorizationState::AUTHORIZED->value));
			$this->onAuthorized($auth);
		} elseif (0 === $try_max || $remainder <= 0) {
			// it is our last tentative or we already exceed auth_try_max
			$this->save($auth->setState(AuthorizationState::REFUSED->value));
			$this->onTooMuchRetry($auth);
		} else {
			// we have another chance
			$this->save($auth->setTryCount($auth->try_count + 1));

			if (AuthorizationSecretType::CODE === $type) {
				$this->onInvalidCode($auth);
			} else {
				$this->onInvalidToken($auth);
			}
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function getState(): AuthorizationState
	{
		$ref = $this->credentials->getReference();

		return Auth::getRequired($ref)
			->getState();
	}

	/**
	 * {@inheritDoc}
	 */
	public function generate(?AuthUserInterface $user = null): self
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
				->setPermissions($this->scope->getAccessRight()->toArray());

			if (null !== $user) {
				$auth->setOwnerType($user->getAuthUserType())
					->setOwnerId($user->getAuthIdentifier());
			}

			$auth->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save auth entity.', null, $t);
		}

		$this->onInit($auth);

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 * @throws InvalidFormException
	 * @throws GoblException
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
				$auth->setState(AuthorizationState::PENDING->value);
			}

			$this->save($auth);

			$this->onRefresh($auth);
		}

		return $this;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws NotFoundException
	 * @throws UnauthorizedException
	 */
	public function cancel(): self
	{
		$ref  = $this->credentials->getReference();
		$auth = Auth::getRequired($ref);

		$this->scope = $this->scope::from($auth);

		try {
			$auth->selfDelete();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to cancel auth process.', null, $t);
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
	 * Save auth entity into the database.
	 *
	 * @param OZAuth $auth
	 *
	 * @throws GoblException
	 */
	protected function save(OZAuth $auth): void
	{
		try {
			$auth->setUpdatedAT((string) \time())
				->setPermissions($this->scope->getAccessRight()->toArray())
				->save();
		} catch (CRUDException|ORMException $e) {
			throw new RuntimeException('Unable to save auth entity data.', null, $e);
		}
	}
}
