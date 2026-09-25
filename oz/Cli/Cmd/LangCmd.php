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

namespace OZONE\Core\Cli\Cmd;

use Kli\KliArgs;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Lang\Polyglot;

/**
 * Class LangCmd.
 *
 * The API sends message keys, never text: a client translates them itself, from the catalogs this
 * command exports.
 */
final class LangCmd extends Command
{
	/**
	 * Exports the catalogs of the enabled languages, merged as the server reads them.
	 *
	 * @param KliArgs $args
	 */
	public function export(KliArgs $args): void
	{
		$export = Polyglot::exportCatalogs();
		$cli    = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson($export);
		}

		$cli->info(\sprintf('Default language: %s', $export['default']));

		foreach ($export['catalogs'] as $lang => $catalog) {
			if ([] === $catalog) {
				$cli->warn(\sprintf('%s: enabled, but no catalog (every text falls back to the default)', $lang));

				continue;
			}

			$cli->writeLn(\sprintf('%s: %d entries', $lang, \count($catalog)));
		}

		if ([] !== $export['filters']) {
			$cli->writeLn(\sprintf('Filters: %s', \implode(', ', $export['filters'])));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	#[Override]
	protected function describe(): void
	{
		$this->description('Manage the translation catalogs.');

		$export = $this->action(
			'export',
			'Export the catalogs of the enabled languages, merged as the server reads them.'
		);
		self::withJsonSupport($export);
		$export->handler($this->export(...));
	}
}
