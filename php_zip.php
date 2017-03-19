<?php
	$zip = new ZipArchive();
	$filename = "./test112.zip";
	if ( $zip->open( $filename, ZipArchive::CREATE ) !== true ) {
		exit( "cannot open <$filename>\n" );
	}
	$zip->addFromString( "testfilephp.txt" . time(), "#1 This is a test string added as testfilephp.txt.\n" );
	$zip->addFromString( "testfilephp2.txt" . time(), "#2 This is a test string added as testfilephp2.txt.\n" );
	$zip->addFile( $thisdir . "/too.php", "/testfromfile.php" );
	echo "numfiles: " . $zip->numFiles . "\n";
	echo "status:" . $zip->status . "\n";
	$zip->close();

	//Example #2 Dump the archive details and listing

	$za = new ZipArchive();
	$za->open( 'test_with_comment.zip' );
	print_r( $za );
	var_dump( $za );
	echo "numFiles: " . $za->numFiles . "\n";
	echo "status: " . $za->status . "\n";
	echo "statusSys: " . $za->statusSys . "\n";
	echo "filename: " . $za->filename . "\n";
	echo "comment: " . $za->comment . "\n";
	for ( $i = 0 ; $i < $za->numFiles ; $i++ ) {
		echo "index: $i\n";
		print_r( $za->statIndex( $i ) );
	}
	echo "numFile:" . $za->numFiles . "\n";

	//Example #3 Zip stream wrapper, read an OpenOffice meta info

	$reader = new XMLReader();
	$reader->open( 'zip://' . dirname( __FILE__ ) . '/test.odt#meta.xml' );
	$odt_meta = array();
	while ( $reader->read() ) {
		if ( $reader->nodeType == XMLREADER::ELEMENT ) {
			$elm = $reader->name;
		} else {
			if ( $reader->nodeType == XMLREADER::END_ELEMENT && $reader->name == 'office:meta' ) {
				break;
			}
			if ( !trim( $reader->value ) ) {
				continue;
			}
			$odt_meta[ $elm ] = $reader->value;
		}
	}
	print_r( $odt_meta );