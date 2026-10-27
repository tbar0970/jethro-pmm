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

	/**
	 * The page fragment this request asked for (`?fragment=<name>`), or NULL for a full page.
	 *
	 * A fragment request runs the same permission check and processView() as the full page, then
	 * System_Controller prints only {@see printFragment()}, without main.template.php. processView()
	 * uses this to skip full-page-only behaviour such as redirecting to a single search result.
	 *
	 * @see docs/docs/developer/reference/view-fragments.md
	 */
	public static function requestedFragment(): ?string
	{
		$name = array_get($_GET, 'fragment');
		return is_string($name) && $name !== '' ? $name : NULL;
	}

	/**
	 * Print one named region of this view, for a `?view=…&fragment=<name>` request (typically a
	 * Datastar @get that morphs the region in place by its top-level element id).
	 *
	 * Views that offer fragments override this and print the region's id'd wrapper element, the
	 * same markup printView() uses for that region. The default rejects every name.
	 *
	 * @see docs/docs/developer/reference/view-fragments.md
	 */
	public function printFragment(string $name): void
	{
		http_response_code(404);
		echo 'Unknown fragment';
	}

}//end class