<?php

	final class OTplFeature_Loop {
		const REG = "#loop[\s]*\([\s]*(.+?)[\s]*\:[\s]*([$][a-zA-Z0-9_]+)[\s]*(?:\:[\s]*([$][a-zA-Z0-9_]+)[\s]*)?\)[\s]*\{#";

		public static function exec( $in, OTpl $context ) {
			$data = $in[ 1 ];
			$key = $in[ 2 ];

			if ( isset( $in[ 3 ] ) ) {
				$value = $in[ 3 ];

				return "foreach($data as $key=>$value){";
			} else {
				$value = $key;

				return "foreach($data as $value){";
			}
		}
	}

	OTplUtils::addReplacer( OTplFeature_Loop::REG, array( 'OTplFeature_Loop', 'exec' ) );