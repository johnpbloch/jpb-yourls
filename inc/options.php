<?php

// Display notice prompting for settings
function wp_ozh_yourls_admin_notice() {
	global $plugin_page;
	if( $plugin_page == 'ozh_yourls' ) {
		$message = __( '<strong>YOURLS - WordPress to Twitter</strong> configuration incomplete', 'wp-ozh-yourls' );
	} else {
		$url = menu_page_url( 'ozh_yourls', false );
		$message = sprintf( __( 'Please configure <strong>YOURLS - WordPress to Twitter</strong> <a href="%s">settings</a> now', 'wp-ozh-yourls' ), $url );
	}
	$notice = <<<NOTICE
	<div class="error"><p>$message</p></div>
NOTICE;
	echo apply_filters( 'ozh_yourls_notice', $notice );
}

// Add page to menu
function wp_ozh_yourls_add_page() {
	// Loading CSS & JS *only* where needed. Do it this way too, goddamnit.
	$page = add_options_page( __( 'YOURLS: WordPress to Twitter', 'wp-ozh-yourls' ), 'YOURLS', 'manage_options', 'ozh_yourls', 'wp_ozh_yourls_do_page' );
	add_action("load-$page", 'wp_ozh_yourls_add_css_js_plugin');
	add_action("load-$page", 'wp_ozh_yourls_handle_action_links');
	// Add the JS & CSS for the char counter. This is too early to check wp_ozh_yourls_generate_on('post') or ('page')
	add_action('load-post.php', 'wp_ozh_yourls_add_css_js_post');
	add_action('load-post-new.php', 'wp_ozh_yourls_add_css_js_post');
	add_action('load-page.php', 'wp_ozh_yourls_add_css_js_post');
	add_action('load-page-new.php', 'wp_ozh_yourls_add_css_js_post');
}

// Add style & JS on the plugin page
function wp_ozh_yourls_add_css_js_plugin() {
	add_thickbox();
	$plugin_url = wp_ozh_yourls_pluginurl();
	wp_enqueue_script('yourls_js', $plugin_url.'res/yourls.js');
	wp_enqueue_script('wp-ajax-response');
	wp_enqueue_style('yourls_css', $plugin_url.'res/yourls.css');
}

// Add style & JS on the Post/Page Edit page
function wp_ozh_yourls_add_css_js_post() {
	global $pagenow;
	$current = str_replace( array('-new.php', '.php'), '', $pagenow);
	if ( wp_ozh_yourls_generate_on($current) ) {
		$plugin_url = wp_ozh_yourls_pluginurl();
		wp_enqueue_script('yourls_js', $plugin_url.'res/post.js');
		wp_enqueue_style('yourls_css', $plugin_url.'res/post.css');
	}
}

// Sanitize & validate options that are submitted
function wp_ozh_yourls_sanitize( $in ) {
	global $wp_ozh_yourls;
	
	// all options: sanitized strings
	$in = array_map( 'esc_attr', $in);
	
	// 0 or 1 for generate_on_*, tweet_on_*, link_on_*
	foreach( $in as $key=>$value ) {
		if( preg_match( '/^(generate|tweet)_on_/', $key ) ) {
			$in[$key] = ( $value == 1 ? 1 : 0 );
		}
	}
	
	// Twitter keys
	foreach( array( 'consumer_key', 'consumer_secret', 'yourls_acc_token', 'yourls_acc_secret' ) as $key ) {
		$in[$key] = wp_ozh_yourls_validate_key( $in[$key] );
	}
	
	// Get the shortener base URL based, on the new settings
	$in['shortener_base_url'] = wp_ozh_yourls_determine_base_url( $in );
	
	return $in;
}

// Validate Twitter keys
function wp_ozh_yourls_validate_key( $key ) {
	$key = trim( $key );
	if( !preg_match('/^[A-Za-z0-9-_]+$/', $key) )
		  $key = '';
	return $key;
}

// Admin notice telling the Twitter keys were reset because invalid
function wp_ozh_yourls_admin_notice_twitter_key() {
	echo <<<OOPS
	<div class="error"><p>The Consumer or Secret Key is invalid. Please re-input.</p></div>
OOPS;
}

// Check if plugin seems configured. Param: 'overall' return one single bool, otherwise return details
function wp_ozh_yourls_settings_are_ok( $check = 'overall' ) {
	global $wp_ozh_yourls;

	$check_twitter   = ( wp_ozh_yourls_twitter_keys_empty() ? false : true );
	$check_twitter   = ( $check_twitter && ( wp_ozh_yourls_twitter_check() === true ) );
	$check_yourls    = ( isset( $wp_ozh_yourls['service'] ) && !empty( $wp_ozh_yourls['service'] ) ? true : false );
	$check_wordpress = ( isset( $wp_ozh_yourls['twitter_message'] ) && !empty( $wp_ozh_yourls['twitter_message'] ) ? true : false );
	
	$check_buddypress = true; // We don't really care if BP is set up
	
	if( $check == 'overall' ) {
		$overall = $check_twitter && $check_yourls && $check_wordpress ;
		return $overall;
	} else {
		return array( 'check_yourls' => $check_yourls, 'check_twitter' => $check_twitter, 'check_wordpress' => $check_wordpress, 'check_buddypress' => $check_buddypress );
	}
}

// Handle action links (reset or unlink)
function wp_ozh_yourls_handle_action_links() {
	$actions = array( 'reset', 'unlink' );
	if( !isset( $_GET['action'] ) or !in_array( $_GET['action'], $actions ) )
		return;

	$action = $_GET['action'];
	$nonce  = $_GET['_wpnonce'];
	
	if ( !wp_verify_nonce( $nonce, $action.'-yourls') )
		wp_die( "Invalid link" );
	
	global $wp_ozh_yourls;
		
	switch( $action ) {
	
		case 'unlink':
			wp_ozh_yourls_session_destroy();
			$wp_ozh_yourls['consumer_key'] =
				$wp_ozh_yourls['consumer_secret'] =
				$wp_ozh_yourls['yourls_acc_token'] = 
				$wp_ozh_yourls['yourls_acc_secret'] = '';
			update_option( 'ozh_yourls', $wp_ozh_yourls );
			break;

		case 'reset':
			wp_ozh_yourls_session_destroy();
			$wp_ozh_yourls = array();
			delete_option( 'ozh_yourls' );
			break;

	}
	
	wp_redirect( menu_page_url( 'ozh_yourls', false ) );
}

// Destroy session
function wp_ozh_yourls_session_destroy() {
	$_SESSION = array();
	if ( isset( $_COOKIE[session_name()] ) ) {
	   setcookie( session_name(), '', time()-42000, '/' );
	}
	session_destroy();
}

// Draw the option page
function wp_ozh_yourls_do_page() {
	$plugin_url = wp_ozh_yourls_pluginurl();
	
	$ozh_yourls = get_option('ozh_yourls'); 
	
	extract( wp_ozh_yourls_settings_are_ok( 'all' ) ); // $check_twitter, $check_yourls, $check_wordpress
	
	// If only one of the 3 $check_ is false, expand that section, otherwise expand first
	switch( intval( $check_twitter ) + intval( $check_yourls ) + intval( $check_wordpress ) ) {
		case 0:
		case 3:
			$script_expand = "jQuery('#h3_yourls').click();";
			break;
		case 1:
		case 2:
			if( !$check_yourls ) {
				$script_expand = "jQuery('#h3_yourls').click();";
			} elseif( !$check_twitter ) {
				$script_expand = "jQuery('#h3_twitter').click();";
			} else {
				$script_expand = "jQuery('#h3_wordpress').click();";
			}
			break;
	}

	
	?>
	<script>
	jQuery(document).ready(function(){
		toggle_ok_notok('#h3_check_yourls', '<?php echo $check_yourls ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_twitter', '<?php echo $check_twitter ? 'ok' : 'notok' ; ?>' );
		toggle_ok_notok('#h3_check_wordpress', '<?php echo $check_wordpress ? 'ok' : 'notok' ; ?>' );
		<?php echo $script_expand; ?>
	});
	</script>	
	
	<div class="wrap">
	
	<?php /** ?>
	<pre><?php print_r(get_option('ozh_yourls')); ?></pre>
	<pre><?php print_r($_SESSION); ?></pre>
	<?php /**/ ?>

	<div class="icon32" id="icon-plugins"><br/></div>
	<h2><?php _e( 'YOURLS - WordPress to Twitter', 'wp-ozh-yourls' ) ?></h2>
	
	<div id="y_logo">
		<div class="y_logo">
			<a href="http://yourls.org/"><img src="<?php echo $plugin_url; ?>/res/yourls-logo.png"></a>
		</div>
		<div class="y_text">
			<p><?php _e( '<a href="http://yourls.org/">YOURLS</a> is a free URL shortener service you can run on your webhost to have your own personal TinyURL.', 'wp-ozh-yourls' ) ?></p>
			<p><?php _e( 'This plugin is a bridge between <a href="http://yourls.org/">YOURLS</a>, <a href="http://twitter.com/">Twitter</a> and your blog: when you submit a new post or page, your blog will tap into YOURLS to generate a short URL for it, and will then tweet it.', 'wp-ozh-yourls' ) ?></p>
			<p><?php _e( 'Note that, for maximum fun, this plugin also supports a few other public URL shortener services: is.gd, tinyURL and bit.ly', 'wp-ozh-yourls' ) ?></p>
		</div>
	</div>
	
	<form method="post" action="options.php">
	<?php settings_fields('wp_ozh_yourls_options'); ?>

	<h3><?php _e( 'URL Shortener Settings', 'wp-ozh-yourls' ) ?> <span class="h3_toggle expand" id="h3_yourls">+</span> <span id="h3_check_yourls" class="h3_check">*</span></h3>

	<div class="div_h3" id="div_h3_yourls">
	<table class="form-table">

	<tr valign="top">
	<th scope="row"><?php _e( 'URL Shortener Service', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td>

	<label for="y_service"><?php _e( 'You are using:', 'wp-ozh-yourls' ) ?></label>
	<select name="ozh_yourls[service]" id="y_service" class="y_toggle">
	<option value="" <?php selected( '', $ozh_yourls['service'] ); ?> ><?php _e( 'Please select..', 'wp-ozh-yourls' ) ?></option>
	<option value="yourls" <?php selected( 'yourls', $ozh_yourls['service'] ); ?> ><?php _e( 'your own YOURLS install', 'wp-ozh-yourls' ) ?></option>
	<option value="other" <?php selected( 'other', $ozh_yourls['service'] ); ?> ><?php _e( 'another public service such as TinyURL or bit.ly', 'wp-ozh-yourls' ) ?></option>
	</select>
	
	<?php $hidden = ( $ozh_yourls['service'] == 'yourls' ? '' : 'y_hidden' ) ; ?>
	<div id="y_show_yourls" class="<?php echo $hidden; ?> y_service y_level2">
		<label for="y_location"><?php _e( 'Your YOURLS installation is', 'wp-ozh-yourls' ) ?></label>
		<select name="ozh_yourls[location]" id="y_location" class="y_toggle">
		<option value="" <?php selected( '', $ozh_yourls['location'] ); ?> ><?php _e( 'Please select...', 'wp-ozh-yourls' ) ?></option>
		<option value="local" <?php selected( 'local', $ozh_yourls['location'] ); ?> ><?php _e( 'local, on the same webserver', 'wp-ozh-yourls' ) ?></option>
		<option value="remote" <?php selected( 'remote', $ozh_yourls['location'] ); ?> ><?php _e( 'remote, on another webserver', 'wp-ozh-yourls' ) ?></option>
		</select>
		
		<?php $hidden = ( $ozh_yourls['location'] == 'local' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_local" class="<?php echo $hidden; ?> y_location y_level3">
			<label for="y_path">Path to YOURLS <tt>config.php</tt></label> <input type="text" class="y_longfield" id="y_path" name="ozh_yourls[yourls_path]" value="<?php echo $ozh_yourls['yourls_path']; ?>"/> <span id="check_path" class="yourls_check button">check</span><br/>
			<em><?php _e( 'Example:', 'wp-ozh-yourls' ) ?></em> <tt>/home/you/site.com/yourls/includes/config.php</tt>
		</div>
		
		<?php $hidden = ( $ozh_yourls['location'] == 'remote' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_remote" class="<?php echo $hidden; ?> y_location y_level3">
			<label for="y_url"><?php _e( 'URL to the YOURLS API', 'wp-ozh-yourls' ) ?></label> <input type="text" id="y_url" class="y_longfield" name="ozh_yourls[yourls_url]" value="<?php echo $ozh_yourls['yourls_url']; ?>"/> <span id="check_url" class="yourls_check button">check</span><br/>
			<em><?php _e( 'Example:', 'wp-ozh-yourls' ) ?></em> <tt>http://site.com/yourls-api.php</tt><br/>
			<label for="y_yourls_login"><?php _e( 'YOURLS Login', 'wp-ozh-yourls' ) ?></label> <input type="text" id="y_yourls_login" name="ozh_yourls[yourls_login]" value="<?php echo $ozh_yourls['yourls_login']; ?>"/><br/>
			<label for="y_yourls_passwd"><?php _e( 'YOURLS Password', 'wp-ozh-yourls' ) ?></label> <input type="password" id="y_yourls_passwd" name="ozh_yourls[yourls_password]" value="<?php echo $ozh_yourls['yourls_password']; ?>"/><br/>
		</div>
		<?php
		wp_nonce_field( 'yourls', '_ajax_yourls', false );
		?>
	</div>
	
	<?php $hidden = ( $ozh_yourls['service'] == 'other' ? '' : 'y_hidden' ) ; ?>
	<div id="y_show_other" class="<?php echo $hidden; ?> y_service y_level2">

		<label for="y_other"><?php _e( 'Public service', 'wp-ozh-yourls' ) ?></label>
		<select name="ozh_yourls[other]" id="y_other" class="y_toggle">
		<option value="" <?php selected( '', $ozh_yourls['other'] ); ?> ><?php _e( 'Please select...', 'wp-ozh-yourls' ) ?></option>
		<!--<option value="pingfm" <?php selected( 'pingfm', $ozh_yourls['other'] ); ?> >ping.fm</option>-->
		<option value="bitly" <?php selected( 'bitly', $ozh_yourls['other'] ); ?> >bit.ly</option>
		<option value="tinyurl" <?php selected( 'tinyurl', $ozh_yourls['other'] ); ?> >tinyURL</option>
		<option value="isgd" <?php selected( 'isgd', $ozh_yourls['other'] ); ?> >is.gd</option>
		</select>
		
		<?php $hidden = ( $ozh_yourls['other'] == 'bitly' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_bitly" class="<?php echo $hidden; ?> y_other y_level3">
			<label for="y_api_bitly_login"><?php _e( 'API Login', 'wp-ozh-yourls' ) ?> </label> <input type="text" id="y_api_bitly_login" name="ozh_yourls[bitly_login]" value="<?php echo $ozh_yourls['bitly_login']; ?>"/> <?php _e( '(case sensitive!)', 'wp-ozh-yourls' ) ?><br/>
			<label for="y_api_bitly_pass"><?php _e( 'API Key', 'wp-ozh-yourls' ) ?></label> <input type="text" id="y_api_bitly_pass" class="y_longfield" name="ozh_yourls[bitly_password]" value="<?php echo $ozh_yourls['bitly_password']; ?>"/><br/>
			<p class="description"><?php _e( 'If you have a <a href="http://bit.ly/account/">bit.ly</a> account, entering your credentials will link the short URLs to it', 'wp-ozh-yourls' ) ?></p>
		</div>

		<?php $hidden = ( $ozh_yourls['other'] == 'pingfm' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_pingfm" class="<?php echo $hidden; ?> y_other y_level3">
			<label for="y_api_pingfm_user_app_key"><?php _e( 'Web Key', 'wp-ozh-yourls' ) ?></label> <input type="text" id="y_api_pingfm_user_app_key" name="ozh_yourls[pingfm_user_app_key]" value="<?php echo $ozh_yourls['pingfm_user_app_key']; ?>"/><br/>
			<p class="description"><?php _e( 'If you have a <a href="http://ping.fm/">ping.fm</a> account, enter your private <a href="http://ping.fm/key/">Web Key</a>', 'wp-ozh-yourls' ) ?></p>
		</div>
		
		<?php $hidden = ( $ozh_yourls['other'] == 'tinyurl' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_tinyurl" class="<?php echo $hidden; ?> y_other y_level3">
			<p class="description"><?php _e( '(this service needs no authentication)', 'wp-ozh-yourls' ) ?></p>
		</div>
		

		<?php $hidden = ( $ozh_yourls['other'] == 'isgd' ? '' : 'y_hidden' ) ; ?>
		<div id="y_show_isgd" class="<?php echo $hidden; ?> y_other y_level3">
			<p class="description"><?php _e( '(this service needs no authentication)', 'wp-ozh-yourls' ) ?></p>
		</div>
		
	</div>

	</td>
	</tr>
	</table>
	</div><!-- div_h3_yourls -->
	
	<h3><?php _e( 'Twitter Settings', 'wp-ozh-yourls' ) ?> <span class="h3_toggle expand" id="h3_twitter">+</span> <span id="h3_check_twitter" class="h3_check">*</span></h3> 
	
	<?php
	$blogurl  = get_home_url();
	$blogname = urlencode( get_bloginfo( 'name' ) );
	$blogdesc = urlencode( trim( get_bloginfo( 'description' ), '.' ).'. Powered by YOURLS.' );
	$help_url = $plugin_url."res/help.jpg?tb_iframe=1&width=677&height=608";
	?>
	
	<div class="div_h3" id="div_h3_twitter">
	<p><?php _e( 'To connect your site to Twitter, you need to register your blog as a <strong>Twitter Application</strong> and get a <strong>Consumer Key</strong>, a <strong>Consumer Secret</strong>, an <strong>Access Token</strong> and an <strong>Access Token Secret</strong>. Phew. Complicated? Blame Twitter :)', 'wp-ozh-yourls' ) ?></p>
	<p><?php _e( 'Already registered? Find your keys on <a href="http://dev.twitter.com/apps">Twitter Application List</a>', 'wp-ozh-yourls' ) ?></p>
	<p><?php _e( 'Need to register? <a href="http://dev.twitter.com/apps/new">Register an Application</a> and fill the form as <a title="Register your app like this" href="<?php echo $help_url; ?>" class="thickbox">in this help screen</a> to get your keys and tokens', 'wp-ozh-yourls' ) ?></p>
	<ul id="appdetails">
		<li><?php _e( 'Set <strong>Application Type</strong> to <strong>Browser</strong>', 'wp-ozh-yourls' ) ?></li>
		<li><?php _e( 'Set <strong>Callback URL</strong> to <strong><?php echo $blogurl; ?></strong>', 'wp-ozh-yourls' ) ?></li>
		<li><?php _e( 'Set <strong>Default Access type</strong> to <strong>Read &amp; Write</strong>', 'wp-ozh-yourls' ) ?></li>
	</ul>

	<table class="form-table">

	<tr valign="top">
	<th scope="row"><?php _e( 'Consumer Key', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td><input id="consumer_key" name="ozh_yourls[consumer_key]" type="password" size="50" value="<?php echo $ozh_yourls['consumer_key']; ?>"/></td>
	</tr>

	<tr valign="top">
	<th scope="row"><?php _e( 'Consumer Secret', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td><input id="consumer_secret" name="ozh_yourls[consumer_secret]" type="password" size="50" value="<?php echo $ozh_yourls['consumer_secret']; ?>"/></td>
	</tr>
	
	<tr valign="top"><td colspan="2"><p><?php _e( 'On the right hand column of your application page, click on \'My Access Token\' for the following values:', 'wp-ozh-yourls' ) ?></p></td></tr>

	<tr valign="top">
	<th scope="row"><?php _e( 'Access Token', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td><input id="yourls_acc_token" name="ozh_yourls[yourls_acc_token]" type="password" size="50" value="<?php echo $ozh_yourls['yourls_acc_token']; ?>"/></td>
	</tr>
	
	<tr valign="top">
	<th scope="row"><?php _e( 'Access Token Secret', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td><input id="yourls_acc_secret" name="ozh_yourls[yourls_acc_secret]" type="password" size="50" value="<?php echo $ozh_yourls['yourls_acc_secret']; ?>"/></td>
	</tr>
	
	<tr>
	<td colspan="2" id="yourls_twitter_infos">
	<?php
	if( !wp_ozh_yourls_twitter_keys_empty() ) {
		wp_ozh_yourls_twitter_button_or_infos(); // in oauth.php
	}
	?>
	</td>
	</tr>
	
	</table>
	
	
	</div> <!-- div_h3_twitter -->
	
	<h3><?php _e( 'WordPress settings', 'wp-ozh-yourls' ) ?> <span class="h3_toggle expand" id="h3_wordpress">+</span> <span id="h3_check_wordpress" class="h3_check">*</span></h3> 

	<div class="div_h3" id="div_h3_wordpress">

	<h4><?php _e( 'When to generate a short URL and tweet it', 'wp-ozh-yourls' ) ?></h4> 
	
	<table class="form-table">

	<?php
	$types = get_post_types( array('publicly_queryable' => 1 ), 'objects' );
	foreach( $types as $type=>$object ) {
		$name = $object->labels->singular_name;
		$generate_checked = isset( $ozh_yourls['generate_on_' . $type] ) && 1 == $ozh_yourls['generate_on_' . $type] ? 1 : 0;	
		$tweet_checked = isset( $ozh_yourls['tweet_on_' . $type] ) && 1 == $ozh_yourls['tweet_on_' . $type] ? 1 : 0;
		
		?>
		<tr valign="top">
		<th scope="row"><?php printf( __( 'New <strong>%s</strong> published', 'wp-ozh-yourls' ), $name ) ?></th>
		<td>
		<input class="y_toggle" id="generate_on_<?php echo $type; ?>" name="ozh_yourls[generate_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $generate_checked ); ?> /><label for="generate_on_<?php echo $type; ?>"> <?php _e( 'Generate short URL', 'wp-ozh-yourls' ) ?></label><br/>
		<?php $hidden = ( $generate_checked == '1' ? '' : 'y_hidden' ) ; ?>
		<?php if( $type != 'attachment' ) { ?>
		<div id="y_show_generate_on_<?php echo $type; ?>" class="<?php echo $hidden; ?> generate_on_<?php echo $type; ?>">
			<input id="tweet_on_<?php echo $type; ?>" name="ozh_yourls[tweet_on_<?php echo $type; ?>]" type="checkbox" value="1" <?php checked( '1', $tweet_checked ) ?> /><label for="tweet_on_<?php echo $type; ?>"> <?php _e( 'Send a tweet with the short URL', 'wp-ozh-yourls' ) ?></label>
		</div>
		<?php } ?>
		</td>
		</tr>
	<?php } ?>

	</table>

	<h4><?php _e( 'What to tweet', 'wp-ozh-yourls' ) ?></h4> 

	<table class="form-table">

	<tr valign="top">
	<th scope="row"><?php _e( 'Tweet message', 'wp-ozh-yourls' ) ?><span class="mandatory">*</span></th>
	<td><input id="tw_msg" name="ozh_yourls[twitter_message]" type="text" size="50" value="<?php echo $ozh_yourls['twitter_message']; ?>"/><br/>
	<?php _e( 'This is your tweet template. The plugin will replace <tt>%T</tt> with the post title and <tt>%U</tt> with its short URL, with as much text as possible so it fits in the 140 character limit<br/>
	Examples (click one to copy)', 'wp-ozh-yourls' ) ?><br/>
	<ul id="tw_msg_sample">
		<li><code class="tw_msg_sample"><?php printf( __( 'Fresh on %s', 'wp-ozh-yourls' ), get_bloginfo() ) ?>: %T %U</code></li>
		<li><code class="tw_msg_sample"><?php _e( 'Just posted:', 'wp-ozh-yourls' ) ?> %T %U</code></li>
		<li><code class="tw_msg_sample">%T - %U</code></li>
	</ul>
	<em><?php _e( 'Tip: Keep the tweet message template short!', 'wp-ozh-yourls' ) ?></em>
	<h4 id="toggle_advanced_template"><?php _e( 'Advanced template', 'wp-ozh-yourls' ) ?></h4>
	<div id="advanced_template">
		<?php _e( 'You can use all of the following tokens in your tweet template:', 'wp-ozh-yourls' ) ?>
		<ul>
			<li><b><tt>%U</tt></b>: <?php _e( 'shorturl', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%T</tt></b>: <?php _e( 'post title', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%A</tt></b>: <?php _e( 'author\'s display name', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%A{<?php _e( 'something', 'wp-ozh-yourls' ) ?>}</tt></b>: <?php _e( 'author\'s \'something\' as stored in the database. Example: %A{first_name}. See <a href="http://codex.wordpress.org/Function_Reference/get_userdata">get_userdata()</a>.', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%F{<?php _e( 'something', 'wp-ozh-yourls' ) ?>}</tt></b>: <?php _e( 'custom post field \'something\'. See <a href="http://codex.wordpress.org/Function_Reference/get_post_meta">get_post_meta()</a>.', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%X{<?php _e( 'taxonomy', 'wp-ozh-yourls' ) ?>}</tt></b>: <?php _e( 'terms as plaintext and lowercase for taxonomy. Example: %X{category} (space separated if more than one, up to 3 tags)', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%Y{<?php _e( 'taxonomy', 'wp-ozh-yourls' ) ?>}</tt></b>: <?php _e( 'terms as #hashtags and lowercase for taxonomy. Example: %Y{category} (space separated if more than one, up to 3 tags)', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%L</tt></b>: <?php _e( 'tags as plaintext and lowercase (space separated if more than one, up to 3 tags)', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%H</tt></b>: <?php _e( 'tags as #hashtags and lowercase (space separated if more than one, up to 3 tags)', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%C</tt></b>: <?php _e( 'categories as plaintext and lowercase (space separated if more than one, up to 3 categories)', 'wp-ozh-yourls' ) ?></li>
			<li><b><tt>%D</tt></b>: <?php _e( 'categories as #hashtags and lowercase (space separated if more than one, up to 3 categories)', 'wp-ozh-yourls' ) ?></li>
		</ul>
		<?php _e( 'Remember that you only have 140 characters! The title will be added last, so if you put too many tokens like hashtags and stuff, the title might get trimmed hard!', 'wp-ozh-yourls' ) ?>
	</div>
	</td>
	</tr>

	</table>
	
	</div> <!-- div_h3_wordpress -->
	
	<?php do_action( 'ozh_yourls_admin_sections' ) ?>
	
	<?php
	$reset = add_query_arg( array('action' => 'reset'), menu_page_url( 'ozh_yourls', false ) );
	$reset = wp_nonce_url( $reset, 'reset-yourls' );
	?>

	<p class="submit">
	<input type="submit" class="button-primary y_submit" value="<?php _e('Save Changes') ?>" />
	<?php echo "<a href='$reset' id='reset-yourls' class='submitdelete'>Reset</a> all settings"; ?>
	</p>
	
	<p><small><?php printf( __( '<span class="mandatory">*</span> denotes a mandatory field. A green check<img src="%1$s/res/accept.png" /> indicates the section main parameters are <em>filled</em>, not necessarily <em>correct</em>. Click on the <img src="%1$s/res/expand.png" /> to expand a setting section.', 'wp-ozh-yourls' ), $plugin_url ) ?></small></p>

	</form>

	</div> <!-- wrap -->

	
	<?php	
}

// Add meta boxes to post & page edit
function wp_ozh_yourls_addbox() {
	// What page are we on? (new Post, new Page, new custom post type?)
	if( isset ( $_GET["post"] ) ) {
		$post_id   = (int)$_GET["post"];
		$post_type = get_post_type( $post_id );
	} else {
		$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : 'post' ;
	}
	
	if ( wp_ozh_yourls_generate_on( $post_type ) ) {
		add_meta_box( 'yourlsdiv', 'YOURLS', 'wp_ozh_yourls_drawbox', $post_type, 'side', 'default' );
	}
	
	// TODO: do something with links. Or wait till they're considered custom post types. Yeah, we'll wait.
}


// Draw meta box
function wp_ozh_yourls_drawbox( $post ) {
	$type = $post->post_type;
	$status = $post->post_status;
	$id = $post->ID;
	$title = $post->post_title;
        $post_type = get_post_type_object($type);
        $type_label = $post_type->labels->singular_name;
	
	$account = wp_ozh_yourls_get_twitter_screen_name();
	
	// Too early, young Padawan
	if ( $status != 'publish' ) {
		_e( '<p>Depending on <a href="options-general.php?page=ozh_yourls">configuration</a>, a short URL will be generated and/or a tweet will be sent.</p>', 'wp-ozh-yourls' );
		return;
	}
	
	$shorturl = wp_ozh_yourls_geturl( $id );
	
	// Bummer, could not generate a short URL
	if ( !$shorturl ) {
		_e( '<p>Bleh. The URL shortening service you configured could not be reached as of now. This might be a temporary problem, please try again later!</p>', 'wp-ozh-yourls' );
		return;
	}
	
	// YOURLS part:	
	wp_nonce_field( 'yourls', '_ajax_yourls', false );
	echo '
	<input type="hidden" id="yourls_post_id" value="'.$id.'" />
	<input type="hidden" id="yourls_shorturl" value="'.$shorturl.'" />
	<input type="hidden" id="yourls_twitter_account" value="'.$account.'" />';
	
	echo '<p><strong>' . __( 'Short URL', 'wp-ozh-yourls' ) . '</strong></p>';
	echo '<div id="yourls-shorturl">';
	
	echo "<p>" . sprintf( __( 'This %1$s\'s short URL: <strong><a href="%2$s">%2$s</a></strong>', 'wp-ozh-yourls' ), $type, $shorturl ) . "</p>
	<p>" . __( "You can click Reset to generate another short URL if you picked another URL shortening service in the <a href='options-general.php?page=ozh_yourls'>plugin options</a>", 'wp-ozh-yourls' ) . "</p>";
	echo '<p style="text-align:right"><input class="button" id="yourls_reset" type="submit" value="' . __( 'Reset short URL', 'wp-ozh-yourls' ) . '" /></p>';
	echo '</div>';
	

	// Twitter part:
	if( wp_ozh_yourls_twitter_keys_empty() or wp_ozh_yourls_get_twitter_screen_name() === false )
		return;
	
	$action = __( 'Tweet this', 'wp-ozh-yourls' );
	$promote = sprintf( __( "Promote this %s", 'wp-ozh-yourls' ), $type_label );
	$tweeted = get_post_meta( $id, 'yourls_tweeted', true );

	echo '<p><strong>'.$promote.' on <a href="http://twitter.com/'.$account.'">@'.$account.'</a>: </strong></p>
	<div id="yourls-promote">';
	if ($tweeted) {
		$action = __( 'Retweet this', 'wp-ozh-yourls' );
		$promote = sprintf( __( "Promote this %s again", 'wp-ozh-yourls' ), $type_label );
		echo '<p>' . __( '<em>Note:</em> this post has already been tweeted. Not that there\'s something wrong to promote it again, of course :)', 'wp-ozh-yourls' ) . '</p>';
	}
	echo '<p><textarea id="yourls_tweet" rows="1" style="width:100%">'.wp_ozh_yourls_maketweet( $shorturl, $title, $id ).'</textarea></p>
	<p style="text-align:right"><input class="button" id="yourls_promote" type="submit" value="'.$action.'" /></p>
	</div>';
}

?>