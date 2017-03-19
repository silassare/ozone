<?php
	/**
	 * Copyright (c) Silas E. Sare <emile.silas@gmail.com>
	 *
	 * This file is part of the OZone package.
	 *
	 * For the full copyright and license information, please view the LICENSE
	 * file that was distributed with this source code.
	 */

	namespace OZONE\OZ\Utils;

	defined( 'OZ_SELF_SECURITY_CHECK' ) or die;

	final class OZoneOmlTextHelper {

		/**
		 * format a given data according to a given oml type
		 *
		 * @param string $type the oml text type
		 * @param mixed  $data the oml text data
		 *
		 * @return string
		 */
		public static function formatText( $type, $data = null ) {
			$text = '';
			switch ( $type ) {
				case 'file':
					$fid = $data[ 'fid' ];
					$data[ 'fthumb' ] = ( !!$data[ 'fthumb' ] AND file_exists( $data[ 'fthumb' ] ) ) ? 1 : 0;
					$data_str = json_encode( array(
						'fid'    => $data[ 'fid' ],
						'fkey'   => $data[ 'fkey' ],
						'fclone' => $data[ 'fclone' ], //<--SILO::TODO user a t'il vraiment besoins de savoir?
						'ftype'  => $data[ 'ftype' ],
						'fname'  => $data[ 'fname' ],
						'fthumb' => $data[ 'fthumb' ]
					) );

					$data_str = base64_encode( $data_str );
					$text = "{" . $fid . ":" . $data_str . "}";
					break;
				case 'user':
					$text = "{uid:" . $data[ 'uid' ] . "}";
					break;
				case 'group':
					$text = "{gid:" . $data[ 'gid' ] . "}";
					break;
				case 'new_line':
					$text = "{o:nl}";
					break;
				/*
				SILO::TODO
					- si un message est partarger 1million de fois il y aura 1million de {msg_trace...} ce qui est inacceptable
					- pense a une autre solution pour traquer un message
					- car un user ( ou amis/ex-collaborateur/ex-co-developpeur en colere qui est au courrant de la syntaxe de msg_trace )
						- peut ecrire un message et inserer un msg_trace et rendre faux les statistiques
					- de plus pour faire sortir le chemin prit par un message pour parvenir a un autre user
						- cela va augmenter le nombre de requette a la table des messages de la base de donnees
					- il faudra donc creer une autre table ou carrement une autre base de donnees pour l'enregistrement et le traitement des donnees statistiques
					- si une solution est trouvee alors la methode OZoneOmlTextHelper::removeMsgTrace est a supprimer
				case 'msg_trace':
					return "{msg_trace:".$infos['msgid'].",source:".$infos['source']."}";
					break;
				*/
			}

			return $text;
		}

		//public static function removeMsgTrace( $msg )
		//{
		//	//lire commentaire au niveau de la methode OZoneOmlTextHelper::formatText a propos de msg_trace
		//	$msg_trace_reg = '#^\{msg_trace\:([0-9]+),source\:([0-9]+)\}#';
		//	$data = array();
		//	$found = preg_match( $msg_trace_reg, $msg, $data);
		//	if ( $found )
		//	{
		//		return array(
		//			'msgid' => $data[1],
		//			'source' => $data[2]
		//		);
		//	}
		//
		//	return null;
		//}
	}