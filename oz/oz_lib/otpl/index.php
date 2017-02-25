<?php

	require_once 'OTpl.php';

	function test_run( $opt ) {
		$DS = DIRECTORY_SEPARATOR;
		$root = __DIR__ . $DS;

		$out_dir = $root . 'output';
		$fname = $opt[ 'file' ] . '.html';
		$url = $root . '..' . $DS . 'files' . $DS . $opt[ 'file' ];
		$out_file = $out_dir . $DS . $fname;

		@mkdir( $out_dir, 0777 );

		$start = microtime( true );

		$obj = new OTpl();
		$obj->parse( $url )->runSave( $opt[ 'data' ], $out_file );

		$end = microtime( true );

		echo "<br/>start: $start<br/>end: $end<br/>duration: " . ( $end - $start ) . "s<br/>";
		echo "<a href='./output/$fname' >$fname</a><br/>";
	}

	test_run( array(
		'file' => 'test-var-1.otpl',
		'data' => array( 'Franck', 23 )
	) );

	test_run( array(
		'file' => 'test-var-2.otpl',
		'data' => array(
			'name' => 'Franck',
			'age'  => 23
		)
	) );

	test_run( array(
		'file' => 'test-var-3.otpl',
		'data' => array(
			array(
				'name' => 'Stella',
				'age'  => 41
			), array(
				'name' => 'Steve'
			), array(
				'name' => 'Franck',
				'age'  => 23
			)
		)
	) );

	test_run( array(
		'file' => 'test-attr.otpl',
		'data' => array(
			'input' => array(
				'id'          => 'name-field',
				'type'        => 'text',
				'placeholder' => 'Name',
				'value'       => 'toto'
			),
			'label' => 'Your name:'
		)
	) );

	test_run( array(
		'file' => 'test-loop.otpl',
		'data' => array( 'Apple', 'HTC', 'Samsung' )
	) );

	//SILO TODO write test for OPathResolver::resolve('d:/','../tyut/');