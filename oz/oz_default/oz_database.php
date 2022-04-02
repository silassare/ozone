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

use OZONE\OZ\Auth\AuthState;
use OZONE\OZ\Core\Configs;
use OZONE\OZ\Forms\Fields;

return [
	'oz_users'     => [
		'plural_name'   => 'oz_users',
		'singular_name' => 'oz_user',
		'column_prefix' => 'user',
		'relations'     => [
			'files'            => ['type' => 'one-to-many', 'target' => 'oz_files'],
			'country'          => [
				'type'   => 'one-to-one',
				'target' => 'oz_countries',
			],
			'sessions'         => [
				'type'   => 'one-to-many',
				'target' => 'oz_sessions',
			],
			'attached-clients' => [
				'type'    => 'one-to-many',
				'target'  => 'oz_clients',
				'columns' => ['id' => 'user_id'],
			],
			'owned-clients'    => [
				'type'    => 'one-to-many',
				'target'  => 'oz_clients',
				'columns' => ['id' => 'added_by'],
			],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique', 'columns' => ['phone']],
			['type' => 'unique', 'columns' => ['email']],
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
				'null'       => !Configs::get('oz.users', 'OZ_USER_PHONE_REQUIRED'),
			],
			'email'      => [
				'type'       => 'email',
				'registered' => false,
				'null'       => !Configs::get('oz.users', 'OZ_USER_EMAIL_REQUIRED'),
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
			'birth_date' => Fields::birthDate(Configs::get('oz.users', 'OZ_USER_MIN_AGE'), Configs::get('oz.users', 'OZ_USER_MAX_AGE')),
			'pic'        => [
				'type'    => 'file',
				'mime'    => ['image/png', 'image/jpeg'],
				'null'    => true,
			],
			'cc2'        => [
				'type'       => 'cc2',
				'authorized' => true,
			],
			'data'       => [
				'type'    => 'array',
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
			'valid'      => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_roles'     => [
		'private'       => true,
		'plural_name'   => 'oz_roles',
		'singular_name' => 'oz_role',
		'column_prefix' => 'role',
		'relations'     => [
			'user' => ['type' => 'one-to-one', 'target' => 'oz_users'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique', 'columns' => ['user_id', 'name']],
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
				'type'    => 'array',
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
			'valid'      => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
	'oz_clients'   => [
		'private'       => true,
		'plural_name'   => 'oz_clients',
		'singular_name' => 'oz_client',
		'column_prefix' => 'client',
		'relations'     => [
			'owner'    => [
				'type'    => 'many-to-one',
				'target'  => 'oz_users',
				'columns' => ['added_by' => 'id'],
			],
			'user'     => [
				'type'    => 'many-to-one',
				'target'  => 'oz_users',
				'columns' => ['user_id' => 'id'],
			],
			'sessions' => [
				'type'   => 'one-to-many',
				'target' => 'oz_sessions',
			],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique', 'columns' => ['api_key']],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_users',
				'columns'   => ['added_by' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'cascade',
			],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_users',
				'columns'   => ['user_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'cascade',
			],
		],
		'columns'       => [
			'id'                => [
				'type'           => 'bigint',
				'unsigned'       => true,
				'auto_increment' => true,
			],
			'api_key'           => [
				'type' => 'string',
				'max'  => 64,
			],
			// this client owner
			'added_by'          => 'ref:oz_users.id',
			// when specified, the attached user right will be used every time
			// the api key of the client is used
			'user_id'           => [
				'type' => 'ref:oz_users.id',
				'null' => true,
			],
			'url'               => [
				'type' => 'string',
				'max'  => 255,
			],
			'session_life_time' => [
				'type'     => 'bigint',
				'unsigned' => true,
				'default'  => 86400,
			],
			'about'             => [
				'type' => 'string',
			],
			'data'              => [
				'type'    => 'array',
				'default' => [],
			],
			'created_at'        => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'updated_at'        => [
				'type'   => 'date',
				'format' => 'timestamp',
				'auto'   => true,
			],
			'valid'             => [
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
			'client' => ['type' => 'many-to-one', 'target' => 'oz_clients'],
			'user'   => ['type' => 'many-to-one', 'target' => 'oz_users'],
		],
		'constraints'   => [
			['type' => 'primary_key', 'columns' => ['id']],
			['type' => 'unique', 'columns' => ['token']],
			[
				'type'      => 'foreign_key',
				'reference' => 'oz_clients',
				'columns'   => ['client_id' => 'id'],
				'update'    => 'cascade',
				'delete'    => 'cascade',
			],
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
				'type' => 'string',
				'min'  => 6,
				'max'  => 128,
			],
			'client_id'  => 'ref:oz_clients.id',
			'user_id'    => [
				'type' => 'ref:oz_users.id',
				'null' => true,
			],
			'token'      => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 250,
			],
			'expire'     => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'verified'   => [
				'type'    => 'bool',
				'default' => false,
			],
			'last_seen'  => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'data'       => [
				'type'    => 'array',
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
			'valid'      => [
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
			['type' => 'unique', 'columns' => ['refresh_key']],
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
			'for'         => [
				'type' => 'string',
			],
			'code_hash'        => [
				'type' => 'string',
				'max'  => 128,
			],
			'token_hash'       => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'state'       => [
				'type'    => 'string',
				'max'     => 32,
				'one_of'  => [AuthState::PENDING->value, AuthState::AUTHORIZED->value, AuthState::REFUSED->value],
				'default' => AuthState::PENDING->value,
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
			'lifetime'      => [
				'type'     => 'int',
				'unsigned' => true,
			],
			'expire'      => [
				'type'   => 'date',
				'format' => 'timestamp',
			],
			'data'        => [
				'type'    => 'array',
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
			'disabled'    => [
				'type'    => 'bool',
				'default' => false,
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
			'cc2'        => [
				'type' => 'cc2',
			],
			'code'       => [
				'type' => 'string',
				'max'  => 6,
			],
			'name'       => [
				'type' => 'string',
				'max'  => 255,
			],
			'name_real'  => [
				'type' => 'string',
				'max'  => 255,
			],
			'data'       => [
				'type'    => 'array',
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
			'valid'      => [
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
				'columns'   => ['user_id' => 'id'],
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
			'user_id'    => [
				'type' => 'ref:oz_users.id',
				'null' => true,
			],
			'key'        => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'ref'        => [
				'type' => 'string',
				'min'  => 32,
				'max'  => 128,
			],
			'driver'     => [
				'type' => 'string',
				'max'  => 32,
			],
			'clone_id'   => [
				'type'     => 'bigint',
				'unsigned' => true,
				'null'     => true,
				'default'  => null,
			],
			'source_id'  => [
				'type'     => 'bigint',
				'unsigned' => true,
				'null'     => true,
				'default'  => null,
			],
			'size'       => [
				'type'     => 'int',
				'unsigned' => true,
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
			'label'      => [
				'type' => 'string',
				'max'  => 255,
			],
			'data'       => [
				'type'    => 'array',
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
			'valid'      => [
				'type'    => 'bool',
				'default' => true,
			],
		],
	],
];
