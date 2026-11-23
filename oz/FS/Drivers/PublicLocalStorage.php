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

namespace OZONE\Core\FS\Drivers;

use Override;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\Uri;
use Uri\Rfc3986\Uri as Rfc3986Uri;

/**
 * Class PublicLocalStorage.
 *
 * Public files live in the application's public files directory (`data/root/static`), under
 * `uploads/`. A direct link to one is `OZ_PUBLIC_FILES_BASE_URL` followed by its path in that
 * directory: OZone cannot know where a web server, a subdomain or a CDN exposes the directory, and
 * the `static` link in a scope's document root reaches that scope's own directory, not this one.
 */
final class PublicLocalStorage extends AbstractLocalStorage
{
	/**
	 * The path of a base URL, or the whole of a base that is a path: segments that are neither empty
	 * nor `.` nor `..`, each followed by `/`.
	 */
	private const string BASE_PATH_PATTERN = '~^/(?:(?!\.{1,2}/)[A-Za-z0-9\-._\~!$&\'()*+,;=:@%]+/)*$~';

	#[Override]
	public static function get(string $name): static
	{
		return new self($name);
	}

	/**
	 * The base of direct links to public files, `OZ_PUBLIC_FILES_BASE_URL` (`oz.files`), or null when
	 * it is not set.
	 *
	 * @throws RuntimeException when the value is neither an absolute http(s) URL nor a path, both
	 *                          ending with `/`
	 */
	public static function baseUrl(): ?string
	{
		$base = Settings::get('oz.files', 'OZ_PUBLIC_FILES_BASE_URL');

		if (null === $base || '' === $base) {
			return null;
		}

		if (!\is_string($base) || !self::isBaseUrl($base)) {
			throw (new RuntimeException(\sprintf(
				'OZ_PUBLIC_FILES_BASE_URL (oz.files) should be an absolute http(s) URL or a path starting'
					. ' with "/", ending with "/" and with no query, fragment or credentials: "%s" is not.',
				\is_string($base) ? $base : \get_debug_type($base)
			)))->suspectConfig('oz.files', 'OZ_PUBLIC_FILES_BASE_URL');
		}

		return $base;
	}

	#[Override]
	public function publicUri(Context $context, OZFile $file): Uri
	{
		$abs_path = $this->require($file->getRef());

		$allow_direct_access = (bool) Settings::get('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED');

		if (!$allow_direct_access) {
			return FS::buildFileUri($context, $file);
		}

		$base = self::baseUrl();

		if (null === $base) {
			throw (new RuntimeException(
				'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED (oz.files) is on and OZ_PUBLIC_FILES_BASE_URL is not set:'
					. ' map the public files directory in the web server, a subdomain or a CDN, and set'
					. ' OZ_PUBLIC_FILES_BASE_URL to the URL it answers on.'
			))->suspectConfig('oz.files', 'OZ_PUBLIC_FILES_BASE_URL');
		}

		// A URL path, whatever the directory separator of the server.
		$path = \str_replace(DS, '/', app()->getPublicFilesDir()->relativePath($abs_path));

		if (!\str_starts_with($base, '/')) {
			return Uri::createFromString($base . $path);
		}

		// A path is read from the root of the host being served, not under the base path of the app:
		// it says where the web server exposes the directory, which the app's own path has no part in.
		return $context->buildUri('/')
			->withPath($base . $path);
	}

	#[Override]
	protected function uploadsDir(): FilesManager
	{
		return app()
			->getPublicFilesDir()
			->cd('uploads' . DS, true);
	}

	/**
	 * Whether a value can be a base of direct links: an absolute http(s) URL or a path, ending with
	 * `/`, so a file's path is appended as it is.
	 */
	private static function isBaseUrl(string $base): bool
	{
		if (\str_starts_with($base, '/')) {
			return 1 === \preg_match(self::BASE_PATH_PATTERN, $base);
		}

		if (!Uri::isStrictUrl($base)) {
			return false;
		}

		$parsed = Rfc3986Uri::parse($base);

		return null !== $parsed
			&& null === $parsed->getRawUserInfo()
			&& null === $parsed->getRawQuery()
			&& null === $parsed->getRawFragment()
			&& 1 === \preg_match(self::BASE_PATH_PATTERN, $parsed->getRawPath());
	}
}
