<?php

class ShortlinkIsGd implements Shortlink {

	public static function shorten( $longURL, $desired = false ) {
		return static::apiHelper( 'shorten', $longURL, $desired );
	}

	public static function expand( $shortURL ) {
		return static::apiHelper( 'expand', $shortURL );
	}

	protected static function apiHelper( $action, $url, $desired = false ) {
		$haveWeHitTheLimit = get_transient( '_jpb_shortlink_isgd_exceed' );
		if($haveWeHitTheLimit)
			return new WP_Error( 'is-gd-error-code-' . $haveWeHitTheLimit->errorcode, $haveWeHitTheLimit->errormessage );
		$url = static::formatRequestURL( $action, $url, $desired );
		$response = static::getResponse( $url );
		if( is_wp_error( $response ) ) {
			return $response;
		} else {
			if( !empty( $response->errorcode ) ) {
				if( 3 === (int)$response->errorcode ) {
					set_transient( '_jpb_shortlink_isgd_exceed', $response, 60 );
				}
				return new WP_Error( 'is-gd-error-code-' . $response->errorcode, $response->errormessage );
			}
			if( 'shorten' == $action ) {
				return $response->shorturl;
			} else {
				return $response->url;
			}
		}
	}

	protected static function getResponse( $url ) {
		$request = wp_remote_get( $url );
		if( is_wp_error( $request ) )
			return $request;
		elseif( empty( $request['response'] ) || 200 != $request['response'] )
			return new WP_Error( 'response-code-not-valid', 'The response code was not valid.' );
		$response = json_decode( $request['body'] );
		if( !is_object( $response ) )
			return new WP_Error( 'response-data-corrupted', 'The response data was corrupted and could not be decoded' );
		return $response;
	}

	protected static function formatRequestURL( $action, $url, $desired = false ) {
		$base = 'http://is.gd/';
		$base .= 'shorten' == $action ? 'create.php' : 'forward.php';
		$request_arguments = array(
			'format' => 'json',
		);
		if( $action == 'shorten' ) {
			$request_arguments['url'] = $url;
			if( $desired && strlen( (string)$desired ) >= 5 ) {
				$desired = preg_replace( '@[^_a-z0-9]@i', '', (string)$desired );
				$desired = substr( $desired, 0, 30 );
				if( strlen( $desired >= 5 ) )
					$request_arguments['shorturl'] = $desired;
			}
		} else {
			$request_arguments['shorturl'] = $url;
		}
		return add_query_arg( $request_arguments, $base );
	}

}
