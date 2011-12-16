<?php

// Add <link> in <head> if applicable
function wp_ozh_yourls_add_head_link() {
	global $wp_ozh_yourls;
	if(
		( is_single() && $wp_ozh_yourls['link_on_post'] ) ||
		( is_page() && $wp_ozh_yourls['link_on_page'] )
	) {
		wp_ozh_yourls_head_linkrel();
	}
}

// Manual tweet from the Edit interface
function wp_ozh_yourls_promote() {
	check_ajax_referer( 'yourls' );
	$post_id = (int) $_POST['yourls_post_id'];
	
	$sent = wp_ozh_yourls_send_tweet( stripslashes($_POST['yourls_tweet']) );
	
	if ( !isset($sent->error) ) {
		$account = wp_ozh_yourls_get_twitter_screen_name();
		$result = sprintf( __( "Success! Post was promoted on <a href='http://twitter.com/%1$s'>%2$s</a>!", 'wp-ozh-yourls' ), $account, $account );
		update_post_meta($post_id, 'yourls_tweeted', 1);
	} else {
		$result = $sent->error;
	}
	$x = new WP_AJAX_Response( array(
		'data' => $result
	) );
	$x->send();
	die('1');	
}

// Manual reset of the short URL from the Edit interface
function wp_ozh_yourls_reset_url() {
	check_ajax_referer( 'yourls' );
	$post_id = (int) $_POST['yourls_post_id'];

	$old_shorturl = $_POST['yourls_shorturl'];
	delete_post_meta($post_id, 'yourls_shorturl');
	$shorturl = wp_ozh_yourls_geturl( $post_id );

	if ( $shorturl ) {
		$result = sprintf( __( "New short URL generated: <a href='%1$s'>%2$s</a>", 'wp-ozh-yourls' ), $shorturl, $shorturl );
		update_post_meta($post_id, 'yourls_shorturl', $shorturl);
	} else {
		$result = __( "Bleh. Could not generate short URL. Maybe the URL shortening service is down? Please try again later!", 'wp-ozh-yourls' );
	}
	$x = new WP_AJAX_Response( array(
		'data' => $result,
		'supplemental' => array(
			'old_shorturl' => $old_shorturl,
			'shorturl' => $shorturl
		)
	) );
	$x->send();
	die('1');	
}

// Check YOURLS config - the part that receives Ajax: check if config/API are found
function wp_ozh_yourls_check_yourls() {
	check_ajax_referer( 'yourls' );
	
	switch( $_REQUEST['yourls_type'] ) {
		case 'path':
			$url = $_REQUEST['location'];
			$result = wp_ozh_yourls_find_yourls_loader( $url ) ? __( 'OK !', 'wp-ozh-yourls' ) : __( 'Not found', 'wp-ozh-yourls' );
			break;
		
		case 'url':
			// Make a JSON request
			$params = array(
				'format'   => 'json',
				'username' => $_REQUEST['username'],
				'password' => $_REQUEST['password'],
				'action'   => 'stats'
			);
			$url = add_query_arg( $params, $_REQUEST['location'] );

			$request = wp_ozh_yourls_remote_json( $url );
			
			// Check if we have JSON and if it's successful
			if( $request ) {
				if( $request->statusCode == 200 ) {
					$result = 'OK !';
				} else {
					$result = $request->message;
				}
			} else {
				$result = 'Not found';
			}
			break;
	}

	$x = new WP_AJAX_Response( array(
		'data' => $result,
		'supplemental' => array(
			'location'  => $url,
			'type'  => $_REQUEST['yourls_type'],
			'req' => !empty( $request ) ? serialize( $request ) : '',
			'param' => !empty( $params ) ? $params : array( 'action' => 'stats' )
		),
		'action' => ''
	) );
	$x->send();
	die('1');	
}

// Function called when new post. Expecting post object.
function wp_ozh_yourls_newpost( $post ) {
	global $wp_ozh_yourls;
	
	do_action( 'yourls_newpost' );
	
	$post_id = $post->ID;
	$url = get_permalink( $post_id );
	
	// Generate short URL ?
	if ( !wp_ozh_yourls_generate_on( $post->post_type ) ) {
		return;
	}
	
	$url = get_permalink ( $post_id );
	$keyword = '';
	
	// Get any suggested keyword
	if( get_post_meta( $post_id, 'yourls_keyword', true ) ) {
		$keyword = get_post_meta( $post_id, 'yourls_keyword', true );
		delete_post_meta( $post_id, 'yourls_keyword' );
	} elseif( get_post_meta( $post_id, 'yourls-keyword', true ) ) {
		$keyword = get_post_meta( $post_id, 'yourls-keyword', true );
		delete_post_meta( $post_id, 'yourls-keyword' );
	}
	
	$url = apply_filters( 'yourls_custom_url', $url, $post_id );
	$keyword = apply_filters( 'yourls_custom_keyword', $keyword, $post_id );
	
	$short = wp_ozh_yourls_get_new_short_url( $url, $post_id, $keyword );
	
	// Tweet short URL ?
	if ( !wp_ozh_yourls_tweet_on( $post->post_type ) ) {
		return;
	}

	if ( !get_post_custom_values( 'yourls_tweeted', $post_id ) ) {
		// Not tweeted yet
		$tweet = wp_ozh_yourls_maketweet( $short, $post->post_title, $post_id );
		if ( wp_ozh_yourls_send_tweet( $tweet ) )
			update_post_meta($post_id, 'yourls_tweeted', 1);
	}
	
}

// Tweet something. Returns stuff.
function wp_ozh_yourls_send_tweet( $tweet ) {
	global $wp_ozh_yourls;
	
	do_action( 'yourls_send_tweet', $tweet );
	
	require_once( dirname(__FILE__) . '/oauth.php' );
	return wp_ozh_yourls_tweet_it( $tweet );
}

// The WP <-> YOURLS bridge function: get short URL of a WP post. Returns string(url)
function wp_ozh_yourls_get_new_short_url( $url, $post_id = 0, $keyword = '', $title = '' ) {
	global $wp_ozh_yourls;
	
	do_action( 'yourls_get_new_short_url', $url, $post_id, $keyword, $title );
	
	// Check plugin is configured
	$service = wp_ozh_yourls_service();
	if( !$service )
		return __( 'Plugin not configured: cannot find which URL shortening service to use', 'wp-ozh-yourls' );

	// Mark this post as "I'm currently fetching the page to get its title"
	if( $post_id ) {
		update_post_meta( $post_id, 'yourls_fetching', 1 );
		update_post_meta( $post_id, 'yourls_shorturl', '' ); // temporary empty title to avoid loop on creating short URL
	}
	
	// Get short URL
	$shorturl = wp_ozh_yourls_api_call( $service, $url, $keyword, $title );
	
	// Remove fetching flag
	if( $post_id )
		delete_post_meta( $post_id, 'yourls_fetching' );

	// Store short URL in a custom field
	if ( $post_id && $shorturl )
		update_post_meta( $post_id, 'yourls_shorturl', $shorturl );

	return $shorturl;
}

// Find yourls loader
function wp_ozh_yourls_find_yourls_loader( $path = '' ) {
	global $wp_ozh_yourls;
	
	$path = $path ? $path : $wp_ozh_yourls['yourls_path'];
	
	if( file_exists( dirname($path).'/load-yourls.php' ) ) {
		// YOURLS 1.4+ & config.php in /includes
		$path = dirname($path).'/load-yourls.php';

	} elseif ( file_exists( dirname(dirname($path)).'/includes/load-yourls.php' ) )  {
		// YOURLS 1.4+ & config.php in /user
		$path = dirname(dirname($path)).'/includes/load-yourls.php';

	} else {
		// Bleh, wtf, loader not found?
		$path = false;
	}
	
	return $path;
}

function wp_ozh_yourls_not_found_error() {
	?>
	
	<div id="message" class="error">
		<p><?php _e( 'Cannot find YOURLS. Please check your config.', 'wp-ozh-yourls' ) ?></p>
	</div>
	
	<?php
}

// Tap into one of the available APIs. Return a short URL or false if error
function wp_ozh_yourls_api_call( $api, $url, $keyword = '', $title = '' ) {
	global $wp_ozh_yourls;

        if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );

	$shorturl = '';
	
	switch( $api ) {

		case 'yourls-local':
			global $yourls_reserved_URL;
			if( !defined('YOURLS_FLOOD_DELAY_SECONDS') )
				define('YOURLS_FLOOD_DELAY_SECONDS', 0); // Disable flood check
			if( !defined('YOURLS_UNIQUE_URLS') && ( !defined('YOURLS_ALWAYS_FRESH') || YOURLS_ALWAYS_FRESH != true ) )
				define('YOURLS_UNIQUE_URLS', true); // Don't duplicate long URLs

			$include = wp_ozh_yourls_find_yourls_loader();
			if( !$include ) {
				add_action( 'admin_notices', 'wp_ozh_yourls_not_found_error' );
				break;
			}
			
			global $ydb;
			require_once( $include ); 
			$yourls_result = yourls_add_new_link( $url, $keyword, $title );

			if ($yourls_result)
				$shorturl = $yourls_result['shorturl'];
			break;
			
		case 'yourls-remote':
			$params = array(
				'username' => $wp_ozh_yourls['yourls_login'],
				'password' => $wp_ozh_yourls['yourls_password'],
				'url'      => urlencode( $url ),
				'keyword'  => urlencode( $keyword ),
				'title'    => urlencode( $title ),
				'format'   => 'json',
				'source'   => 'plugin',
				'action'   => 'shorturl'
			);
			$params = apply_filters( 'yourls_remote_params', $params );
			$api_url = add_query_arg( $params, $wp_ozh_yourls['yourls_url'] );
			
			$json = wp_ozh_yourls_remote_json( $api_url );			
			if ( $json )
				$shorturl = isset( $json->shorturl ) ? $json->shorturl : '';
			break;
		
		case 'bitly':
			$api_url = sprintf( 'http://api.bit.ly/shorten?version=2.0.1&longUrl=%s&login=%s&apiKey=%s',
				urlencode($url), $wp_ozh_yourls['bitly_login'], $wp_ozh_yourls['bitly_password'] );
			$json = wp_ozh_yourls_remote_json( $api_url );
			if ($json)
				$shorturl = $json->results->$url->shortUrl; // bit.ly's API makes ugly JSON, seriously, tbh
			break;
			
		case 'pingfm':
			$api_url = 'http://api.ping.fm/v1/url.create';
			$body = array(
				'api_key' => 'd0e1aad9057142126728c3dcc03d7edb',
				'user_app_key' => $wp_ozh_yourls['pingfm_user_app_key'],
				'long_url' => $url
			);
			$xml = wp_ozh_yourls_fetch_url( $api_url, 'POST', $body );
			if ($xml) {
				preg_match_all('!<short_url>[^<]+</short_url>!', $xml, $matches);
				$shorturl = $matches[0][0];				
			}
			break;
		
		case 'tinyurl':
			$api_url = sprintf( 'http://tinyurl.com/api-create.php?url=%s', urlencode($url) );
			$shorturl = wp_ozh_yourls_remote_simple( $api_url );
			break;
		
		case 'isgd':
			$api_url = sprintf( 'http://is.gd/api.php?longurl=%s', urlencode($url) );
			$shorturl = wp_ozh_yourls_remote_simple( $api_url );
			break;
			
		default:
			die('Error, unknown service');
	
	}
	
	// at this point, if ($shorturl), it should contain expected short URL. Potential TODO: deal with edge cases?
	
	return $shorturl;
}

/**
 * Get the expanded version of a short url
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param str $api Shortener type
 * @param str $url The URL to be expanded
 * @return mixed $expanded_url
 */
function wp_ozh_yourls_api_call_expand( $api, $url ) {
	global $wp_ozh_yourls;

        if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );

	switch( $api ) {

		case 'yourls-local':
			$include = wp_ozh_yourls_find_yourls_loader();
			if( !$include ) {
				add_action( 'admin_notices', 'wp_ozh_yourls_not_found_error' );
				break;
			}
			
			require_once( $include ); 
			$yourls_result = yourls_api_expand( $url );

			if ( $yourls_result )
				return $yourls_result;
			else
				return false;
			break;
			
		case 'yourls-remote':
			$params = array(
				'username' => $wp_ozh_yourls['yourls_login'],
				'password' => $wp_ozh_yourls['yourls_password'],
				'shorturl' => urlencode( $url ),
				'format'   => 'json',
				'source'   => 'plugin',
				'action'   => 'expand'
			);
			
			$api_url = add_query_arg( $params, $wp_ozh_yourls['yourls_url'] );

			$api = wp_ozh_yourls_remote_json( $api_url );	

			break;
	
		default:
			die('Error, unknown service');
	
	}
	
	return $api;
}

// Poke a remote API that returns a simple string
function wp_ozh_yourls_remote_simple( $url ) {
	return wp_ozh_yourls_fetch_url( $url );
}

// Poke a remote API with JSON and return a object (decoded JSON) or NULL if error
function wp_ozh_yourls_remote_json( $url ) {
	$input = wp_ozh_yourls_fetch_url( $url );
	$obj = json_decode($input);
	return $obj;
	// TODO: some error handling ?
}

// Fetch a remote page. Input url, return content
function wp_ozh_yourls_fetch_url( $url, $method='GET', $body=array(), $headers=array() ) {
	if( !class_exists( 'WP_Http' ) )
		include_once( ABSPATH . WPINC. '/class-http.php' );
	$request = new WP_Http;
	$result = $request->request( $url , array( 'method'=>$method, 'body'=>$body, 'headers'=>$headers, 'user-agent'=>'YOURLS http://yourls.org/' ) );

	// Success?
	if ( !is_wp_error($result) && isset($result['body']) ) {
		return $result['body'];

	// Failure (server problem...)
	} else {
		// TODO: something more useful ?
		return false;
	}
}

// Parse the tweet template and make a 140 char string
function wp_ozh_yourls_maketweet( $url, $title, $id ) {
	global $wp_ozh_yourls;

        if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );

	$tweet = $wp_ozh_yourls['twitter_message'];

	// Plugin author: interrupt here before everything is parsed
	$tweet = apply_filters( 'pre_ozh_yourls_tweet', $tweet, $url, $title, $id );

	// Replace %U with short url
	$tweet = str_replace('%U', $url, $tweet);
	
	// Replace %F{bleh} with custom post field 'bleh'
	if( preg_match_all( '/%F\{([^\}]+)\}/', $tweet, $matches ) ) {
		foreach( $matches[1] as $match ) {
			$field = get_post_meta( $id, $match, true );
			$tweet = str_replace('%F{'.$match.'}', $field, $tweet);
		}
		unset( $matches );
	}

        if (strpos($tweet, '%A') !== false) {
            // Get author info
            $post = get_post( $id );
            $author_id = $post->post_author;
            $author_info = get_userdata( $author_id );
            unset( $post );

            // Replace %A{bleh} with author data 'bleh'
            if( preg_match_all('/%A\{([^\}]+)\}/', $tweet, $matches)) {
                foreach( $matches[1] as $match ) {
                    $tweet = str_replace('%A{'.$match.'}', $author_info->$match, $tweet);
                }
                unset( $matches );
            }

            // Replace %A with author display name
            $tweet = str_replace('%A', $author_info->display_name, $tweet);
        }

        if (strpos($tweet, '%X') !== false) {
            if (preg_match_all('/%X\{([^\}]+)\}/', $tweet, $matches)) {
                foreach ($matches[1] as $tax) {
                    $_terms = array_slice((array)get_the_terms($id, $tax), 0, 3);
                    $terms = array();
                    foreach ($_terms as $term) {
                        $terms[] = strtolower(str_replace(" ", "", $term->name));
                    }
                    unset($_terms);

                    $tweet = str_replace('%X{'.$tax.'}', join(' ', $terms), $tweet);
                }
                unset($matches);
            }
        }

        if (strpos($tweet, '%Y') !== false) {
            if (preg_match_all('/%Y\{([^\}]+)\}/', $tweet, $matches)) {
                foreach ($matches[1] as $tax) {
                    $_terms = array_slice((array)get_the_terms($id, $tax), 0, 3);
                    $terms = array();
                    foreach ($_terms as $term) {
                        $terms[] = strtolower(str_replace(" ", "", $term->name));
                    }
                    unset($_terms);

                    $tweet = str_replace('%Y{'.$tax.'}', "#".join(' #', $terms), $tweet);
                }
                unset($matches);
            }
        }

        if (strpos($tweet, '%L') !== false || strpos($tweet, '%H') !== false) {
            // Get tags (up to 3)
            $_tags = array_slice( (array)get_the_tags( $id ), 0, 3 );
            $tags = array();
            foreach( $_tags as $tag ) { $tags[] = strtolower( str_replace( " ", "", $tag->name ) ); }
            unset( $_tags );

            // Replace %L with tags as plaintext (space separated if more than one) (up to 3 tags)
            $tweet = str_replace('%L', join(' ', $tags), $tweet);

            // Replace %H with tags as hashtags (space separated if more than one) (up to 3 tags) 
            $tweet = str_replace('%H', '#'.join(' #', $tags), $tweet);
        }

        if (strpos($tweet, '%C') !== false || strpos($tweet, '%D') !== false) {
            // Get categories (up to 3)
            $_cats = array_slice( (array)get_the_category( $id ), 0, 3 );
            $cats = array();
            foreach( $_cats as $cat ) { $cats[] = strtolower( str_replace( " ", "", $cat->name ) ); }
            unset( $_cats );

            // Replace %C with categories (space separated if more than one) (up to 3 categories) 
            $tweet = str_replace('%C', join(' ', $cats), $tweet);

            // Replace %D with categories as hashtags (space separated if more than one) (up to 3 categories) 
            $tweet = str_replace('%D', '#'.join(' #', $cats), $tweet);
        }

	// Finally replace %T with as many chars as possible to keep under 140
	$tweet = trim( $tweet );
	$maxlen = 140 - ( strlen( $tweet ) - 2); // 2 = "%T"
	if (strlen($title) > $maxlen) {
		$title = substr($title, 0, ($maxlen - 3)) . '...';
	}
	$tweet = str_replace('%T', $title, $tweet);

	$tweet = apply_filters( 'ozh_yourls_tweet', $tweet, $url, $title, $id );

	return $tweet;
}

// Init plugin on public part
function wp_ozh_yourls_init() {
	global $wp_ozh_yourls;
	$wp_ozh_yourls = get_option('ozh_yourls');
	
	// check for OAuth requests on plugin load.
	if( isset($_GET['oauth_start']) ) {
		require_once( dirname(__FILE__).'/oauth.php' );
		wp_ozh_yourls_oauth_start();
	}
	if( isset($_GET['oauth_token']) ) {
		require_once( dirname(__FILE__).'/oauth.php' );
		wp_ozh_yourls_oauth_confirm();
	}
	
}

// Init admin stuff
function wp_ozh_yourls_admin_init() {
	global $wp_ozh_yourls;
	$wp_ozh_yourls = get_option('ozh_yourls');

	register_setting( 'wp_ozh_yourls_options', 'ozh_yourls', 'wp_ozh_yourls_sanitize' );

	if ( !wp_ozh_yourls_settings_are_ok() ) {
		add_action( 'admin_notices', 'wp_ozh_yourls_admin_notice' );
	}

	// Deprecated settings since we now use OAuth
	if( isset( $wp_ozh_yourls['twitter_login'] ) or isset( $wp_ozh_yourls['twitter_password'] ) ) {
		unset( $wp_ozh_yourls['twitter_login'] );
		unset( $wp_ozh_yourls['twitter_password'] );
		update_option( 'ozh_yourls', $wp_ozh_yourls );
	}
}

// Generate on... $type = 'post' or 'page' or any custom post type, returns boolean
function wp_ozh_yourls_generate_on( $type ) {
	global $wp_ozh_yourls;

        if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );

	return ( isset( $wp_ozh_yourls['generate_on_'.$type] ) && $wp_ozh_yourls['generate_on_'.$type] == 1 );
}

// Send tweet on... $type = 'post' or 'page' or any custom post type, returns boolean
function wp_ozh_yourls_tweet_on( $type ) {
	global $wp_ozh_yourls;

        if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );

	return ( isset( $wp_ozh_yourls['tweet_on_'.$type] ) && $wp_ozh_yourls['tweet_on_'.$type] == 1 );
}

// Determine which service to use. Return string
function wp_ozh_yourls_service() {
	global $wp_ozh_yourls;
	
	if ( empty( $wp_ozh_yourls ) )
		$wp_ozh_yourls = get_option( 'ozh_yourls' );
	
	if ( $wp_ozh_yourls['service'] == 'yourls' && $wp_ozh_yourls['location'] == 'local' )
		return 'yourls-local';
	
	if ( $wp_ozh_yourls['service'] == 'yourls' && $wp_ozh_yourls['location'] == 'remote' )
		return 'yourls-remote';
		
	if ( $wp_ozh_yourls['service'] == 'other')
		return $wp_ozh_yourls['other'];
}

// Hooked into 'ozh_adminmenu_icon', this function give this plugin its own icon
function wp_ozh_yourls_customicon( $in ) {
	return wp_ozh_yourls_pluginurl().'res/icon.gif';
}

// Add the 'Settings' link to the plugin page
function wp_ozh_yourls_plugin_actions($links) {
	$links[] = "<a href='options-general.php?page=ozh_yourls'><b>Settings</b></a>";
	return $links;
}

// Shortcut to WP function wp_get_shortlink. First parameter passed by filter, $id is post id
function wp_ozh_yourls_wp_get_shortlink( $false, $id, $context = '' ) {
	
	global $wp_query;
	$post_id = 0;
	if ( 'query' == $context && is_single() ) {
		$post_id = $wp_query->get_queried_object_id();
	} elseif ( 'post' == $context ) {
		$post = get_post($id);
		$post_id = $post->ID;
	}
	
	// No ID and still post? Fail.
	if( !$post_id && $context == 'post' )
		return null;
	
	// TODO: Generate shortlinks for things other than posts
	if( !$post_id && $context == 'query' ) 
		return null;
	
	// Check if user wants a short link generated for this type of post
	$type = get_post_type( $post_id );
	if( !wp_ozh_yourls_generate_on( $type ) )
		return null;
		
	// Check if this post is published
	if( 'publish' != get_post_status( $post_id ) )
		return null;

	// Still here? Must mean we really need a short URL then!
	return wp_ozh_yourls_geturl( $post_id );
}

// Return plugin URL (https://site.com/wp-content/plugins/bleh/)
function wp_ozh_yourls_pluginurl() {
	return plugin_dir_url( dirname(__FILE__) );
}

/**
 * Echoes the output of wp_ozh_yourls_get_shortener_base_url()
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 */
function wp_ozh_yourls_shortener_base_url() {
	echo wp_ozh_yourls_get_shortener_base_url();
}

/**
 * Gets the current shortener's base URL. Looks first in the saved settings, and if not
 * found there, calculates it based on the current service and
 * wp_ozh_yourls_determine_base_url()
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @return str $url The base URL for the shortener (eg http://bit.ly)
 */
function wp_ozh_yourls_get_shortener_base_url() {
        // Usually this will be stored in the settings
        $wp_ozh_yourls = get_option( 'ozh_yourls' );

        if ( isset( $wp_ozh_yourls['shortener_base_url'] ) ) {
                $url = $wp_ozh_yourls['shortener_base_url'];
        } else {
                $url = wp_ozh_yourls_determine_base_url();
        }

        return $url;
}

/**
 * Determine the base URL of the current URL shortening service
 *
 * This function is called whenever the Dashboard options are saved, to account for changed URL
 * Shortener Settings.
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param array $wp_ozh_yourls (optional) When called during the process of saving options, this
 *   function takes the settings array being saved as an argument, and uses them to find the current
 *   service. This is necessary in order to catch a newly selected service in time. When this param
 *   is omitted, $service is set with wp_ozh_yourls_service()
 * @return $url The base URL of the shortener
 */
function wp_ozh_yourls_determine_base_url( $wp_ozh_yourls = false ) {	
	if ( $wp_ozh_yourls ) {
		if ( $wp_ozh_yourls['service'] == 'yourls' && $wp_ozh_yourls['location'] == 'local' )
			$service = 'yourls-local';
	
		if ( $wp_ozh_yourls['service'] == 'yourls' && $wp_ozh_yourls['location'] == 'remote' )
			$service = 'yourls-remote';
			
		if ( $wp_ozh_yourls['service'] == 'other')
			$service = $wp_ozh_yourls['other'];
	} else {	
		$service = wp_ozh_yourls_service();
	}
	
	if ( !$service )
		return false;
	
	$url = false;
	
	switch ( $service ) {
		case 'yourls-local' :
			// Load the YOURLS config file
			$include = wp_ozh_yourls_find_yourls_loader();
			if ( $include )
				require_once( $include );
			
			if ( defined( 'YOURLS_SITE' ) )
				$url = YOURLS_SITE;
			
			break;
		
		case 'yourls-remote' :		
			if ( !$wp_ozh_yourls )
				$wp_ozh_yourls = get_option( 'ozh_yourls' );
			$yourls_url = isset( $wp_ozh_yourls['yourls_url'] ) ? $wp_ozh_yourls['yourls_url'] : false;
			
			if ( $yourls_url ) {
				$url = str_replace( 'yourls-api.php', '', $yourls_url );
			}
			
			break;
		
		case 'bitly' :
			$url = 'http://bit.ly';
			break;
		
		case 'tinyurl' :
			$url = 'http://tinyurl.com';
			break;
		
		case 'isgd' :
			$url = 'http://is.gd';
			break;
		
		default :
			break;
	}
	
	if ( $url ) {
		$url = trailingslashit( $url );

	}
	
	return $url;
}

/**
 * Does the current shortening service allow custom URL slugs?
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @return bool
 */
function wp_ozh_yourls_service_allows_custom_urls() {
	$service = wp_ozh_yourls_service();

	$customizable = apply_filters( 'wp_ozh_yourls_customizable_services', array( 'yourls-remote', 'yourls-local' ) );
	
	return in_array( $service, $customizable );
}

/**
 * Get a sanitized version of a BP/WP slug
 *
 * YOURLS only allows a limited character set for shorturls. This function attempts to reduce the
 * provided slug to a keyword containing only legal characters. In the case of a local YOURLS
 * installation, this happens by using YOURLS's own yourls_sanitize_string() function. Otherwise,
 * we make a guess that the remote YOURLS installation uses 36-bit encoding, and sanitize based on
 * that guess.
 *
 * @package YOURLS WordPress to Twitter
 * @since 1.5
 *
 * @param str $slug The slug you want sanitized
 * @return str $sanitized The sanitized slug
 */
function wp_ozh_yourls_sanitize_slug( $slug ) {
	$service = wp_ozh_yourls_service();
	
	switch ( $service ) {
		case 'yourls-local' :
			// Bootstrap YOURLS and use its function to sanitize
			$include = wp_ozh_yourls_find_yourls_loader();
			if ( $include ) {
				require_once( $include );
				$sanitized = yourls_sanitize_string( $slug );
				
				// Putting the break here means that we can use the fallback
				// method when YOURLS cannot be found or loaded
				break;
			}
		
		default :
			// On remote requests, we have to make a guess about encoding. 36 is
			// probably more common. This method is basically torn right out of YOURLS
			$charset = '0123456789abcdefghijklmnopqrstuvwxyz';
			$pattern = preg_quote( $charset, '-' );
			$sanitized = substr(preg_replace('![^'.$pattern.']!', '', $slug ), 0, 199);
			
			break;
	}
	
	return apply_filters( 'wp_ozh_yourls_sanitize_slug', $sanitized, $slug, $service );
}

?>