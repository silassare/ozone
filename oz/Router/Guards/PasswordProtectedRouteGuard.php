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

use OZONE\Core\Columns\Types\TypePassword;
use OZONE\Core\Crypt\Password;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Router\RouteInfo;

/**
 * Class PasswordProtectedRouteGuard.
 */
class PasswordProtectedRouteGuard extends AbstractRouteGuard
{
	private string $password_hash;
	private FormData $form_data;

	/**
	 * PasswordProtectedRouteGuard constructor.
	 *
	 * @param string $password           The password
	 * @param bool   $is_hashed_password Set to true if the password is already hashed
	 */
	public function __construct(string $password, protected bool $is_hashed_password = false)
	{
		$this->password_hash = $is_hashed_password ? $password : Password::hash($password);
		$this->form_data     = new FormData();
	}

	/**
	 * {@inheritDoc}
	 */
	public function getRules(): array
	{
		return [
			'password_hash' => $this->password_hash,
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function fromRules(array $rules): self
	{
		return new self($rules['password_hash'], true);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws \OZONE\Core\Exceptions\ForbiddenException
	 * @throws \OZONE\Core\Exceptions\InvalidFormException
	 * @throws \OZONE\Core\Exceptions\UnauthorizedActionException
	 */
	public function checkAccess(RouteInfo $ri): void
	{
		$context = $ri->getContext();
		$form    = new Form();
		$form->field($this->password_field)
			->type(new TypePassword())
			->required();

		$fd = $this->requireForm($context, $form);

		$known_password_hash = $this->password_hash;
		$req_password        = $fd->get($this->password_field);

		if (!Password::verify($req_password, $known_password_hash)) {
			// invalid password
			throw new ForbiddenException();
		}

		$this->form_data->merge($fd);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getFormData(): FormData
	{
		return $this->form_data;
	}
}
