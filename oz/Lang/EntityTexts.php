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

namespace OZONE\Core\Lang;

use Gobl\DBAL\Column;
use Gobl\DBAL\Table;

/**
 * The translatable texts of the tables and their columns, under keys derived from their names.
 *
 * Each table, by its morph type (unique in a database):
 *
 *   ENTITY.<TYPE>.SINGULAR, ENTITY.<TYPE>.PLURAL, ENTITY.<TYPE>.DESCRIPTION, ENTITY.<TYPE>.CHOOSE
 *
 * and each column the client can read, by its short name (unique in its table):
 *
 *   ENTITY.<TYPE>.COLUMN.<NAME>.LABEL, .DESCRIPTION, .HELP
 *
 * both upper-cased, which every gobl name can be: it is lower case, digits and `_`. A catalog holds
 * them nested (`ENTITY => [USER => [COLUMN => [CC2 => [LABEL => ...]]]]`), which is how it reads a
 * dotted key, so the texts of one table sit together in the file.
 *
 * `oz db build` writes the keys a catalog misses, in the default language, from what the schema
 * says: a column's `field.label`, `field.description` and `field.help`, a table's
 * `api.doc.description`, else the name made readable for what always has one (a label, a name). So
 * every key resolves from the first build, and a translator adds the other languages.
 *
 * `CHOOSE` is what a picker of the table's records says before one is picked ("Choose a client").
 * A sentence and not a name, so each language writes its own grammar: a French article follows the
 * noun's gender, and an English one its sound, neither of which a name tells. The default the build
 * writes is "Choose" and the readable singular, with no article, for a translator to make a
 * sentence of.
 */
final class EntityTexts
{
	/** The group every key of this convention sits under. */
	public const string ROOT = 'ENTITY';

	/** The group of a table's columns. */
	public const string COLUMNS = 'COLUMN';

	/** What a table has a text for. */
	public const array TABLE_TEXTS = ['SINGULAR', 'PLURAL', 'DESCRIPTION', 'CHOOSE'];

	/**
	 * What a column has a text for, and the meta key each one is read from in the schema.
	 *
	 * The meta holds either the text itself, written in the default language, or a key of its own
	 * choosing, which then wins over the derived one.
	 */
	public const array COLUMN_TEXTS = [
		'LABEL'       => 'field.label',
		'DESCRIPTION' => 'field.description',
		'HELP'        => 'field.help',
	];

	/** The meta key a table's description is read from in the schema. */
	public const string TABLE_DESCRIPTION_META = 'api.doc.description';

	/**
	 * The key of a table's text.
	 *
	 * @param value-of<self::TABLE_TEXTS> $text
	 */
	public static function tableKey(Table $table, string $text): string
	{
		return self::ROOT . '.' . \strtoupper($table->getMorphType()) . '.' . $text;
	}

	/**
	 * The key of a column's text.
	 *
	 * @param key-of<self::COLUMN_TEXTS> $text
	 */
	public static function columnKey(Table $table, Column $column, string $text): string
	{
		return self::ROOT . '.' . \strtoupper($table->getMorphType()) . '.' . self::COLUMNS
			. '.' . \strtoupper($column->getName()) . '.' . $text;
	}

	/**
	 * What a field built from a column says for one of its texts: a key, or a text, or nothing.
	 *
	 * A key the schema names in the meta wins. Then the derived key, where the default catalog holds
	 * it. Then the text the meta holds, as it is, so a project whose catalog has not been written yet
	 * reads what it always did.
	 *
	 * @param key-of<self::COLUMN_TEXTS> $text
	 */
	public static function columnText(Table $table, Column $column, string $text): ?string
	{
		$meta = $column->getMeta()->get(self::COLUMN_TEXTS[$text]);
		$meta = \is_string($meta) && '' !== $meta ? $meta : null;

		if (null !== $meta && Polyglot::isLangKey($meta)) {
			return $meta;
		}

		$key = self::columnKey($table, $column, $text);

		return Polyglot::hasText($key) ? $key : $meta;
	}

	/**
	 * The texts of the given tables that a language's catalog (the default language's by default)
	 * misses, by key, each with the text the schema gives it.
	 *
	 * What has no text in the schema and no name to read one from is left out: a description or a
	 * help nobody wrote is no text at all, and a client draws none rather than an empty one. A column
	 * whose meta names a key of its own is left out too: that key is where its text goes.
	 *
	 * @param iterable<Table> $tables
	 *
	 * @return array<string, string>
	 */
	public static function missing(iterable $tables, ?string $lang = null): array
	{
		$lang ??= Polyglot::getDefaultLanguage();
		$out    = [];

		foreach ($tables as $table) {
			$texts = [
				'SINGULAR'    => self::readable($table->getSingularName()),
				'PLURAL'      => self::readable($table->getPluralName()),
				'DESCRIPTION' => self::metaText($table->getMeta()->get(self::TABLE_DESCRIPTION_META)),
				'CHOOSE'      => 'Choose ' . \strtolower(self::readable($table->getSingularName())),
			];

			foreach ($texts as $text => $value) {
				$key = self::tableKey($table, $text);

				if (null !== $value && !Polyglot::hasText($key, $lang)) {
					$out[$key] = $value;
				}
			}

			// The columns a field can be built from: a private one never reaches a client.
			foreach ($table->getColumns(false) as $column) {
				foreach (self::COLUMN_TEXTS as $text => $meta_key) {
					$meta = $column->getMeta()->get($meta_key);

					if (\is_string($meta) && Polyglot::isLangKey($meta)) {
						continue;
					}

					$value = self::metaText($meta) ?? ('LABEL' === $text ? self::readable($column->getName()) : null);
					$key   = self::columnKey($table, $column, $text);

					if (null !== $value && !Polyglot::hasText($key, $lang)) {
						$out[$key] = $value;
					}
				}
			}
		}

		return $out;
	}

	/**
	 * A gobl name as a reader would write it: `first_name` -> `First name`.
	 */
	public static function readable(string $name): string
	{
		return \ucfirst(\str_replace('_', ' ', $name));
	}

	/**
	 * A text the meta holds, written in the default language; not a key, not empty.
	 */
	private static function metaText(mixed $meta): ?string
	{
		return \is_string($meta) && '' !== $meta && !Polyglot::isLangKey($meta) ? $meta : null;
	}
}
