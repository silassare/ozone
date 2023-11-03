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

namespace OZONE\Core\FS\Services;

use Gobl\DBAL\Types\Exceptions\TypesException;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Db\OZFile;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Forms\Form;
use OZONE\Core\FS\FS;
use OZONE\Core\FS\Interfaces\StorageInterface;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Hasher;
use OZONE\Core\Utils\Random;
use Throwable;

/**
 * Class UploadFiles.
 */
class UploadFiles extends Service
{
	public const MAIN_ROUTE        = 'oz:upload';
	public const CHUNK_START_ROUTE = 'oz:upload:chunk:start';
	public const CHUNK_ADD_ROUTE   = 'oz:upload:chunk:add';
	public const PARAM_REF         = 'ref';
	public const PARAM_NAME        = 'name';
	public const PARAM_SIZE        = 'size';
	public const PARAM_TYPE        = 'type';
	public const PARAM_CHUNK       = 'chunk';
	public const PARAM_CHUNK_INDEX = 'chunk_index';
	public const PARAM_FILES       = 'files';

	public const CHUNK_MAX_SIZE   = 1024 * 1024; // 1MB
	public const CHUNK_FILE_LABEL = 'oz:upload:chunk';

	/**
	 * @param RouteInfo $r
	 *
	 * @throws UnverifiedUserException
	 */
	public function upload(RouteInfo $r): void
	{
		$r->getContext()->getUsers()->assertUserVerified();

		$files = $r->getCleanFormField(self::PARAM_FILES);

		$this->json()
			->setDone()
			->setDataKey(self::PARAM_FILES, $files);
	}

	public function uploadChunkStart(RouteInfo $r): void
	{
		$name = $r->getCleanFormField(self::PARAM_NAME);
		$size = $r->getCleanFormField(self::PARAM_SIZE);
		$type = $r->getCleanFormField(self::PARAM_TYPE);

		$ref = Hasher::hash64($name . $size . $type . Random::string());

		$key = self::getKey($ref);

		$session = $r->getContext()->session();
		$session->state()->set($key, [
			'name'   => $name,
			'size'   => $size,
			'type'   => $type,
			'chunks' => [],
		]);

		$this->json()
			->setDone()
			->setDataKey(self::PARAM_REF, $ref);
	}

	/**
	 * @throws Throwable
	 */
	public function uploadChunkAdd(RouteInfo $r): void
	{
		$ref         = $r->getCleanFormField(self::PARAM_REF);
		$chunk_index = $r->getCleanFormField(self::PARAM_CHUNK_INDEX);
		$chunk       = $r->getCleanFormField(self::PARAM_CHUNK);

		$key = self::getKey($ref);

		$session = $r->getContext()->session();
		$state   = $session->state();
		$info    = $state->get($key);

		if (!$info) {
			throw new InvalidFormException(
				'Invalid ref.',
				[
					self::PARAM_REF => $ref,
				]
			);
		}

		/** @var array<int, array{chunk:string,   chunk_size:int}> $chunks */
		$chunks = $info['chunks'];

		if (isset($chunks[$chunk_index])) {
			FS::deleteFileByID($chunk);

			// we are not throwing an exception here
			// because the client may have sent the same
			// chunk twice because of a network issue
			return;
		}

		/** @var UploadedFile $uploaded */
		$uploaded = $r->getUnsafeFormField(self::PARAM_CHUNK);

		$chunks[$chunk_index] = [
			'chunk'      => $chunk,
			'chunk_size' => $uploaded->getSize(),
		];

		$info['chunks'] = $chunks;

		$total_size    = 0;
		$expected_size = $info['size'];

		foreach ($chunks as $c) {
			$total_size += $c['chunk_size'];
		}

		if ($total_size > $expected_size) {
			$this->deleteChunks($r, $ref);

			throw new InvalidFormException(
				'Total size of chunks is greater than the file size.',
				[
					self::PARAM_REF         => $ref,
					self::PARAM_CHUNK_INDEX => $chunk_index,
				]
			);
		}

		if ($total_size === $expected_size) {
			// sort by chunk index
			\ksort($chunks);

			/** @var null|OZFile $target_file */
			$target_file = null;

			/** @var StorageInterface $target_storage */
			$target_storage = null;

			foreach ($chunks as $c) {
				$chunk_file = FS::getFileByID($c['chunk']);

				if ($chunk_file) {
					$storage = FS::getStorage($chunk_file->storage);
					$stream  = $storage->getStream($chunk_file);

					if (null === $target_file) {
						$target_file    = $storage->saveStream($stream, $info['type'], $info['name']);
						$target_storage = $storage;
					} else {
						$target_storage->append($target_file, $stream);
					}

					$storage->delete($chunk_file);
				}
			}

			$this->json()->setDataKey('file', $target_file->getID());

			$state->remove($key);
		}

		$state->set($key, $info);
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	public static function uploadForm(): Form
	{
		$max_file_count = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_COUNT');
		$max_file_size  = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_SIZE');
		$max_total_size = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_TOTAL_SIZE');

		$form = new Form();

		$form->file(self::PARAM_FILES)->multiple()
			->fileMinCount(1)
			->fileMaxCount($max_file_count)
			->fileMinSize(1)
			->fileMaxSize($max_file_size)
			->fileUploadTotalSize($max_total_size);

		return $form;
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	public static function uploadChunkStartForm(): Form
	{
		$form     = new Form();
		$min_size = '1';
		$max_size = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_SIZE');

		$form->string(self::PARAM_NAME, true);
		$form->bigint(self::PARAM_SIZE, true)
			->unsigned()->min($min_size)->max($max_size);
		$form->string(self::PARAM_TYPE, true);

		return $form;
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	public static function uploadChunkAddForm(): Form
	{
		$form = new Form();

		$form->string(self::PARAM_REF, true);
		$form->int(self::PARAM_CHUNK_INDEX, true)->min(1);
		$form->file(self::PARAM_CHUNK, true)
			->fileLabel(self::CHUNK_FILE_LABEL)
			->fileMaxSize(self::CHUNK_MAX_SIZE);

		return $form;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @throws Throwable
	 */
	public static function registerRoutes(Router $router): void
	{
		$router->group('/upload', static function (Router $router) {
			$router
				->post('/', static function (RouteInfo $ri) {
					$s = new static($ri);

					$s->upload($ri);

					return $s->respond();
				})->name(self::MAIN_ROUTE)
				->form(self::uploadForm());

			$router->post('/chunk/start', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->uploadChunkStart($ri);

				return $s->respond();
			})
				->name(self::CHUNK_START_ROUTE)
				->form(self::uploadChunkStartForm());

			$router->post('/chunk/add', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->uploadChunkAdd($ri);

				return $s->respond();
			})
				->name(self::CHUNK_ADD_ROUTE)
				->form(self::uploadChunkAddForm());

			$router->delete('/chunk/:ref', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->deleteChunks($ri, $ri->param('ref'));

				return $s->respond();
			})->param('ref', '[a-zA-Z0-9_-]+');
		})->rateLimit(static function (RouteInfo $ri) {
			if ($ri->getContext()->hasAuthenticatedUser()) {
				return new IPRateLimit($ri, 100, 60);
			}

			return new IPRateLimit($ri, 10, 60);
		});
	}

	/**
	 * @param string $ref
	 *
	 * @return string
	 */
	private static function getKey(string $ref): string
	{
		return 'oz_upload_files.' . $ref;
	}

	private function deleteChunks(RouteInfo $r, string $ref): void
	{
		$key = self::getKey($ref);

		$session = $r
			->getContext()
			->session();

		$state = $session->state();
		$info  = $state->get($key);

		if ($info) {
			/** @var array<int, array{chunk:string, chunk_size:int}> $chunks */
			$chunks = $info->get($key . '.chunks');

			foreach ($chunks as $c) {
				FS::deleteFileByID($c['chunk']);
			}

			$state->remove($key);
		}
	}
}
