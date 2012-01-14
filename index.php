<?php

if( !function_exists( 'spl_autoload_register' ) ) {
	trigger_error( 'The Standard PHP Library is required by this program. Please make sure it is installed.' );
	return;
}

function jpb_shortlink_library_autoloader( $name ) {
	if( 0 === stripos( $name, 'lib' ) && file_exists( dirname( __FILE__ ) . "/lib/$name.php" ) )
		require( dirname( __FILE__ ) . "/lib/$name.php" );
}

spl_autoload_register( 'jpb_shortlink_library_autoloader' );

define( 'JPB_SHORTLINK_LIBRARY_AUTOLOADER', true );
