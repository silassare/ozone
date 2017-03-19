<?php
	require_once 'OTpl.php';

	ob_start();

	function test_run( $opt ) {
		$DS = DIRECTORY_SEPARATOR;
		$root = __DIR__ . $DS;
		$out_dir = $root . 'output';
		$url = $root . '..' . $DS . 'files' . $DS . $opt[ 'file' ];
		$pinfos = pathinfo( $url );
		$fname = $pinfos[ 'filename' ] . '.' . $pinfos[ 'extension' ] . '.html';
		$out_file = $out_dir . $DS . $fname;

		@mkdir( $out_dir, 0777 );

		$start = microtime( true );

		$o = new OTpl();
		$o->parse( $url, true )
			->runSave( $opt[ 'data' ], $out_file );

		$end = microtime( true );
		$duration = $end - $start;

		echo "<table>
			<tbody>
				<tr><td>SOURCE</td><td>{$o->getSrcPath()}</td></tr>
				<tr><td>OUTPUT FILE</td><td><a href='./output/$fname' >Open</a></td></tr>
				<tr><td>START</td><td>$start</td></tr>
				<tr><td>END</td><td>$end</td></tr>
				<tr><td>DURATION (s) </td><td>$duration</td></tr>
			</tbody>
		</table>";
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

	test_run( array(
		'file' => 'sub/test-import.otpl',
		'data' => array(
			'data_a' => array( 'Franck', 23 ),
			'data_b' => array(
				'input' => array(
					'id'          => 'name-field',
					'type'        => 'text',
					'placeholder' => 'Name',
					'value'       => 'toto'
				),
				'label' => 'Your name:'
			)
		)
	) );

	$content = ob_get_clean();

	echo <<<TPL
<!DOCTYPE html>
<html>
	<head>
		<meta name="format-detection" content="telephone=no">
		<meta name="msapplication-tap-highlight" content="no">
		<meta name="viewport"
			  content="user-scalable=no, initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width">
		<link rel="stylesheet" type="text/css" href="../web/css/style.css">
		<title>OTpl: PHP Test</title>
	</head>
	<body>
	$content
	</body>
</html>
TPL;

