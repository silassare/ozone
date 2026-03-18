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

use Blate\Blate;
use OZONE\Core\Exceptions\RuntimeException;
use Throwable;

/**
 * Class Templates.
 */
class Templates
{
	/**
	 * ozone templates directory.
	 *
	 * @var string
	 */
	public const OZ_TEMPLATE_DIR = OZ_OZONE_DIR . 'oz_templates' . DS;

	/**
	 * Compiles a template file with the given data and returns the output.
	 *
	 * @param string $template the template path
	 * @param array  $data     the data to be used in the template
	 *
	 * @return string the template result output
	 */
	public static function compile(string $template, array $data): string
	{
		$src = Assets::localize($template);

		if (!$src) {
			throw new RuntimeException(\sprintf('Unable to locate template file: %s', $template));
		}

		try {
			if (\str_ends_with($src, '.blate')) {
				$b      = Blate::fromPath($src);
				$result = $b->runGet($data);
			} else {
				throw new RuntimeException(\sprintf('Unsupported template file type: %s', $template));
			}
		} catch (Throwable $t) {
			throw new RuntimeException(\sprintf('Unable to compile template file: %s', $template), null, $t);
		}

		return $result;
	}
}
