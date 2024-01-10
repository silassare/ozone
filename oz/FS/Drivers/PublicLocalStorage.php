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

use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\Uri;

/**
 * Class PublicLocalStorage.
 */
final class PublicLocalStorage extends AbstractLocalStorage
{
	/**
	 * {@inheritDoc}
	 */
	public static function get(string $name): self
	{
		return new self($name);
	}

	/**
	 * {@inheritDoc}
	 */
	public function publicUri(Context $context, OZFile $file): Uri
	{
		$abs_path = $this->require($file->getRef());

		$allow_direct_access = (bool) Settings::get('oz.files', 'OZ_PUBLIC_URI_DIRECT_ACCESS_ENABLED');

		if (!$allow_direct_access) {
			return FS::buildFileUri($context, $file);
		}

		$relative_path = app()->getPublicFilesDir()->relativePath($abs_path);

		return $context->buildUri($relative_path);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function uploadsDir(): FilesManager
	{
		return app()
			->getPublicFilesDir()
			->cd('uploads' . DS, true);
	}
}
