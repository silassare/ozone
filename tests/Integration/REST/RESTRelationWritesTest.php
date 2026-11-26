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

namespace OZONE\Tests\Integration\REST;

use Override;
use OZONE\Core\Testing\DbTestConfig;
use OZONE\Core\Testing\OZTestProject;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Writing a row's relatives in the request that writes the row, and asking a relation's candidates,
 * on a real server and every configured database.
 *
 * What the relations do is the ORM's; what is checked here is the request's half: an entry's markers
 * reach the right operation and never the row, a relation's refusal reaches the client as itself on
 * the field it names, and the candidates route answers the records the relation offers.
 *
 * @internal
 */
#[CoversNothing]
final class RESTRelationWritesTest extends TestCase
{
	/** @var array<string, OZTestProject> */
	private static array $projects = [];

	/** @var array<string, array{0: Process, 1: string, 2: int}> */
	private static array $servers = [];

	/** @var array<string, null|string> */
	private static array $dbFiles = [];

	#[Override]
	public static function tearDownAfterClass(): void
	{
		foreach (self::$servers as [$server]) {
			$server->stop();
		}

		foreach (self::$projects as $proj) {
			$proj->destroy();
		}

		foreach (self::$dbFiles as $file) {
			if (null !== $file && \is_file($file)) {
				\unlink($file);
			}
		}

		self::$servers  = [];
		self::$projects = [];
		self::$dbFiles  = [];

		parent::tearDownAfterClass();
	}

	#[DataProvider('provideDbConfig')]
	public function testSetup(DbTestConfig $config): void
	{
		$rdbms = $config->rdbms;
		$proj  = OZTestProject::create('rest-relations-' . $rdbms, shared: true, fresh: true);
		$ns    = $proj->getNamespace();

		self::$projects[$rdbms] = $proj;
		self::$dbFiles[$rdbms]  = $config->isSQLite() ? $config->host : null;

		$id = ['type' => 'bigint', 'auto_increment' => true, 'unsigned' => true];

		$proj->useDatabase($config);
		$proj->setSetting('oz.db.schema', 'clients', [
			'plural_name'   => 'clients',
			'singular_name' => 'client',
			'column_prefix' => 'client',
			'columns'       => ['id' => $id, 'name' => ['type' => 'string', 'max' => 60]],
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
		]);
		$proj->setSetting('oz.db.schema', 'accounts', [
			'plural_name'   => 'accounts',
			'singular_name' => 'account',
			'column_prefix' => 'account',
			'columns'       => [
				'id'        => $id,
				'label'     => ['type' => 'string', 'max' => 60],
				'client_id'       => ['type' => 'ref:clients.id', 'nullable' => true],
				'local_client_id' => ['type' => 'ref:clients.id', 'nullable' => true],
			],
			'constraints' => [
				['type' => 'primary_key', 'columns' => ['id']],
				['type' => 'foreign_key', 'reference' => 'clients', 'columns' => ['client_id' => 'id']],
				['type' => 'foreign_key', 'reference' => 'clients', 'columns' => ['local_client_id' => 'id']],
			],
			'relations' => [
				// What an account may be pointed at: any client but a blocked one.
				'client' => [
					'type'       => 'many-to-one',
					'target'     => 'clients',
					'link'       => ['type' => 'columns', 'columns' => ['client_id' => 'id'], 'filters' => ['name', 'neq', 'blocked']],
					'candidates' => true,
				],
				// Narrowed by a provider of the project, with the request's context.
				'local_client' => [
					'type'       => 'many-to-one',
					'target'     => 'clients',
					'link'       => ['type' => 'columns', 'columns' => ['local_client_id' => 'id']],
					'candidates' => "{$ns}\\LocalClientCandidates",
				],
				// Removing one refuses by default: a transaction's key cannot be null.
				'transactions' => ['type' => 'one-to-many', 'target' => 'transactions'],
				'owned_transactions' => [
					'type'      => 'one-to-many',
					'target'    => 'transactions',
					'on_remove' => 'delete',
				],
			],
		]);
		$proj->setSetting('oz.db.schema', 'transactions', [
			'plural_name'   => 'transactions',
			'singular_name' => 'transaction',
			'column_prefix' => 'transaction',
			'columns'       => [
				'id'         => $id,
				'account_id' => 'ref:accounts.id',
				'state'      => ['type' => 'string', 'max' => 20],
			],
			'constraints' => [
				['type' => 'primary_key', 'columns' => ['id']],
				['type' => 'foreign_key', 'reference' => 'accounts', 'columns' => ['account_id' => 'id']],
			],
		]);

		// One service per table, each in a namespace of its own, so the one stub serves all three.
		foreach (['clients' => 'Clients', 'accounts' => 'Accounts', 'transactions' => 'Transactions'] as $table => $sub) {
			$proj->writeFileFromStub('TableRESTService', "app/{$sub}/TableRESTService.php", [
				'namespace' => "{$ns}\\{$sub}",
				'table'     => $table,
			]);
			$proj->setSetting('oz.routes.api', "{$ns}\\{$sub}\\TableRESTService", true);
		}

		$proj->writeFileFromStub('LocalClientCandidates', 'app/LocalClientCandidates.php', ['namespace' => $ns]);

		// Several transactions created at once (an account with its transactions, the bulk route)
		// are a bulk create, which nothing opens by default: opened here while `OPEN` says so.
		$proj->writeFileFromStub('BulkCreateOpener', 'app/BulkCreateOpener.php', [
			'namespace' => $ns,
			'table'     => 'transactions',
		]);
		$proj->setSetting('oz.gobl.crud', "{$ns}\\BulkCreateOpener", true);
		$proj->setSetting('oz.test.bulk', 'OPEN', true);

		// Read by the test, which has no user to sign in.
		$proj->setSetting('oz.api.doc', 'OZ_API_DOC_PUBLIC', true);

		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();
		$proj->cleanDb();
		$proj->oz('migrations', 'create', '--force', '--label=initial')->mustRun();
		$proj->oz('migrations', 'run', '--skip-backup')->mustRun();
		$proj->oz('db', 'build', '--build-all', '--class-only')->mustRun();

		self::$servers[$rdbms] = $proj->startServer('api');

		foreach (['open', 'blocked'] as $name) {
			[$status, $json] = self::send($config, 'POST', '/clients', ['name' => $name]);

			self::assertSame(201, $status, \json_encode($json));
		}
	}

	/**
	 * A row and its relatives in one request, the relatives linked to it.
	 */
	#[DataProvider('provideDbConfig')]
	public function testARowIsCreatedWithItsRelatives(DbTestConfig $config): void
	{
		[$status, $json] = self::send($config, 'POST', '/accounts', [
			'label'        => 'with transactions',
			'transactions' => [['state' => 'one'], ['state' => 'two']],
		]);

		self::assertSame(201, $status, \json_encode($json));

		$id = $json['data']['item']['account_id'];

		[, $list] = self::send($config, 'GET', "/accounts/{$id}/transactions");

		self::assertSame(['one', 'two'], \array_column($list['data']['items'] ?? [], 'transaction_state'));
	}

	/**
	 * A relative's payload naming another row than the one it is written with is refused, said as
	 * the relation's own refusal, on the field it names.
	 */
	#[DataProvider('provideDbConfig')]
	public function testARelativeNamingAnotherRowIsRefusedOnItsField(DbTestConfig $config): void
	{
		$other = self::account($config, 'other');

		[$status, $json] = self::send($config, 'POST', '/accounts', [
			'label'        => 'refused',
			'transactions' => [['state' => 'x', 'account_id' => $other]],
		]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('GOBL_ORM_RELATION_FIXES_THIS_FIELD', $json['msg'], \json_encode($json));
		self::assertSame('transaction_account_id', $json['data']['field'] ?? null, \json_encode($json));
		self::assertSame('transactions', $json['data']['relation'] ?? null, \json_encode($json));
	}

	/**
	 * `_delete` reaches the removal and never the row: refused where the relation removes the link
	 * only and the key cannot be null, done where the relation deletes what it removes.
	 */
	#[DataProvider('provideDbConfig')]
	public function testARemovedRelativeFollowsItsRelationsStrategy(DbTestConfig $config): void
	{
		$account = self::account($config, 'removing', [['state' => 'kept']]);

		[, $list]        = self::send($config, 'GET', "/accounts/{$account}/transactions");
		$transaction     = $list['data']['items'][0]['transaction_id'];
		[$status, $json] = self::send($config, 'PATCH', "/accounts/{$account}", [
			'transactions' => [['id' => $transaction, '_delete' => true]],
		]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('GOBL_ORM_RELATION_CANNOT_UNLINK', $json['msg'], \json_encode($json));

		[$status, $json] = self::send($config, 'PATCH', "/accounts/{$account}", [
			'owned_transactions' => [['id' => $transaction, '_delete' => true]],
		]);

		self::assertSame(200, $status, \json_encode($json));

		[, $list] = self::send($config, 'GET', "/accounts/{$account}/transactions");

		self::assertSame([], $list['data']['items'] ?? null, \json_encode($list));
	}

	/**
	 * `_attach` links an existing record, picked as a candidate: one the relation does not offer is
	 * refused, and an entry cannot both attach and remove.
	 */
	#[DataProvider('provideDbConfig')]
	public function testAnAttachedRecordMustBeACandidate(DbTestConfig $config): void
	{
		$account = self::account($config, 'attaching');
		$open    = self::client($config, 'open');
		$blocked = self::client($config, 'blocked');

		[$status, $json] = self::send($config, 'PATCH', "/accounts/{$account}", [
			'client' => ['id' => $blocked, '_attach' => true],
		]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('GOBL_ORM_RELATION_NOT_A_CANDIDATE', $json['msg'], \json_encode($json));

		[$status, $json] = self::send($config, 'PATCH', "/accounts/{$account}", [
			'client' => ['id' => $open, '_attach' => true],
		]);

		self::assertSame(200, $status, \json_encode($json));

		[, $item] = self::send($config, 'GET', "/accounts/{$account}");

		self::assertSame((string) $open, (string) $item['data']['item']['account_client_id']);

		[$status, $json] = self::send($config, 'PATCH', "/accounts/{$account}", [
			'client' => ['id' => $open, '_attach' => true, '_delete' => true],
		]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('OZ_RELATION_ENTRY_ATTACH_OR_DELETE', $json['msg'], \json_encode($json));
	}

	/**
	 * The candidates route answers the records the relation offers, for a row not saved yet or a
	 * stored one, searched like any list; a host naming a field it does not have is refused.
	 */
	#[DataProvider('provideDbConfig')]
	public function testTheCandidatesRouteAnswersWhatTheRelationOffers(DbTestConfig $config): void
	{
		[$status, $json] = self::send($config, 'GET', '/accounts/relations/client/candidates');

		self::assertSame(200, $status, \json_encode($json));

		$names = \array_column($json['data']['items'] ?? [], 'client_name');

		self::assertContains('open', $names);
		self::assertNotContains('blocked', $names);

		$account         = self::account($config, 'stored');
		[$status, $json] = self::send($config, 'GET', "/accounts/relations/client/candidates?host[id]={$account}&host[label]=edited");

		self::assertSame(200, $status, \json_encode($json));
		self::assertNotContains('blocked', \array_column($json['data']['items'] ?? [], 'client_name'));

		[$status, $json] = self::send($config, 'GET', '/accounts/relations/client/candidates?host[nowhere]=1');

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('OZ_CANDIDATES_HOST_INVALID', $json['msg'], \json_encode($json));

		// A relation that declares none has no route.
		[$status] = self::send($config, 'GET', '/accounts/relations/transactions/candidates');

		self::assertSame(404, $status);
	}

	/**
	 * A provider of the project narrows the candidates with the request's context, typed as OZone's
	 * by the generated class; a plain write is checked with the same context, which the ORM is
	 * handed at boot.
	 */
	#[DataProvider('provideDbConfig')]
	public function testAProviderDecidesWithTheRequestsContext(DbTestConfig $config): void
	{
		[$status, $json] = self::send($config, 'GET', '/accounts/relations/local_client/candidates?client_name=open');

		self::assertSame(200, $status, \json_encode($json));
		self::assertSame(['open'], \array_column($json['data']['items'] ?? [], 'client_name'));

		$open    = self::client($config, 'open');
		$blocked = self::client($config, 'blocked');

		[$status, $json] = self::send($config, 'POST', '/accounts?client_name=open', ['label' => 'local', 'local_client_id' => $open]);

		self::assertSame(201, $status, \json_encode($json));

		[$status, $json] = self::send($config, 'POST', '/accounts?client_name=open', ['label' => 'local', 'local_client_id' => $blocked]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertSame('GOBL_ORM_RELATION_NOT_A_CANDIDATE', $json['msg'], \json_encode($json));
		self::assertSame('account_local_client_id', $json['data']['field'] ?? null, \json_encode($json));

		// The context the provider reads is typed as OZone's in the class the build generated.
		$generated = null;

		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::$projects[$config->rdbms]->getPath(), \FilesystemIterator::SKIP_DOTS)) as $file) {
			if ('AccountLocalClientCandidates.php' === $file->getFilename()) {
				$generated = (string) \file_get_contents($file->getPathname());

				break;
			}
		}

		self::assertNotNull($generated, 'the build generated the candidates context');
		self::assertStringContainsString('public function context(): \\OZONE\\Core\\App\\Context', $generated);
	}

	/**
	 * What the services export lists is what answers: `get_candidates` for a table with a relation
	 * offering candidates, and not for the others.
	 */
	#[DataProvider('provideDbConfig')]
	public function testTheServicesExportListsCandidatesWhereTheyAreServed(DbTestConfig $config): void
	{
		$out      = self::$projects[$config->rdbms]->oz('services', 'export', '--json')->mustRun()->getOutput();
		$services = \array_column((array) (\json_decode($out, true)['data']['services'] ?? []), 'actions', 'name');

		self::assertContains('get_candidates', $services['accounts'] ?? [], $out);
		self::assertNotContains('get_candidates', $services['transactions'] ?? [], $out);
		self::assertNotContains('get_candidates', $services['clients'] ?? [], $out);
	}

	/**
	 * The bulk route: several rows at once, all or none, a refused one located by its position.
	 */
	#[DataProvider('provideDbConfig')]
	public function testTheBulkRouteCreatesEveryRowOrNone(DbTestConfig $config): void
	{
		$account = self::account($config, 'bulk target');

		[$status, $json] = self::send($config, 'POST', '/transactions/bulk', ['items' => [
			['account_id' => $account, 'state' => 'bulk one'],
			['account_id' => $account, 'state' => 'bulk two'],
		]]);

		self::assertSame(201, $status, \json_encode($json));
		self::assertSame(['bulk one', 'bulk two'], \array_column($json['data']['items'] ?? [], 'transaction_state'));

		// The second names no account: refused where it is, and the first not written either.
		[$status, $json] = self::send($config, 'POST', '/transactions/bulk', ['items' => [
			['account_id' => $account, 'state' => 'never one'],
			['account_id' => 'not-an-account', 'state' => 'never two'],
		]]);

		self::assertSame(400, $status, \json_encode($json));
		self::assertStringStartsWith('items.1', (string) ($json['data']['field'] ?? ''), \json_encode($json));

		[, $list] = self::send($config, 'GET', "/accounts/{$account}/transactions");

		self::assertNotContains('never one', \array_column($list['data']['items'] ?? [], 'transaction_state'));
	}

	/**
	 * Closed, as it is by default: the bulk route and an account with several transactions are
	 * refused, one transaction still created as before; and the cap holds whatever is open.
	 */
	#[DataProvider('provideDbConfig')]
	public function testABulkCreateIsRefusedUntilOpenedAndCapped(DbTestConfig $config): void
	{
		$account = self::account($config, 'closed bulk');

		self::restart($config, ['oz.test.bulk' => ['OPEN' => false]]);

		try {
			[$status, $json] = self::send($config, 'POST', '/transactions/bulk', ['items' => [
				['account_id' => $account, 'state' => 'closed one'],
				['account_id' => $account, 'state' => 'closed two'],
			]]);

			self::assertSame(403, $status, \json_encode($json));

			[$status, $json] = self::send($config, 'POST', '/accounts', [
				'label'        => 'two at once',
				'transactions' => [['state' => 'a'], ['state' => 'b']],
			]);

			self::assertSame(403, $status, \json_encode($json));

			[$status, $json] = self::send($config, 'POST', '/accounts', [
				'label'        => 'one alone',
				'transactions' => [['state' => 'alone']],
			]);

			self::assertSame(201, $status, \json_encode($json));
		} finally {
			self::restart($config, ['oz.test.bulk' => ['OPEN' => true], 'oz.db' => ['OZ_DB_CREATE_BULK_MAX_ITEMS' => 2]]);
		}

		try {
			[$status, $json] = self::send($config, 'POST', '/transactions/bulk', ['items' => [
				['account_id' => $account, 'state' => 'cap one'],
				['account_id' => $account, 'state' => 'cap two'],
				['account_id' => $account, 'state' => 'cap three'],
			]]);

			self::assertSame(400, $status, \json_encode($json));
			self::assertSame('OZ_FORM_BULK_TOO_MANY_ITEMS', $json['msg'] ?? null, \json_encode($json));
		} finally {
			self::restart($config, ['oz.db' => ['OZ_DB_CREATE_BULK_MAX_ITEMS' => 100]]);
		}
	}

	/**
	 * Writes settings of the project, then starts its server again: the dev server keeps the settings
	 * files it compiled.
	 *
	 * @param array<string, array<string, mixed>> $groups
	 */
	private static function restart(DbTestConfig $config, array $groups): void
	{
		$project = self::$projects[$config->rdbms] ?? null;

		self::assertNotNull($project, 'The project of this database is gone.');

		foreach ($groups as $group => $settings) {
			foreach ($settings as $key => $value) {
				$project->setSetting($group, $key, $value);
			}
		}

		[$process] = self::$servers[$config->rdbms];

		$process->stop(3);

		self::$servers[$config->rdbms] = $project->startServer('api');
	}

	/**
	 * The API docs describe each relation's candidates, and none for a relation that declares none.
	 */
	#[DataProvider('provideDbConfig')]
	public function testTheCandidatesAreDocumentedPerRelation(DbTestConfig $config): void
	{
		[$status, $json] = self::send($config, 'GET', '/api-doc-spec.json');

		self::assertSame(200, $status, \json_encode($json));

		$spec = (string) \json_encode($json);

		self::assertStringContainsString('get_candidates.client', $spec);
		self::assertStringNotContainsString('get_candidates.transactions', $spec);
	}

	public static function provideDbConfig(): iterable
	{
		return DbTestConfig::allConfigured('rest-relations');
	}

	/**
	 * Creates an account, with transactions when given, and answers its id.
	 *
	 * @param list<array<string, mixed>> $transactions
	 */
	private static function account(DbTestConfig $config, string $label, array $transactions = []): int|string
	{
		[$status, $json] = self::send($config, 'POST', '/accounts', ['label' => $label] + ([] === $transactions ? [] : ['transactions' => $transactions]));

		self::assertSame(201, $status, \json_encode($json));

		return $json['data']['item']['account_id'];
	}

	/**
	 * The id of the client of this name.
	 */
	private static function client(DbTestConfig $config, string $name): int|string
	{
		[, $json] = self::send($config, 'GET', '/clients?filters[0]=name&filters[1]=eq&filters[2]=' . $name);

		return $json['data']['items'][0]['client_id'];
	}

	/**
	 * @param array<string, mixed> $body
	 *
	 * @return array{0: int, 1: array<string, mixed>}
	 */
	private static function send(DbTestConfig $config, string $method, string $path, array $body = []): array
	{
		if (!isset(self::$servers[$config->rdbms])) {
			self::fail(\sprintf('Project for %s not initialized. Did testSetup pass?', $config->rdbms));
		}

		[, $host, $port] = self::$servers[$config->rdbms];

		$headers = "Accept: application/json\r\n";
		$content = null;

		if ([] !== $body) {
			$headers .= "Content-Type: application/json\r\n";
			$content = \json_encode($body, \JSON_THROW_ON_ERROR);
		}

		$response = @\file_get_contents(
			"http://{$host}:{$port}{$path}",
			false,
			\stream_context_create(['http' => [
				'method'        => $method,
				'ignore_errors' => true,
				'timeout'       => 30,
				'header'        => $headers,
				'content'       => $content,
			]])
		);
		$status = (int) \explode(' ', (\http_get_last_response_headers() ?? [])[0] ?? 'HTTP/1.1 0')[1];

		return [$status, (array) \json_decode((string) $response, true)];
	}
}
