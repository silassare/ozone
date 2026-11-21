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

use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\Db;
use Gobl\DBAL\DbConfig;
use Gobl\DBAL\Drivers\SQLite\SQLite;
use Gobl\DBAL\Interfaces\RDBMSInterface;
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
	 * A morph pair is two fields: the key names the pair and what it may point at, one target per
	 * morph relation over it, and the type names its key, for a client to draw one control.
	 */
	public function testAMorphPairIsTheKeyNamingItsTypeAndTargets(): void
	{
		$form = Form::fromTable(self::morphTable());

		self::assertSame([
			'type_field' => 'commentable_type',
			'targets'    => [
				['type' => 'posts', 'entity' => 'posts', 'relation' => 'post', 'candidates' => true],
				['type' => 'clips', 'entity' => 'clips', 'relation' => 'video', 'candidates' => false],
			],
		], $form->getField('commentable_id')?->getMorph());
		self::assertSame('commentable_id', $form->getField('commentable_type')?->getMorphOf());

		// Neither is a plain relation's key, and nothing else is a morph field.
		self::assertNull($form->getField('commentable_id')?->getRelation());
		self::assertNull($form->getField('body')?->getMorph());
		self::assertNull($form->getField('body')?->getMorphOf());
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

	/**
	 * Comments pointing at a post or at a video, through one pair of columns.
	 */
	private static function morphTable(): Table
	{
		$db = self::db();
		$ns = $db->ns('form_morphs');

		$ns->table('posts', static function (TableBuilder $t): void {
			$t->id();
			$t->softDeletable();
		});

		$ns->table('videos', static function (TableBuilder $t): void {
			$t->morphType('clips');
			$t->id();
			$t->softDeletable();
		});

		$ns->table('comments', static function (TableBuilder $t): void {
			$t->id();
			$t->string('body');
			$t->morph('commentable');
			$t->belongsTo('post')->from('posts')->usingMorph('commentable')->candidates();
			$t->belongsTo('video')->from('videos')->usingMorph('commentable');
		});

		return $db->getTableOrFail('comments');
	}

	private static function table(string $name): Table
	{
		$db = self::db();

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

	private static function db(): RDBMSInterface
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
}
