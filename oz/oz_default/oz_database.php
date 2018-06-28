<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	use OZONE\OZ\Core\SettingsManager;

	return [
		'oz_users'          => [
			'plural_name'   => 'OZ_users',
			'singular_name' => 'OZ_user',
			'column_prefix' => 'user',
			'relations'     => [
				'OZ_files'   => ['type' => 'one-to-many', 'target' => 'oz_files'],
				'OZ_country' => ['type' => 'one-to-one', 'target' => 'oz_countries', 'columns' => ['cc2' => 'cc2']]
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
				['type' => 'unique', 'columns' => ['phone']],
				['type' => 'unique', 'columns' => ['email']],
				['type' => 'foreign_key', 'reference' => 'oz_countries', 'columns' => ['cc2' => 'cc2']]
			],
			'columns'       => [
				'id'           => [
					'type'           => 'bigint',
					'auto_increment' => true,
					'unsigned'       => true
				],
				'phone'        => [
					'type'       => 'phone',
					'registered' => false,
					'null'       => !SettingsManager::get('oz.users', 'OZ_USERS_PHONE_REQUIRED')
				],
				'email'        => [
					'type'       => 'email',
					'registered' => false,
					'null'       => !SettingsManager::get('oz.users', 'OZ_USERS_EMAIL_REQUIRED')
				],
				'pass'         => [
					'type' => 'password'
				],
				'name'         => [
					'type' => 'user_name'
				],
				'gender'       => [
					'type' => 'gender'
				],
				'birth_date'   => [
					'type'       => 'date',
					'birth_date' => true,
					'min_age'    => SettingsManager::get('oz.ofv.const', 'OZ_USER_MIN_AGE'),
					'max_age'    => SettingsManager::get('oz.ofv.const', 'OZ_USER_MAX_AGE')

				],
				'sign_up_time' => [
					'type' => 'timestamp',
					'auto' => true
				],
				'picid'        => [
					'type'    => 'string',
					'default' => '0_0',
					'max'     => 50
				],
				'cc2'          => [
					'type'       => 'cc2',
					'authorized' => true
				],
				'valid'        => [
					'type'    => 'bool',
					'default' => true
				]
			]
		],
		'oz_administrators' => [
			'plural_name'   => 'OZ_administrators',
			'singular_name' => 'OZ_admin',
			'column_prefix' => 'admin',
			'relations'     => [
				'OZ_user' => ['type' => 'one-to-one', 'target' => 'oz_users']
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['user_id']],
				['type' => 'foreign_key', 'reference' => 'oz_users', 'columns' => ['user_id' => 'id']]
			],
			'columns'       => [
				'user_id' => 'ref:oz_users.id',
				'time'    => [
					'type'     => 'timestamp',
					'auto' => true
				],
				'valid'   => [
					'type' => 'bool',
					'default' => true
				]
			]
		],
		'oz_clients'        => [
			'plural_name'   => 'OZ_clients',
			'singular_name' => 'OZ_client',
			'column_prefix' => 'client',
			'relations'     => [
				'OZ_client_owner' => [
					'type'   => 'one-to-one',
					'target' => 'oz_users'
				]
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['api_key']],
				['type' => 'foreign_key', 'reference' => 'oz_users', 'columns' => ['user_id' => 'id']]
			],
			'columns'       => [
				'api_key'           => [
					'type' => 'string',
					'max'  => 35
				],
				'user_id'           => [
					'type' => 'ref:oz_users.id',
					'null' => true
				],
				'url'               => [
					'type' => 'string',
					'max'  => 255
				],
				'session_life_time' => [
					'type'     => 'bigint',
					'unsigned' => true,
					'default'  => 86400
				],
				'about'             => [
					'type' => 'string'
				],
				'create_time'       => [
					'type' => 'timestamp',
					'auto' => true
				],
				'valid'             => [
					'type' => 'bool',
					'default'=> true
				]
			]
		],
		'oz_sessions'       => [
			'plural_name'   => 'OZ_sessions',
			'singular_name' => 'OZ_session',
			'column_prefix' => 'session',
			'relations'     => [
				'OZ_client' => ['type' => 'many-to-one', 'target' => 'oz_clients'],
				'OZ_user'   => ['type' => 'many-to-one', 'target' => 'oz_users']
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_clients',
					'columns'   => ['client_api_key' => 'api_key'],
					'update'    => 'cascade',
					'delete'    => 'cascade'
				],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_users',
					'columns'   => ['user_id' => 'id'],
					'update'    => 'cascade',
					'delete'    => 'cascade'
				]
			],
			'columns'       => [
				'id'             => [
					'type' => 'string',
					'max'  => 128
				],
				'client_api_key' => 'ref:oz_clients.api_key',
				'user_id'        => [
					'type' => 'ref:oz_users.id',
					'null' => true
				],
				'token'          => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 250
				],
				'data'           => [
					'type' => 'string'
				],
				'expire'         => [
					'type' => 'timestamp'
				],
				'last_seen'      => [
					'type' => 'timestamp'
				]
			]
		],
		'oz_authenticator'  => [
			'plural_name'   => 'OZ_authenticator',
			'singular_name' => 'OZ_auth',
			'column_prefix' => 'auth',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['label', 'for']]],
			'columns'       => [
				'label'     => [
					'type' => 'string',
					'max'  => 60
				],
				'for'       => [
					'type' => 'string',
					'max'  => 255
				],
				'code'      => [
					'type' => 'string',
					'max'  => 32
				],
				'token'     => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 32
				],
				'try_max'   => [
					'type'     => 'int',
					'unsigned' => true,
					'default'  => '1'
				],
				'try_count' => [
					'type'     => 'int',
					'unsigned' => true,
					'default'  => '0'
				],
				'expire'    => [
					'type' => 'timestamp'
				]
			]
		],
		'oz_countries'      => [
			'plural_name'   => 'OZ_countries',
			'singular_name' => 'OZ_country',
			'column_prefix' => 'country',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['cc2']]],
			'columns'       => [
				'cc2'       => [
					'type' => 'cc2'
				],
				'code'      => [
					'type' => 'string',
					'max'  => 6
				],
				'name'      => [
					'type' => 'string',
					'max'  => 60
				],
				'name_real' => [
					'type' => 'string',
					'max'  => 60
				],
				'valid'     => [
					'type' => 'bool',
					'default'=> true
				]
			]
		],
		'oz_files'          => [
			'plural_name'   => 'OZ_files',
			'singular_name' => 'OZ_file',
			'column_prefix' => 'file',
			'relations'     => [
				'OZ_file_owner'  => ['type' => 'many-to-one', 'target' => 'oz_users'],
				'OZ_file_clones' => ['type' => 'one-to-many', 'target' => 'oz_files', 'columns' => ['id' => 'clone']]
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['id']],
				['type' => 'foreign_key', 'reference' => 'oz_users', 'columns' => ['user_id' => 'id']]
			],
			'columns'       => [
				'id'          => [
					'type'           => 'bigint',
					'unsigned'       => true,
					'auto_increment' => true
				],
				'user_id'     => 'ref:oz_users.id',
				'key'         => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 32
				],
				'clone'       => [
					'type'     => 'bigint',
					'unsigned' => true,
					'default'  => 0
				],
				'origin'      => [
					'type'     => 'bigint',
					'unsigned' => true,
					'default'  => 0
				],
				'size'        => [
					'type' => 'bigint'
				],
				'type'        => [
					'type' => 'string',
					'max'  => 60,
					'null' => true
				],
				'name'        => [
					'type' => 'string',
					'max'  => 100,
					'null' => true
				],
				'label'       => [
					'type' => 'string'
				],
				'path'        => [
					'type' => 'string',
					'max'  => 255
				],
				'thumb'       => [
					'type' => 'string',
					'max'  => 255,
					'null' => true
				],
				'upload_time' => [
					'type' => 'timestamp',
					'auto' => true
				]
			]
		]
	];