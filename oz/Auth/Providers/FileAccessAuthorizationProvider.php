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

namespace OZONE\Core\Auth\Providers;

use OZONE\Core\App\Context;
use OZONE\Core\Db\OZAuth;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\FS;

/**
 * Class FileAccessAuthorizationProvider.
 */
class FileAccessAuthorizationProvider extends AuthorizationProvider
{
	public const NAME = 'auth:provider:file';

	/**
	 * FileAccessAuthorizationProvider constructor.
	 */
	public function __construct(Context $context, protected OZFile $file)
	{
		parent::__construct($context);
	}

	/**
	 * Gets the file.
	 *
	 * @return OZFile
	 */
	public function getFile(): OZFile
	{
		return $this->file;
	}

	/**
	 * {@inheritDoc}
	 */
	public static function resolve(Context $context, OZAuth $auth): self
	{
		$payload = $auth->getPayload();
		$id      = $payload['file_id'] ?? null;

		if (empty($id)) {
			throw (new RuntimeException('Missing "file_id" in payload.'))->suspectObject($payload);
		}

		$file = FS::getFileByID($id);

		if (!$file || !$file->isValid()) {
			throw (new RuntimeException('Unable to load file using provided "file_id".'))->suspectObject($payload);
		}

		return new self($context, $file);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPayload(): array
	{
		return [
			'file_id' => $this->file->getID(),
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getName(): string
	{
		return self::NAME;
	}
}
