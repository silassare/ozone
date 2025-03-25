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
use Gobl\DBAL\Column;
use OZONE\Core\App\Settings;
use OZONE\Core\Auth\Enums\AuthState;
use OZONE\Core\Columns\Types\TypeCC2;
use OZONE\Core\Columns\Types\TypeGender;
use OZONE\Core\Columns\Types\TypeUsername;
use OZONE\Core\Columns\TypeUtils;
use OZONE\Core\FS\Enums\FileKind;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;
use OZONE\Core\Users\UsersRepository;

return static function (NamespaceBuilder $ns) {
	$ns->table('oz_users', static function (TableBuilder $tb) {
		$tb->plural('oz_users')
			->singular('oz_user')
			->columnPrefix('user');

		$min_age = Settings::get('oz.users', 'OZ_USER_MIN_AGE');
		$max_age = Settings::get('oz.users', 'OZ_USER_MAX_AGE');

		// columns
		UsersRepository::makeAuthUserTable($tb);

		$tb->column('name', new TypeUsername());
		$tb->column('gender', new TypeGender());
		$tb->column('birth_date', TypeUtils::birthDate($min_age, $max_age));
		$tb->bool('is_valid')->default(true);

		// constraints
		$tb->collectFk(static function () use ($tb) {
			$tb->foreign('cc2', 'oz_countries', 'cc2', false, static function (Column $column) {
				/** @var TypeCC2 $cc2_type */
				$cc2_type = $column->getType();
				$cc2_type->authorized();
			})
				->onUpdateCascade()
				->onDeleteRestrict();
		});

		// relations
		$tb->collectRelation(static function () use ($tb) {
			$tb->hasMany('roles')->from('oz_roles')->usingMorph('owner');
			$tb->hasMany('files')->from('oz_files')->usingMorph('for');
			$tb->hasMany('sessions')->from('oz_sessions')->usingMorph('owner');
			$tb->belongsTo('country')->from('oz_countries');
		});
	});

	$ns->table('oz_countries', static function (TableBuilder $tb) {
		$tb->plural('oz_countries')
			->singular('oz_country')
			->columnPrefix('country');

		// columns
		$tb->column('cc2', (new TypeCC2())->check(false));
		$tb->string('calling_code')->max(6);
		$tb->string('name')->max(255);
		$tb->string('name_real')->max(255);
		$tb->map('data')->default([]);
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->primary('cc2');
	});

	$ns->table('oz_files', static function (TableBuilder $tb) {
		$tb->plural('oz_files')
			->singular('oz_file')
			->columnPrefix('file');

		// columns
		$tb->id();
		$tb->string('key')->min(8)->max(128);
		$tb->string('ref')->min(1)->max(255);
		$tb->string('storage')->min(1)->max(128);
		$tb->int('size')->unsigned();
		$tb->enum('kind', FileKind::class);
		$tb->string('mime')->max(60);
		$tb->string('extension')->max(20);
		$tb->string('name')->max(100)->truncate();
		$tb->string('real_name')->max(100)->truncate();
		// this is a morph field: user_id
		// ex:
		// 	- user have an avatar/profile pic
		//	- post has an image/video/audio/file/attachment
		$tb->morph('for', TypeUtils::morphAnyId(), null, true);
		// this file is used for what
		// ex: asset, avatar, profile_pic, post_image, post_video, post_audio, post_file, post_attachment etc...
		$tb->string('for_label')->max(64)->default('asset');
		$tb->map('data')->default([]);
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();
		$tb->softDeletable();

		$tb->morph('uploader', TypeUtils::morphAnyId(), null, true);

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
			->columnPrefix('role');

		// columns
		$tb->id();
		$tb->string('name')->min(1)->max(60);
		$tb->map('data')->default([]);
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->morph('owner', TypeUtils::morphAnyId());

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('owner_id', 'owner_type', 'name');
		});
	});

	$ns->table('oz_jobs', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_jobs')
			->singular('oz_job')
			->columnPrefix('job');

		// columns
		$tb->id();
		$tb->string('ref')->min(32)->max(128);
		$tb->enum('state', JobState::class)->default(JobState::PENDING);
		$tb->string('queue')->min(1)->max(128)->default(Queue::DEFAULT);
		$tb->string('name')->min(1)->max(128);
		$tb->string('worker')->min(1)->max(128);
		$tb->int('priority')->unsigned()->default(0);
		$tb->int('try_count')->unsigned()->default(0);
		$tb->int('retry_max')->unsigned()->default(3);
		$tb->int('retry_delay')->unsigned()->default(180 /* 3 minutes */);
		$tb->map('payload')->default([]);
		$tb->map('result')->default([]);
		$tb->map('errors')->default([]);
		$tb->bool('locked')->default(false);
		$tb->timestamp('started_at')->microseconds()->nullable();
		$tb->timestamp('ended_at')->microseconds()->nullable();
		$tb->timestamps();

		// constraints
		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('ref');
		});
	});

	$ns->table('oz_sessions', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_sessions')
			->singular('oz_session')
			->columnPrefix('session');

		// columns
		$tb->string('id')->min(6)->max(128);
		$tb->string('request_source_key')->min(6)->max(250)->truncate();
		$tb->timestamp('expire');
		$tb->timestamp('last_seen');
		$tb->map('data')->default([]);
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();

		// constraints
		$tb->primary('id');

		$tb->morph('owner', TypeUtils::morphAnyId(), null, true);
	});

	$ns->table('oz_db_stores', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_db_stores')
			->singular('oz_db_store')
			->columnPrefix('store');

		// columns
		$tb->id();
		$tb->string('group')->min(1)->max(128);
		$tb->string('key')->min(32)->max(128);
		$tb->string('value')->nullable();
		$tb->string('label')->max(255);
		$tb->map('data')->default([]);
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
			->columnPrefix('auth');

		// columns
		$tb->string('ref')->min(32)->max(128);
		$tb->string('label')->min(1)->max(128);
		$tb->string('refresh_key')->min(32)->max(128);
		$tb->string('provider')->min(1)->max(128);
		$tb->map('payload')->default([]);
		$tb->string('code_hash')->max(128);
		$tb->string('token_hash')->min(32)->max(128);
		$tb->enum('state', AuthState::class)->default(AuthState::PENDING);
		$tb->int('try_max')->unsigned()->default(1);
		$tb->int('try_count')->unsigned()->default(0);
		$tb->int('lifetime')->unsigned();
		$tb->timestamp('expire');
		$tb->map('permissions')->default([]);
		$tb->morph('owner', TypeUtils::morphAnyId(), null, true);
		$tb->bool('is_valid')->default(true);
		$tb->timestamps();
		$tb->softDeletable();

		// constraints
		$tb->primary('ref');

		$tb->collectIndex(static function (TableBuilder $tb) {
			$tb->unique('refresh_key');
			$tb->unique('token_hash');
		});
	});

	// this table is supposed to have only one entry
	// and is for storing the current version of the database
	// and to make sure the database is versioned
	$ns->table('oz_migrations', static function (TableBuilder $tb) {
		$tb->getTable()->setPrivate();

		$tb->plural('oz_migrations')
			->singular('oz_migration')
			->columnPrefix('migration');

		// columns
		$tb->id();
		$tb->int('version')->unsigned()->min(1);
		$tb->timestamps();
	});
};
