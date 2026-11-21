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

namespace OZONE\Tests\Forms;

use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Table;
use OZONE\Core\Forms\Form;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Class FormFromTableRelationTest.
 *
 * A form built from a table says, on a foreign column's field, which relation that column holds the
 * key of: what a client draws a picker from.
 *
 * @internal
 */
#[CoversClass(Form::class)]
final class FormFromTableRelationTest extends TestCase
{
	public function testAColumnNamesTheRelationThatOffersCandidatesAmongSeveral(): void
	{
		// `client` and `open_client` both run over `client_id`: the one offering candidates is named,
		// since its rule is what a write of the column is checked against.
		self::assertSame([
			'name'       => 'open_client',
			'entity'     => 'clients',
			'host'       => 'accounts',
			'candidates' => true,
		], $this->relationOf('accounts', 'client_id'));
	}

	public function testAColumnNamesItsOnlyRelation(): void
	{
		self::assertSame([
			'name'       => 'account',
			'entity'     => 'accounts',
			'host'       => 'notes',
			'candidates' => false,
		], $this->relationOf('notes', 'account_id'));
	}

	public function testAColumnWithSeveralRelationsAndNoCandidatesNamesNone(): void
	{
		// Two relations over `owner_id`, neither offering candidates: no picker is guessed.
		self::assertNull($this->relationOf('notes', 'owner_id'));
	}

	public function testAColumnThatIsNoKeyNamesNone(): void
	{
		self::assertNull($this->relationOf('accounts', 'label'));
	}

	/**
	 * What the field of a column, in the form built from its table, says of its relation.
	 *
	 * @return null|array{name: string, entity: string, host: string, candidates: bool}
	 */
	private function relationOf(string $table, string $column): ?array
	{
		return Form::fromTable(self::table($table))->getField($column)?->getRelation();
	}

	private static function table(string $name): Table
	{
		$db = Db::newInstanceOf(SQLite::NAME, new DbConfig([
			'db_host'    => ':memory:',
			'db_port'    => '',
			'db_name'    => '',
			'db_user'    => '',
			'db_pass'    => '',
			'db_charset' => 'utf8',
			'db_collate' => 'utf8',
		]));

		$id = ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true];

		$db->ns('form_relations')->schema([
			'clients' => [
				'column_prefix' => 'client',
				'columns'       => ['id' => $id, 'name' => ['type' => 'string', 'max' => 60]],
				'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			],
			'accounts' => [
				'column_prefix' => 'account',
				'columns'       => [
					'id'        => $id,
					'label'     => ['type' => 'string', 'max' => 60],
					'client_id' => ['type' => 'ref:clients.id', 'nullable' => true],
				],
				'constraints' => [
					['type' => 'primary_key', 'columns' => ['id']],
					['type' => 'foreign_key', 'reference' => 'clients', 'columns' => ['client_id' => 'id']],
				],
				'relations' => [
					'client'      => ['type' => 'many-to-one', 'target' => 'clients'],
					'open_client' => [
						'type'       => 'many-to-one',
						'target'     => 'clients',
						'link'       => ['type' => 'columns', 'filters' => ['name', 'neq', 'blocked']],
						'candidates' => true,
					],
				],
			],
			'notes' => [
				'column_prefix' => 'note',
				'columns'       => [
					'id'         => $id,
					'account_id' => 'ref:accounts.id',
					'owner_id'   => ['type' => 'ref:clients.id', 'nullable' => true],
				],
				'constraints' => [
					['type' => 'primary_key', 'columns' => ['id']],
					['type' => 'foreign_key', 'reference' => 'accounts', 'columns' => ['account_id' => 'id']],
					['type' => 'foreign_key', 'reference' => 'clients', 'columns' => ['owner_id' => 'id']],
				],
				'relations' => [
					'account'      => ['type' => 'many-to-one', 'target' => 'accounts'],
					'owner'        => ['type' => 'many-to-one', 'target' => 'clients'],
					'second_owner' => ['type' => 'many-to-one', 'target' => 'clients'],
				],
			],
		]);

		return $db->getTableOrFail($name);
	}
}
