<?php

// Initiate OAuth with a request token
function wp_ozh_yourls_oauth_start() {
	global $wp_ozh_yourls;

	if ( wp_ozh_yourls_twitter_keys_empty( 'consumer' ) )
		return false;
	
	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );

	$twitter = new TwitterOAuth( $wp_ozh_yourls['consumer_key'], $wp_ozh_yourls['consumer_secret'] );
	$request = $twitter->getRequestToken();

	$token = $request['oauth_token'];
	$_SESSION['yourls_req_token']  = $token;
	$_SESSION['yourls_req_secret'] = $request['oauth_token_secret'];
	$_SESSION['yourls_callback']   = $_GET['yourls_callback'];
	$_SESSION['yourls_callback_action'] = $_GET['yourls_callback_action'];
	
	if ( $_GET['type'] == 'authorize' ) {
		$url = $twitter->getAuthorizeURL($token);
	} else {
		$url = $twitter->getAuthenticateURL( $token );
	}

	wp_redirect( $url );
	exit;
}

// Confirm OAuth with an access token
function wp_ozh_yourls_oauth_confirm() {
	global $wp_ozh_yourls;

	if( wp_ozh_yourls_twitter_keys_empty( 'consumer' ) )
		return false;

	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );
	
	$yourls_req_token = $_SESSION['yourls_req_token'] ? $_SESSION['yourls_req_token'] : $wp_ozh_yourls['yourls_req_token'];
	$yourls_req_secret = $_SESSION['yourls_req_secret'] ? $_SESSION['yourls_req_secret'] : $wp_ozh_yourls['yourls_req_secret'];
	
	if( !$yourls_req_token or !$yourls_req_secret )
		return false;
	
	$twitter = new TwitterOAuth( $wp_ozh_yourls['consumer_key'], $wp_ozh_yourls['consumer_secret'], $_SESSION['yourls_req_token'], $_SESSION['yourls_req_secret']);
	$access  = $twitter->getAccessToken();
	
	if( $access['oauth_token'] && $access['oauth_token_secret'] ) {
		$wp_ozh_yourls['yourls_acc_token']  = $access['oauth_token'];
		$wp_ozh_yourls['yourls_acc_secret'] = $access['oauth_token_secret'];
		update_option( 'ozh_yourls', $wp_ozh_yourls );
	}

	$twitter = new TwitterOAuth( $wp_ozh_yourls['consumer_key'], $wp_ozh_yourls['consumer_secret'], $access['oauth_token'], $access['oauth_token_secret'] );
	
	// Allow plugins to interrupt the callback
	if ( $_SESSION['yourls_callback_action'] ) {
		do_action( 'yourls_'.$_SESSION['yourls_callback_action'] );
		unset( $_SESSION['yourls_callback_action'] );
	}
	
	wp_redirect( $_SESSION['yourls_callback'] );
	exit;
}

// Send an OAuth request
function wp_ozh_yourls_send_request( $url, $args = array(), $type = NULL ) {

	if( wp_ozh_yourls_twitter_keys_empty() )
		return false;

	global $wp_ozh_yourls;
	
	// Allow token override via parameter. Otherwise, get from options
	if( isset( $args['acc_token'] ) && $args['acc_token'] ) {
		$acc_token = $args['acc_token'];
		unset($args['acc_token']);
	} else {
		$acc_token = $wp_ozh_yourls['yourls_acc_token'];
	}
	
	if( isset( $args['acc_secret'] ) && $args['acc_secret'] ) {
		$acc_secret = $args['acc_secret'];
		unset($args['acc_secret']);
	} else {
		$acc_secret = $wp_ozh_yourls['yourls_acc_secret'];
	}
	
	$acc_token = $wp_ozh_yourls['yourls_acc_token'];
	$acc_secret = $wp_ozh_yourls['yourls_acc_secret'];
	
	if( empty($acc_token) or empty($acc_secret) )
		return false;
		
	if( !class_exists('TwitterOAuth') )
		require_once( dirname(__FILE__).'/oauth_lib/twitterOAuth.php' );

	$twitter = new TwitterOAuth( $wp_ozh_yourls['consumer_key'], $wp_ozh_yourls['consumer_secret'], $acc_token, $acc_secret );
	$json = $twitter->OAuthRequest( $url.'.json', $args, $type );
	
	return json_decode($json);
}

// Check connection with Twitter (check auth & check if site down). Return true or an error message.
function wp_ozh_yourls_twitter_check() {
	if( wp_ozh_yourls_twitter_keys_empty() )
		return 'Please connect with Twitter first';

	$check = wp_ozh_yourls_get_auth_infos( );
	
	if( $check == NULL ) {
		// Twitter probably down
		return 'Unable to connect to Twitter. Either it is temporarily down, or your webserver cannot reach it.';
		
	} else {
		// error:
		if( isset( $check->error ) )
			return $check->error;
			
		// success:
		return true;
	}
}

// Display either connect button or Twitter infos
function wp_ozh_yourls_twitter_button_or_infos() {
	$plugin_url = wp_ozh_yourls_pluginurl();
	
	// wrong keys: wp_ozh_yourls_get_twitter_screen_name() === false && 

	// Twitter down: wp_ozh_yourls_get_twitter_screen_name() === false, wp_ozh_yourls_send_request( 'http://twitter.com/account/verify_credentials' ) === NULL
	
	$check = wp_ozh_yourls_twitter_check();
	
	if( wp_ozh_yourls_twitter_keys_empty() || $check !== true ) {
		// Need "Connect button";
		$img = $plugin_url.'/res/Sign-in-with-Twitter-lighter.png';
		$connect_url = wp_ozh_yourls_get_connect_link();
		echo "<p>$check</p>";
		echo "<p><a href='$connect_url'><img src='$img' alt='Connect with Twitter' /></a></p>";

	} else if ( $check === true ) {
		// we're in!
		$screen_name = wp_ozh_yourls_get_twitter_screen_name();
		$profile_pic = wp_ozh_yourls_get_twitter_profile_pic();
		$f_count = wp_ozh_yourls_get_twitter_follower_count();
		
		echo "<p>Logged on Twitter as <strong><a class='twitter_profile' href='http://twitter.com/$screen_name'><img src='$profile_pic' style='vertical-align:-32px'/>@$screen_name</a></strong> ($f_count followers)</p>";
		
		$unlink = add_query_arg( array('action' => 'unlink'), menu_page_url( 'ozh_yourls', false ) );
		$unlink = wp_nonce_url( $unlink, 'unlink-yourls' );
	}
	
	echo "<p>Reset Twitter info? <a href='$unlink' class='submitdelete' id='unlink-yourls'>Unlink</a> Twitter and your blog.</p>";

}

// Connect link
function wp_ozh_yourls_get_connect_link( $action='', $type='authenticate', $image ='Sign-in-with-Twitter-darker' ) {
	$current_url = 	( isset($_SERVER["HTTPS"]) ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
	$current_url = add_query_arg( 'oauth_connected', 1 );
	$url = add_query_arg(
		array(
			'oauth_start' => 1,
			'yourls_callback' => $current_url,
			'type' => 'authorize', // authorize / authenticate
			'yourls_callback_action' => urlencode( $action )
		), trailingslashit( get_home_url() )
	);
	
	return $url ;
}

// Check authentication
function wp_ozh_yourls_get_auth_infos( $refresh = false ) {
	if( isset( $_SESSION['yourls_credentials'] ) && $_SESSION['yourls_credentials'] && !$refresh )
		return $_SESSION['yourls_credentials'];
	
	$_SESSION['yourls_credentials'] = wp_ozh_yourls_send_request( 'http://twitter.com/account/verify_credentials' );
	
	return $_SESSION['yourls_credentials'];
}

// Get Twitter screen name
function wp_ozh_yourls_get_twitter_screen_name() {
	$infos = wp_ozh_yourls_get_auth_infos();
		
	if( $infos->screen_name )
			return $infos->screen_name;
			
	return false;
}

// Get Twitter profile pic
function wp_ozh_yourls_get_twitter_profile_pic() {
	$infos = wp_ozh_yourls_get_auth_infos();
		
	if( $infos->profile_image_url )
			return $infos->profile_image_url;
			
	return false;
}

// Get Twitter follower count
function wp_ozh_yourls_get_twitter_follower_count() {
	$infos = wp_ozh_yourls_get_auth_infos();
		
	if( $infos->followers_count )
			return $infos->followers_count;
			
	return false;
}

// Check if Twitter keys and tokens are empty. Check for consumer only if $what == 'consumer'
function wp_ozh_yourls_twitter_keys_empty( $what = 'all' ) {
	global $wp_ozh_yourls;
	
	$keys = array('consumer_key', 'consumer_secret' );
	if( $what != 'consumer' ) {
		$keys[] = 'yourls_acc_token';
		$keys[] = 'yourls_acc_secret';
	}
	
	foreach( $keys as $key ) {
		if( !isset( $wp_ozh_yourls[$key] ) or empty( $wp_ozh_yourls[$key] ) )
			return true;	
	}
	
	return false;
}

// Send a message to Twitter. Returns Twitter response.
function wp_ozh_yourls_tweet_it( $message ) {
	global $wp_ozh_yourls;
	$args = array();
	$args['status'] = $message;
	$args['acc_token'] = $wp_ozh_yourls['yourls_acc_token'];
	$args['acc_secret'] = $wp_ozh_yourls['yourls_acc_secret'];
	
	$resp = wp_ozh_yourls_send_request( 'http://api.twitter.com/1/statuses/update', $args );
	
	return $resp;
}
