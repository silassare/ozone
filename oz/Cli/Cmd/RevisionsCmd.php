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

use Kli\KliAction;
use Kli\KliArgs;
use Kli\Table\KliTable;
use Override;
use OZONE\Core\Cli\Command;
use OZONE\Core\Cli\Utils\Utils;
use OZONE\Core\Revisions\Revision;
use OZONE\Core\Revisions\Revisions;

/**
 * Class RevisionsCmd.
 *
 * The history of a resource kept with revisions, and going back to one of them, for an operator
 * with a shell. Writing a resource is its owner's: only it knows what a valid one is.
 */
final class RevisionsCmd extends Command
{
	/**
	 * Lists the revisions of a resource, newest first.
	 */
	public function history(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$history = Revisions::of((string) $args->get('namespace'))->history((string) $args->get('key'));
		$rows    = \array_map(self::rowOf(...), $history);
		$cli     = $this->getCli();

		if ($args->get('json')) {
			$cli->writeJson(['revisions' => $rows]);
		}

		$table = new KliTable();

		$table->addHeader('Number', 'number')->alignRight();
		$table->addHeader('Written', 'written')->alignLeft();
		$table->addHeader('Author', 'author')->alignLeft();
		$table->addHeader('Content', 'content')->alignLeft();
		$table->addHeader('Note', 'note')->alignLeft();
		$table->addRows(\array_map(static fn (array $row): array => [
			'number'   => $row['number'],
			'written'  => \date('Y-m-d H:i:s', $row['created_at']),
			'author'   => null === $row['author'] ? '' : \implode(':', $row['author']),
			// The first digits of the digest, after its `sha256:`: enough to tell two contents apart.
			'content'  => $row['removed'] ? 'removed' : \substr((string) $row['digest'], 7, 12),
			'note'     => (string) $row['note'],
		], $rows));

		$cli->writeLn((string) $table, false);
	}

	/**
	 * Writes again what a past revision of a resource held, as its next revision.
	 */
	public function restore(KliArgs $args): void
	{
		Utils::assertDatabaseAccess();

		$key       = (string) $args->get('key');
		$revisions = Revisions::of((string) $args->get('namespace'));
		$write     = $revisions->restore($key, (int) $args->get('number'), $revisions->current($key)?->number);
		$cli       = $this->getCli();
		$row       = null === $write->revision ? null : self::rowOf($write->revision);

		if ($args->get('json')) {
			$cli->writeJson(['applied' => $write->applied, 'revision' => $row]);
		}

		if ($write->applied && null !== $write->revision) {
			$cli->success(\sprintf('"%s" restored as revision %d.', $key, $write->revision->number));

			return;
		}

		$cli->error(\sprintf('"%s" changed while it was restored: try again.', $key));
	}

	#[Override]
	protected function describe(): void
	{
		$this->description('Read the history of a resource kept with revisions, and go back in it.');

		$history = $this->action('history', 'List the revisions of a resource, newest first.');
		self::withResource($history);
		self::withJsonSupport($history);
		$history->handler($this->history(...));

		$restore = $this->action('restore', 'Write again what a past revision held, as the next revision.');
		self::withResource($restore);
		$restore->option('number', 'n', [], 3)
			->required()
			->description('The number of the revision to restore.')
			->number();
		self::withJsonSupport($restore);
		$restore->handler($this->restore(...));
	}

	/**
	 * The options naming a resource: its namespace and its key.
	 */
	private static function withResource(KliAction $action): void
	{
		$action->option('namespace', 's', [], 1)
			->required()
			->description('The namespace of the resource, as its owner names it.')
			->string();
		$action->option('key', 'k', [], 2)
			->required()
			->description('The key of the resource in its namespace.')
			->string();
	}

	/**
	 * A revision as the command reports it.
	 *
	 * @return array{
	 *     number: int,
	 *     created_at: int,
	 *     author: null|array{type: null|string, id: string},
	 *     removed: bool,
	 *     digest: null|string,
	 *     note: null|string,
	 * }
	 */
	private static function rowOf(Revision $revision): array
	{
		return [
			'number'     => $revision->number,
			'created_at' => $revision->createdAt,
			'author'     => null === $revision->authorId
				? null
				: ['type' => $revision->authorType, 'id' => $revision->authorId],
			'removed'    => $revision->isRemoval(),
			'digest'     => $revision->digest,
			'note'       => $revision->note,
		];
	}
}
