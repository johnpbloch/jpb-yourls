<?php

function wp_ozh_yourls_create_bp_member_url( $user_id, $type = 'normal', $keyword = false ) {
	
	// Check plugin is configured
	$service = wp_ozh_yourls_service();
	if( !$service )
		return 'Plugin not configured: cannot find which URL shortening service to use';
	
	// Mark this post as "I'm currently fetching the page to get its title"
	if( $user_id ) {
		update_user_meta( $user_id, 'yourls_fetching', 1 );
		update_user_meta( $user_id, 'yourls_shorturl', '' ); // temporary empty title to avoid loop on creating short URL
	}
	
	$url 	  = bp_core_get_user_domain( $user_id );
	$userdata = get_userdata( $user_id );
	$title    = bp_core_get_user_displayname( $user_id );
	
	// Only send a keyword if this is a pretty URL
	if ( 'pretty' == $type ) {
		if ( !$keyword )
			$keyword = $userdata->user_login;
	} else {
		$keyword = false;
	}
	var_dump( $keyword );
	// Get short URL
	$shorturl = wp_ozh_yourls_api_call( $service, $url, $keyword, $title );
	
	// Remove fetching flag
	if( $user_id )
		delete_user_meta( $user_id, 'yourls_fetching' );

	// Store short URL in a custom field
	if ( $user_id && $shorturl )
		update_user_meta( $user_id, 'yourls_shorturl', $shorturl );

	return $shorturl;
}

function wp_ozh_yourls_display_user_url() {
	$shorturl = wp_ozh_yourls_get_displayed_user_url();
	
	if ( $shorturl ) {
	?>
		<div class="shorturl">
			<?php printf( __( 'Short URL: %s', 'wp-ozh-yourls' ), $shorturl ) ?> <?php if ( wp_ozh_user_can_edit_url() ) : ?><?php wp_ozh_yourls_user_edit_link() ?><?php endif ?>
		</div>
	<?php
	}
}
add_action( 'bp_before_member_header_meta', 'wp_ozh_yourls_display_user_url' );

function wp_ozh_yourls_displayed_user_url() {
	echo wp_ozh_yourls_get_displayed_user_url();
}
	function wp_ozh_yourls_get_displayed_user_url() {
		global $bp;
		
		$url = isset( $bp->displayed_user->shorturl ) ? $bp->displayed_user->shorturl : false;
		
		return $url;
	}

/**
 * Echo the content of wp_ozh_yourls_get_user_edit_link()
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param int $user_id The id of the user. Defaults to the displayed user, then to the loggedin user
 * @param str $return 'html' to return a full link, otherwise just retrieve the URL
 */
function wp_ozh_yourls_user_edit_link( $user_id = false, $return = 'html' ) {
	echo wp_ozh_yourls_get_user_edit_link( $user_id, $return );
}
	/**
	 * Return the URL to a user's General Settings screen, where he can edit his shorturl
	 *
	 * @package YOURLS WordPress to Twitter
	 * @since 1.5
	 *
	 * @param int $user_id The id of the user. Defaults to the displayed user, then to the
	 *     loggedin user
	 * @param str $return 'html' to return a full link, otherwise just retrieve the URL
	 * @return str $link The link
	 */
	 function wp_ozh_yourls_get_user_edit_link( $user_id = false, $return = 'html' ) {
	 	global $bp;
	 	
	 	// If no user_id is passed, first try to default to the displayed user
	 	if ( !$user_id ) {
	 		$user_id = !empty( $bp->displayed_user->id ) ? $bp->displayed_user->id : false;
	 		$domain = !empty( $bp->displayed_user->domain ) ? $bp->displayed_user->domain : false;
	 	}
	 	
	 	// If there's still no user_id, get the logged in user
	 	if ( !$user_id ) {
	 		$user_id = !empty( $bp->loggedin_user->id ) ? $bp->loggedin_user->id : false;
	 		$domain = !empty( $bp->loggedin_user->domain ) ? $bp->loggedin_user->domain : false;
	 	}
	 	
	 	// If there's *still* no displayed user, bail
	 	if ( !$user_id ) {
	 		return false;
	 	}
	 	
	 	// If a $user_id was passed manually to the function, we'll need to set $domain
	 	if ( !isset( $domain ) ) {
	 		$domain = bp_core_get_user_domain( $user_id );
	 	}
	 	
	 	// Create the URL to the settings page
	 	$link = $domain . BP_SETTINGS_SLUG;
	 	
	 	// Add the markup if necessary
	 	if ( 'html' == $return ) {
	 		$link = sprintf( '<a href="%1$s">%2$s</a>', $link, __( 'Edit', 'wp-ozh-yourls' ) );	
	 	}
	 	
	 	return $link;
	 }
	
/**
 * USER SHORTURL EDITING
 */

/**
 * Renders the Edit field on the General Settings page
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_render_user_edit_field() {
	if ( !wp_ozh_user_can_edit_url() )
		return;
	
	$ozh_yourls = get_option('ozh_yourls'); 
	
	if ( empty( $ozh_yourls['bp_shortener_base'] ) ) {
		$ozh_yourls['bp_shortener_base'] = wp_ozh_yourls_guess_base_url();
	}
	
	$shorturl_name = get_user_meta( bp_displayed_user_id(), 'yourls_shorturl_name', true );

	?>
	
	<label for="shorturl"><?php _e( 'Short URL: ', 'wp-ozh-yourls' ) ?></label>
	<code>http://<?php echo $ozh_yourls['bp_shortener_base'] ?>/</code><input type="text" name="shorturl" id="shorturl" value="<?php echo $shorturl_name ?>" class="settings-input" />
	
	<?php
}
add_action( 'bp_core_general_settings_before_submit', 'wp_ozh_yourls_render_user_edit_field' );

/**
 * Processes shorturl edits by the member and displays proper success/error messages
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_save_user_edit() {
	global $bp;
	
	if ( isset( $_POST['shorturl'] ) ) {
		$shorturl_name = untrailingslashit( trim( $_POST['shorturl'] ) );
		
		// Remove the limitation on duplicate shorturls
		// This is a temporary workaround
		define( 'YOURLS_UNIQUE_URLS', false );
		add_filter( 'yourls_remote_params', 'wp_ozh_yourls_remote_allow_dupes' );
		
		// First, try to create a URL with this name
		$shorturl = wp_ozh_yourls_create_bp_member_url( bp_displayed_user_id(), 'pretty', $shorturl_name );
		
		remove_filter( 'yourls_remote_params', 'wp_ozh_yourls_remote_allow_dupes' );
		
		if ( !$shorturl ) {
			// Something has gone wrong. Check to see whether this is a reversion to a
			// previous shorturl
			$expand = wp_ozh_yourls_api_call_expand( 'yourls-remote', $shorturl_name );
			
			if ( empty( $expand->longurl ) || $expand->longurl != $bp->displayed_user->domain ) {
				// No match.
				bp_core_add_message( __( 'That URL is unavailable. Please choose another.', 'wp-ozh-yourls' ), 'error' );
			} else {
				$shorturl = $expand->shorturl;
			}
		}
		
		if ( $shorturl ) {
			update_user_meta( bp_displayed_user_id(), 'yourls_shorturl', $shorturl );
			update_user_meta( bp_displayed_user_id(), 'yourls_shorturl_name', $shorturl_name );
			
			// Just in case this needs to be refreshed
			$bp->displayed_user->shorturl = $shorturl;
		}
	}
}
add_action( 'bp_core_general_settings_after_save', 'wp_ozh_yourls_save_user_edit' );

function wp_ozh_yourls_remote_allow_dupes( $params ) {	
	$params['source'] = '';
	
	return $params;
}

/**
 * Figure out whether the logged-in user can edit the shorturl in question
 */
function wp_ozh_user_can_edit_url() {
	// Some services do not allow for custom URLs
	if ( !wp_ozh_yourls_service_allows_custom_urls() )
		return false;
	
	$ozh_yourls = get_option('ozh_yourls');

	// Check to see whether the admin has allowed editing
	if ( !isset( $ozh_yourls['bp_members_can_edit'] ) )
		return false;

	// Access control
	if ( !is_super_admin() && !bp_is_my_profile() )
		return false;

	return true;
}
?>