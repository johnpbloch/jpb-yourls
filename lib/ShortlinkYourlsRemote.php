<?php

class ShortlinkYourlsRemote implements Shortlink
{

	public static function shorten( $longURL, $desired = false )
	{
		return self::apiHelper( 'shorten', $longURL, $desired );
	}

	public static function expand( $shortURL )
	{
		return self::apiHelper( 'expand', $shortURL );
	}

	protected static function apiHelper( $action, $url, $desired = false )
	{
		$response = self::doRequest( $action, $url, $desired );
		if( is_wp_error( $response ) )
		{
			return $response;
		}
		else
		{
			if( $action === 'shorten' )
			{
				if( !empty( $response->shorturl ) )
				{
					return $response->shorturl;
				}
				return new WP_Error( $response->code, $response->message );
			}
			else
			{
				if( !empty( $response->longurl ) )
				{
					return $response->longurl;
				}
				return new WP_Error( $response->simple, $response->message );
			}
		}
	}

	protected static function doRequest( $action, $url, $desired )
	{
		$url = self::requestUrl();
		$data = self::requestData( $action, $url, $desired );
		$request = wp_remote_post( $url, array( 'body' => $data ) );
		if( is_wp_error( $request ) )
		{
			return $request;
		}
		elseif( empty( $request['response'] ) || 200 !== (int)$request['response'] )
		{
			return new WP_Error( 'response-code-not-valid', 'The response code was not valid.' );
		}
		$response = json_decode( $request['body'] );
		if( !is_object( $response ) )
		{
			return new WP_Error( 'response-data-corrupted', 'The response data was corrupted and could not be decoded' );
		}
		return $response;
	}

	protected static function requestUrl( $base = false )
	{
		if( !$base )
		{
			$base = JBY_Options::instance()->shortlinkURI;
		}
		$base = trailingslashit( $base );
		$base .= 'yourls-api.php';
		return $base;
	}

	protected static function requestData( $action, $url, $desired )
	{
		$data = array(
			'format' => 'json',
		);
		$auth = JBY_Options::instance()->authentication;
		if( !empty( $auth ) )
		{
			if( !empty( $auth['signature'] ) )
			{
				$data['signature'] = $auth['signature'];
			}
			elseif( !empty( $auth['username'] ) && !empty( $auth['password'] ) )
			{
				$data['username'] = $auth['username'];
				$data['password'] = $auth['password'];
			}
		}
		if( 'expand' === $action )
		{
			$data['action'] = 'expand';
			$data['shorturl'] = $url;
		}
		else
		{
			$data['action'] = 'shorturl';
			$data['url'] = $url;
			if( $desired )
			{
				$data['keyword'] = $desired;
			}
		}
		return $data;
	}

}
