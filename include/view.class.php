<?php
abstract class View
{
	public static function getMenuPermissionLevel()
	{
		return 0;
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
