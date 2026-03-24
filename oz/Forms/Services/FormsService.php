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

namespace OZONE\Core\Forms\Services;

use Override;
use OZONE\Core\App\Service;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Forms\Form;
use OZONE\Core\Forms\FormRegistry;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;

/**
 * Class FormsService.
 *
 * Exposes forms registered via {@see Form::key()} as JSON at a
 * stable URL so clients can fetch a form definition independently of the route
 * that uses it.
 */
final class FormsService extends Service
{
	public const FORM_KEY_PARAM = 'form_key';

	public const GET_FORM_ROUTE = 'oz:forms.get';

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->get('/forms/:' . self::FORM_KEY_PARAM, static function (RouteInfo $ri) {
			return (new self($ri))->actionGetForm($ri->param(self::FORM_KEY_PARAM, ''));
		})->name(self::GET_FORM_ROUTE);
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Forms', 'Form discovery endpoints.');
		$doc->addOperationFromRoute(
			self::GET_FORM_ROUTE,
			'GET',
			'Get Form Definition',
			[
				$doc->success([
					'form' => $doc->object([
						'key'          => $doc->string('The form key.'),
						'version'      => $doc->string('Structural fingerprint of the form (16-char hex). Changes when the form structure changes.'),
						'prefix'       => $doc->string('Optional field name prefix.', ['nullable' => true]),
						'method'       => $doc->string('HTTP method the form should be submitted with.'),
						'resume_scope' => $doc->string('Resume scope strategy, or null if resume is disabled.', ['nullable' => true]),
						'resume_ttl'   => $doc->integer('Resume cache TTL in seconds, or null if resume is disabled.', ['nullable' => true]),
					]),
				]),
				$doc->response(404, 'Form not found.', []),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Forms.getForm',
				'description' => 'Returns the serialized form definition for a form registered via Form::key(). Forms must explicitly opt in by calling key() on the Form instance.',
			]
		);
	}

	/**
	 * Returns the serialized form definition for the given key.
	 *
	 * @param string $key The form key registered via {@see Form::key()}
	 *
	 * @return Response
	 *
	 * @throws NotFoundException when no form is registered under the given key
	 */
	private function actionGetForm(string $key): Response
	{
		$form = FormRegistry::get($key);

		if (!$form) {
			throw new NotFoundException('OZ_FORM_NOT_FOUND');
		}

		$this->json()->setDone()->setDataKey('form', $form);

		return $this->respond();
	}
}
