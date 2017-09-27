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
			'singular'      => 'user',
			'column_prefix' => 'user',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			'columns'       => [
				'id'      => [
					'type'           => 'bigint',
					'auto_increment' => true,
					'unsigned'       => true
				],
				'phone'   => [
					'type' => 'string',
					'max'  => 30,
					'null' => true
				],
				'email'   => [
					'type' => 'string',
					'max'  => 255,
					'null' => true
				],
				'pass'    => [
					'type' => 'string',
					'max'  => 255
				],
				'name'    => [
					'type' => 'string',
					'max'  => 255
				],
				'gender'  => [
					'type' => 'string',
					'max'  => 30
				],
				'bdate'   => [
					'type' => 'string',
					'max'  => 10 // 10: for jj-mm-yyyy
				],
				'regdate' => [
					'type'     => 'bigint',
					'unsigned' => true
				],
				'picid'   => [
					'type'    => 'string',
					'default' => '0_0',
					'max'     => 50
				],
				'cc2'     => 'oz_countries.cc2',
				'valid'   => [
					'type' => 'bool'
				]
			]
		],
		'oz_administrators' => [
			'singular'      => 'administrator',
			'column_prefix' => 'admin',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['user_id']]],
			'columns'       => [
				'user_id' => ':oz_users.id',
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
			'singular'      => 'client',
			'column_prefix' => 'client',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['clid']]],
			'columns'       => [
				'clid'   => [
					'type' => 'string',
					'max'  => 35
				],
				'user_id' => [
					'type'     => ':oz_users.id'
				],
				'url'    => [
					'type' => 'string',
					'max'  => 255
				],
				'pkey'   => [
					'type' => 'string'
				],
				'about'  => [
					'type' => 'string'
				],
				'geoloc' => [
					'type' => 'string'
				],
				'valid'  => [
					'type' => 'bool'
				]
			]
		],
		'oz_clients_users'  => [
			'singular'      => 'client',
			'column_prefix' => 'client',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['clid', 'user_id']]],
			'columns'       => [
				'clid'       => ':oz_clients.clid',
				'user_id'     => [
					'type' => ':oz_clients.user_id',
					'null' => false
				],
				'sid'        => ':oz_sessions.sid',
				'token'      => [
					'type' => 'string',
					'min'  => 32,
					'max'  => 32
				],
				'last_check' => [
					'type'     => 'bigint',
					'unsigned' => true
				]
			]
		],
		'oz_authenticator'  => [
			'singular'      => 'authenticator',
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
			'singular'      => 'session',
			'column_prefix' => 'sess',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['sid']]],
			'columns'       => [
				'sid'    => [
					'type' => 'string',
					'max'  => 32
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
			'singular'      => 'country',
			'column_prefix' => 'country',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			'columns'       => [
				'id'      => [
					'type'           => 'int',
					'unsigned'       => true,
					'auto_increment' => true
				],
				'cc2'     => [
					'type' => 'string',
					'min'  => 2,
					'max'  => 2
				],
				'code'    => [
					'type' => 'string',
					'max'  => 6
				],
				'name'    => [
					'type' => 'string',
					'max'  => 60
				],
				'name_fr' => [
					'type' => 'string',
					'max'  => 60
				],
				'ok'      => [
					'type' => 'bool'
				]
			]
		],
		'oz_files'          => [
			'singular'      => 'file',
			'column_prefix' => 'file',
			'constraints'   => [['type' => 'primary_key', 'columns' => ['id']]],
			'columns'       => [
				'id'          => [
					'type'           => 'bigint',
					'unsigned'       => true,
					'auto_increment' => true
				],
				'user_id'     => ':oz_users.id',
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