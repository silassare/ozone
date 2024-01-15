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

namespace OZONE\Core\Router\Guards;

use Gobl\DBAL\Types\Exceptions\TypesException;
use Gobl\DBAL\Types\Interfaces\TypeInterface;
use Gobl\DBAL\Types\TypeString;
use Gobl\DBAL\Types\Utils\TypeUtils;
use InvalidArgumentException;
use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnauthorizedActionException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\RouteInfo;

/**
 * Class CredentialsRouteGuard.
 */
class CredentialsRouteGuard extends AbstractRouteGuard
{
	private FormData $form_data;

	/**
	 * CredentialsRouteGuard constructor.
	 *
	 * @param array<string,string>                           $credentials        username and password as key value pair
	 * @param bool                                           $is_hashed_password
	 * @param null|\Gobl\DBAL\Types\Interfaces\TypeInterface $username_type
	 * @param null|\Gobl\DBAL\Types\Interfaces\TypeInterface $password_type
	 */
	public function __construct(
		private array $credentials,
		bool $is_hashed_password = false,
		private readonly ?TypeInterface $username_type = null,
		private readonly ?TypeInterface $password_type = null,
	) {
		if (empty($credentials)) {
			throw new InvalidArgumentException('Empty credentials.');
		}

		if (!$is_hashed_password) {
			foreach ($this->credentials as $user_name => $password) {
				if (\is_string($user_name) && \is_string($password)) {
					$this->credentials[$user_name] = Password::hash($password);
				}
			}
		}

		$this->form_data = new FormData();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRules(): array
	{
		return [
			'credentials'   => $this->credentials,
			'username_type' => $this->username_type?->toArray(),
			'password_type' => $this->password_type?->toArray(),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws TypesException
	 */
	public static function fromRules(array $rules): self
	{
		$username_type = $rules['username_type'] ? TypeUtils::getTypeInstance($rules['username_type']['type'], $rules['username_type']) : null;
		$password_type = $rules['password_type'] ? TypeUtils::getTypeInstance($rules['password_type']['type'], $rules['password_type']) : null;

		return new self(
			$rules['credentials'],
			true,
			$username_type,
			$password_type
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws ForbiddenException
	 * @throws InvalidFormException
	 * @throws UnauthorizedActionException
	 */
	public function checkAccess(RouteInfo $ri): void
	{
		$context = $ri->getContext();

		$username_type = $this->username_type ?? (new TypeString())->oneLine();
		$password_type = $this->password_type ?? new TypePassword();
		$credentials   = $this->credentials;

		if (empty($credentials)) {
			throw new ForbiddenException(null, [
				'_reason' => 'No credentials provided.',
			]);
		}

		$form = new Form();
		$form->field($this->username_field)
			->type($username_type)
			->required();
		$form->field($this->password_field)
			->type($password_type)
			->required();

		$clean_form = $this->requireForm($context, $form);

		$req_user     = $clean_form->get($this->username_field);
		$req_password = $clean_form->get($this->password_field);

		if (!isset($credentials[$req_user])) {
			// invalid user
			throw new ForbiddenException();
		}

		$known_password_hash = $credentials[$req_user];

		if (!Password::verify($req_password, $known_password_hash)) {
			// invalid password
			throw new ForbiddenException();
		}

		$this->form_data->set('username', $req_user)
			->set('password', $req_password);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFormData(): FormData
	{
		return $this->form_data;
	}
}
