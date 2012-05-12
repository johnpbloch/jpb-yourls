<?php

class JPB_Yourls
{

	public static function bootstrap()
	{
		if( !self::getShortlinkAPIClass() )
		{
			return;
		}
	}

	public static function getShortlinkAPIClass( $API = false )
	{
		if( !$API )
		{
			$API = JBY_Options::instance()->shortlinkAPI;
			if( $API === 'yourls' )
			{
				$URI = JBY_Options::instance()->shortlinkURI;
				$API .= (preg_match( '@^https?://@', $URI )) ? 'remote' : 'local';
			}
		}
		$apis = array(
			'isgd' => 'ShortlinkIsGd',
			'yourlslocal' => 'ShortlinkYourlsLocal',
			'yourlsremote' => 'ShortlinkYourlsRemote',
		);
		$apis = apply_filters( 'JPB_Yourls_API_classes', $apis );
		$class = empty( $apis[$API] ) ? false : $apis[$API];
		return $class;
	}

}
