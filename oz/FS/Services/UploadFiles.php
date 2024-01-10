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
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnverifiedUserException;
use OZONE\Core\Forms\Form;
use OZONE\Core\FS\FilesManager;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Router\Router;
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
	public const CHUNK_MAX_SIZE    = 1000 * 1000; // 1MB

	/**
	 * @param RouteInfo $r
	 *
	 * @throws UnverifiedUserException
	 */
	public function upload(RouteInfo $r): void
	{
		$r->getContext()->getUsers()->assertUserVerified();

		$files_ids = $r->getCleanFormField(self::PARAM_FILES);
		$ref       = self::newRef();

		$this->saveUploadedFilesIds($ref, $files_ids);

		$files = FS::getFilesByIDs($files_ids);

		$this->json()
			->setDone()
			->setDataKey(self::PARAM_REF, $ref)
			->setDataKey(self::PARAM_FILES, \iterator_to_array($files));
	}

	public function uploadChunkStart(RouteInfo $r): void
	{
		$name = $r->getCleanFormField(self::PARAM_NAME);
		$size = $r->getCleanFormField(self::PARAM_SIZE);
		$type = $r->getCleanFormField(self::PARAM_TYPE);

		$ref = self::newRef();

		$this->saveChunksInfo($ref, [
			'name'   => $name,
			'size'   => (int) $size,
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
		$chunk_path  = $r->getCleanFormField(self::PARAM_CHUNK);

		$info = $this->loadChunksInfo($ref);

		if (!$info) {
			throw new InvalidFormException(
				'Invalid ref.',
				[
					self::PARAM_REF => $ref,
				]
			);
		}

		$chunks = $info['chunks'];

		if (isset($chunks[$chunk_index])) {
			\unlink($chunk_path);

			// we are not throwing an exception here
			// because the client may have sent the same
			// chunk twice because of a network issue
			return;
		}

		/** @var UploadedFile $uploaded */
		$uploaded = $r->getUnsafeFormField(self::PARAM_CHUNK);

		$chunks[$chunk_index] = [
			'chunk_path' => (string) $chunk_path,
			'chunk_size' => $uploaded->getSize(),
		];

		$info['chunks'] = $chunks;

		$total_size    = 0;
		$expected_size = (int) $info['size'];

		foreach ($chunks as $c) {
			$total_size += $c['chunk_size'];
		}

		if ($total_size > $expected_size) {
			foreach ($chunks as $chunk) {
				\unlink($chunk['chunk_path']);
			}

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

			/** @var null|string $target_path */
			$target_path = null;
			$storage     = FS::getStorage();
			$fm          = new FilesManager();

			foreach ($chunks as $c) {
				$path = $c['chunk_path'];
				if (null === $target_path) {
					$target_path = $path;
				} else {
					$fm->append($target_path, FileStream::fromPath($path));
					\unlink($path);
				}
			}

			$upload = new UploadedFile(
				$target_path,
				$info['name'],
				$info['type'],
				$total_size,
				\UPLOAD_ERR_OK,
				false
			);

			$target_file = $storage->upload($upload);

			$target_file->save();

			/** @var string $file_id */
			$file_id = $target_file->getID();

			$this->saveUploadedFilesIds($ref, [$file_id]);

			$this->json()->setDataKey(self::PARAM_FILES, [$target_file]);

			return;
		}

		$this->saveChunksInfo($ref, $info);
	}

	public function deleteUpload(string $ref): void
	{
		$key   = self::getKey($ref);
		$state = $this->getContext()->requireState();
		$data  = $state->get($key);

		if (isset($data['chunks'])) {
			$chunks = $data['chunks'];

			foreach ($chunks as $c) {
				$path = $c['chunk_path'];
				if (\file_exists($path)) {
					\unlink($path);
				}
			}

			$state->remove($key);
		} elseif (\is_array($data)) {
			foreach ($data as $file_id) {
				FS::deleteFileByID($file_id);
			}

			$state->remove($key);
		}
	}

	/**
	 * @return Form
	 *
	 * @throws TypesException
	 */
	public static function uploadForm(): Form
	{
		$form = new Form();
		$form->file(self::PARAM_FILES)->multiple()
			->fileMinCount(1);

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
		$form->int(self::PARAM_CHUNK_INDEX, true)
			->unsigned();
		$form->file(self::PARAM_CHUNK, true)
			->temp()
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
				->form(self::uploadForm(...));

			$router->post('/chunk/start', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->uploadChunkStart($ri);

				return $s->respond();
			})
				->name(self::CHUNK_START_ROUTE)
				->form(self::uploadChunkStartForm(...));

			$router->post('/chunk/add', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->uploadChunkAdd($ri);

				return $s->respond();
			})
				->name(self::CHUNK_ADD_ROUTE)
				->form(self::uploadChunkAddForm(...));

			$router->delete('/chunk/:ref', static function (RouteInfo $ri) {
				$s = new static($ri);

				$s->deleteUpload($ri->param('ref'));

				return $s->respond();
			})->param('ref', '[a-zA-Z0-9_-]+');
		})->rateLimit(static function (RouteInfo $ri) {
			$has_user = $ri->getContext()->hasAuthenticatedUser();
			$key      = $has_user ? 'OZ_UPLOAD_AUTHENTICATED_RATE_LIMIT' : 'OZ_UPLOAD_ANONYMOUS_RATE_LIMIT';
			$limit    = Settings::get('oz.files', $key);

			return new IPRateLimit($ri, $limit, 60);
		});
	}

	/**
	 * Saves the uploaded files info.
	 *
	 * @param string   $ref
	 * @param string[] $files_ids
	 */
	private function saveUploadedFilesIds(string $ref, array $files_ids): void
	{
		$key   = self::getKey($ref);
		$state = $this->getContext()->requireState();
		$state->set($key, $files_ids);
	}

	/**
	 * Saves the chunks info.
	 *
	 * @param string                                                                                            $ref
	 * @param array{name:string, size:int, type:string, chunks:array<array{chunk_path:string, chunk_size:int}>} $info
	 */
	private function saveChunksInfo(string $ref, array $info): void
	{
		$key   = self::getKey($ref);
		$state = $this->getContext()->requireState();
		$state->set($key, $info);
	}

	/**
	 * Loads the chunks info.
	 *
	 * @param string $ref
	 *
	 * @return null|array{name:string, size:int, type:string, chunks:array<array{chunk_path:string, chunk_size:int}>}
	 */
	private function loadChunksInfo(string $ref): ?array
	{
		$key = self::getKey($ref);

		return $this->getContext()->requireState()->get($key);
	}

	/**
	 * Gets the state key.
	 *
	 * @param string $ref
	 *
	 * @return string
	 */
	private static function getKey(string $ref): string
	{
		return 'oz_upload_files.' . $ref;
	}

	/**
	 * Generates a new upload ref.
	 *
	 * @return string
	 */
	private static function newRef(): string
	{
		return Random::alphaNum(16);
	}
}
