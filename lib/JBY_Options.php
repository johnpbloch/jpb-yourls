<?php

class JBY_Options extends JPB_Options
{

	protected $_option_name = 'jpb-yourls-options';
	public $shortlinkAPI = 'yourls';
	public $shortlinkURI = '';

	/**
	 * The singleton instance
	 * @var JBY_Options
	 */
	private static $_instance;

	/**
	 * Access the singleton instance of this option object.
	 * @return JBY_Options The singleton
	 */
	public static function instance()
	{
		if( !(self::$_instance instanceof JBY_Options) )
		{
			self::$_instance = new JBY_Options();
		}
		return self::$_instance;
	}

	protected function _install()
	{
		$this->update();
	}

	protected function _upgrade( array $option )
	{
		
	}

}
