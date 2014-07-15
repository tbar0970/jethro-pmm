<?php
abstract class View
{
	public static function getMenuPermissionLevel()
	{
		return 0;
	}
	
	public static function getMenuRequiredFeature()
	{
		return NULL;
	}

	public function processView()
	{
	}
	
	public function getTitle()
	{
		return '';
	}

	abstract public function printView();

}//end class
?>
