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
