<?php

/**
 * BuddyPress functions for YOURLS
 */

// Require the Members integration 
require_once( dirname( __FILE__ ) . '/bp-members.php' );

// Require the admin functions
if ( is_admin() )
	require_once( dirname( __FILE__ ) . '/bp-admin.php' );

/**
 * Catch page requests, and fetch any shorturls that are called for by the current settings
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_maybe_create_url() {
	global $bp;
	
	// Members
	if ( $user_id = bp_displayed_user_id() ) {
		// Check to see whether it's already created
		if ( $shorturl = get_user_meta( $user_id, 'yourls_shorturl', true ) ) {
			$bp->displayed_user->shorturl = $shorturl;
		
		} else {	
			$ozh_yourls = get_option('ozh_yourls'); 
			$type = isset( $ozh_yourls['bp_members_pretty'] ) ? 'pretty' : false;
			
			$shorturl = wp_ozh_yourls_create_bp_member_url( $user_id, $type );
			$bp->displayed_user->shorturl = $shorturl;
		}
	}
}
add_action( 'wp', 'wp_ozh_yourls_maybe_create_url', 1 );


?>