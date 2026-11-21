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

namespace OZONE\Tests\Lang;

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Interfaces\RDBMSInterface;
use Gobl\DBAL\Table;
use OZONE\Core\App\Db as OZDb;
use OZONE\Core\App\Settings;
use OZONE\Core\Forms\Form;
use OZONE\Core\Lang\EntityTexts;
use OZONE\Core\Lang\I18nMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class EntityTextsTest.
 *
 * The texts of a table and its columns, under keys derived from their names: which key each one
 * has, which ones a catalog misses, and what a form built from the table says.
 *
 * @internal
 */
#[CoversClass(EntityTexts::class)]
final class EntityTextsTest extends TestCase
{
	public function testEachTextHasAKeyDerivedFromTheMorphTypeAndTheColumnName(): void
	{
		$table = self::table();

		// The morph type, which is the table's name unless the schema gives one.
		self::assertSame('ENTITY.TEXT_NOTES.SINGULAR', EntityTexts::tableKey($table, 'SINGULAR'));
		self::assertSame('ENTITY.TEXT_NOTES.PLURAL', EntityTexts::tableKey($table, 'PLURAL'));
		self::assertSame(
			'ENTITY.TEXT_NOTES.COLUMN.FIRST_NAME.LABEL',
			EntityTexts::columnKey($table, $table->getColumnOrFail('first_name'), 'LABEL')
		);
	}

	public function testACatalogMissesWhatTheSchemaHasATextOrANameFor(): void
	{
		$missing = EntityTexts::missing([self::table()], 'en');

		self::assertSame([
			'ENTITY.TEXT_NOTES.SINGULAR'                     => 'Text note',
			'ENTITY.TEXT_NOTES.PLURAL'                       => 'Text notes',
			'ENTITY.TEXT_NOTES.DESCRIPTION'                  => 'A note somebody wrote.',
			// A sentence for a translator to write, given without an article no name can tell.
			'ENTITY.TEXT_NOTES.CHOOSE'                       => 'Choose text note',
			'ENTITY.TEXT_NOTES.COLUMN.ID.LABEL'              => 'Id',
			// The schema's own text, written in the default language.
			'ENTITY.TEXT_NOTES.COLUMN.FIRST_NAME.LABEL'      => 'Given name',
			'ENTITY.TEXT_NOTES.COLUMN.FIRST_NAME.HELP'       => 'As on an identity card.',
			// No text in the schema: the name, made readable. No description, no help: none written.
			'ENTITY.TEXT_NOTES.COLUMN.BODY.LABEL'            => 'Body',
		], $missing);

		// `secret` is private and never reaches a client; `code` names a key of its own.
		self::assertArrayNotHasKey('ENTITY.TEXT_NOTES.COLUMN.SECRET.LABEL', $missing);
		self::assertArrayNotHasKey('ENTITY.TEXT_NOTES.COLUMN.CODE.LABEL', $missing);
	}

	public function testWhatTheCatalogHoldsIsNoLongerMissing(): void
	{
		self::withCatalog(['ENTITY' => ['TEXT_NOTES' => [
			'SINGULAR' => 'Note',
			'COLUMN'   => ['BODY' => ['LABEL' => 'Text']],
		]]], static function (): void {
			$missing = EntityTexts::missing([self::table()], 'en');

			self::assertArrayNotHasKey('ENTITY.TEXT_NOTES.SINGULAR', $missing);
			self::assertArrayNotHasKey('ENTITY.TEXT_NOTES.COLUMN.BODY.LABEL', $missing);
			self::assertArrayHasKey('ENTITY.TEXT_NOTES.PLURAL', $missing);
		});
	}

	public function testAFormSaysTheKeyWhereTheCatalogHasItElseWhatTheSchemaSays(): void
	{
		$labels = static function (): array {
			$out = [];

			foreach (Form::fromTable(self::table())->getFields() as $name => $field) {
				$label      = $field->getLabel();
				$out[$name] = $label instanceof I18nMessage ? $label->getText() : null;
			}

			return $out;
		};

		// No catalog entry yet: the schema's text, as a form always said it, and nothing for a
		// column the schema says nothing of.
		$before = $labels();

		self::assertSame('Given name', $before['first_name']);
		self::assertNull($before['body']);
		// A key the schema names wins whatever the catalog holds.
		self::assertSame('OZ_TEST_CODE_LABEL', $before['code']);

		self::withCatalog(['ENTITY' => ['TEXT_NOTES' => ['COLUMN' => [
			'FIRST_NAME' => ['LABEL' => 'Given name'],
			'BODY'       => ['LABEL' => 'Body'],
			'CODE'       => ['LABEL' => 'Code'],
		]]]], static function () use ($labels): void {
			$after = $labels();

			self::assertSame('ENTITY.TEXT_NOTES.COLUMN.FIRST_NAME.LABEL', $after['first_name']);
			self::assertSame('ENTITY.TEXT_NOTES.COLUMN.BODY.LABEL', $after['body']);
			self::assertSame('OZ_TEST_CODE_LABEL', $after['code']);
		});
	}

	/**
	 * OZone ships the texts of its own tables in both its languages, so a project's build writes
	 * none of them and a French project is not handed English.
	 */
	public function testOZoneShipsEveryTextOfItsOwnTablesInEnglishAndFrench(): void
	{
		$db = self::memoryDb();

		/** @var callable(NamespaceBuilder):void $factory */
		$factory = include OZ_OZONE_DIR . 'oz_default' . \DIRECTORY_SEPARATOR . 'oz_schema.php';
		$factory($db->ns(OZDb::getOZoneDbNamespace()));

		$tables = $db->getTables(OZDb::getOZoneDbNamespace());

		self::assertNotEmpty($tables);
		self::assertSame([], EntityTexts::missing($tables, 'en'));
		self::assertSame([], EntityTexts::missing($tables, 'fr'));
	}

	public function testANameReadsAsAReaderWouldWriteIt(): void
	{
		self::assertSame('First name', EntityTexts::readable('first_name'));
		self::assertSame('Cc2', EntityTexts::readable('cc2'));
	}

	/**
	 * Runs with the given texts in the default catalog, from a source of its own, then without.
	 *
	 * @param array<string, mixed> $catalog
	 */
	private static function withCatalog(array $catalog, callable $run): void
	{
		$dir  = \sys_get_temp_dir() . '/oz-entity-texts-' . \bin2hex(\random_bytes(4));
		$file = $dir . '/lang/oz.en.php';

		\mkdir($dir . '/lang', 0o755, true);
		\file_put_contents($file, '<?php return ' . \var_export($catalog, true) . ';');

		try {
			Settings::addSource($dir);
			Settings::load('lang/oz.en', true);

			$run();
		} finally {
			\unlink($file);
			\rmdir($dir . '/lang');
			\rmdir($dir);
			Settings::load('lang/oz.en', true);
		}
	}

	private static function memoryDb(): RDBMSInterface
	{
		return Db::newInstanceOf(SQLite::NAME, new DbConfig([
			'db_host'    => ':memory:',
			'db_port'    => '',
			'db_name'    => '',
			'db_user'    => '',
			'db_pass'    => '',
			'db_charset' => 'utf8',
			'db_collate' => 'utf8',
		]));
	}

	private static function table(): Table
	{
		$db = self::memoryDb();

		$db->ns('entity_texts')->schema([
			'text_notes' => [
				'singular_name' => 'text_note',
				'plural_name'   => 'text_notes',
				'column_prefix' => 'note',
				'meta'          => ['api.doc.description' => 'A note somebody wrote.'],
				'columns'       => [
					'id'         => ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true],
					'first_name' => [
						'type' => 'string',
						'max'  => 60,
						'meta' => ['field.label' => 'Given name', 'field.help' => 'As on an identity card.'],
					],
					'body'       => ['type' => 'string', 'max' => 255],
					'code'       => ['type' => 'string', 'max' => 8, 'meta' => ['field.label' => 'OZ_TEST_CODE_LABEL']],
					'secret'     => ['type' => 'string', 'max' => 60, 'private' => true],
				],
				'constraints' => [['type' => 'primary_key', 'columns' => ['id']]],
			],
		]);

		return $db->getTableOrFail('text_notes');
	}
}
