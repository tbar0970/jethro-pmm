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

	/**
	 * Get the title that should be shown in the <head><title>
	 * @return string
	 */
	public function getTitle()
	{
		return '';
	}

	/**
	 * Get the heading that should be shown as an H1 at the top of the page
	 * Defaults to getTitle().
	 * @return strong
	 */
	public function getPageHeading()
	{
		return $this->getTitle();
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