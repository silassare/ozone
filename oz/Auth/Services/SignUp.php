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

namespace OZONE\Core\Auth\Services;

use Gobl\DBAL\Exceptions\DBALUniqueViolationException;
use Gobl\DBAL\Table;
use Gobl\DBAL\Types\Exceptions\TypesInvalidValueException;
use Gobl\Exceptions\GoblException;
use Gobl\ORM\ORM;
use Override;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\AuthUsersRepository;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Providers\EmailOwnershipVerificationProvider;
use OZONE\Core\Auth\Providers\PhoneOwnershipVerificationProvider;
use OZONE\Core\Auth\VerificationPolicy;
use OZONE\Core\Exceptions\InternalErrorException;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Forms\Form;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class SignUp.
 *
 * A visitor creates an auth user of a type `OZ_AUTH_USER_SIGN_UP_TYPES` lists (`user` by default),
 * named by the form's `auth_user_type`. The form is the default type's table's, by the columns'
 * short names: every table made by `makeAuthUserTable()` has the same standard columns, so the
 * same form fills any of them, and a value for a column the chosen type's table lacks is refused.
 */
final class SignUp extends Service
{
	public const string ROUTE_SIGN_UP = 'oz:signup';

	/**
	 * @throws GoblException
	 * @throws InternalErrorException
	 */
	public function actionSignUp(RouteInfo $ri): void
	{
		$form  = $ri->getCleanFormData()->getData();
		$type  = (string) ($form[AuthUsers::FIELD_AUTH_USER_TYPE] ?? AuthUsersRepository::DEFAULT_AUTH_USER_TYPE);
		$table = self::signUpTable($type);

		unset($form[AuthUsers::FIELD_AUTH_USER_TYPE]);

		if (null === $table) {
			throw new InvalidFormException('OZ_ERROR_USER_TYPE_NOT_ALLOWED', [
				'field' => AuthUsers::FIELD_AUTH_USER_TYPE,
			]);
		}

		foreach ($form as $field => $value) {
			if (null !== $value && !$table->hasColumn((string) $field)) {
				throw new InvalidFormException(null, ['field' => $field]);
			}
		}

		// By the columns' full names (the form says `email`): a verified identifier set below
		// replaces the one the form gave, instead of naming the column a second time.
		$data  = ORM::formByFullNames($table, \array_filter($form, static fn ($v): bool => null !== $v));
		$email = $table->getColumnOrFail(AuthUserInterface::IDENTIFIER_TYPE_EMAIL)->getFullName();
		$phone = $table->getColumnOrFail(AuthUserInterface::IDENTIFIER_TYPE_PHONE)->getFullName();

		// What this auth user type must prove (`oz.auth.verification`): null when nothing, and then
		// the identifiers are the ones the form carries, for the project to verify later.
		$provider = VerificationPolicy::resolve($ri, VerificationPolicy::SIGN_UP, $type);

		if ($provider instanceof EmailOwnershipVerificationProvider) {
			$data[$email] = $provider->getEmail();
		} elseif ($provider instanceof PhoneOwnershipVerificationProvider) {
			$data[$phone] = $provider->getPhone();
		} elseif (null !== $provider) {
			// A project named its own provider: it knows what the authorization proves, so it says
			// which identifier it fills through the payload of the authorization.
			foreach ($provider->getPayload() as $column => $value) {
				if (\in_array($column, [$email, $phone], true)) {
					$data[$column] = $value;
				}
			}
		}

		try {
			/** @var AuthUserInterface $user */
			$user = ORM::ctrl($table)->addItem($data);
		} catch (DBALUniqueViolationException $e) {
			throw self::takenField($e, $data) ?? $e;
		}

		if (null !== $provider) {
			VerificationPolicy::consume($provider);
		}

		$ri->getContext()
			->getAuthUsers()
			->logUserIn($user);

		$this->json()
			->setDone('OZ_USER_SIGN_UP_SUCCESS')
			->setData($user);
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$route = $router
			->post('/signup', static function (RouteInfo $r) {
				$s = new self($r);
				$s->actionSignUp($r);

				return $s->respond();
			})
			->name(self::ROUTE_SIGN_UP)
			// The default type's table's columns (its private `data` and `is_valid` are not in it),
			// and the type to sign up as.
			->form(static function () {
				$form = Form::fromTable(
					self::signUpTable(AuthUsersRepository::DEFAULT_AUTH_USER_TYPE)
						?? throw new InternalErrorException('The default auth user type has no auth user table.')
				);

				$form->string(AuthUsers::FIELD_AUTH_USER_TYPE);

				return $form;
			});

		// The route asks for a verification at its door only when every user type needs one; otherwise
		// the rule of the type being signed up is what `actionSignUp()` enforces.
		if (VerificationPolicy::alwaysRequired(VerificationPolicy::SIGN_UP)) {
			$route->withAuthorization(...VerificationPolicy::allProviders(VerificationPolicy::SIGN_UP));
		}
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('AuthUsers', 'Auth users endpoints.');
		$doc->addOperationFromRoute(
			self::ROUTE_SIGN_UP,
			'POST',
			'Sign Up',
			[
				$doc->success(['user' => $doc->object([], ['description' => 'The newly created auth user.'])]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'AuthUsers.signUp',
				'description' => 'Create a new auth user, of a type `OZ_AUTH_USER_SIGN_UP_TYPES` lists (`'
					. AuthUsers::FIELD_AUTH_USER_TYPE . '`, `' . AuthUsersRepository::DEFAULT_AUTH_USER_TYPE
					. '` by default). Requires prior email or phone ownership verification where the type'
					. ' asks for it.',
			]
		);
	}

	/**
	 * The table a visitor may sign up into for a type: one `OZ_AUTH_USER_SIGN_UP_TYPES` lists, made
	 * by `makeAuthUserTable()`; null otherwise.
	 */
	private static function signUpTable(string $type): ?Table
	{
		$allowed = (array) Settings::get('oz.auth.users', 'OZ_AUTH_USER_SIGN_UP_TYPES', []);

		if (!\in_array($type, $allowed, true)) {
			return null;
		}

		$table = db()->getTableByMorphType($type);

		return $table && AuthUsersRepository::isTableSupported($table) ? $table : null;
	}

	/**
	 * The field error of a sign-up that met a unique key: another sign-up with the same email (or
	 * phone) passed the column's own "not registered" check at the same time, and was written first.
	 * That check, run again, now refuses the value, and its error is the field's, as the form gives it.
	 *
	 * @param DBALUniqueViolationException $e    the key met
	 * @param array<array-key, mixed>      $data the form, by the columns' full names
	 */
	private static function takenField(DBALUniqueViolationException $e, array $data): ?InvalidFormException
	{
		$columns = $e->getColumns();

		if (1 !== \count($columns)) {
			return null;
		}

		$column = $e->getTable()->getColumnOrFail($columns[0]);

		try {
			$column->getType()->validate($data[$column->getFullName()] ?? null);
		} catch (TypesInvalidValueException $refused) {
			return new InvalidFormException($refused->getMessage(), $refused->getData(true), $e)
				->mergeData(['field' => $column->getName()]);
		}

		return null;
	}
}
