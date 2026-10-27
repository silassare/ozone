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

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Enums\VerificationPurpose;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormData;
use OZONE\Core\Users\UsersRepository;

/**
 * Proves that whoever answers owns an email or a phone, for a purpose the owner is told.
 */
abstract class OwnershipVerificationProvider extends AuthorizationProvider
{
	/**
	 * @param Context             $context    the request
	 * @param string              $identifier the email or the phone verified
	 * @param VerificationPurpose $purpose    what the verification is for
	 * @param null|string         $user_type  the type of the account an account recovery is for
	 * @param null|string         $label      the label shown to the owner
	 */
	final public function __construct(
		Context $context,
		protected string $identifier,
		protected VerificationPurpose $purpose = VerificationPurpose::CHECK,
		protected ?string $user_type = null,
		protected ?string $label = null
	) {
		parent::__construct($context);

		// How long the owner has to answer, which is the purpose's business: a sign-up may be
		// confirmed much later than a password reset.
		$lifetimes = (array) Settings::get('oz.auth', 'OZ_AUTH_VERIFICATION_LIFETIMES', []);
		$lifetime  = $lifetimes[$purpose->value] ?? null;

		if (\is_int($lifetime) && $lifetime > 0) {
			$this->scope->setLifetime($lifetime);
		}
	}

	/**
	 * Adds what a request starting a verification says besides the identifier: its `purpose`
	 * (`check` when not given), and for an account recovery the `auth_user_type` of the account
	 * (`user` when not given).
	 */
	public static function purposeFields(Form $form): void
	{
		$form->enum('purpose', VerificationPurpose::class);
		$form->string(AuthUsers::FIELD_AUTH_USER_TYPE);
	}

	/**
	 * The verification a request asks for, with the fields {@see self::purposeFields()} added.
	 */
	public static function fromForm(Context $context, string $identifier, FormData $fd): static
	{
		$purpose = $fd->get('purpose');
		$purpose = $purpose instanceof VerificationPurpose ? $purpose : VerificationPurpose::CHECK;

		$user_type = null;

		if (VerificationPurpose::ACCOUNT_RECOVERY === $purpose) {
			$given     = $fd->get(AuthUsers::FIELD_AUTH_USER_TYPE);
			$user_type = \is_string($given) && '' !== $given ? $given : UsersRepository::DEFAULT_USER_TYPE;
		}

		return new static($context, $identifier, $purpose, $user_type);
	}

	/**
	 * The email or the phone verified.
	 */
	public function getIdentifier(): string
	{
		return $this->identifier;
	}

	/**
	 * What the verification is for.
	 */
	public function getPurpose(): VerificationPurpose
	{
		return $this->purpose;
	}

	/**
	 * The type of the account an account recovery is for, null for another purpose.
	 */
	public function getUserType(): ?string
	{
		return $this->user_type;
	}

	/**
	 * The identifier type it verifies, `email` or `phone`: its key in the payload, and in a user
	 * selector.
	 */
	abstract public static function identifierType(): string;

	#[Override]
	public static function resolve(Context $context, OZAuth $auth): static
	{
		$payload    = $auth->getPayload();
		$identifier = $payload[static::identifierType()] ?? null;

		if (empty($identifier) || !\is_string($identifier)) {
			throw new RuntimeException(
				\sprintf('Missing "%s" in payload.', static::identifierType())
			)->suspectObject($payload);
		}

		// An authorization saved before purposes existed proves ownership and nothing more.
		$purpose   = VerificationPurpose::tryFrom((string) ($payload['purpose'] ?? '')) ?? VerificationPurpose::CHECK;
		$user_type = $payload['user_type'] ?? null;
		$label     = $payload['label'] ?? null;

		return new static(
			$context,
			$identifier,
			$purpose,
			\is_string($user_type) ? $user_type : null,
			\is_string($label) ? $label : null
		);
	}

	#[Override]
	public function getPayload(): array
	{
		return [
			static::identifierType() => $this->identifier,
			'purpose'                => $this->purpose->value,
			'user_type'              => $this->user_type,
			'label'                  => $this->label,
		];
	}

	#[Override]
	protected function onInit(OZAuth $auth): void
	{
		parent::onInit($auth);

		$this->send();

		// Added to what `generate()` published (the reference and the refresh key), never replacing it.
		$this->json_response->setDone()
			->setDataKey('first', true);
	}

	#[Override]
	protected function onRefresh(OZAuth $auth): void
	{
		parent::onRefresh($auth);

		$this->send();

		$this->json_response->setDone()
			->setDataKey('first', false);
	}

	/**
	 * What the messages of this purpose are given: the credentials, the label, the identifier, and the
	 * lang key of each of their texts.
	 *
	 * @param list<string> $parts the texts, as {@see VerificationPurpose::text()} names them
	 *
	 * @return array<string, mixed>
	 */
	protected function messageData(array $parts): array
	{
		$texts = [];

		foreach ($parts as $part) {
			$texts[\strtolower($part)] = $this->purpose->text($part);
		}

		return $this->credentials->toArray() + [
			'label'      => $this->label,
			'identifier' => $this->identifier,
			'texts'      => $texts,
		];
	}

	/**
	 * Sends the secret to the email or the phone.
	 */
	abstract protected function send(): void;
}
