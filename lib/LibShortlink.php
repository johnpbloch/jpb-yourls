<?php

interface LibShortlink {

	public static function shorten( $longURL, $desired );

	public static function expand( $shortURL );
}
