<?php

/**
 * BuddyPress functions for YOURLS
 */

// Require the Members integration 
require_once( dirname( __FILE__ ) . '/bp-members.php' );

// Require the Groups integration, if necessary
if ( bp_is_active( 'groups' ) )
	require_once( dirname( __FILE__ ) . '/bp-groups.php' );

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
	
	$ozh_yourls = get_option( 'ozh_yourls' ); 
	
	// Members
	if ( isset( $ozh_yourls['bp_members'] ) ) {
		if ( $user_id = bp_displayed_user_id() ) {
			// Check to see whether it's already created
			if ( $shorturl = get_user_meta( $user_id, 'yourls_shorturl', true ) ) {
				$bp->displayed_user->shorturl = $shorturl;	
			} else {	
				$type = isset( $ozh_yourls['bp_members_pretty'] ) ? 'pretty' : false;
				
				$shorturl = wp_ozh_yourls_create_bp_member_url( $user_id, $type );
				$bp->displayed_user->shorturl = $shorturl;
			}
		}
	}
	
	// Groups
	if ( isset( $ozh_yourls['bp_groups'] ) ) {
		if ( bp_is_group() ) {
			// Check to see whether it's already created
			if ( $shorturl = groups_get_groupmeta( $bp->groups->current_group->id, 'yourls_shorturl' ) ) {
				$bp->groups->current_group->shorturl = $shorturl;
			} else {
				$ozh_yourls = get_option( 'ozh_yourls' ); 
				$type = isset( $ozh_yourls['bp_groups_pretty'] ) ? 'pretty' : false;
				
				$shorturl = wp_ozh_yourls_create_bp_group_url( $bp->groups->current_group->id, $type );
				$bp->groups->current_group->shorturl = $shorturl;
			}
		}
	}
}
add_action( 'wp', 'wp_ozh_yourls_maybe_create_url', 1 );

/**
 * Can the current user edit the short URL of the current object?
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @return bool
 */
function wp_ozh_user_can_edit_url() {
	// Some services do not allow for custom URLs
	if ( !wp_ozh_yourls_service_allows_custom_urls() )
		return false;
	
	$ozh_yourls = get_option('ozh_yourls');

	// Next checks depend on the component
	if ( bp_is_group() ) {		
		if ( !isset( $ozh_yourls['bp_groups_can_edit'] ) )
			return false;

		if ( !bp_group_is_admin() )
			return false;
	}
	
	// Members component
	if ( bp_is_user() ) {		
		// Check to see whether the admin has allowed editing
		if ( !isset( $ozh_yourls['bp_members_can_edit'] ) )
			return false;
	
		// Access control
		if ( !is_super_admin() && !bp_is_my_profile() )
			return false;
	}

	return true;
}

/**
 * Print styles to the head of the document
 *
 * Hooked to wp_head to save an HTTP request. So sue me.
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yoruls_print_bp_styles() {
	
	if ( !bp_is_group() && !bp_is_user() )
		return;
		
	?>
	
<style type="text/css">
span.shorturl { white-space: nowrap; display: inline-block; }
</style>
	
	<?php
}
add_action( 'wp_head', 'wp_ozh_yoruls_print_bp_styles' );


?>