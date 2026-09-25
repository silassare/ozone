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

namespace OZONE\Core\Forms;

use LogicException;
use Override;
use PHPUtils\Interfaces\ArrayCapableInterface;
use PHPUtils\Traits\ArrayCapableTrait;

/**
 * Class AsyncValue.
 *
 * A value resolved on the server when a form is validated, from the validation context (a stock
 * level, a list read from the database, a secret). Whether a client ever sees it is decided by the
 * constructor, never by an optional argument, so a secret cannot become public by accident:
 *
 *  - {@see self::secret()}: never leaves the server. A rule set holding one is withheld whole
 *    (`{ref, $secret: true}`), and a client asks the form session's `evaluate` about it.
 *  - {@see self::public()}: the client gets a preview of it, computed without the validation
 *    context, and checks the rule itself (`{$preview: {value: <T>}}`). The server still compares
 *    against the value its factory gives when the form is validated, which may differ.
 *
 * The preview is wrapped so that a preview of `null` is told apart from none.
 *
 * @template T The type of the value.
 */
final class AsyncValue implements ArrayCapableInterface
{
	use ArrayCapableTrait;

	private static int $s_wp_counter = 0;

	/**
	 * @param callable(FormValidationContext):T $factory
	 * @param null|callable():T                 $preview_factory null for a secret value
	 */
	private function __construct(
		private readonly mixed $factory,
		private readonly mixed $preview_factory,
	) {}

	/**
	 * A value the client never sees: its rule set is withheld.
	 *
	 * @template V
	 *
	 * @param callable(FormValidationContext):V $factory receives the validation context
	 *
	 * @return self<V>
	 */
	public static function secret(callable $factory): self
	{
		return new self($factory, null);
	}

	/**
	 * A value the client gets a preview of, to check its rule without asking the server.
	 *
	 * @template V
	 *
	 * @param callable(FormValidationContext):V $factory what the server compares against on validation
	 * @param callable():V                      $preview what the client is sent; it has no validation
	 *                                                   context, since the form is not submitted yet
	 *
	 * @return self<V>
	 */
	public static function public(callable $factory, callable $preview): self
	{
		return new self($factory, $preview);
	}

	/**
	 * Runs $cb with the previews of public values computed when they are serialized.
	 *
	 * {@see Form::toClientArray()} serializes a form for a client inside it, and nothing else does:
	 * {@see Form::getVersion()} fingerprints a form without running a preview ({@see self::withoutPreview()}).
	 *
	 * @template R
	 *
	 * @param callable():R $cb
	 *
	 * @return R
	 *
	 * @internal
	 */
	public static function withPreview(callable $cb): mixed
	{
		++self::$s_wp_counter;

		try {
			return $cb();
		} finally {
			--self::$s_wp_counter;
		}
	}

	/**
	 * Runs $cb with no preview computed, even inside {@see self::withPreview()}: what
	 * {@see Form::getVersion()} fingerprints, so that a preview never changes a form's version.
	 *
	 * @template R
	 *
	 * @param callable():R $cb
	 *
	 * @return R
	 *
	 * @internal
	 */
	public static function withoutPreview(callable $cb): mixed
	{
		$saved              = self::$s_wp_counter;
		self::$s_wp_counter = 0;

		try {
			return $cb();
		} finally {
			self::$s_wp_counter = $saved;
		}
	}

	/**
	 * Gets the value, evaluated against the current validation context.
	 *
	 * @param FormValidationContext $ctx
	 *
	 * @return T
	 */
	public function getValue(FormValidationContext $ctx): mixed
	{
		return \call_user_func($this->factory, $ctx);
	}

	/**
	 * Whether the client must never see this value.
	 */
	public function isSecret(): bool
	{
		return null === $this->preview_factory;
	}

	/**
	 * {@inheritDoc}
	 *
	 * A public value's preview, computed only inside {@see self::withPreview()} (`null` outside it,
	 * where the form is not being sent to a client).
	 *
	 * @return array{'$preview': null|array{value: T}}
	 *
	 * @throws LogicException for a secret value, which is never serialized: its rule set is withheld
	 */
	#[Override]
	public function toArray(): array
	{
		if (null === $this->preview_factory) {
			throw new LogicException(
				\sprintf('A secret %s is never serialized: its rule set is withheld.', self::class)
			);
		}

		return [
			'$preview' => self::$s_wp_counter > 0
				? ['value' => \call_user_func($this->preview_factory)]
				: null,
		];
	}
}
