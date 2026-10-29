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
use Gobl\DBAL\Types\TypeBigint;
use Gobl\DBAL\Types\TypeInt;
use OpenApi\Annotations\MediaType;
use Override;
use OZONE\Core\App\Service;
use OZONE\Core\App\Settings;
use OZONE\Core\Columns\Types\TypeFile;
use OZONE\Core\Columns\ValidatedFile;
use OZONE\Core\Exceptions\InvalidFormException;
use OZONE\Core\Exceptions\UnauthenticatedException;
use OZONE\Core\Forms\Form;
use OZONE\Core\FS\FileStream;
use OZONE\Core\FS\FS;
use OZONE\Core\Http\UploadedFile;
use OZONE\Core\REST\ApiDoc;
use OZONE\Core\Router\Rates\IPRateLimit;
use OZONE\Core\Router\RouteInfo;
use OZONE\Core\Stores\StoreEntries;
use OZONE\Core\Router\Router;
use OZONE\Core\Utils\Random;
use Throwable;

/**
 * Class UploadFiles.
 */
class UploadFiles extends Service
{
	public const string MAIN_ROUTE        = 'oz:upload';
	public const string CHUNK_START_ROUTE = 'oz:upload:chunk:start';
	public const string CHUNK_ADD_ROUTE   = 'oz:upload:chunk:add';
	public const string PARAM_REF         = 'ref';
	public const string PARAM_NAME        = 'name';
	public const string PARAM_SIZE        = 'size';
	public const string PARAM_TYPE        = 'type';
	public const string PARAM_CHUNK       = 'chunk';
	public const string PARAM_CHUNK_INDEX = 'chunk_index';
	public const string PARAM_FILES       = 'files';

	/**
	 * The default of `oz.files`: `OZ_UPLOAD_CHUNK_MAX_SIZE`, which a project may change.
	 *
	 * Read the setting through {@see self::chunkMaxSize()} rather than this constant.
	 */
	public const int CHUNK_MAX_SIZE    = 1000 * 1000; // 1MB

	/**
	 * The store branch every upload ref of a session lives under.
	 */
	public const string STORE_BRANCH = 'oz_upload_files';

	/**
	 * The length {@see self::newRef()} draws, and what {@see self::isRefShape()} expects.
	 */
	public const int REF_LENGTH = 16;

	/**
	 * @throws UnauthenticatedException
	 */
	public function upload(RouteInfo $r): void
	{
		$r->getContext()->getAuthUsers()->assertUserIsAuthenticated();

		// A validated file is an object holding the id (or the reference of a temporary file), and both
		// the state store and a query want that value, not the object.
		$files_ids = \array_map(
			static fn (ValidatedFile $file): string => (string) $file,
			$r->getCleanFormField(self::PARAM_FILES)
		);
		$ref = self::newRef();

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

		// Before the store is asked anything: a ref is part of a dot path, and a value with a dot,
		// a dash or a bracket in it is not a path php-utils reads -- it raises, which left this
		// route answering 500 to a ref that was simply never issued.
		self::assertRefShape($ref);

		/** @var ValidatedFile $chunk_file */
		$chunk_file = $r->getCleanFormField(self::PARAM_CHUNK);

		$info = $this->loadChunksInfo($ref);

		if (!$info) {
			throw $this->refRefused($ref);
		}

		$chunks = $info['chunks'];

		if (isset($chunks[$chunk_index])) {
			\unlink($chunk_file->getPath());

			// we are not throwing an exception here
			// because the client may have sent the same
			// chunk twice because of a network issue
			return;
		}

		/** @var UploadedFile $uploaded */
		$uploaded = $r->getUnsafeFormField(self::PARAM_CHUNK);

		$chunks[$chunk_index] = [
			// The TempFS reference, never a path: the chunks of one upload may be
			// received by processes that do not resolve it to the same place.
			'chunk_ref'  => (string) $chunk_file,
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
				\unlink(self::chunkPath($chunk['chunk_ref']));
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
			$fm          = FS::fromRoot();

			foreach ($chunks as $c) {
				$path = self::chunkPath($c['chunk_ref']);
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
		self::assertRefShape($ref);

		$uploads = $this->uploads();
		$data    = $uploads->get($ref);

		if (isset($data['chunks'])) {
			$chunks = $data['chunks'];

			foreach ($chunks as $c) {
				$path = self::chunkPath($c['chunk_ref']);
				if (\file_exists($path)) {
					\unlink($path);
				}
			}

			$uploads->remove($ref);
		} elseif (\is_array($data)) {
			foreach ($data as $file_id) {
				FS::deleteFileByID($file_id);
			}

			$uploads->remove($ref);
		}
	}

	/**
	 * The biggest chunk of a chunked upload the server accepts, in bytes.
	 *
	 * A client asks for it to slice a file accordingly; the form of `chunk/add` refuses a bigger one.
	 */
	public static function chunkMaxSize(): int
	{
		return (int) Settings::get('oz.files', 'OZ_UPLOAD_CHUNK_MAX_SIZE', self::CHUNK_MAX_SIZE);
	}

	/**
	 * @throws TypesException
	 */
	public static function uploadForm(): Form
	{
		$form = new Form();
		$form->file(self::PARAM_FILES)
			->configureType(static fn (TypeFile $t) => $t->multiple()->fileMinCount(1));

		return $form;
	}

	/**
	 * @throws TypesException
	 */
	public static function uploadChunkStartForm(): Form
	{
		$form     = new Form();
		$min_size = '1';
		$max_size = Settings::get('oz.files', 'OZ_UPLOAD_FILE_MAX_SIZE');

		$form->string(self::PARAM_NAME, true);
		$form->bigint(self::PARAM_SIZE, true)
			->configureType(static fn (TypeBigint $t) => $t->unsigned()->min($min_size)->max($max_size));
		$form->string(self::PARAM_TYPE, true);

		return $form;
	}

	/**
	 * @throws TypesException
	 */
	public static function uploadChunkAddForm(): Form
	{
		$form = new Form();

		$form->string(self::PARAM_REF, true);
		$form->int(self::PARAM_CHUNK_INDEX, true)
			->configureType(static fn (TypeInt $t) => $t->unsigned());
		$form->file(self::PARAM_CHUNK, true)
			->configureType(static fn (TypeFile $t) => $t->temp()->fileMaxSize(self::chunkMaxSize()));

		return $form;
	}

	/**
	 * @throws Throwable
	 */
	#[Override]
	public static function registerRoutes(Router $router): void
	{
		$router->group('/upload', static function (Router $router): void {
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

			return new IPRateLimit($ri, $limit, (int) Settings::get('oz.files', 'OZ_UPLOAD_RATE_INTERVAL'));
		});
	}

	#[Override]
	public static function apiDoc(ApiDoc $doc): void
	{
		$tag           = $doc->addTag('Files', 'File upload endpoints.');
		$file_schema   = $doc->type('string', null, ['format' => 'binary']);
		$files_schema  = $doc->array($file_schema, ['description' => 'The uploaded files.']);
		$ref_schema    = $doc->string('The upload reference.');

		$op = $doc->addOperationFromRoute(self::MAIN_ROUTE, 'POST', 'Upload Files', [
			$doc->success([
				self::PARAM_REF   => $ref_schema,
				self::PARAM_FILES => $files_schema,
			]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Files.upload',
			'description' => 'Upload one or more files.',
		]);
		$op->requestBody = $doc->requestBody([
			'multipart/form-data' => new MediaType([
				'schema' => $doc->object([
					self::PARAM_FILES => $doc->array($file_schema, ['description' => 'Files to upload.']),
				]),
			]),
		]);

		$doc->addOperationFromRoute(self::CHUNK_START_ROUTE, 'POST', 'Start Chunked Upload', [
			$doc->success([self::PARAM_REF => $ref_schema]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Files.chunkStart',
			'description' => 'Initialize a chunked file upload session.',
		]);

		$op = $doc->addOperationFromRoute(self::CHUNK_ADD_ROUTE, 'POST', 'Add Chunk', [
			$doc->success([self::PARAM_FILES => $files_schema]),
		], [
			'tags'        => [$tag->name],
			'operationId' => 'Files.chunkAdd',
			'description' => 'Upload a single chunk for an ongoing chunked upload.',
		]);
		$op->requestBody = $doc->requestBody([
			'multipart/form-data' => new MediaType([
				'schema' => $doc->object([
					self::PARAM_REF         => $ref_schema,
					self::PARAM_CHUNK_INDEX => $doc->integer('The zero-based chunk index.'),
					self::PARAM_CHUNK       => $file_schema,
				]),
			]),
		]);
	}

	/**
	 * Saves the uploaded files info.
	 *
	 * @param string[] $files_ids
	 */
	private function saveUploadedFilesIds(string $ref, array $files_ids): void
	{
		$this->uploads()->set($ref, $files_ids);
	}

	/**
	 * Saves the chunks info.
	 *
	 * @param array{name:string, size:int, type:string, chunks:array<array{chunk_ref:string, chunk_size:int}>} $info
	 */
	private function saveChunksInfo(string $ref, array $info): void
	{
		$this->uploads()->set($ref, $info);
	}

	/**
	 * Loads the chunks info.
	 *
	 * @return null|array{name:string, size:int, type:string, chunks:array<array{chunk_ref:string, chunk_size:int}>}
	 */
	private function loadChunksInfo(string $ref): ?array
	{
		$info = $this->uploads()->get($ref);

		// The same key holds the ids of the files an upload produced once it is over (`upload`, and
		// `chunk/add` on the last chunk), so what is there is not always chunks. Only chunks answer
		// here; {@see self::refRefused()} tells the other shape apart.
		return \is_array($info) && \is_array($info['chunks'] ?? null) ? $info : null;
	}

	/**
	 * The uploads this session has in flight, one entry per ref.
	 *
	 * A ref is an entry's name and is written as one, so a request writing its own ref cannot take
	 * the refs another request is holding with it.
	 */
	private function uploads(): StoreEntries
	{
		return $this->getContext()
			->requireAuthStore()
			->entries(self::STORE_BRANCH);
	}

	/**
	 * Generates a new upload ref.
	 */
	private static function newRef(): string
	{
		return Random::alphaNum(self::REF_LENGTH);
	}

	/**
	 * Whether a value is the shape {@see self::newRef()} draws.
	 *
	 * `Random::alphaNum()` draws from {@see Random::CHARS_ALPHA_NUM}, which is what `ctype_alnum()`
	 * accepts, so a value that fails this was never issued by this server.
	 */
	private static function isRefShape(string $value): bool
	{
		return self::REF_LENGTH === \strlen($value) && \ctype_alnum($value);
	}

	/**
	 * Refuses a value that is not the shape a ref is drawn in, before it reaches a dot path.
	 *
	 * @throws InvalidFormException
	 */
	private static function assertRefShape(string $ref): void
	{
		if (!self::isRefShape($ref)) {
			throw new InvalidFormException('OZ_UPLOAD_REF_UNKNOWN', [self::PARAM_REF => $ref]);
		}
	}

	/**
	 * Says which of the three remaining things happened to a ref of the right shape that the store
	 * holds no chunks for.
	 *
	 * Four cases answered `Invalid ref.`, and only one of them is a bug: a caller could not tell a
	 * ref it never had from one the server lost, and neither could we when one was reported.
	 */
	private function refRefused(string $ref): InvalidFormException
	{
		$data = [self::PARAM_REF => $ref];
		$held = $this->uploads()->all();

		if ([] === $held) {
			// Nothing of this kind is in this store: the session is not the one the ref was issued
			// to, or it expired and the client is on a new one.
			return new InvalidFormException('OZ_UPLOAD_REF_SESSION_CHANGED', $data);
		}

		if (isset($held[$ref])) {
			// The ref is there and holds something other than chunks: the ids of the files an upload
			// that is over produced. A chunk sent under it is a client carrying on past the end, not
			// a ref that went missing.
			return new InvalidFormException('OZ_UPLOAD_REF_DONE', $data);
		}

		// The store holds other refs and not this one, so it was issued to this session and is
		// gone. Logged, because unlike the other three this is ours to explain.
		oz_logger()->error('OZ_UPLOAD_REF_LOST', [
			'_ref'       => $ref,
			'_held_refs' => \array_keys($held),
		]);

		return new InvalidFormException('OZ_UPLOAD_REF_LOST', $data);
	}

	/**
	 * Resolves the current path of a stored chunk reference.
	 */
	private static function chunkPath(string $chunk_ref): string
	{
		return ValidatedFile::forTempValue($chunk_ref)->getPath();
	}
}
