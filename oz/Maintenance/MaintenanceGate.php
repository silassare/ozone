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

namespace OZONE\Core\Maintenance;

use OZONE\Core\App\Context;
use OZONE\Core\Cli\Cron\CronEndpoint;
use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\Cookies;
use OZONE\Core\Http\Response;
use OZONE\Core\Roles\Enums\RoleCheckMode;
use OZONE\Core\Roles\Roles;
use OZONE\Core\Router\RouteInfo;
use Throwable;

/**
 * Class MaintenanceGate.
 *
 * What a maintenance does to one request, in the order a request meets it.
 *
 * Before the request is routed ({@see self::hold()}): a request of a scope the maintenance does not
 * hold is served, and so is one from an allowed address -- the client address as `oz.proxies` lets
 * OZone read it -- or from a browser that followed the secret link. A request carrying the secret
 * is answered there and then: a redirection to the same address without it, setting the cookie that
 * lets that browser through until the next `oz maintenance on`.
 *
 * Any other request is held. It is routed, so that its route's own authentication can say who is
 * asking, and a signed-in administrator is let through ({@see self::admits()}); everything else
 * that happens to a held request on the way -- no route, an authentication it cannot give, a
 * database being migrated under it -- is answered as maintenance ({@see self::refusal()}).
 */
final class MaintenanceGate
{
	/**
	 * The query parameter carrying the secret of a maintenance's link.
	 */
	public const string LINK_PARAM = 'oz_maintenance';

	/**
	 * The cookie that lets a browser through, once it has followed the link.
	 */
	public const string COOKIE = 'oz_maintenance';

	/**
	 * MaintenanceGate constructor.
	 */
	public function __construct(private readonly MaintenanceMode $mode) {}

	/**
	 * Screens a request before it is routed.
	 *
	 * Answers the request carrying the secret of the link, which ends it.
	 *
	 * @return null|self null when the request is served as usual, the gate holding it otherwise
	 */
	public static function hold(Context $context): ?self
	{
		$mode = Maintenance::current();

		if (null === $mode) {
			return null;
		}

		$gate = new self($mode);

		if (!$gate->covers($context) || $gate->letsThrough($context)) {
			return null;
		}

		$link = $gate->followLink($context);

		if (null !== $link) {
			$context->respond($link);
		}

		return $gate;
	}

	/**
	 * The maintenance this gate applies.
	 */
	public function mode(): MaintenanceMode
	{
		return $this->mode;
	}

	/**
	 * Whether the maintenance holds the requests of the scope serving this one.
	 */
	public function covers(Context $context): bool
	{
		return $this->mode->covers(scope()->getName(), $context->isApiContext());
	}

	/**
	 * Whether the request comes from an allowed address, or from a browser that followed the link.
	 */
	public function letsThrough(Context $context): bool
	{
		if ($this->mode->allowsIp($context->getUserIP())) {
			return true;
		}

		$cookie = $context->getRequest()->getCookieParam(self::COOKIE);

		return \is_string($cookie) && $this->mode->acceptsBypassToken($cookie);
	}

	/**
	 * The answer to a request carrying the secret of the link, or null for any other.
	 *
	 * A redirection rather than the page itself, so the secret leaves the address bar, the history
	 * and the `Referer` of whatever the page loads. The target is relative and on the same path: it
	 * is the request's own address without the parameter, so nothing in the request can send the
	 * browser elsewhere.
	 */
	public function followLink(Context $context): ?Response
	{
		$request = $context->getRequest();
		$secret  = $request->getQueryParam(self::LINK_PARAM);
		$token   = $this->mode->bypassToken();

		if (!\is_string($secret) || null === $token || !$this->mode->acceptsSecret($secret)) {
			return null;
		}

		$query = $request->getQueryParams();

		unset($query[self::LINK_PARAM]);

		$uri = $request->getUri();
		// One leading slash: `//host/...` is an address on another host.
		$path     = '/' . \ltrim(\rtrim($uri->getBasePath(), '/') . '/' . \ltrim($uri->getPath(), '/'), '/');
		$query    = \http_build_query($query, '', '&', \PHP_QUERY_RFC3986);
		$location = '' === $query ? $path : $path . '?' . $query;

		$response = $context->getResponse()
			->withRedirect($location, 302)
			->withHeader('Cache-Control', 'no-store');

		return new Cookies()
			->add(Cookie::create($context, self::COOKIE, $token))
			->applyTo($response);
	}

	/**
	 * Whether a route is served whatever the maintenance: the `oz:cron` route, which runs cron for
	 * a host with no scheduler and is answered with its own key. Cron keeps running under a
	 * maintenance unless it pauses the jobs, and then the tick the route asks for waits.
	 */
	public function exempts(RouteInfo $ri): bool
	{
		return CronEndpoint::ROUTE === $ri->route()->getName();
	}

	/**
	 * Whether the authentication of the request shows a signed-in administrator: the admin role, or
	 * one weighing at least as much (super-admin).
	 *
	 * Fails closed: a maintenance is often a migration, and the session, the user or the roles may be
	 * what it is changing. Whatever cannot answer -- no authentication, a session that does not load,
	 * a roles table being rewritten -- answers no. Under a scoped authentication (an API key with
	 * rights of its own), a user's role grants nothing, as for every role check of OZone.
	 */
	public function admits(Context $context): bool
	{
		try {
			$method = $context->authState()->current();

			if (null === $method || $method->isScoped()) {
				return false;
			}

			// READ: the scoped authentication is ruled out above, on the request being screened,
			// where GRANT_ACCESS would ask the current context, which is not always this one.
			return Roles::isAdmin($method->user(), false, RoleCheckMode::READ);
		} catch (Throwable) {
			return false;
		}
	}

	/**
	 * Lets a held request its route has authenticated through when {@see self::admits()} it, and
	 * refuses it otherwise.
	 *
	 * A refused request was not served, so nothing of its authentication is written back with the
	 * answer: its session is not saved while the database may be being migrated under it.
	 *
	 * @throws MaintenanceException
	 */
	public function screenAuthenticated(Context $context): void
	{
		if ($this->admits($context)) {
			return;
		}

		$context->authState()->forget();

		throw $this->refusal();
	}

	/**
	 * The answer to a request the maintenance holds.
	 */
	public function refusal(): MaintenanceException
	{
		return MaintenanceException::of($this->mode);
	}
}
