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

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Interfaces\TNetSectionInterface;
use OZONE\Core\Auth\TNet\ReaderSection;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\OZone;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Class TNet.
 */
final class TNet extends Service
{
	public const string ROUTE_TNET = 'oz:tnet';

	public function actionTNet(): void
	{
		$context = $this->getContext();
		$health  = [
			'is_installed'     => OZone::isInstalled(),
			'has_db_access'    => OZone::hasDbAccess(),
			'has_db_installed' => OZone::hasDbInstalled(),
			'has_super_admin'  => OZone::hasSuperAdmin(),
		];

		$user = null;
		if ($context->hasAuthenticatedUser()) {
			$user = $context->auth()->user();
		}

		$asked = $context->getRequest()->getQueryParam('sections');

		$this->json()
			->setData([
				'ok'            => null !== $user,
				'_current_user' => $user?->toArray(),
				'_health'       => $health,
				// An object even when empty: a client reads it by name, and `[]` is not a map.
				'sections'      => (object) self::answers(
					$context,
					Settings::load('oz.tnet.sections'),
					\is_string($asked) ? $asked : null,
					null !== $user
				),
			]);
	}

	/**
	 * The sections answered to this request: those asked for, or all of them.
	 *
	 * A section that needs a signed-in user is not built for nobody. One that is not a section, or
	 * that throws, is left out and logged: the heartbeat answers whatever a section did, because a
	 * page asking whether its session is alive must get that answer. A name nothing registered is
	 * ignored, so a page asking a server for a section it does not have still gets the rest.
	 *
	 * @param array<string, mixed> $registered the `oz.tnet.sections` group: a name, a class, or
	 *                                         `null` where a later source removed it
	 * @param null|string          $asked      the `sections` query parameter, comma separated
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function answers(Context $context, array $registered, ?string $asked, bool $signed_in): array
	{
		$names = null === $asked
			? \array_keys($registered)
			: \array_filter(\array_map('trim', \explode(',', $asked)), static fn (string $name) => '' !== $name);

		$out = [];

		foreach ($names as $name) {
			$class = $registered[$name] ?? null;

			if (!\is_string($class)) {
				continue;
			}

			try {
				if (!\is_subclass_of($class, TNetSectionInterface::class)) {
					throw new RuntimeException(\sprintf(
						'The tnet section "%s" should be a class implementing: %s',
						$name,
						TNetSectionInterface::class
					))->suspectConfig('oz.tnet.sections', $name);
				}

				if (!$signed_in && $class::requiresAuthenticatedUser()) {
					continue;
				}

				$answer = new $class()->answer($context);

				if (null !== $answer) {
					$out[$name] = $answer;
				}
			} catch (Throwable $t) {
				oz_logger()->error($t);
			}
		}

		return $out;
	}

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get('/tnet', static function (RouteInfo $ri) {
				$s = new self($ri);
				$s->actionTNet();

				// Per user, and asked again whenever a page wants to know: never kept by anything.
				return $s->respond()->withHeader('Cache-Control', 'no-store');
			})
			->name(self::ROUTE_TNET);
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Health', 'System health & connectivity.');
		$doc->addOperationFromRoute(
			self::ROUTE_TNET,
			'GET',
			'Health Check',
			[
				$doc->success([
					'ok'            => $doc->boolean('Whether the user is authenticated.'),
					'_current_user' => $doc->object([], [
						'description' => 'The current user, if authenticated.',
						'nullable'    => true,
					]),
					'_health'       => $doc->object([
						'is_installed'     => $doc->boolean(),
						'has_db_access'    => $doc->boolean(),
						'has_db_installed' => $doc->boolean(),
						'has_super_admin'  => $doc->boolean(),
					]),
					'sections'      => $doc->object([
						ReaderSection::NAME => $doc->object([
							'type'    => $doc->string('The identity type, which is not a role.'),
							'id'      => $doc->string('The identity id within its type.'),
							'roles'   => $doc->array($doc->string(), [
								'description' => 'The roles that grant access in this request: '
									. 'none under a scoped auth.',
							]),
							'weights' => $doc->object([], [
								'description' => 'The weight of every role of the project, by name.',
							]),
						], [
							'description' => 'Who is reading; answered to a signed-in user only.',
						]),
					], [
						'description' => 'The sections of `oz.tnet.sections`: those asked for, or all of them. '
							. 'A section that fails is left out.',
					]),
				]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Health.check',
				'description' => 'Check API connectivity and system health, and answer the registered sections.',
				'parameters'  => [
					$doc->parameter(
						'sections',
						$doc->string(),
						'Section names, comma separated: answers those only.',
						'query'
					),
				],
			]
		);
	}
}
