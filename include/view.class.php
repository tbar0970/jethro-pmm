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

	/**
	 * Whether the main nav should be shown when this is the current view.
	 * (Allows some public views to appear as a standalone page)
	 * @return boolean
	 */
	public function shouldShowNavigation()
	{
		return TRUE;
	}

	abstract public function printView();

}//end class
?>
