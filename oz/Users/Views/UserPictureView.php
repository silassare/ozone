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

namespace OZONE\Core\Users\Views;

use Closure;
use Gobl\ORM\ORMOptions;
use OpenApi\Annotations\MediaType;
use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Db\OZUser;
use OZONE\Core\Db\OZUsersQuery;
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
use OZONE\Core\Users\Picture\Enums\UserPictureFallbackReason;
use OZONE\Core\Users\Picture\Interfaces\UserPictureAccessInterface;
use OZONE\Core\Users\Picture\Interfaces\UserPictureFallbackInterface;
use OZONE\Core\Users\Picture\SelfOrAdminPictureAccess;
use OZONE\Core\Users\Picture\UserPictureImage;
use OZONE\Core\Web\WebView;

/**
 * A user's picture, served only to whom `OZ_USER_PICTURE_ACCESS` allows: no file key is ever in its
 * address, so a link copied out works only for them.
 *
 * It is served as `oz:files` serves an image, through the same filter chain: the tokens of the
 * address (sizes, effects, a project's own), the metadata always stripped, and never the original.
 * `OZ_USER_PICTURE_TOKENS` are rendered first, and the tokens asked for on what they gave, so a
 * size cap or a crop holds whatever a link asks. A viewer the rule refuses, and an allowed one asking
 * for a user with no picture, get what `OZ_USER_PICTURE_FALLBACK` says: a status (403, 404) or an
 * image, rendered the same way. No user by that id is answered as a refusal, so a refused viewer
 * learns nothing of who exists, nor of who has a picture.
 */
class UserPictureView extends WebView
{
	public const string MAIN_ROUTE = 'oz:user-picture';

	public const string PATH = '/users/{oz_user_id}/picture[/{oz_picture_token}]';

	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router
			->get(self::PATH, static fn (RouteInfo $ri): Response => self::handle($ri))
			->name(self::MAIN_ROUTE)
			->params([
				'oz_user_id'       => '[0-9]+',
				'oz_picture_token' => GetFilesView::FILTERS_PARAM_PATTERN,
			]);
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag = $doc->addTag('Users', 'Users endpoints.');
		$doc->addOperationFromRoute(
			self::MAIN_ROUTE,
			'GET',
			'Get a User Picture',
			[
				$doc->response(200, 'The picture, or the image answered in place of it.', [
					'image/*' => new MediaType([
						'schema' => $doc->type('string', null, ['format' => 'binary']),
					]),
				]),
			],
			[
				'tags'        => [$tag->name],
				'operationId' => 'Users.picture',
				'description' => 'A user\'s picture, to whom the project\'s rule allows, at the image tokens'
					. ' asked for (joined by `' . FS::FILTERS_SEPARATOR . '`). 403 to a viewer the rule refuses,'
					. ' 404 to an allowed one when the user has no picture, unless the project answers an'
					. ' image instead.',
			]
		);
	}

	/**
	 * Serves the picture of the route's user.
	 *
	 * @throws ForbiddenException when the rule refuses the viewer, or the original is asked for
	 * @throws NotFoundException  when an allowed viewer asks for a user with no picture
	 */
	public static function handle(RouteInfo $ri): Response
	{
		$context   = $ri->getContext();
		$requested = \array_values(\array_filter(
			\explode(FS::FILTERS_SEPARATOR, (string) $ri->param('oz_picture_token', ''))
		));

		// The stripped image is what a picture is: its original is never served here, to anyone.
		if (\in_array(GetFilesView::ORIGINAL_TOKEN, $requested, true)) {
			throw new ForbiddenException(null, ['_reason' => 'The original of a user picture is not served.']);
		}

		$owner = self::owner((string) $ri->param('oz_user_id'));

		if (null === $owner || !self::access()->allows($owner, $context)) {
			return self::fallback(UserPictureFallbackReason::REFUSED, $owner, $context, $requested)
				?? throw new ForbiddenException();
		}

		$file = self::pictureOf($owner);

		if (null === $file) {
			return self::fallback(UserPictureFallbackReason::MISSING, $owner, $context, $requested)
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
	 * The rule `OZ_USER_PICTURE_ACCESS` names.
	 */
	public static function access(): UserPictureAccessInterface
	{
		$class = Settings::get('oz.users', 'OZ_USER_PICTURE_ACCESS', SelfOrAdminPictureAccess::class);

		if (!\is_string($class) || !\is_subclass_of($class, UserPictureAccessInterface::class)) {
			throw new RuntimeException('OZ_USER_PICTURE_ACCESS must name a UserPictureAccessInterface class.');
		}

		return new $class();
	}

	/**
	 * The image `OZ_USER_PICTURE_FALLBACK` answers for a reason, or null for the status.
	 */
	public static function fallbackImage(
		UserPictureFallbackReason $reason,
		?OZUser $owner,
		Context $context
	): ?UserPictureImage {
		$setting = Settings::get('oz.users', 'OZ_USER_PICTURE_FALLBACK');
		$value   = \is_array($setting) ? ($setting[$reason->value] ?? null) : $setting;

		if (null === $value) {
			return null;
		}

		if (!\is_string($value) || '' === $value) {
			throw new RuntimeException(
				'OZ_USER_PICTURE_FALLBACK must be null, an image path or a UserPictureFallbackInterface class,'
					. ' alone or under "refused" and "missing".',
				['_reason' => $reason->value]
			);
		}

		// A class name holds no "/" and no "."; a path to an image always holds one of them.
		if (1 === \preg_match('~^\\\?[A-Za-z_]\w*(?:\\\[A-Za-z_]\w*)*$~', $value)) {
			if (!\is_subclass_of($value, UserPictureFallbackInterface::class)) {
				throw new RuntimeException(
					'OZ_USER_PICTURE_FALLBACK names a class that is no UserPictureFallbackInterface.',
					['_class' => $value, '_reason' => $reason->value]
				);
			}

			return new $value()->fallback($owner, $context, $reason);
		}

		return UserPictureImage::fromPath($value);
	}

	/**
	 * The user of an id, or null when there is none.
	 */
	private static function owner(string $id): ?OZUser
	{
		/** @var null|OZUser */
		return new OZUsersQuery()
			->whereIdIs($id)
			->whereIsNotDeleted()
			->find(ORMOptions::makePaginated(1))
			->fetchClass();
	}

	/**
	 * The user's picture as `oz:files` would serve it, or null when there is none to serve.
	 */
	private static function pictureOf(OZUser $owner): ?OZFile
	{
		$pic = $owner->getPic();

		if (null === $pic || $pic->isTemporary()) {
			return null;
		}

		$file = $pic->loadFile();

		return null !== $file && $file->isValid() && FileScan::isServable($file) ? $file : null;
	}

	/**
	 * Answers the fallback for a reason, or null when the status answers.
	 *
	 * @param list<string> $requested
	 */
	private static function fallback(
		UserPictureFallbackReason $reason,
		?OZUser $owner,
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
			(array) Settings::get('oz.users', 'OZ_USER_PICTURE_TOKENS', [])
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
