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

namespace OZONE\Core\Sessions;

use Gobl\DBAL\Types\Utils\JsonPatch;
use Gobl\ORM\ORMOptions;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\GarbageCollector;
use OZONE\Core\App\Keys;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Methods\SessionAuth;
use OZONE\Core\Auth\StatefulAuthenticationMethodStore;
use OZONE\Core\CSRF\CSRF;
use OZONE\Core\Db\OZSession;
use OZONE\Core\Db\OZSessionsQuery;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Hooks\Events\DbReadyHook;
use OZONE\Core\Hooks\Interfaces\BootHookReceiverInterface;
use OZONE\Core\Http\Cookie;
use OZONE\Core\Http\Cookies;
use OZONE\Core\Http\Enums\RequestScope;
use OZONE\Core\OZone;
use OZONE\Core\Stores\CacheRegistry;
use PHPUtils\Store\Map;
use Throwable;

/**
 * Class Session.
 *
 * @internal
 */
final class Session implements BootHookReceiverInterface
{
	private const string SESSION_ID_REG = '~^[-,a-zA-Z0-9]{32,128}$~';

	private ?StatefulAuthenticationMethodStore $state = null;

	private ?OZSession $session_entry = null;

	private bool $started = false;

	private bool $delete_cookie = false;

	/**
	 * Whether the session ID was handed out ({@see self::id()}): a CSRF token, a rate-limit key, a
	 * form-resume scope is bound to it, so the session has to exist on the next request.
	 */
	private bool $id_bound = false;

	/**
	 * The store's data when the session started, to tell a used session from an untouched one.
	 */
	private array $initial_data = [];

	/**
	 * Whether the session did not exist before this request.
	 *
	 * A new session has no `OZSession` entity until it is saved, only its ID (made when first needed)
	 * and its owner: an anonymous request, which never keeps its session, then builds no entity at
	 * all -- and so does not initialize the database either.
	 */
	private bool $is_new = false;

	/**
	 * Whether the loaded session was opened from another source than this request's.
	 *
	 * Set by {@see self::start()} from the stored `request_source_key`, acted upon by
	 * {@see SessionAuth}.
	 */
	private bool $source_changed = false;

	private ?string $new_id = null;

	private ?string $new_owner_type = null;

	private ?string $new_owner_id = null;

	/**
	 * Distinguishes the data stores of successive new sessions in one request (see start()).
	 */
	private static int $new_sessions = 0;

	/**
	 * Session constructor.
	 */
	public function __construct(private Context $context, private readonly string $request_source_key) {}

	/**
	 * Returns session lifetime in seconds from settings.
	 */
	public static function lifetime(): int
	{
		return (int) Settings::get('oz.sessions', 'OZ_SESSION_LIFE_TIME');
	}

	/**
	 * Returns session cookie name from setting.
	 */
	public static function cookieName(): string
	{
		return Settings::get('oz.sessions', 'OZ_SESSION_COOKIE_NAME');
	}

	/**
	 * Returns session ID.
	 */
	public function id(): string
	{
		// Whoever asks for the ID binds something to it: the session is kept.
		$this->id_bound = true;

		return $this->ensureID();
	}

	/**
	 * Returns the session ID without binding anything to it.
	 *
	 * Unlike {@see self::id()}, asking does not keep the session: for saying which session something
	 * happened to, not for handing the ID to something that will come back with it.
	 */
	public function peekID(): string
	{
		return $this->ensureID();
	}

	/**
	 * Returns session source key.
	 *
	 * This is the source of the current request, not the one the session was opened from: the stored
	 * key is the baseline {@see self::sourceChanged()} compares against.
	 */
	public function sourceKey(): string
	{
		return $this->request_source_key;
	}

	/**
	 * Returns the source key the session was opened from, the baseline
	 * {@see self::sourceChanged()} compares the current request's against.
	 *
	 * This request's own key for a new session: nothing is stored yet, and that is the key the
	 * session will be opened with.
	 */
	public function openedSourceKey(): string
	{
		return $this->session_entry?->getRequestSourceKey() ?? $this->request_source_key;
	}

	/**
	 * Whether this session was opened from another source than the current request's.
	 *
	 * False for a new session: there is no stored key to compare with.
	 */
	public function sourceChanged(): bool
	{
		return $this->source_changed;
	}

	/**
	 * To checks if session has started.
	 */
	public function hasStarted(): bool
	{
		return $this->started;
	}

	/**
	 * Start the session.
	 */
	public function start(?string $session_id = null): static
	{
		$entry = $session_id ? self::findSessionByID($session_id) : null;

		$this->session_entry  = $entry;
		$this->is_new         = null === $entry;
		$this->source_changed = null !== $entry && $entry->getRequestSourceKey() !== $this->request_source_key;
		$this->new_id         = null;
		$this->new_owner_type = null;
		$this->new_owner_id   = null;

		if (null !== $entry) {
			$data      = $entry->getData()->getData();
			$state_key = $entry->getID();
		} else {
			// No entity and no ID yet: see $is_new. ensureID() and save() make them when kept.
			$data      = [];
			$state_key = 'new:' . ++self::$new_sessions;
		}

		$this->state         = StatefulAuthenticationMethodStore::getInstance($state_key, $data);
		$this->initial_data  = $this->state->getData();
		$this->id_bound      = false;
		$this->started       = true;
		$this->delete_cookie = false;

		return $this;
	}

	/**
	 * Restart the session.
	 */
	public function restart(): static
	{
		$this->assertSessionStarted();

		return $this->destroy()
			->start();
	}

	/**
	 * Destroy the session.
	 */
	public function destroy(): static
	{
		$this->assertSessionStarted();

		if (!$this->is_new && null !== $this->session_entry) {
			self::delete($this->session_entry->getID());
		}

		return $this->letGo();
	}

	/**
	 * Lets go of the session and drops its cookie, leaving the stored session itself alone.
	 *
	 * For a request that may not use the session it carries, when that is no reason for the session
	 * to go: the client stops sending an ID it cannot use and asks for one of its own on the next
	 * request, while whoever the session belongs to keeps it. A client holding the cookie can
	 * therefore not end someone else's session by sending it.
	 */
	public function abandon(): static
	{
		$this->assertSessionStarted();

		return $this->letGo();
	}

	/**
	 * Returns session attached user.
	 */
	public function attachedAuthUser(): ?AuthUserInterface
	{
		$this->assertSessionStarted();

		if ($this->is_new) {
			$c_type = $this->new_owner_type;
			$c_id   = $this->new_owner_id;
		} else {
			$c_type = $this->session_entry?->getOwnerType();
			$c_id   = $this->session_entry?->getOwnerID();
		}

		return $c_type && $c_id ? AuthUsers::identify($c_type, $c_id) : null;
	}

	/**
	 * Attach user to this session.
	 */
	public function attachAuthUser(AuthUserInterface $user): static
	{
		$this->assertSessionStarted();

		$current_user = $this->attachedAuthUser();

		$sid = $this->ensureID();

		if ($current_user && !AuthUsers::same($current_user, $user)) {
			throw new RuntimeException('OZ_SESSION_DISTINCT_USER_CANT_ATTACH_USER', [
				'_session_id'    => $sid,
				'_owner_current' => AuthUsers::selector($current_user),
				'_owner_new'     => AuthUsers::selector($user),
			]);
		}

		$this->setOwner($user->getAuthUserType(), $user->getAuthIdentifier());

		return $this;
	}

	/**
	 * Detach the current user from the session.
	 */
	public function detachAuthUser(): static
	{
		$this->assertSessionStarted();

		$this->setOwner(null, null);

		return $this;
	}

	/**
	 * Gets the session data store.
	 */
	public function store(): StatefulAuthenticationMethodStore
	{
		$this->assertSessionStarted();

		// assertSessionStarted() has already ruled out null, for both the store and the entry.
		return $this->state;
	}

	#[Override]
	public static function boot(): void
	{
		GarbageCollector::register('oz:sessions', self::gc(...));

		// The class is checked when the database is ready, not here: loading a generated ORM class
		// at boot cost every request, including the ones that never touch a session.
		DbReadyHook::listen(static function (): void {
			if (\class_exists(OZSession::class)) {
				OZSession::crud()
					->onBeforePKColumnWrite(static fn () => true);
			}
		});
	}

	/**
	 * Find session by ID.
	 */
	public static function findSessionByID(string $sid): ?OZSession
	{
		if (!OZone::hasDbInstalled() || !self::isSessionIdLike($sid)) {
			return null;
		}

		$factory = static function () use ($sid): ?OZSession {
			try {
				$sqb = new OZSessionsQuery();

				$result = $sqb->whereIdIs($sid)
					->whereIsValid()
					->find(ORMOptions::makePaginated(1));

				$item = $result->fetchClass();

				if ($item && $item->getExpireAT() > \time()) {
					return $item;
				}
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to load session with session ID.', ['_session_id' => $sid], $t);
			}

			return null;
		};

		return CacheRegistry::runtime(__METHOD__)->remember($sid, $factory);
	}

	/**
	 * Response ready hook.
	 *
	 * @internal
	 */
	public function responseReady(): void
	{
		$response            = $this->context->getResponse();
		$session_cookie_name = self::cookieName();

		if ($this->started && !$this->isKept()) {
			// Nothing used this new session: no row, no cookie, so an anonymous request costs no write.
			// A cookie the request carried for a session that no longer exists is dropped, so the
			// client stops sending it.
			if (null !== $this->context->getRequest()->getCookieParam($session_cookie_name)) {
				$this->context->setResponse(new Cookies()
					->add(Cookie::create($this->context, $session_cookie_name)->drop())
					->applyTo($response));
			}

			return;
		}

		/** @var null|Cookie $cookie */
		$cookie = null;

		if ($this->started) {
			$this->save();
			$cookie          = Cookie::create($this->context, $session_cookie_name, $this->ensureID());
			$cookie->expires = \time() + self::lifetime();
		}

		if ($this->delete_cookie) {
			$cookie = Cookie::create($this->context, $session_cookie_name)->drop();
		}

		$cookies_jar = new Cookies();

		if ($cookie) {
			$cookies_jar->add($cookie);
		}

		$csrf_cookie = $this->csrfCookie();

		if ($csrf_cookie) {
			$cookies_jar->add($csrf_cookie);
		}

		// Added to the response's `Set-Cookie` lines, never in place of them: a handler's cookies stay.
		$this->context->setResponse($cookies_jar->applyTo($response));
	}

	/**
	 * Forgets what the session was, so that nothing is saved for it afterwards and its cookie is
	 * dropped on the way out.
	 */
	private function letGo(): static
	{
		$this->session_entry  = null;
		$this->source_changed = false;
		$this->new_id         = null;
		$this->new_owner_type = null;
		$this->new_owner_id   = null;
		$this->state          = null;
		$this->started        = false;
		$this->delete_cookie  = true;

		return $this;
	}

	/**
	 * The session's ID, made when first needed: a new session has none until it is kept.
	 */
	private function ensureID(): string
	{
		$this->assertSessionStarted();

		if (!$this->is_new && null !== $this->session_entry) {
			return $this->session_entry->getID();
		}

		return $this->new_id ??= Keys::newSessionID();
	}

	/**
	 * Sets the owner, on the entity of an existing session or in memory for a new one.
	 */
	private function setOwner(?string $type, ?string $id): void
	{
		if ($this->is_new) {
			$this->new_owner_type = $type;
			$this->new_owner_id   = $id;
		} else {
			$this->session_entry?->setOwnerType($type);
			$this->session_entry?->setOwnerID($id);
		}
	}

	/**
	 * Whether the session has to outlive this request: it existed already (its expiry slides), its ID
	 * was handed out, a user is attached, or its data changed since it started.
	 */
	private function isKept(): bool
	{
		return !$this->is_new
			|| $this->id_bound
			|| null !== $this->new_owner_id
			|| $this->store()->getData() !== $this->initial_data;
	}

	/**
	 * The cookie handing the session's CSRF token to scripts (`OZ_CSRF_COOKIE_NAME`, not
	 * HttpOnly), unless the request already carries a valid one; dropped with the session.
	 */
	private function csrfCookie(): ?Cookie
	{
		$name = CSRF::cookieName();

		if (null === $name) {
			return null;
		}

		if ($this->delete_cookie) {
			return Cookie::create($this->context, $name)->drop();
		}

		if (!$this->started) {
			return null;
		}

		$csrf    = new CSRF($this->context, RequestScope::STATE, $this->ensureID());
		$current = $this->context->getRequest()->getCookieParam($name);

		if (\is_string($current) && $csrf->isValid($current)) {
			return null;
		}

		$cookie           = Cookie::create($this->context, $name, $csrf->generateToken());
		$cookie->httponly = false;
		$cookie->expires  = \time() + self::lifetime();

		return $cookie;
	}

	/**
	 * Asserts that the session started.
	 */
	private function assertSessionStarted(): void
	{
		if (!$this->started || null === $this->state) {
			throw new RuntimeException('Session not yet started.');
		}
	}

	/**
	 * Save session data.
	 */
	private function save(): void
	{
		if (!OZone::hasDbInstalled()) {
			return;
		}

		$sid = $this->ensureID();

		if ($this->is_new) {
			// The entity of a new session exists only once it is kept.
			$this->session_entry ??= new OZSession()
				->setID($sid)
				->setRequestSourceKey($this->request_source_key);

			$this->session_entry->setOwnerType($this->new_owner_type)
				->setOwnerID($this->new_owner_id);
		}

		$entry = $this->session_entry;

		if (null === $entry) {
			throw new RuntimeException('Session not yet started.');
		}

		try {
			$now    = \time();
			$expire = $now + self::lifetime();

			$data = $this->store()->getData();

			if ($this->is_new) {
				$entry->setData($data);
			} else {
				// What this request changed, as the keys it changed: the database applies them to the
				// row it holds. Writing the whole store back would carry every key as it was when the
				// request started, so a request that overlaps this one would have its own writes
				// undone by whichever of the two answers last -- a sign-out among them.
				//
				// The store kept the writes as they were made, which is the only account of them that
				// does not have to guess what a writer meant. When it cannot answer for its document
				// -- something changed the data in place rather than through it -- the change is
				// worked out by comparing what the request started with, and that is said out loud
				// rather than left to look like a store that was never written to.
				$patch = $this->state?->recordedPatch();

				if (null === $patch) {
					$patch = self::dataPatch($entry->getData(), $this->initial_data, $data);

					if ($patch->hasOperations()) {
						oz_logger()->error('Session store write did not go through the store.', [
							'_session_id' => $sid,
							'_keys'       => \array_map(
								static fn (array $operation): string => \implode('.', $operation['path']),
								$patch->getOperations()
							),
							'_help'       => 'A session store write did not go through the store itself,'
								. ' so the keys it changed had to be worked out by comparing documents.',
						]);
					}
				}

				if ($patch->hasOperations()) {
					$entry->setData($patch);
				}
			}

			$entry->setExpireAT($expire)
				->setLastSeenAT($now)
				->setUpdatedAT($now)
				->save();
		} catch (Throwable $t) {
			throw new RuntimeException('Unable to save session.', ['_session_id' => $sid], $t);
		}
	}

	/**
	 * The patch from the data the session started with to the data it ends the request with.
	 *
	 * @param Map   $stored the document the entity holds, which the patch falls back to
	 * @param array $before the store's data when the session started
	 * @param array $after  the store's data now
	 */
	private static function dataPatch(Map $stored, array $before, array $after): JsonPatch
	{
		$patch = new JsonPatch($stored);

		self::collectDataChanges($patch, $before, $after, []);

		return $patch;
	}

	/**
	 * Adds to a patch every key of the store whose value is not what it was.
	 *
	 * @param list<string> $path the keys walked into so far
	 */
	private static function collectDataChanges(JsonPatch $patch, array $before, array $after, array $path): void
	{
		foreach ($after as $key => $value) {
			$key_path = [...$path, (string) $key];

			if (!\array_key_exists($key, $before)) {
				// A branch this request is the first to see is written one entry at a time, never
				// whole: another request creating the same branch at the same moment would have its
				// entry replaced by this one's, which is the loss below with nothing yet to lose.
				if (self::isDataBranch($value)) {
					self::collectDataChanges($patch, [], $value, $key_path);
				} else {
					$patch->setAt($key_path, $value);
				}

				continue;
			}

			$previous = $before[$key];

			if (self::isDataBranch($previous) && self::isDataBranch($value)) {
				self::collectDataChanges($patch, $previous, $value, $key_path);

				continue;
			}

			// An empty array is neither a map nor a list: it is what a map's last key left behind,
			// and what a map a request has not filled yet starts as. It is walked into when the
			// other side is a map, so that emptying a branch sends the keys that went rather than
			// the branch itself -- writing the branch whole undoes a key another request added to
			// it meanwhile, which is how a session store keeping its entries under one branch lost
			// an entry to a request that removed its own.
			if (
				([] === $previous && self::isDataBranch($value))
				|| (self::isDataBranch($previous) && [] === $value)
			) {
				self::collectDataChanges($patch, $previous, $value, $key_path);

				continue;
			}

			if ($previous !== $value) {
				$patch->setAt($key_path, $value);
			}
		}

		foreach ($before as $key => $_) {
			if (!\array_key_exists($key, $after)) {
				$patch->removeAt([...$path, (string) $key]);
			}
		}
	}

	/**
	 * Whether a value is walked into rather than written whole: a map of named keys.
	 *
	 * A list is written whole, since what its entries mean is their order.
	 */
	private static function isDataBranch(mixed $value): bool
	{
		return \is_array($value) && [] !== $value && !\array_is_list($value);
	}

	/**
	 * Delete all expired sessions.
	 */
	private static function gc(): void
	{
		if (OZone::hasDbInstalled()) {
			try {
				$s_table = new OZSessionsQuery();
				$s_table->whereExpireAtIsLte(\time())
					->delete()
					->execute();
			} catch (Throwable $t) {
				throw new RuntimeException('Unable to delete expired session.', null, $t);
			}
		}
	}

	/**
	 * Checks for session id string.
	 */
	private static function isSessionIdLike(mixed $value): bool
	{
		return \is_string($value) && \preg_match(self::SESSION_ID_REG, $value);
	}

	/**
	 * Delete session from database.
	 */
	private static function delete(string $sid): void
	{
		if (!OZone::hasDbInstalled()) {
			return;
		}

		try {
			$s_table = new OZSessionsQuery();

			$s_table->whereIdIs($sid)
				->delete()
				->execute();
		} catch (Throwable $t) {
			throw new RuntimeException('OZ_SESSION_DELETION_FAILED', ['_session_id' => $sid], $t);
		}
	}
}
