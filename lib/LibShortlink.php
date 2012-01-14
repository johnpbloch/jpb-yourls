<?php

interface LibShortlink {

	public static function shorten( $longURL );

	public static function expand( $shortURL );
}
