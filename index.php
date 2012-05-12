<?php

if( !function_exists( 'spl_autoload_register' ) )
{
	trigger_error( 'The Standard PHP Library is required by this program. Please make sure it is installed.' );
	return;
}

define( 'JPB_YOURLS_DIR', WP_PLUGIN_DIR . '/' . basename( dirname( __FILE__ ) ) . '/' );
define( 'JPB_YOURLS_URL', trailingslashit( plugins_url( '', __FILE__ ) ) );

function jpb_shortlink_autoloader( $name )
{
	static $files = null;
	if( is_null( $files ) )
	{
		$files = array( );
		$tempFiles = require( JPB_YOURLS_DIR . 'var/files.php' );
		foreach( $tempFiles as $section => $fileList )
		{
			foreach( $fileList as $file )
			{
				$files[$file] = "$section/$file.php";
			}
		}
		unset( $tempFiles, $section, $fileList, $file );
	}
	if( !empty( $files[$name] ) )
	{
		require( JPB_YOURLS_DIR . $files[$name] );
	}
}

spl_autoload_register( 'jpb_shortlink_autoloader' );

define( 'JPB_SHORTLINK_LIBRARY_AUTOLOADER', true );

JPB_Yourls::bootstrap();
