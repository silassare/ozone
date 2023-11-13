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

use OZONE\Core\App\Settings;
use OZONE\Core\Auth\AuthState;
use OZONE\Core\Forms\Fields;
use OZONE\Core\FS\Enums\FileType;
use OZONE\Core\Queue\JobState;
use OZONE\Core\Queue\Queue;

return [
	'oz_users'     => [
		'plural_name'   => 'oz_users',
		'singular_name' => 'oz_user',
		'column_prefix' => 'user',
		'relations'     => [
			'files'    => ['type' => 'one-to-many', 'target' => 'oz_files'],
			'country'  => [
				'type'   => 'one-to-one',
				'target' => 'oz_countries',
			],
			'sessions' => [
				'type'   => 'one-to-many',
				'target' => 'oz_sessions',
			],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique_key', 'columns' => ['phone']],
			['type' => 'unique_key', 'columns' => ['email']],
			['type' => 'foreign_key', 'reference' => 'oz_countries', 'columns' => ['cc2' => 'cc2']],
		],
		'columns'       => [
			'id'         => [
				'type'           => 'bigint',
				'auto_increment' => true,
				'unsigned'       => true,
			],
			'phone'      => [
				'type'       => 'phone',
				'registered' => false,
				'nullable'   => !Settings::get('oz.users', 'OZ_USER_PHONE_REQUIRED'),
			],
			'email'      => [
				'type'       => 'email',
				'registered' => false,
				'nullable'   => !Settings::get('oz.users', 'OZ_USER_EMAIL_REQUIRED'),
			],
			'pass'       => [
				'type' => 'password',
			],
			'name'       => [
				'type' => 'user_name',
			],
			'gender'     => [
				'type' => 'gender',
			],
			'birth_date' => Fields::birthDate(Settings::get('oz.users', 'OZ_USER_MIN_AGE'), Settings::get('oz.users', 'OZ_USER_MAX_AGE')),
			'pic'        => [
				'type'     => 'file',
				'mime'     => ['image/png', 'image/jpeg'],
				'nullable' => true,
			],
			'cc2'        => [
				'type'       => 'cc2',
				'authorized' => true,
			],
			'data'       => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'   => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_roles'     => [
		'plural_name'   => 'oz_roles',
		'singular_name' => 'oz_role',
		'column_prefix' => 'role',
		'relations'     => [
			'user' => ['type' => 'one-to-one', 'target' => 'oz_users'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique_key', 'columns' => ['user_id', 'name']],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_users',
				'columns'   => ['user_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'cascade',
			],
		],
		'columns'       => [
			'id'         => [
				'type'           => 'bigint',
				'unsigned'       => true,
				'auto_increment' => true,
			],
			'user_id'    => 'ref:oz_users.id',
			'name'       => [
				'type' => 'string', // super-admin, admin, editor, etc
				'min'  => 1,
				'max'  => 60,
			],
			'data'       => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'   => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_countries' => [
		'plural_name'   => 'oz_countries',
		'singular_name' => 'oz_country',
		'column_prefix' => 'country',
		'relations'     => [
			'users' => ['type' => 'one-to-many', 'target' => 'oz_users'],
		],
		'constraints'   => [['type' => 'primary_key', 'columns' => ['cc2']]],
		'columns'       => [
			'cc2'          => [
				'type' => 'string',
				'min'  => 2,
				'max'  => 2,
			],
			'calling_code' => [
				'type' => 'string',
				'max'  => 6,
			],
			'name'         => [
				'type' => 'string',
				'max'  => 255,
			],
			'name_real'    => [
				'type' => 'string',
				'max'  => 255,
			],
			'data'         => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at'   => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at'   => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'     => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_files'     => [
		'plural_name'   => 'oz_files',
		'singular_name' => 'oz_file',
		'column_prefix' => 'file',
		'relations'     => [
			'owner'       => ['type' => 'many-to-one', 'target' => 'oz_users'],
			'clones'      => ['type' => 'one-to-many', 'target' => 'oz_files', 'columns' => ['id' => 'clone_id']],
			'cloned_from' => ['type' => 'many-to-one', 'target' => 'oz_files', 'columns' => ['clone_id' => 'id']],
			'source'      => ['type' => 'many-to-one', 'target' => 'oz_files', 'columns' => ['id' => 'source_id']],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_users',
				'columns'   => ['owner_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'set_null',
			],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_files',
				'columns'   => ['clone_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'set_null',
			],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_files',
				'columns'   => ['source_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'set_null',
			],
		],
		'columns'       => [
			'id'         => [
				'type'           => 'bigint',
				'unsigned'       => true,
				'auto_increment' => true,
			],
			'owner_id'   => [
				'type'     => 'ref:oz_users.id',
				'nullable' => true,
			],
			'key'        => [
				'type' => 'string',
				'min'  => 8,
				'max'  => 128,
			],
			'ref'        => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 255,
			],
			'storage'    => [
				'type' => 'string',
				'max'  => 128,
			],
			'clone_id'   => [
				'type'     => 'bigint',
				'unsigned' => true,
				'nullable' => true,
				'default'  => null,
			],
			'source_id'  => [
				'type'     => 'bigint',
				'unsigned' => true,
				'nullable' => true,
				'default'  => null,
			],
			'size'       => [
				'type'     => 'int',
				'unsigned' => true,
			],
			'type'       => [
				'type'       => 'enum',
				'enum_class' => FileType::class,
			],
			'mime_type'  => [
				'type' => 'string',
				'max'  => 60,
			],
			'extension'  => [
				'type' => 'string',
				'max'  => 20,
			],
			'name'       => [
				'type'     => 'string',
				'max'      => 100,
				'truncate' => true,
			],
			// this is a morph field: user_id
			// ex:
			// 	- user have an avatar/profile pic
			//	- post has an image/video/audio/file/attachment
			'for_id'     => [
				'type'     => 'string',
				'max'      => 128,
				'nullable' => true,
			],
			// this is a morph field: oz_users...
			'for_type'   => [
				'type'     => 'string',
				'max'      => 64,
				'nullable' => true,
			],
			// this file is used for what
			// ex: asset, avatar, profile_pic, post_image, post_video, post_audio, post_file, post_attachment etc...
			'for_label'  => [
				'type'    => 'string',
				'default' => 'asset',
				'max'     => 64,
			],
			'data'       => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'   => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_jobs'      => [
		'plural_name'   => 'oz_jobs',
		'singular_name' => 'oz_job',
		'column_prefix' => 'job',
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			[
				'type'    => 'unique_key',
				'columns' => ['ref'],
			],
		],
		'columns'       => [
			'id'          => [
				'type'           => 'bigint',
				'unsigned'       => true,
				'auto_increment' => true,
			],
			'ref'         => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'state'       => [
				'type'       => 'enum',
				'enum_class' => JobState::class,
				'default'    => JobState::PENDING,
			],
			'queue'       => [
				'type'    => 'string',
				'min'     => 1,
				'max'     => 128,
				'default' => Queue::DEFAULT,
			],
			'name'        => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 128,
			],
			'worker'      => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 128,
			],
			'priority'    => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 0,
			],
			'try_count'   => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 0,
			],
			'retry_max'   => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 3,
			],
			'retry_delay' => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 60, // 1 minute
			],
			'payload'     => [
				'type'    => 'map',
				'default' => [],
			],
			'result'      => [
				'type'    => 'map',
				'default' => [],
			],
			'errors'      => [
				'type'    => 'map',
				'default' => [],
			],
			'locked'      => [
				'type'    => 'bool',
				'default' => false,
			],
			'started_at'  => [
				'type'      => 'date',
				'format'    => 'timestamp',
				'precision' => 'microseconds',
				'nullable'  => true,
			],
			'ended_at'    => [
				'type'      => 'date',
				'format'    => 'timestamp',
				'precision' => 'microseconds',
				'nullable'  => true,
			],
			'created_at'  => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at'  => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
		],
	],

	// private tables that should not
	// be exposed to the public
	'oz_db_stores' => [
		'private'       => true,
		'plural_name'   => 'oz_db_stores',
		'singular_name' => 'oz_db_store',
		'column_prefix' => 'store',
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			[
				'type'    => 'unique_key',
				'columns' => ['group', 'key'],
			],
		],
		'columns'       => [
			'id'         => [
				'type'           => 'bigint',
				'unsigned'       => true,
				'auto_increment' => true,
			],
			'group'      => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 128,
			],
			'key'        => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'value'      => [
				'type'     => 'string',
				'nullable' => true,
			],
			'label'      => [
				'type' => 'string',
				'max'  => 255,
			],
			'data'       => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at' => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'   => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_sessions'  => [
		'private'       => true,
		'plural_name'   => 'oz_sessions',
		'singular_name' => 'oz_session',
		'column_prefix' => 'session',
		'relations'     => [
			'user' => ['type' => 'many-to-one', 'target' => 'oz_users'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_users',
				'columns'   => ['user_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'cascade',
			],
		],
		'columns'       => [
			'id'                 => [
				'type' => 'string',
				'min'  => 6,
				'max'  => 128,
			],
			'user_id'            => [
				'type'     => 'ref:oz_users.id',
				'nullable' => true,
			],
			'request_source_key' => [
				// used to prevent session hijacking
				'type' => 'string',
				'min'  => 6,
				'max'  => 250,
			],
			'expire'             => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'last_seen'          => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'data'               => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at'         => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at'         => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'           => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_auths'     => [
		'private'       => true,
		'plural_name'   => 'oz_auths',
		'singular_name' => 'oz_auth',
		'column_prefix' => 'auth',
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['ref']],
			['type' => 'unique_key', 'columns' => ['refresh_key']],
			['type' => 'unique_key', 'columns' => ['token_hash']],
		],
		'columns'       => [
			'ref'         => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'label'       => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 128,
			],
			'refresh_key' => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'provider'    => [
				'type' => 'string',
				'min'  => 1,
				'max'  => 128,
			],
			'payload'     => [
				'type'    => 'map',
				'default' => [],
			],
			'code_hash'   => [
				'type' => 'string',
				'max'  => 128,
			],
			'token_hash'  => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'state'       => [
				'type'       => 'enum',
				'enum_class' => AuthState::class,
				'default'    => AuthState::PENDING,
			],
			'try_max'     => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 1,
			],
			'try_count'   => [
				'type'     => 'int',
				'unsigned' => true,
				'default'  => 0,
			],
			'lifetime'    => [
				'type'     => 'int',
				'unsigned' => true,
			],
			'expire'      => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'options'     => [
				'type'    => 'map',
				'default' => [],
			],
			'created_at'  => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at'  => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'is_valid'    => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
];
