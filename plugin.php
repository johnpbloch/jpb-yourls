<?php
/*
Plugin Name: YOURLS: WordPress to Twitter
Plugin URI: http://planetozh.com/blog/my-projects/yourls-wordpress-to-twitter-a-short-url-plugin/
Description: Create short URLs for posts with <a href="http://yourls.org/" title="Your Own URL Shortener">YOURLS</a> (or other services such as tr.im) and tweet them.
Author: Ozh
Author URI: http://planetozh.com/
Version: 1.3.2
*/

/* Release History :
 * 1.0:       Initial release
 * 1.1:       Fixed: template tag makes post previews die (more generally, plugin wasn't properly initiated when triggered from the public part of the blog). Thanks moggy!
 * 1.2:       Added: ping.fm support, unused at the moment because those fucktards from ping.fm just don't approve the api key.
              Added: template tag wp_ozh_yourls_raw_url()
			  Added: uninstall procedure
			  Added: "get url" button as on wp.com
			  Improved: using internal WP_Http class instead of cURL for posting to Twitter
			  Fixed: short URLs generated on pages or posts even if option unchecked in settings (thanks Viper007Bond for noticing)
			  Fixed: PEAR class was included without checking existence first, conflicting with Twitter Tools for instance (thanks Doug Stewart for noticing)
 * 1.2.1:     Fixed: oops, forgot to remove a test hook
 * 1.3:       Fixed: Don't generate short URLs on preview pages
              Fixed: Tweet when posting scheduled post or using the XMLRPC API
 * 1.3.1:     Added: option to add <link> in <real>
 * 1.3.2:     Fixed: compat with YOURLS 1.4
 */


/********************* DO NOT EDIT *********************/

global $wp_ozh_yourls;

require_once(dirname(__FILE__).'/inc/core.php');

// Template tag: echo short URL for current post
function wp_ozh_yourls_url() {
	global $id;
	$short = wp_ozh_yourls_geturl( $id );
	if ($short)
		echo "<a href=\"$short\" rel=\"nofollow alternate short shorter shorturl shortlink\" title=\"short URL\">$short</a>";
}

// Template tag: echo short URL alternate link in <head> for current post. See http://revcanonical.appspot.com/ && http://shorturl.appjet.net/
function wp_ozh_yourls_head_linkrel() {
	global $id;
	$short = wp_ozh_yourls_geturl( $id );
	if ($short)
		echo "<link rel=\"alternate short shorter shorturl shortlink\" href=\"$short\" />\n";
}

// Template tag: return/echo short URL with no formatting
function wp_ozh_yourls_raw_url( $echo = false ) {
	global $id;
	$short = wp_ozh_yourls_geturl( $id );
	if ($short) {
		if ($echo)
			echo $short;
		return $short;
	}
}

// Get or create the short URL for a post. Input integer (post id), output string(url)
function wp_ozh_yourls_geturl( $id ) {
	$short = get_post_meta( $id, 'yourls_shorturl', true );
	if ( !$short && !is_preview() ) {
		// short URL never was not created before, let's get it now
		require_once(dirname(__FILE__).'/inc/core.php');
		$short = wp_ozh_yourls_get_new_short_url( get_permalink($id), $id );
	}
	
	return $short;
}

if (is_admin()) {
	require_once(dirname(__FILE__).'/inc/options.php');
	// Add menu page, init options, add box on the Post/Edit interface
	add_action('admin_menu', 'wp_ozh_yourls_add_page');
	add_action('admin_init', 'wp_ozh_yourls_addbox', 10);
	// Handle AJAX requests
	add_action('wp_ajax_yourls-promote', 'wp_ozh_yourls_promote' );
	add_action('wp_ajax_yourls-reset', 'wp_ozh_yourls_reset_url' );
	// Custom icon & plugin action link
	add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'wp_ozh_yourls_plugin_actions', -10);
	add_filter( 'ozh_adminmenu_icon_ozh_yourls', 'wp_ozh_yourls_customicon' );
	// Init plugin
	add_action('admin_init', 'wp_ozh_yourls_init', 1 );
} else {
	// Add <link> in <head> if applicable
	add_action('wp_head', 'wp_ozh_yourls_add_head_link');
	// Init plugin
	add_action('init', 'wp_ozh_yourls_init', 1 );
}

// Handle new stuff published
add_action('new_to_publish', 'wp_ozh_yourls_newpost', 10, 1);
add_action('draft_to_publish', 'wp_ozh_yourls_newpost', 10, 1);
add_action('pending_to_publish', 'wp_ozh_yourls_newpost', 10, 1);
add_action('future_to_publish', 'wp_ozh_yourls_newpost', 10, 1);


