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

namespace __PLH_NAMESPACE__;

use Gobl\DBAL\Types\TypeString;
use OZONE\Core\App\Service as BaseService;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\ValidatedFile;
use OZONE\Core\Forms\AsyncValue;
use OZONE\Core\Forms\Fieldset;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\Resume\FormSessionStore;
use OZONE\Core\Forms\RuleSet;
use OZONE\Core\Forms\TypesSwitcher;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteFormDeclaration;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Exposes the ways downstream code consumes form data, as plain HTTP routes so
 * integration tests can exercise them.
 *
 * Routes registered:
 *  GET  /test/require-completion/:ref                 -- requireCompletion() expecting TestFormProvider
 *  GET  /test/require-completion-as-irreversible/:ref -- requireCompletion() expecting TestFormIrreversibleProvider
 *  POST /test/drop-session/:ref                       -- drop(); returns {dropped: true}
 *  POST /test/wizard-route                            -- route declared with TestFormProvider
 *  POST /test/irreversible-route                      -- route declared with TestFormIrreversibleProvider
 *  POST /test/incremental-a, /test/incremental-b      -- same Form::resumable() form on two routes
 *  POST /test/incremental-upload                      -- Form::resumable() form with file fields
 *  POST /test/secret-form                             -- a form hiding SECRET wherever a secret can sit
 *
 * Every route returns what it consumed as {values: ...}.
 */
final class TestFormConsumerRoutesProvider extends BaseService
{
	/** What no response may ever contain: every secret value of `secretForm()` holds it. */
	public const SECRET = 'oz-secret-sentinel';

	/** A public value's own value, which the client is never sent either: only its preview. */
	public const PUBLIC_RUNTIME = 'oz-public-runtime';

	public static function registerRoutes(Router $router): void
	{
		$router->get('/test/require-completion/:ref', static function (RouteInfo $ri) {
			$form_data = FormSessionStore::requireCompletion($ri->param('ref'), $ri->getContext(), TestFormProvider::class);

			return self::values($ri, $form_data->toArray());
		})->name('test:require-completion');

		$router->get('/test/require-completion-as-irreversible/:ref', static function (RouteInfo $ri) {
			$form_data = FormSessionStore::requireCompletion($ri->param('ref'), $ri->getContext(), TestFormIrreversibleProvider::class);

			return self::values($ri, $form_data->toArray());
		})->name('test:require-completion-as-irreversible');

		$router->post('/test/drop-session/:ref', static function (RouteInfo $ri) {
			$s = new self($ri);
			FormSessionStore::drop($ri->param('ref'));
			$s->json()->setDone()->setData(['dropped' => true]);

			return $s->respond();
		})->name('test:drop-session');

		$router->post('/test/wizard-route', static fn (RouteInfo $ri) => self::values($ri, $ri->getCleanFormData()->toArray()))
			->name('test:wizard-route')
			->form(RouteFormDeclaration::provider(TestFormProvider::class));

		$router->post('/test/irreversible-route', static fn (RouteInfo $ri) => self::values($ri, $ri->getCleanFormData()->toArray()))
			->name('test:irreversible-route')
			->form(RouteFormDeclaration::provider(TestFormIrreversibleProvider::class));

		$router->post('/test/incremental-a', static fn (RouteInfo $ri) => self::values($ri, $ri->getCleanFormData()->toArray()))
			->name('test:incremental-a')
			->form(static fn () => self::incrementalForm());

		$router->post('/test/incremental-b', static fn (RouteInfo $ri) => self::values($ri, $ri->getCleanFormData()->toArray()))
			->name('test:incremental-b')
			->form(static fn () => self::incrementalForm());

		$router->post('/test/incremental-upload', static function (RouteInfo $ri) {
			$fd    = $ri->getCleanFormData();
			$doc   = $fd->get('doc');
			$photo = $fd->get('photo');

			return self::values($ri, [
				'note'               => $fd->get('note'),
				'doc_is_temp'        => $doc instanceof ValidatedFile && $doc->isTemporary(),
				'doc_exists'         => $doc instanceof ValidatedFile && $doc->isTemporary() && \is_file($doc->getPath()),
				'photo_is_persisted' => $photo instanceof ValidatedFile && $photo->isPersisted(),
				'photo_loads'        => $photo instanceof ValidatedFile && null !== $photo->loadFile(),
			]);
		})
			->name('test:incremental-upload')
			->form(static fn () => self::uploadForm());

		$router->post('/test/secret-form', static function (RouteInfo $ri) {
			return self::values($ri, $ri->getCleanFormData()->toArray());
		})
			->name('test:secret-form')
			->form(static fn () => self::secretForm());
	}

	public static function apiDoc(ApiDoc $doc): void {}

	/**
	 * Two required fields; a failed attempt keeps whichever was valid.
	 */
	private static function incrementalForm(): Form
	{
		$form = (new Form())->setId('incremental')->resumable(RequestScope::HOST);
		$form->string('first', true);
		$form->string('second', true);

		return $form;
	}

	/**
	 * A temporary file, a persisted file, then a note.
	 *
	 * Files come first: validation stops at the first invalid field, and `note` is
	 * the one the first attempt leaves out.
	 */
	private static function uploadForm(): Form
	{
		$form = (new Form())->setId('incremental-upload')->resumable(RequestScope::HOST);
		$form->file('doc', true)->configureType(static fn (TypeFile $t) => $t->temp());
		$form->file('photo', true);
		$form->string('note', true);

		return $form;
	}

	/**
	 * A secret in each place a rule set can be: the form's expect() and ensure(), a field's if(), a
	 * switcher's branch, a fieldset's if(), expect() and ensure(); and a public value whose own value
	 * differs from its preview.
	 */
	private static function secretForm(): Form
	{
		$secret = static fn (): string => self::SECRET;
		$form   = (new Form())->setId('secret-form');

		$form->expect()
			->notIn('code', AsyncValue::secret(static fn (): array => ['blocked', self::SECRET]), 'CODE_BLOCKED');
		$form->expect()->neq('code', AsyncValue::public(
			static fn (): string => self::PUBLIC_RUNTIME,
			static fn (): string => 'shown-preview'
		));
		$form->string('code', true);
		$form->string('hint')->if()->neq('code', AsyncValue::secret($secret));
		$form->switcher('pick')->configureType(static fn (TypesSwitcher $s) => $s
			->when(static fn (RuleSet $rs) => $rs->eq('code', AsyncValue::secret($secret)), new TypeString())
			->otherwise(new TypeString()));
		$form->fieldset('box', static function (Fieldset $fs) use ($secret): void {
			$fs->string('inner');
			$fs->expect()->neq('box.inner', AsyncValue::secret($secret));
			$fs->ensure()->neq('box.inner', AsyncValue::secret($secret));
		})->if()->neq('code', AsyncValue::secret($secret));
		$form->ensure()->neq('code', AsyncValue::secret($secret));

		return $form;
	}

	private static function values(RouteInfo $ri, array $values)
	{
		$s = new self($ri);
		$s->json()->setDone()->setData(['values' => $values]);

		return $s->respond();
	}
}
