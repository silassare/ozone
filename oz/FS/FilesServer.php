<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace OZONE\OZ\FS;

use OZONE\OZ\Core\Assert;
use OZONE\OZ\Core\Context;
use OZONE\OZ\Core\SettingsManager;
use OZONE\OZ\Exceptions\InternalErrorException;
use OZONE\OZ\Exceptions\NotFoundException;
use OZONE\OZ\Http\Body;

class FilesServer
{
	/**
	 * @param \OZONE\OZ\Core\Context $context
	 * @param array                  $params
	 *
	 * @throws \OZONE\OZ\Exceptions\InvalidFormException
	 * @throws \OZONE\OZ\Exceptions\NotFoundException
	 * @throws \Exception
	 *
	 * @return \OZONE\OZ\Http\Response
	 */
	public function serve(Context $context, array $params)
	{
		Assert::assertForm($params, [
			'file_src',
			'file_name',
			'file_mime',
			'file_quality',
		], new NotFoundException());

		\set_time_limit(0);

		$response = $context->getResponse();
		$src      = $params['file_src'];

		if (empty($src) || !\file_exists($src) || !\is_file($src) || !\is_readable($src)) {
			throw new NotFoundException();
		}

		$file_name    = $params['file_name'];
		$file_mime    = $params['file_mime'];
		$file_quality = (int) ($params['file_quality']);
		$size         = \filesize($src);

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
							 ->withHeader('Content-Length', $size)
							 ->withHeader('Content-type', $file_mime)
							 ->withHeader('Content-Disposition', "attachment; filename=\"$file_name\";");

		if ($file_quality > 0) {
			// thumbnails
			// 0: 'original file'
			// 1: 'low quality',
			// 2: 'normal quality',
			// 3: 'high quality'
			$jpeg_quality_array = [60, 80, 100];
			$jpeg_quality       = $jpeg_quality_array[$file_quality - 1];
			$img_utils_obj      = new ImagesUtils($src);

			if ($img_utils_obj->load()) {
				$max_size = SettingsManager::get('oz.users', 'OZ_THUMB_MAX_SIZE');
				$advice   = $img_utils_obj->adviceBestSize($max_size, $max_size);
				$content  = $img_utils_obj->resizeImage($advice['w'], $advice['h'], $advice['crop'])
										  ->getString('image/jpeg', $jpeg_quality);
				$body     = Body::fromString($content);

				return $response->withBody($body);
			}
		}

		// open in readonly mode
		$body = Body::fromPath($src, 'r');

		return $response->withBody($body);
	}

	/**
	 * starts a file download server
	 *
	 * @param array $options      the server options
	 * @param bool  $allow_resume should we support download resuming
	 * @param bool  $is_stream    is it a stream
	 *
	 * @throws \OZONE\OZ\Exceptions\InternalErrorException
	 * @throws \OZONE\OZ\Exceptions\UnauthorizedActionException
	 */
	public static function startDownloadServer(array $options, $allow_resume = false, $is_stream = false)
	{
		// TODO Full rewrite

		// - turn off compression on the server
		if (\function_exists('apache_setenv')) {
			@apache_setenv('no-gzip', 1);
		}

		@\ini_set('zlib.output_compression', 'Off');

		$mime_default = 'application/octet-stream';
		$file_src     = $options['file_src'];
		$file_name    = isset($options['file_name']) ? $options['file_name'] : \basename($file_src);
		$expires_date = isset($options['file_expires_date']) ? $options['file_expires_date'] : -1;
		$mime         = isset($options['file_mime']) ? $options['file_mime'] : $mime_default;
		$range        = '';

		// make sure the file exists
		Assert::assertAuthorizeAction(\is_file($file_src), new NotFoundException());

		$file_size = \filesize($file_src);
		$file      = @\fopen($file_src, 'rb');

		// make sure file open success
		if (!$file) {
			throw new InternalErrorException('OZ_FILE_OPEN_ERROR', [$file_src]);
		}

		// sets the headers, prevent caching
		\header('Pragma: public');
		\header('Expires: ' . $expires_date);
		\header('Cache-Control: public, max-age=' . $expires_date . ', must-revalidate, post-check=0, pre-check=0');

		// sets appropriate headers for attachment or streamed file
		if (!$is_stream) {
			\header("Content-Disposition: attachment; filename=\"$file_name\";");
		} else {
			\header('Content-Disposition: inline;');
			\header('Content-Transfer-Encoding: binary');
		}

		// sets the mime type
		\header('Content-Type: ' . $mime);

		// checks if http_range is sent by browser (or download manager)
		if ($allow_resume && isset($_SERVER['HTTP_RANGE'])) {
			@list($size_unit, $range_orig) = \explode('=', $_SERVER['HTTP_RANGE'], 2);

			if ($size_unit === 'bytes') {
				// multiple ranges could be specified at the same time, but for simplicity only serve the first range
				// http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
				@list($range) = \explode(',', $range_orig, 2);
			} else {
				\header('HTTP/1.1 416 Requested Range Not Satisfiable');
				exit;
			}
		}

		// figure out download piece from range (if set)
		@list($seek_start, $seek_end) = \explode('-', $range, 2);

		// sets start and end based on range (if set), else set defaults
		// also check for invalid ranges.
		$seek_end   = (empty($seek_end)) ? ($file_size - 1) : \min(\abs((int) $seek_end), ($file_size - 1));
		$seek_start = (empty($seek_start) || $seek_end < \abs((int) $seek_start)) ? 0 : \max(\abs((int) $seek_start), 0);

		// Only send partial content header if downloading a piece of the file (IE workaround)
		if ($seek_start > 0 || $seek_end < ($file_size - 1)) {
			$chunk_size = ($seek_end - $seek_start + 1);

			\header('HTTP/1.1 206 Partial Content');
			\header('Content-Range: bytes ' . $seek_start . '-' . $seek_end . '/' . $file_size);
			\header("Content-Length: $chunk_size");
		} else {
			\header("Content-Length: $file_size");
		}

		\header('Accept-Ranges: bytes');

		\set_time_limit(0);
		\fseek($file, $seek_start);

		while (!\feof($file)) {
			print @\fread($file, 1024 * 8);
			\ob_flush();
			\flush();

			if (\connection_status() != 0) {
				@\fclose($file);
				exit;
			}
		}

		// file download was a success
		@\fclose($file);
		exit;
	}
}
