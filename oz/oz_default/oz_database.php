<?php
	/**
	 * Copyright (c) Emile Silas Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

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
					'type' => 'phone',
					'null' => !\OZONE\OZ\Core\SettingsManager::get('oz.users', 'OZ_USERS_PHONE_REQUIRED')
				],
				'email'        => [
					'type' => 'email',
					'null' => !\OZONE\OZ\Core\SettingsManager::get('oz.users', 'OZ_USERS_EMAIL_REQUIRED')
				],
				'pass'         => [
					'type' => 'password'
				],
				'name'         => [
					'type' => 'uname'
				],
				'gender'       => [
					'type' => 'gender'
				],
				'birth_date'   => [
					'type'       => 'date',
					'birth_date' => true
				],
				'sign_up_time' => [
					'type'     => 'bigint',
					'unsigned' => true
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
					'type' => 'bool'
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
					'type'     => 'bigint',
					'unsigned' => true
				],
				'valid'   => [
					'type' => 'bool'
				]
			]
		],
		'oz_clients'        => [
			'plural_name'   => 'OZ_clients',
			'singular_name' => 'OZ_client',
			'column_prefix' => 'client',
			'relations'     => [
				'OZ_client_owner'  => [
					'type'   => 'one-to-one',
					'target' => 'oz_users'
				],
				'OZ_current_users' => ['type' => 'one-to-many', 'target' => 'oz_clients_users']
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
					'default'  => 86400,
					'unsigned' => true
				],
				'about'             => [
					'type' => 'string'
				],
				'create_time'       => [
					'type'     => 'bigint',
					'unsigned' => true
				],
				'valid'             => [
					'type' => 'bool'
				]
			]
		],
		'oz_clients_users'  => [
			'plural_name'   => 'OZ_clients_users',
			'singular_name' => 'OZ_client_user',
			'relations'     => [
				'OZ_session' => ['type' => 'one-to-one', 'target' => 'oz_sessions'],
				'OZ_user'    => ['type' => 'many-to-one', 'target' => 'oz_users'],
				'OZ_client'  => ['type' => 'many-to-one', 'target' => 'oz_clients']
			],
			'constraints'   => [
				['type' => 'primary_key', 'columns' => ['client_api_key', 'user_id', 'session_id']],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_users',
					'columns'   => ['user_id' => 'id'],
					'update'    => 'cascade',
					'delete'    => 'cascade'
				],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_clients',
					'columns'   => ['client_api_key' => 'api_key'],
					'update'    => 'cascade',
					'delete'    => 'cascade'
				],
				[
					'type'      => 'foreign_key',
					'reference' => 'oz_sessions',
					'columns'   => ['session_id' => 'id'],
					'update'    => 'cascade',
					'delete'    => 'cascade'
				]
			],
			'columns'       => [
				'client_api_key' => 'ref:oz_clients.api_key',
				'user_id'        => [
					'type' => 'ref:oz_users.id',
					'null' => false
				],
				'session_id'     => 'ref:oz_sessions.id',
				'token'          => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 250
				],
				'last_check'     => [
					'type'     => 'bigint',
					'unsigned' => true
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
					'type'     => 'bigint',
					'unsigned' => true
				]
			]
		],
		'oz_sessions'       => [
			'plural_name'   => 'OZ_sessions',
			'singular_name' => 'OZ_session',
			'column_prefix' => 'session',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			'columns'       => [
				'id'     => [
					'type' => 'string',
					'max'  => 128
				],
				'data'   => [
					'type' => 'string'
				],
				'expire' => [
					'type'     => 'bigint',
					'unsigned' => true
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
					'type' => 'bool'
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
					'type'     => 'bigint',
					'unsigned' => true
				]
			]
		]
	];