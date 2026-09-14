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

namespace OZONE\Core\Cli\Deploy;

use OZONE\Core\Exceptions\RuntimeException;
use OZONE\Core\FS\Templates;

/**
 * Class DeployTemplates.
 *
 * Renders the deployment templates of `oz_templates/gen/deploy/`.
 *
 * Plain `__TOKEN__` substitution rather than Blate, deliberately: these are nginx, logrotate and
 * GitHub Actions files, whose own syntax collides with a PHP template engine's -- nginx and
 * logrotate use `{ }` blocks, GitHub uses `${{ }}`, and escaping all of that would make the
 * templates unreadable and unverifiable against the real thing.
 */
final class DeployTemplates
{
	/**
	 * Renders a template.
	 *
	 * A token left unreplaced is an error, not something to ship: an `__NGINX__`-looking string in a
	 * production web server config would be found the hard way.
	 *
	 * @param string                $name   the file name under `gen/deploy/`
	 * @param array<string, string> $tokens token name (without the underscores) => value
	 *
	 * @return string
	 */
	public static function render(string $name, array $tokens): string
	{
		$path = Templates::OZ_TEMPLATE_DIR . 'gen' . DS . 'deploy' . DS . $name;

		if (!\is_file($path) || !\is_readable($path)) {
			throw new RuntimeException(\sprintf('Unable to locate the deployment template "%s".', $name));
		}

		$replacements = [];

		foreach ($tokens as $token => $value) {
			$replacements['__' . \strtoupper($token) . '__'] = $value;
		}

		$content = \strtr((string) \file_get_contents($path), $replacements);

		if (\preg_match('~__[A-Z][A-Z0-9_]*__~', $content, $m)) {
			throw new RuntimeException(\sprintf(
				'The deployment template "%s" has an unreplaced token: %s.',
				$name,
				$m[0]
			));
		}

		return $content;
	}
}
