<?php
require_once 'abstract_view_add_object.class.php';
class View__Add_Venue extends Abstract_View_Add_Object
{
	var $_create_type = 'venue';
	var $_success_message = 'New venue saved';
	var $_on_success_view = 'attendance__checkins';
	var $_failure_message = 'Error creating venue';
	var $_submit_label = 'Save';
	var $_title = 'Add Venue';

	static function getMenuPermissionLevel()
	{
		return PERM_SYSADMIN;
	}

	function XprocessView() {
		if (!empty($_REQUEST['create_another'])) {
			$this->_on_success_view = $_REQUEST['view'];
		}
		parent::processView();
	}

	protected function X_doSuccessRedirect()
	{
		redirect($this->_on_success_view, Array(), 'cat'.array_get($_REQUEST, 'categoryid'));
	}

	public function XprintView()
	{
		if ((!$this->_new_object->id) && !empty($_REQUEST['categoryid'])) {
			$cat = $GLOBALS['system']->getDBObject('service_component_category', (int)$_REQUEST['categoryid']);
			if ($cat) {
				$this->_new_object->setValue('categoryid', array_get($_REQUEST, 'categoryid'));
				foreach (Array('length_mins', 'show_in_handout', 'show_on_slide') as $k) {
					$this->_new_object->setValue($k, $cat->getValue($k.'_default'));
				}
			}
		}

		?>
		<form method="post" class="form-horizontal" id="add-<?php echo $this->_create_type; ?>">
			<input type="hidden" name="new_<?php echo $this->_create_type; ?>_submitted" value="1" />
			<?php
			$this->_new_object->printForm();
			?>
			<hr />
			<div class="controls">
				<input class="btn" type="submit" value="Save" />
				<input class="btn" name="create_another" type="submit" value="Save and add another" />
				<a href="<?php echo build_url(Array('view' => 'services__service_components')); ?>" class="btn">Cancel</a>
			</div>
		</form>
		<?php
	}

}