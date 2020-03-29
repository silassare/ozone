<?php

/**
 * Copyright (c) 2017-present, Emile Silas Sare
 *
 * This file is part of OZone (O'Zone) package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
	
	use OZONE\OZ\Core\SettingsManager;

	return [
		'oz_users'          => [
			'plural_name'   => 'oz_users',
			'singular_name' => 'oz_user',
			'column_prefix' => 'user',
			'relations'     => [
				'oz_files'   => ['type' => 'one-to-many', 'target' => 'oz_files'],
				'oz_country' => ['type' => 'one-to-one', 'target' => 'oz_countries', 'columns' => ['cc2' => 'cc2']],
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
					'null'       => !SettingsManager::get('oz.users', 'OZ_USERS_PHONE_REQUIRED'),
				],
				'email'      => [
					'type'       => 'email',
					'registered' => false,
					'null'       => !SettingsManager::get('oz.users', 'OZ_USERS_EMAIL_REQUIRED'),
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
				'birth_date' => [
					'type'       => 'date',
					'birth_date' => true,
					'min_age'    => SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE'),
					'max_age'    => SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE'),
				],
				'picid'      => [
					'type'    => 'string',
					'default' => '0_0',
					'max'     => 50,
				],
				'cc2'        => [
					'type'       => 'cc2',
					'authorized' => true,
				],
				'data'       => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time'   => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'      => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_administrators' => [
			'plural_name'   => 'oz_administrators',
			'singular_name' => 'oz_admin',
			'column_prefix' => 'admin',
			'relations'     => [
				'oz_user' => ['type' => 'one-to-one', 'target' => 'oz_users'],
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['user_id']],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_users',
					'columns'   => ['user_id' => 'id'],
					'update'    => 'cascade',
					'delete'    => 'cascade',
				],
			],
			'columns'       => [
				'user_id'  => 'ref:oz_users.id',
				'level'    => [
					'type'    => 'int',
					'min'     => 1,
					'max'     => 60,
					'default' => 1, // TODO 1: super admin, 2: admin, 3: editor, etc
				],
				'data'     => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time' => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'    => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_clients'        => [
			'private'       => true,
			'plural_name'   => 'oz_clients',
			'singular_name' => 'oz_client',
			'column_prefix' => 'client',
			'relations'     => [
				'oz_client_owner' => [
					'type'   => 'one-to-one',
					'target' => 'oz_users',
				],
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['api_key']],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_users',
					'columns'   => ['user_id' => 'id'],
					'update'    => 'cascade',
					'delete'    => 'cascade',
				],
			],
			'columns'       => [
				'api_key'           => [
					'type' => 'string',
					'max'  => 256,
				],
				// when specified, the user right will be used every time
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
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time'          => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'             => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_sessions'       => [
			'private'       => true,
			'plural_name'   => 'oz_sessions',
			'singular_name' => 'oz_session',
			'column_prefix' => 'session',
			'relations'     => [
				'oz_client' => ['type' => 'many-to-one', 'target' => 'oz_clients'],
				'oz_user'   => ['type' => 'many-to-one', 'target' => 'oz_users'],
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_clients',
					'columns'   => ['client_api_key' => 'api_key'],
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
				'id'             => [
					'type' => 'string',
					'max'  => 128,
				],
				'client_api_key' => 'ref:oz_clients.api_key',
				'user_id'        => [
					'type' => 'ref:oz_users.id',
					'null' => true,
				],
				'token'          => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 250,
				],
				'expire'         => [
					'type' => 'timestamp',
				],
				'last_seen'      => [
					'type' => 'timestamp',
				],
				'data'           => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time'       => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'          => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_authenticator'  => [
			'private'       => true,
			'plural_name'   => 'oz_authenticator',
			'singular_name' => 'oz_auth',
			'column_prefix' => 'auth',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['label', 'for']]],
			'columns'       => [
				'label'     => [
					'type' => 'string',
					'max'  => 60,
				],
				'for'       => [
					'type' => 'string',
					'max'  => 255,
				],
				'code'      => [
					'type' => 'string',
					'max'  => 32,
				],
				'token'     => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 32,
				],
				'try_max'   => [
					'type'     => 'int',
					'unsigned' => true,
					'default'  => '1',
				],
				'try_count' => [
					'type'     => 'int',
					'unsigned' => true,
					'default'  => '0',
				],
				'expire'    => [
					'type' => 'timestamp',
				],
				'data'      => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time'  => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'     => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_countries'      => [
			'plural_name'   => 'oz_countries',
			'singular_name' => 'oz_country',
			'column_prefix' => 'country',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['cc2']]],
			'columns'       => [
				'cc2'       => [
					'type' => 'cc2',
				],
				'code'      => [
					'type' => 'string',
					'max'  => 6,
				],
				'name'      => [
					'type' => 'string',
					'max'  => 60,
				],
				'name_real' => [
					'type' => 'string',
					'max'  => 60,
				],
				'data'      => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time'  => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'     => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
		'oz_files'          => [
			'plural_name'   => 'oz_files',
			'singular_name' => 'oz_file',
			'column_prefix' => 'file',
			'relations'     => [
				'oz_file_owner'  => ['type' => 'many-to-one', 'target' => 'oz_users'],
				'oz_file_clones' => ['type' => 'one-to-many', 'target' => 'oz_files', 'columns' => ['id' => 'clone']],
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
			],
			'columns'       => [
				'id'       => [
					'type'           => 'bigint',
					'unsigned'       => true,
					'auto_increment' => true,
				],
				'user_id'  => [
					'type' => 'ref:oz_users.id',
					'null' => true,
				],
				'key'      => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 32,
				],
				'clone'    => [
					'type'     => 'bigint',
					'unsigned' => true,
					'default'  => 0,
				],
				'origin'   => [
					'type'     => 'bigint',
					'unsigned' => true,
					'default'  => 0,
				],
				'size'     => [
					'type' => 'bigint',
				],
				'type'     => [
					'type' => 'string',
					'max'  => 60,
					'null' => true,
				],
				'name'     => [
					'type'     => 'string',
					'max'      => 100,
					'truncate' => true,
					'null'     => true,
				],
				'label'    => [
					'type' => 'string',
				],
				'path'     => [
					'type' => 'string',
					'max'  => 255,
				],
				'thumb'    => [
					'type' => 'string',
					'max'  => 255,
					'null' => true,
				],
				'data'     => [
					'type'    => 'string',
					'default' => '[]',
				],
				'add_time' => [
					'type' => 'timestamp',
					'auto' => true,
				],
				'valid'    => [
					'type'    => 'bool',
					'default' => true,
				],
			],
		],
	];
