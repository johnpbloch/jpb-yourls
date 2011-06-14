<?php

/**
 * BuddyPress functions for YOURLS
 */
 
require_once( dirname( __FILE__ ) . '/bp-members.php' );

if ( is_admin() )
	require_once( dirname( __FILE__ ) . '/bp-admin.php' );

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