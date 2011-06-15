<?php

/**
 * Admin markup
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_bp_admin_markup() {
	$ozh_yourls = get_option('ozh_yourls'); 
	
	$can_customize = wp_ozh_yourls_service_allows_custom_urls();
	
	// Set some of the checkmark indexes to avoid WP_DEBUG errors
	$indexes = array( 'bp_members', 'bp_members_pretty', 'bp_members_can_edit', 'bp_groups', 'bp_topics' );
	foreach( $indexes as $index ) {
		if ( !isset( $ozh_yourls[$index] ) )
			$ozh_yourls[$index] = '';
	}
	
	if ( empty( $ozh_yourls['bp_shortener_base'] ) ) {
		$is_a_guess = true;
		$ozh_yourls['bp_shortener_base'] = wp_ozh_yourls_guess_base_url();
	} else {
		$is_a_guess = false;
	}
		
	?>
	
	<h3>BuddyPress settings <span class="h3_toggle expand" id="h3_buddypress">+</span> <span id="h3_check_buddypress" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_buddypress">
	
	<table class="form-table">

	<tr valign="top">
	<th scope="row"><?php _e( 'URL shortener base', 'wp-ozh-yourls' ) ?></th>
	<td>
		http://<input name="ozh_yourls[bp_shortener_base]" type="text" value="<?php echo $ozh_yourls['bp_shortener_base'] ?>" />
		<p class="description"><?php _e( 'This value is displayed wherever users can edit their short URLs.', 'wp-ozh-yourls' ) ?></p> 
		<?php if ( $is_a_guess ) : ?>
			<p class="description"><?php _e( 'We\'ve guessed at the URL, based on your URL Shortener Settings above.', 'wp-ozh-yourls' ) ?></p> 
		<?php endif ?>
	</td>
	</tr>

	</table>
	
	<h4><?php _e( 'Members', 'wp-ozh-yourls' ) ?></h4> 

	<table class="form-table">

	<tr valign="top">
	<th scope="row"><?php _e( 'Each member gets a short URL', 'wp-ozh-yourls' ) ?></th>
	<td>
		<input name="ozh_yourls[bp_members]" type="checkbox" <?php checked( $ozh_yourls['bp_members'], 'on' ) ?> />
	</td>
	</tr>
	
	<?php if ( $can_customize ) : ?>
		<tr valign="top">
		<th scope="row"><?php _e( 'Create short URLs from usernames', 'wp-ozh-yourls' ) ?></th>
		<td>
			<input name="ozh_yourls[bp_members_pretty]" type="checkbox" <?php checked( $ozh_yourls['bp_members_pretty'], 'on' ) ?> />
			<span class="description"><?php _e( 'When checked, member short URLs will look like <code>http://bit.ly/<strong>username</strong></code>, rather than a random string', 'wp-ozh-yourls' ) ?></span>
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row"><?php _e( 'Users can edit their short URLs', 'wp-ozh-yourls' ) ?></th>
		<td>
			<input name="ozh_yourls[bp_members_can_edit]" type="checkbox" <?php checked( $ozh_yourls['bp_members_can_edit'], 'on' ) ?> />
			<span class="description"><?php _e( 'If you\'re using YOURLS, you must set <code>define( \'YOURLS_UNIQUE_URLS\', false );</code> in <a href="http://yourls.org/#Config">your configuration file.', 'wp-ozh-yourls' ) ?></span>
		</td>
		</tr>
	<?php endif ?>

	</table>
	
	</div> <!-- div_h3_buddypress -->
	
	<?php
}
add_action( 'ozh_yourls_admin_sections', 'wp_ozh_yourls_bp_admin_markup' );


?>