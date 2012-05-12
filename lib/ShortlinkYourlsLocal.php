<?php

class ShortlinkYourlsLocal implements Shortlink
{

	public static function shorten( $longURL, $desired = false )
	{
		return self::apiHelper( 'shorten', $longURL, $desired );
	}

	public static function expand( $shortURL )
	{
		return self::apiHelper( 'expand', $shortURL );
	}

	protected static function apiHelper( $action, $url, $desired = false )
	{
		if( self::maybeLoadYOURLS() )
		{
			if( $action == 'shorten' )
			{
				$results = yourls_add_new_link( $url, $desired );
				if( isset( $results['shorturl'] ) )
				{
					return $results['shorturl'];
				}
				return new WP_Error( $results['code'], $results['message'] );
			}
			else
			{
				$results = yourls_api_expand( $url );
				if( isset( $results['longurl'] ) )
				{
					return $results['longurl'];
				}
				return new WP_Error( $results['simple'], $results['message'] );
			}
		}
		return new WP_Error( 'yourls-not-loaded', 'YOURLS did not load.' );
	}

	protected static function maybeLoadYOURLS()
	{
		if( function_exists( 'yourls_add_new_link' ) )
		{
			return true;
		}
		try
		{
			$path = self::findYourlsInstallation( JBY_Options::instance()->shortlinkURI );
		}
		catch( Exception $e )
		{
			return false;
		}
		require( $path );
		return function_exists( 'yourls_add_new_link' );
	}

	protected static function findYourlsInstallation( $path = false )
	{
		if( !$path )
		{
			$path = JBY_Options::instance()->shortlinkURI;
		}
		$path = realpath( $path );
		if( !$path )
		{
			throw new Exception( 'Could not find YOURLS installation!' );
			return;
		}
		if( basename( $path ) != 'load-yourls.php' )
		{
			$path = trailingslashit( $path );
			if( file_exists( $path . 'load-yourls.php' ) )
			{
				$path .= 'load-yourls.php';
			}
			elseif( file_exists( $path . 'includes/load-yourls.php' ) )
			{
				$path .= 'includes/load-yourls.php';
			}
			else
			{
				$path = false;
			}
		}
		if( !$path )
		{
			throw new Exception( 'Could not find YOURLS installation!' );
			return;
		}
	}

}
