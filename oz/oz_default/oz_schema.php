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

use Gobl\DBAL\Builders\NamespaceBuilder;
use Gobl\DBAL\Builders\TableBuilder;
use Gobl\DBAL\MigrationMode;
use OZONE\Core\Auth\Enums\AuthorizationState;
use OZONE\Core\Columns\Types\TypeCC2;
use OZONE\Core\Columns\Types\TypeUsername;
use OZONE\Core\Columns\TypeUtils;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Roles\RolesUtils;
use OZONE\Core\Users\UsersRepository;
use OZONE\Core\Utils\JSONResult;

return static function (NamespaceBuilder $ns) {
	$ns->table('oz_users', static function (TableBuilder $tb) {
		$tb->plural('oz_users')
			->singular('oz_user')
			->columnPrefix('user')
			->morphType('user')
			->meta('api.doc.description', 'The main authentication user table storing credentials, profile info, and status.');

		UsersRepository::makeAuthUserTable($tb);
	});

	// this table is for storing the usernames for all auth users type
	// it's ensure uniqueness of usernames across all auth user types
	// so we can easily check for username availability and also to be able
	// to find the auth user of a given username
	$ns->table('oz_usernames', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_usernames')
			->singular('oz_username')
			->columnPrefix('username')
			->morphType('username')
			->meta('api.doc.description', 'A mapping of unique usernames to their corresponding auth user identifiers for global username management.');

		// columns
		$tb->column('name', new TypeUsername())
			->setMetaKey('field.label', 'Username')
			->setMetaKey('api.doc.description', 'The unique username of the auth user');

		// which auth user is this username for
		$tb->morph('user', TypeUtils::morphAnyId(), null, true);

		$tb->softDeletable();
		$tb->timestamps();

		// constraints
		$tb->primary('name');
	});

	$ns->table('oz_countries', static function (TableBuilder $tb) {
		$tb->plural('oz_countries')
			->singular('oz_country')
			->columnPrefix('country')
			->morphType('country')
			->meta('api.doc.description', 'A country entry with its calling code, names, and validity status.');

		// columns
		$tb->column('cc2', (new TypeCC2())->check(false))
			->setMetaKey('field.label', 'Country Code')
			->setMetaKey('api.doc.description', 'The ISO 3166-1 alpha-2 country code (e.g. FR, US).');

		$tb->string('calling_code')->max(6)
			->setMetaKey('field.label', 'Calling Code')
			->setMetaKey('api.doc.description', 'The international phone calling code (e.g. +33, +1).');

		$tb->string('name')->max(255)
			->setMetaKey('field.label', 'Name')
			->setMetaKey('api.doc.description', 'The localized display name of the country.');

		$tb->string('name_real')->max(255)
			->setMetaKey('field.label', 'Real Name')
			->setMetaKey('api.doc.description', 'The official/native name of the country.');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Additional structured data for the country.');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether the country entry is active and usable.');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->primary('cc2');
	});

	$ns->table('oz_files', static function (TableBuilder $tb) {
		$tb->plural('oz_files')
			->singular('oz_file')
			->columnPrefix('file')
			->morphType('file')
			->meta('api.doc.description', 'An uploaded file with its storage reference, MIME type, and ownership info.');

		// columns
		$tb->id();

		$tb->string('key')->min(8)->max(128)
			->setMetaKey('field.label', 'Key')
			->setMetaKey('api.doc.description', 'The unique file key used for access control.');

		$tb->string('ref')->min(1)->max(255)
			->setMetaKey('field.label', 'Storage Ref')
			->setMetaKey('api.doc.description', 'The storage-layer reference/path of the file.');

		$tb->string('storage')->min(1)->max(128)
			->setMetaKey('field.label', 'Storage')
			->setMetaKey('api.doc.description', 'The storage driver name where this file is stored.');

		$tb->int('size')->unsigned()
			->setMetaKey('field.label', 'Size')
			->setMetaKey('api.doc.description', 'The file size in bytes.');

		$tb->enum('kind', FileKind::class)
			->setMetaKey('field.label', 'Kind')
			->setMetaKey('api.doc.description', 'The file classification (e.g. image, video, audio, document).');

		$tb->string('mime')->max(60)
			->setMetaKey('field.label', 'MIME Type')
			->setMetaKey('api.doc.description', 'The MIME type of the file (e.g. image/jpeg).');

		$tb->string('extension')->max(20)
			->setMetaKey('field.label', 'Extension')
			->setMetaKey('api.doc.description', 'The file extension without the leading dot (e.g. jpg, pdf).');

		$tb->string('name')->max(100)->truncate()
			->setMetaKey('field.label', 'Name')
			->setMetaKey('api.doc.description', 'The sanitized public display name of the file.');

		$tb->string('real_name')->max(100)->truncate()
			->setMetaKey('field.label', 'Real Name')
			->setMetaKey('api.doc.description', 'The original filename as uploaded by the client.');

		// which auth user uploaded this file, if any
		$tb->morph('uploader', TypeUtils::morphAnyId(), null, true);

		// to which entity is this file attached to, if any (e.g. a post, a comment, a profile etc...)
		// this not necessarily the owner of the file but just an entity this file is related to or used for
		$tb->morph('for', TypeUtils::morphAnyId(), null, true);

		// this file is used for what
		// ex: asset, avatar, profile_pic, post_image, post_video, post_audio, post_file, post_attachment etc...
		$tb->string('for_label')->max(64)->default('asset')
			->setMetaKey('field.label', 'For Label')
			->setMetaKey('api.doc.description', 'Describes the intended purpose of this file (e.g. avatar, post_image).');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Additional structured metadata for the file.');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether the file is active and accessible.');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectFk(static function (TableBuilder $tb) {
			$tb->foreign('clone_id', 'oz_files', 'id', true)
				->onUpdateCascade()
				->onDeleteSetNull();
			$tb->foreign('source_id', 'oz_files', 'id', true)
				->onUpdateCascade()
				->onDeleteSetNull();
		});

		// relations
		$tb->collectRelation(static function (TableBuilder $tb) {
			$tb->hasMany('clones')->from('oz_files')->usingColumns([
				'id' => 'clone_id',
			]);

			$tb->belongsTo('cloned_from')
				->from('oz_files')
				->usingColumns([
					'clone_id' => 'id',
				]);

			$tb->belongsTo('source')
				->from('oz_files')
				->usingColumns([
					'source_id' => 'id',
				]);
		});
	});

	// START OF TABLES THAT SHOULD BE KEPT PRIVATE

	$ns->table('oz_roles', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_roles')
			->singular('oz_role')
			->columnPrefix('role')
			->meta('api.doc.description', 'A role assignment linking an owner entity to a specific role.');

		// columns
		$tb->id();

		$tb->morph('owner', TypeUtils::morphAnyId());

		$tb->enum('role', RolesUtils::getRoleEnumClass())
			->setMetaKey('field.label', 'Role')
			->setMetaKey('api.doc.description', 'The role value assigned to this owner.');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Additional structured data for this role assignment.');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether this role assignment is currently active.');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('owner_id', 'owner_type', 'role');
		});
	});

	$ns->table('oz_jobs', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_jobs')
			->singular('oz_job')
			->columnPrefix('job')
			->meta('api.doc.description', 'A background job queued for async processing with retry and state tracking.');

		// columns
		$tb->id();

		$tb->string('ref')->min(32)->max(128)
			->setMetaKey('field.label', 'Reference')
			->setMetaKey('api.doc.description', 'A unique reference string identifying this job instance.');

		$tb->enum('state', JobState::class)->default(JobState::PENDING)
			->setMetaKey('field.label', 'State')
			->setMetaKey('api.doc.description', 'The current execution state of the job (pending, running, done, failed).');

		$tb->string('queue')->min(1)->max(128)->default(Queue::DEFAULT)
			->setMetaKey('field.label', 'Queue')
			->setMetaKey('api.doc.description', 'The queue channel this job belongs to.');

		$tb->string('name')->min(1)->max(128)
			->setMetaKey('field.label', 'Name')
			->setMetaKey('api.doc.description', 'A human-readable name for this job.');

		$tb->string('worker')->min(1)->max(128)
			->setMetaKey('field.label', 'Worker')
			->setMetaKey('api.doc.description', 'The fully qualified class name of the worker that processes this job.');

		$tb->int('priority')->unsigned()->default(0)
			->setMetaKey('field.label', 'Priority')
			->setMetaKey('api.doc.description', 'The job processing priority; higher values run first.');

		$tb->int('try_count')->unsigned()->default(0)
			->setMetaKey('field.label', 'Try Count')
			->setMetaKey('api.doc.description', 'The number of times this job has been attempted so far.');

		$tb->int('retry_max')->unsigned()->default(3)
			->setMetaKey('field.label', 'Max Retries')
			->setMetaKey('api.doc.description', 'The maximum number of retry attempts allowed before marking as failed.');

		$tb->int('retry_delay')->unsigned()->default(180 /* 3 minutes */)
			->setMetaKey('field.label', 'Retry Delay')
			->setMetaKey('api.doc.description', 'The delay in seconds between retry attempts.');

		$tb->map('payload')->default([])
			->setMetaKey('field.label', 'Payload')
			->setMetaKey('api.doc.description', 'The input data passed to the worker when the job runs.');

		$tb->jsonOf('result', JSONResult::class)->default([])
			->setMetaKey('field.label', 'Result')
			->setMetaKey('api.doc.description', 'The output produced by the worker after the job completes.');

		$tb->bool('locked')->default(false)
			->setMetaKey('field.label', 'Is Locked')
			->setMetaKey('api.doc.description', 'Whether the job is currently locked by a running worker process.');

		$tb->timestamp('started_at')->microseconds()->nullable()
			->setMetaKey('field.label', 'Started At')
			->setMetaKey('api.doc.description', 'The timestamp when job execution began.');

		$tb->timestamp('ended_at')->microseconds()->nullable()
			->setMetaKey('field.label', 'Ended At')
			->setMetaKey('api.doc.description', 'The timestamp when job execution ended (success or failure).');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('ref');
		});
	});

	$ns->table('oz_sessions', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_sessions')
			->singular('oz_session')
			->columnPrefix('session')
			->meta('api.doc.description', 'An active user session with expiry and ownership info.');

		// columns
		$tb->string('id')->min(6)->max(128)
			->setMetaKey('field.label', 'Session ID')
			->setMetaKey('api.doc.description', 'The unique session identifier stored in the cookie.');

		$tb->morph('owner', TypeUtils::morphAnyId(), null, true);

		$tb->string('request_source_key')->min(6)->max(250)->truncate()
			->setMetaKey('field.label', 'Request Source Key')
			->setMetaKey('api.doc.description', 'A fingerprint of the client environment used to detect session hijacking.');

		$tb->timestamp('expire_at')
			->setMetaKey('field.label', 'Expires At')
			->setMetaKey('api.doc.description', 'The timestamp after which this session is considered expired.');
		$tb->useColumn('expire_at')->oldName('expire');

		$tb->timestamp('last_seen_at')
			->setMetaKey('field.label', 'Last Seen At')
			->setMetaKey('api.doc.description', 'The timestamp of the most recent activity for this session.');
		$tb->useColumn('last_seen_at')->oldName('last_seen');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Arbitrary session data stored server-side.');

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether this session is still valid and usable.');

		$tb->timestamps();

		// constraints
		$tb->primary('id');
	});

	$ns->table('oz_db_stores', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_db_stores')
			->singular('oz_db_store')
			->columnPrefix('store')
			->meta('api.doc.description', 'A key-value store entry used for persistent framework-level data (e.g. cache, settings).');

		// columns
		$tb->id();

		$tb->string('group')->min(1)->max(128)
			->setMetaKey('field.label', 'Group')
			->setMetaKey('api.doc.description', 'The namespace or group this store entry belongs to.');

		$tb->string('key')->min(32)->max(128)
			->setMetaKey('field.label', 'Key')
			->setMetaKey('api.doc.description', 'The unique key within the group identifying this entry.');

		$tb->string('value')->nullable()
			->setMetaKey('field.label', 'Value')
			->setMetaKey('api.doc.description', 'The stored value for this key (may be null).');

		$tb->timestamp('expire_at')->nullable()
			->setMetaKey('field.label', 'Expires At')
			->setMetaKey('api.doc.description', 'The timestamp after which this store entry is considered expired, should no longer be used and garbage collected.');

		$tb->string('label')->max(255)
			->setMetaKey('field.label', 'Label')
			->setMetaKey('api.doc.description', 'A human-readable label describing this store entry.');

		$tb->map('data')->default([])
			->setMetaKey('field.label', 'Data')
			->setMetaKey('api.doc.description', 'Additional structured metadata for this entry.');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('group', 'key');
		});
	});

	$ns->table('oz_auths', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_auths')
			->singular('oz_auth')
			->columnPrefix('auth')
			->meta('api.doc.description', 'An authorization process record tracking verification state, codes, tokens, and retry limits.');

		// columns
		$tb->string('ref')->min(32)->max(128)
			->setMetaKey('field.label', 'Reference')
			->setMetaKey('api.doc.description', 'The unique reference token identifying this authorization process.');

		$tb->string('label')->min(1)->max(128)
			->setMetaKey('field.label', 'Label')
			->setMetaKey('api.doc.description', 'A human-readable label describing the purpose of this authorization.');

		$tb->string('refresh_key')->min(32)->max(128)
			->setMetaKey('field.label', 'Refresh Key')
			->setMetaKey('api.doc.description', 'A secret key used to refresh this authorization process.');

		$tb->string('provider')->min(1)->max(128)
			->setMetaKey('field.label', 'Provider')
			->setMetaKey('api.doc.description', 'The authorization provider name that owns this process (e.g. auth:provider:email:verify).');

		$tb->map('payload')->default([])
			->setMetaKey('field.label', 'Payload')
			->setMetaKey('api.doc.description', 'Arbitrary data provided by the authorization provider for this process.');

		$tb->string('code_hash')->max(128)
			->setMetaKey('field.label', 'Code Hash')
			->setMetaKey('api.doc.description', 'The hashed one-time code sent to the user for verification.');

		$tb->string('token_hash')->min(32)->max(128)
			->setMetaKey('field.label', 'Token Hash')
			->setMetaKey('api.doc.description', 'The hashed bearer token issued upon successful authorization.');

		$tb->enum('state', AuthorizationState::class)->default(AuthorizationState::PENDING)
			->setMetaKey('field.label', 'State')
			->setMetaKey('api.doc.description', 'The current state of this authorization process (pending, authorized, refused, etc.).');

		$tb->int('try_max')->unsigned()->default(1)
			->setMetaKey('field.label', 'Max Tries')
			->setMetaKey('api.doc.description', 'The maximum number of verification attempts allowed.');

		$tb->int('try_count')->unsigned()->default(0)
			->setMetaKey('field.label', 'Try Count')
			->setMetaKey('api.doc.description', 'The number of verification attempts made so far.');

		$tb->int('lifetime')->unsigned()
			->setMetaKey('field.label', 'Lifetime')
			->setMetaKey('api.doc.description', 'The validity duration of this authorization process in seconds.');

		$tb->timestamp('expire_at')
			->setMetaKey('field.label', 'Expires At')
			->setMetaKey('api.doc.description', 'The timestamp at which this authorization process expires.');
		$tb->useColumn('expire_at')->oldName('expire');

		$tb->map('permissions')->default([])
			->setMetaKey('field.label', 'Permissions')
			->setMetaKey('api.doc.description', 'Permissions granted by this authorization process once completed.');

		$tb->morph('owner', TypeUtils::morphAnyId(), null, true);

		$tb->bool('is_valid')->default(true)
			->setMetaKey('field.label', 'Is Valid')
			->setMetaKey('api.doc.description', 'Whether this authorization process is still active and usable.');

		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->primary('ref');

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('refresh_key');
			$tb->unique('token_hash');
		});
	});

	$ns->table('oz_migrations', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_migrations')
			->singular('oz_migration')
			->columnPrefix('migration')
			->meta('api.doc.description', 'Tracks the database migration history, the last entry represents the current database version.');

		// columns
		$tb->id();

		$tb->int('version')->unsigned()->min(1)
			->setMetaKey('field.label', 'Version')
			->setMetaKey('api.doc.description', 'The version number of the migration applied.');

		$tb->enum('mode', MigrationMode::class)->default(MigrationMode::FULL)
			->setMetaKey('field.label', 'Migration Mode')
			->setMetaKey('api.doc.description', 'The migration mode used for this migration.');

		$tb->timestamps();
	});
};
