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

namespace OZONE\Core\FS;

use Exception;
use OZONE\Core\App\Context;
use OZONE\Core\App\Settings;
use OZONE\Core\Exceptions\NotFoundException;
use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\Http\Body;
use OZONE\Core\Http\Response;

/**
 * Class FilesServer.
 *
 * @deprecated
 */
class FilesServer
{
	/**
	 * @param Context $context
	 * @param array   $params
	 *
	 * @return Response
	 *
	 * @throws Exception
	 * @throws NotFoundException
	 */
	public function serve(Context $context, array $params): Response
	{
		if (empty($params['file_src']) || empty($params['file_name']) || empty($params['file_mime']) || empty($params['file_quality'])) {
			throw new NotFoundException();
		}

		\set_time_limit(0);

		$response     = $context->getResponse();
		$src          = $params['file_src'];
		$file_name    = $params['file_name'];
		$file_mime    = $params['file_mime'];
		$file_quality = (int) $params['file_quality'];

		if (!\file_exists($src) || !\is_file($src) || !\is_readable($src)) {
			throw new NotFoundException();
		}

		oz_logger()->info('Find a way to use nginx directive "internal" feature to serve file.');

		/* https://clubhouse.io/developer-how-to/how-to-use-internal-redirects-in-nginx/
		if (Settings::get('oz.files', 'OZ_USE_NGINX_FILE_FEATURE')){
		// TODO Full rewrite
		}
		*/
		$size = \filesize($src);

		// close the current session
		if (\session_id()) {
			\session_write_close();
		}

		// cleans output buffer
		if (\ob_get_contents()) {
			\ob_clean();
		}

		$response = $response->withHeader('Pragma', 'public')
			->withHeader('Expires', '99936000')
			->withHeader('Cache-Control', 'public,max-age=99936000')
			->withHeader('Content-Transfer-Encoding', 'binary')
			->withHeader('Content-Length', (string) $size)
			->withHeader('Content-type', $file_mime)
			->withHeader('Content-Disposition', "attachment; filename=\"{$file_name}\";");

		if ($file_quality > 0 && $file_quality < 3) {
			// thumbnails
			// 0: 'original file'
			// 1: 'low quality',
			// 2: 'normal quality',
			// 3: 'high quality'
			$jpeg_quality_array = [60, 80, 100];
			$jpeg_quality       = $jpeg_quality_array[$file_quality - 1];
			$img_utils_obj      = new ImagesUtils($src);

			if ($img_utils_obj->load()) {
				$max_size = Settings::get('oz.files', 'OZ_THUMBNAIL_MAX_SIZE');
				$advice   = $img_utils_obj->adviceBestSize($max_size, $max_size);
				$content  = $img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
					->getString('image/jpeg', $jpeg_quality);
				$body     = Body::fromString($content);

				return $response->withBody($body);
			}
		}

		$body = Body::fromPath($src);

		return $response->withBody($body);
	}

	/**
	 * starts a file download server.
	 *
	 * @param array $options      the server options
	 * @param bool  $allow_resume should we support download resuming
	 * @param bool  $is_stream    is it a stream
	 *
	 * @throws NotFoundException
	 */
	public static function startDownloadServer(
		array $options,
		bool $allow_resume = false,
		bool $is_stream = false
	): never {
		// TODO Full rewrite

		// - turn off compression on the server
		if (\function_exists('apache_setenv')) {
			@apache_setenv('no-gzip', '1');
		}

		@\ini_set('zlib.output_compression', 'Off');

		$mime_default = 'application/octet-stream';
		$file_src     = $options['file_src'];
		$file_name    = $options['file_name'] ?? \basename($file_src);
		$expires_date = $options['file_expires_date'] ?? -1;
		$mime         = $options['file_mime'] ?? $mime_default;
		$range        = '';

		// make sure the file exists
		if (!\is_file($file_src)) {
			throw new NotFoundException();
		}

		$file_size = \filesize($file_src);
		$file      = @\fopen($file_src, 'rb');

		// make sure file open success
		if (!$file) {
			throw new RuntimeException('OZ_FILE_OPEN_ERROR', [$file_src]);
		}

		// sets the headers, prevent caching
		\header('Pragma: public');
		\header('Expires: ' . $expires_date);
		\header('Cache-Control: public, max-age=' . $expires_date . ', must-revalidate, post-check=0, pre-check=0');

		// sets appropriate headers for attachment or streamed file
		if (!$is_stream) {
			\header("Content-Disposition: attachment; filename=\"{$file_name}\";");
		} else {
			\header('Content-Disposition: inline;');
			\header('Content-Transfer-Encoding: binary');
		}

		// sets the mime type
		\header('Content-Type: ' . $mime);

		// checks if http_range is sent by browser (or download manager)
		if ($allow_resume && isset($_SERVER['HTTP_RANGE'])) {
			@[$size_unit, $range_orig] = \explode('=', $_SERVER['HTTP_RANGE'], 2);

			if ('bytes' === $size_unit) {
				// multiple ranges could be specified at the same time, but for simplicity only serve the first range
				// http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
				@[$range] = \explode(',', $range_orig, 2);
			} else {
				\header('HTTP/1.1 416 Requested Range Not Satisfiable');

				exit;
			}
		}

		// figure out download piece from range (if set)
		@[$seek_start, $seek_end] = \explode('-', $range, 2);

		// sets start and end based on range (if set), else set defaults
		// also check for invalid ranges.
		$seek_end   = (empty($seek_end)) ? ($file_size - 1) : \min(\abs((int) $seek_end), $file_size - 1);
		$seek_start = (empty($seek_start) || $seek_end < \abs((int) $seek_start))
			? 0 : \max(\abs((int) $seek_start), 0);

		// Only send partial content header if downloading a piece of the file (IE workaround)
		if ($seek_start > 0 || $seek_end < ($file_size - 1)) {
			$chunk_size = ($seek_end - $seek_start + 1);

			\header('HTTP/1.1 206 Partial Content');
			\header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size);
			\header("Content-Length: {$chunk_size}");
		} else {
			\header("Content-Length: {$file_size}");
		}

		\header('Accept-Ranges: bytes');

		\set_time_limit(0);
		\fseek($file, $seek_start);

		while (($chunk = @\fread($file, 1024 * 8)) !== false) {
			echo $chunk;
			\ob_flush();
			\flush();

			if (0 !== \connection_status()) {
				@\fclose($file);

				exit;
			}
		}

		// file download was a success
		@\fclose($file);

		exit;
	}
}
