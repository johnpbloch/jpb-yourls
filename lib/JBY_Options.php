<?php

class JBY_Options extends JPB_Options
{

	public $shortlinkAPI = 'yourls';
	public $shortlinkURI = '';

	protected function _install()
	{
		$this->update();
	}

	protected function _upgrade( array $option )
	{
		
	}

}
