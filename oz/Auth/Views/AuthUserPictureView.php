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

namespace OZONE\Core\Auth\Views;

use Closure;
use Gobl\DBAL\Table;
use Gobl\ORM\ORMEntity;
use OpenApi\Annotations\MediaType;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthUsers;
use OZONE\Core\Auth\Interfaces\AuthUserInterface;
use OZONE\Core\Auth\Picture\AuthUserPictureImage;
use OZONE\Core\Auth\Picture\Enums\AuthUserPictureFallbackReason;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureAccessInterface;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureFallbackInterface;
use OZONE\Core\Auth\Picture\Interfaces\AuthUserPictureInterface;
use OZONE\Core\Auth\Picture\SelfOrAdminPictureAccess;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\ForbiddenException;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\Filters\FileFilters;
use OZONE\Core\FS\Filters\ImageFileFilterHandler;
use OZONE\Core\FS\Filters\ImageFilterTokens;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Scan\FileScan;
use OZONE\Core\FS\Views\GetFilesView;
use OZONE\Core\Http\Response;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Web\WebView;

/**
 * An auth user's picture, of any auth user type, addressed by the type and the id: served only to
 * whom `OZ_AUTH_USER_PICTURE_ACCESS` allows, and no file key is ever in its address, so a link
 * copied out works only for them. A type whose auth users do not implement
 * `AuthUserPictureInterface` (every table made by `makeAuthUserTable()` does) has no picture.
 *
 * It is served as `oz:files` serves an image, through the same filter chain: the tokens of the
 * address (sizes, effects, a project's own), the metadata always stripped, and never the original.
 * `OZ_AUTH_USER_PICTURE_TOKENS` are rendered first, and the tokens asked for on what they gave, so a
 * size cap or a crop holds whatever a link asks. A viewer the rule refuses, and an allowed one asking
 * for a user with no picture, get what `OZ_AUTH_USER_PICTURE_FALLBACK` says: a status (403, 404) or an
 * image, rendered the same way. No auth user of that type and id (a type not registered, an id
 * matching no row, a deleted row) is answered as a refusal, so a refused viewer learns nothing of
 * who exists, nor of who has a picture.
 */
class AuthUserPictureView extends WebView
{
	public const string MAIN_ROUTE = 'oz:auth-user-picture';

	public const string PATH = '/auth-users/{oz_auth_user_type}/{oz_auth_user_id}/picture[/{oz_picture_token}]';

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get(self::PATH, static fn (RouteInfo $ri): Response => self::handle($ri))
			->name(self::MAIN_ROUTE)
			->params([
				// A type is a table's morph type; an id what a repository finds an auth user by.
				'oz_auth_user_type' => Table::NAME_PATTERN,
				'oz_auth_user_id'   => '[A-Za-z0-9_-]+',
				'oz_picture_token'  => GetFilesView::FILTERS_PARAM_PATTERN,
			]);
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('AuthUsers', 'Auth users endpoints.');
		$doc->addOperationFromRoute(
			self::MAIN_ROUTE,
			'GET',
			'Get an Auth User Picture',
			[
				$doc->response(200, 'The picture, or the image answered in place of it.', [
					'image/*' => new MediaType([
						'schema' => $doc->type('string', null, ['format' => 'binary']),
					]),
				]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'AuthUsers.picture',
				'description' => 'An auth user\'s picture, by its type and id, to whom the project\'s rule'
					. ' allows, at the image tokens asked for (joined by `' . FS::FILTERS_SEPARATOR . '`). 403 to a'
					. ' viewer the rule refuses, 404 to an allowed one when the auth user has no picture, unless'
					. ' the project answers an image instead.',
			]
		);
	}

	/**
	 * Serves the picture of the route's auth user.
	 *
	 * @throws ForbiddenException when the rule refuses the viewer, or the original is asked for
	 * @throws NotFoundException  when an allowed viewer asks for an auth user with no picture
	 */
	public static function handle(RouteInfo $ri): Response
	{
		$context   = $ri->getContext();
		$requested = \array_values(\array_filter(
			\explode(FS::FILTERS_SEPARATOR, (string) $ri->param('oz_picture_token', ''))
		));

		// The stripped image is what a picture is: its original is never served here, to anyone.
		if (\in_array(GetFilesView::ORIGINAL_TOKEN, $requested, true)) {
			throw new ForbiddenException(null, ['_reason' => 'The original of an auth user picture is not served.']);
		}

		$owner = self::owner((string) $ri->param('oz_auth_user_type'), (string) $ri->param('oz_auth_user_id'));

		if (null === $owner || !self::access()->allows($owner, $context)) {
			return self::fallback(AuthUserPictureFallbackReason::REFUSED, $owner, $context, $requested)
				?? throw new ForbiddenException();
		}

		$file = self::pictureOf($owner);

		if (null === $file) {
			return self::fallback(AuthUserPictureFallbackReason::MISSING, $owner, $context, $requested)
				?? throw new NotFoundException();
		}

		$driver = FS::getStorage($file->getStorage());

		return self::renderPicture(
			$context,
			$file->getID() . ':' . $file->getKey(),
			// Never the key: it is the file's access secret.
			'file:' . $file->getID() . ':' . $file->getSize() . ':' . $file->getUpdatedAT(),
			static fn (array $tokens, Response $response): Response => FileFilters::apply(
				$file,
				$driver->getStream($file),
				$response,
				$tokens
			),
			$requested
		);
	}

	/**
	 * The rule `OZ_AUTH_USER_PICTURE_ACCESS` names.
	 */
	public static function access(): AuthUserPictureAccessInterface
	{
		$class = Settings::get('oz.auth.users', 'OZ_AUTH_USER_PICTURE_ACCESS', SelfOrAdminPictureAccess::class);

		if (!\is_string($class) || !\is_subclass_of($class, AuthUserPictureAccessInterface::class)) {
			throw new RuntimeException('OZ_AUTH_USER_PICTURE_ACCESS must name an AuthUserPictureAccessInterface class.');
		}

		return new $class();
	}

	/**
	 * The image `OZ_AUTH_USER_PICTURE_FALLBACK` answers for a reason, or null for the status.
	 */
	public static function fallbackImage(
		AuthUserPictureFallbackReason $reason,
		?AuthUserInterface $owner,
		Context $context
	): ?AuthUserPictureImage {
		$setting = Settings::get('oz.auth.users', 'OZ_AUTH_USER_PICTURE_FALLBACK');
		$value   = \is_array($setting) ? ($setting[$reason->value] ?? null) : $setting;

		if (null === $value) {
			return null;
		}

		if (!\is_string($value) || '' === $value) {
			throw new RuntimeException(
				'OZ_AUTH_USER_PICTURE_FALLBACK must be null, an image path or an AuthUserPictureFallbackInterface class,'
					. ' alone or under "refused" and "missing".',
				['_reason' => $reason->value]
			);
		}

		// A class name holds no "/" and no "."; a path to an image always holds one of them.
		if (1 === \preg_match('~^\\\?[A-Za-z_]\w*(?:\\\[A-Za-z_]\w*)*$~', $value)) {
			if (!\is_subclass_of($value, AuthUserPictureFallbackInterface::class)) {
				throw new RuntimeException(
					'OZ_AUTH_USER_PICTURE_FALLBACK names a class that is no AuthUserPictureFallbackInterface.',
					['_class' => $value, '_reason' => $reason->value]
				);
			}

			return new $value()->fallback($owner, $context, $reason);
		}

		return AuthUserPictureImage::fromPath($value);
	}

	/**
	 * The auth user of a type and an id, or null when there is none: a type no repository is
	 * registered for (checked first, so a visitor's guess is not logged as a misconfiguration), an
	 * id matching no row, or a deleted row.
	 */
	private static function owner(string $type, string $id): ?AuthUserInterface
	{
		if (null === AuthUsers::repositoryClass($type)) {
			return null;
		}

		$owner = AuthUsers::identify($type, $id, AuthUserInterface::IDENTIFIER_TYPE_ID);

		if ($owner instanceof ORMEntity && $owner::table()->isSoftDeletable() && $owner->{Table::COLUMN_SOFT_DELETED}) {
			return null;
		}

		return $owner;
	}

	/**
	 * The auth user's picture as `oz:files` would serve it, or null when there is none to serve.
	 */
	private static function pictureOf(AuthUserInterface $owner): ?OZFile
	{
		if (!$owner instanceof AuthUserPictureInterface) {
			return null;
		}

		$file = $owner->getAuthUserPicture();

		return null !== $file && $file->isValid() && FileScan::isServable($file) ? $file : null;
	}

	/**
	 * Answers the fallback for a reason, or null when the status answers.
	 *
	 * @param list<string> $requested
	 */
	private static function fallback(
		AuthUserPictureFallbackReason $reason,
		?AuthUserInterface $owner,
		Context $context,
		array $requested
	): ?Response {
		$image = self::fallbackImage($reason, $owner, $context);

		if (null === $image) {
			return null;
		}

		$identity = 'fallback:' . $reason->value . ':' . $image->identity();

		return self::renderPicture(
			$context,
			$identity,
			$identity,
			static fn (array $tokens, Response $response): Response => new ImageFileFilterHandler()->render(
				$identity,
				$image->getMime(),
				$image->stream(),
				$response,
				$tokens
			),
			$requested
		);
	}

	/**
	 * Renders a picture: the forced tokens first, then the ones asked for on what they gave.
	 *
	 * @param string                                    $identity  what the image is, for the renditions
	 * @param string                                    $etag_of   what the image is, for the validator
	 * @param Closure(list<string>, Response): Response $first     renders the image with tokens
	 * @param list<string>                              $requested the tokens asked for
	 */
	private static function renderPicture(
		Context $context,
		string $identity,
		string $etag_of,
		Closure $first,
		array $requested
	): Response {
		$forced          = \array_values(\array_map(
			'strval',
			(array) Settings::get('oz.auth.users', 'OZ_AUTH_USER_PICTURE_TOKENS', [])
		));
		$forced_canon    = ImageFilterTokens::normalize($forced);
		$requested_canon = ImageFilterTokens::normalize($requested);

		if ([] === $forced_canon) {
			// What `oz:files` answers for these tokens, byte for byte.
			$response = $first($requested, $context->getResponse());
		} else {
			$response = $first($forced, $context->getResponse());

			if ([] !== $requested_canon) {
				$mime     = $response->getHeaderLine('Content-Type');
				$vary     = $response->getHeaderLine('Vary');
				$response = new ImageFileFilterHandler()->render(
					$identity . '|' . \implode(',', $forced_canon) . '|' . $mime,
					$mime,
					FileStream::fromString($response->getBody()->getContents()),
					$context->getResponse(),
					$requested
				);

				if ('' !== $vary && !$response->hasHeader('Vary')) {
					$response = $response->withHeader('Vary', $vary);
				}
			}
		}

		$etag = \hash('xxh64', \implode('|', [
			$etag_of,
			\implode(',', $forced_canon),
			\implode(',', $requested_canon),
			$response->getHeaderLine('Content-Type'),
		]));

		// The answer depends on who asks: no shared cache keeps it, and the browser asks again each
		// time (a 304 when nothing changed), so a viewer who loses the right stops seeing it.
		return $response
			->withHeader('ETag', '"' . $etag . '"')
			->withHeader('Cache-Control', 'private, no-cache')
			->withHeader('Accept-Ranges', 'bytes');
	}
}
