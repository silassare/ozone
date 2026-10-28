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

namespace OZONE\Tests\Stores;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Class SessionStoreKeysTest.
 *
 * What a request changes in the session store is written back as the keys it changed, so two
 * overlapping requests keep each other's writes. Two shapes are written whole instead, and each
 * undoes what another request put there meanwhile:
 *
 *  - a value that is a **list**, since what its entries mean is their order;
 *  - a **branch removed in one call**, which takes every entry it holds with it.
 *
 * Neither is reachable from OZone's own code, and this test is what keeps it that way: a new branch
 * of per-entry state or a new whole-key removal has to be declared here, which is the moment to
 * check the two rules hold for it.
 *
 * @internal
 */
#[CoversNothing]
final class SessionStoreKeysTest extends TestCase
{
	/**
	 * The branches holding one entry per key, each written as `<branch>.<entry>`.
	 *
	 * An entry is a map, never a list, and goes one at a time: `remove('<branch>.<entry>')`.
	 */
	private const array ENTRY_BRANCHES = [
		'oz.captcha_cfg',
		'oz.link_to_cfg',
		'oz.qr_code_cfg',
		'oz_upload_files',
	];

	/**
	 * The keys a request removes whole, each a leaf of its own rather than a branch of entries.
	 */
	private const array REMOVED_WHOLE = [
		'oz.2fa.verified',
		'oz.2fa_pending_user',
	];

	/**
	 * A branch found in the sources and not declared above has not been through the two rules.
	 */
	public function testEveryBranchOfPerEntryStateIsDeclared(): void
	{
		$found = [];

		foreach (self::storeFiles() as $path => $source) {
			foreach (self::entryBranchesOf($source) as $branch) {
				$found[$branch] = $path;
			}
		}

		$undeclared = \array_diff(\array_keys($found), self::ENTRY_BRANCHES);

		self::assertSame([], \array_values($undeclared), \sprintf(
			"A branch of per-entry state is not declared in %s::ENTRY_BRANCHES: %s.\n"
			. 'Declare it, and check that its entries are a map and that the branch itself is never removed.',
			self::class,
			\implode(', ', \array_map(
				static fn (string $b): string => \sprintf('%s (%s)', $b, $found[$b]),
				$undeclared
			))
		));
	}

	/**
	 * Removing a branch sends one removal for the branch: an entry another request added to it
	 * meanwhile goes with it.
	 */
	public function testNoBranchOfPerEntryStateIsRemovedWhole(): void
	{
		$offenders = [];

		foreach (self::storeFiles() as $path => $source) {
			foreach (self::literalRemovalsOf($source) as $key) {
				if (\in_array($key, self::ENTRY_BRANCHES, true)) {
					$offenders[] = \sprintf('%s (%s)', $key, $path);
				}
			}
		}

		self::assertSame([], $offenders, \sprintf(
			'A branch of per-entry state is removed whole; remove one entry at a time instead: %s.',
			\implode(', ', $offenders)
		));
	}

	/**
	 * A key removed whole is either declared or a path to one entry: anything else is the shape that
	 * loses a concurrent write, and declaring it is where that gets checked.
	 */
	public function testEveryWholeKeyRemovalIsDeclared(): void
	{
		$undeclared = [];

		foreach (self::storeFiles() as $path => $source) {
			foreach (self::literalRemovalsOf($source) as $key) {
				if (\in_array($key, self::REMOVED_WHOLE, true)) {
					continue;
				}

				$undeclared[] = \sprintf('%s (%s)', $key, $path);
			}
		}

		self::assertSame([], $undeclared, \sprintf(
			"A key is removed whole and is not declared in %s::REMOVED_WHOLE: %s.\n"
			. 'Declare it if it is a leaf, or remove the entries one at a time if it is a branch.',
			self::class,
			\implode(', ', $undeclared)
		));
	}

	/**
	 * The sources that reach the session store, keyed by their path relative to the repository.
	 *
	 * @return array<string, string>
	 */
	private static function storeFiles(): array
	{
		$root  = \dirname(__DIR__, 2) . \DIRECTORY_SEPARATOR . 'oz';
		$files = [];
		$it    = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));

		/** @var SplFileInfo $file */
		foreach ($it as $file) {
			if (!$file->isFile() || 'php' !== $file->getExtension()) {
				continue;
			}

			$source = (string) \file_get_contents($file->getPathname());

			// Only what reaches the session store: `remove()` is a method of several collections.
			if (!\str_contains($source, 'AuthStore(') && !\str_contains($source, 'StatefulAuthenticationMethodStore')) {
				continue;
			}

			$files['oz/' . \ltrim(\str_replace($root, '', $file->getPathname()), \DIRECTORY_SEPARATOR)] = $source;
		}

		return $files;
	}

	/**
	 * The branches a source writes an entry under: `'<branch>.' . $entry`, and the same through a
	 * constant the file declares.
	 *
	 * @return list<string>
	 */
	private static function entryBranchesOf(string $source): array
	{
		$branches = [];

		\preg_match_all("~'([A-Za-z0-9_.]+)\\.'\\s*\\.\\s*\\$~", $source, $literal);

		foreach ($literal[1] as $branch) {
			$branches[] = $branch;
		}

		// `self::BRANCH . '.' . $entry`, with the constant declared in the same file.
		\preg_match_all("~self::([A-Z][A-Z0-9_]*)\\s*\\.\\s*'\\.'\\s*\\.\\s*\\$~", $source, $named);

		foreach ($named[1] as $constant) {
			if (\preg_match("~const\\s+string\\s+{$constant}\\s*=\\s*'([^']+)'~", $source, $value)) {
				$branches[] = $value[1];
			} else {
				self::fail(\sprintf('The branch constant "%s" is not declared in the file that uses it.', $constant));
			}
		}

		return $branches;
	}

	/**
	 * The keys a source removes by a literal, which is a key written whole.
	 *
	 * @return list<string>
	 */
	private static function literalRemovalsOf(string $source): array
	{
		\preg_match_all("~->remove\\(\\s*'([A-Za-z0-9_.]+)'\\s*\\)~", $source, $matches);

		return $matches[1];
	}
}
