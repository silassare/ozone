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

use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Service as BaseService;
use OZONE\Core\Db\OZMessage;
use OZONE\Core\Db\OZMessagesQuery;
use OZONE\Core\Messaging\Messages\Message;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use Throwable;

/**
 * Routes of MessagingTest.
 *
 * `GET /messaging/send?spec=<json>` sends the message the spec describes (channel, template, to, data,
 * subject, locale, once, sync) and answers the records written, or the error thrown.
 * `GET /messaging/records` answers every record, `GET /messaging/gc` runs the garbage collection.
 */
final class MessagingTestRoutesProvider extends BaseService
{
	public static function registerRoutes(Router $router): void
	{
		$router->get('/messaging/send', static function (RouteInfo $ri) {
			$spec = (array) \json_decode((string) $ri->getContext()->getRequest()->getQueryParam('spec', ''), true);
			$s    = new self($ri);

			try {
				$message = new Message((string) $spec['channel'], (string) $spec['template'])
					->inject((array) ($spec['data'] ?? []))
					->setSubject($spec['subject'] ?? null)
					->setLocale($spec['locale'] ?? null)
					->sync((bool) ($spec['sync'] ?? false));

				foreach ((array) ($spec['to'] ?? []) as $to) {
					$message->addRecipient((string) $to);
				}

				if (isset($spec['once'])) {
					$message->once((string) $spec['once']);
				}

				$s->json()->setDone()->setData(['records' => \array_map(self::describe(...), $message->send())]);
			} catch (Throwable $t) {
				$s->json()->setDone()->setData(['error' => $t->getMessage()]);
			}

			return $s->respond();
		})->name('test:messaging:send');

		self::registerGcRoute($router);

		$router->get('/messaging/records', static function (RouteInfo $ri) {
			$records = new OZMessagesQuery()->find()->fetchAllClass();

			\usort($records, static fn (OZMessage $a, OZMessage $b) => (int) $a->getID() <=> (int) $b->getID());

			$s = new self($ri);
			$s->json()->setDone()->setData(['records' => \array_map(self::describe(...), $records)]);

			return $s->respond();
		})->name('test:messaging:records');
	}

	public static function apiDoc(ApiDoc $doc): void {}

	/**
	 * `GET /messaging/gc` runs the garbage collection, as the hourly `oz:gc` task does.
	 */
	public static function registerGcRoute(Router $router): void
	{
		$router->get('/messaging/gc', static function (RouteInfo $ri) {
			GarbageCollector::run();

			$s = new self($ri);
			$s->json()->setDone();

			return $s->respond();
		})->name('test:messaging:gc');
	}

	private static function describe(OZMessage $m): array
	{
		return [
			'id'          => $m->getID(),
			'channel'     => $m->getChannel(),
			'status'      => $m->getStatus()->value,
			'template'    => $m->getTemplate(),
			'destination' => $m->getDestination(),
			'locale'      => $m->getLocale(),
			'once_key'    => $m->getOnceKey(),
			'job_ref'     => $m->getJobRef(),
			'attempts'    => $m->getAttempts(),
			'last_error'  => $m->getLastError(),
			'sent'        => null !== $m->getSentAT(),
		];
	}
}
