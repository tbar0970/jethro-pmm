<?php
class View_Check_In extends View
{

	private $checkinObj = NULL;
	private $venue = NULL;

	function processView()
	{
		if (!empty($_POST['name'])) {
			if (!empty($_REQUEST['remember'])) {
				$expires = strtotime('+6 months');
				setcookie('checkin_name', $_REQUEST['name'], $expires);
				setcookie('checkin_tel', $_REQUEST['tel'], $expires);
				setcookie('checkin_email', $_REQUEST['email'], $expires);
			} else if (!empty($_COOKIE['checkin_name'])) {
				// clear previously saved details
				setcookie('checkin_name', NULL);
				setcookie('checkin_tel', NULL);
				setcookie('checkin_email', NULL);
			}

			$this->checkinObj = new Checkin();
			$this->checkinObj->processForm();
			$this->checkinObj->create();
		}

		if (!empty($_REQUEST['venueid'])) {
			$this->venue = new Venue((int)$_REQUEST['venueid']);
		}
	}

	function shouldShowNavigation()
	{
		return FALSE;
	}

	function getTitle()
	{
		if ($this->venue && $this->venue->id && !$this->venue->getValue('is_archived')) return 'Check in to '.$this->venue->getValue('name');
	}

	function printView()
	{
		if (empty($_REQUEST['venueid'])) {
			// todo: print venue list ?
			print_message("Please look for a QR code at the venue, or ask for a direct link to check in", 'error');
			return;
		}
		if ((!$this->venue->id) || $this->venue->getValue('is_archived')) {
			print_message("Invalid venue, please get a new link", 'error');
			return;
		}
		if (!empty($this->checkinObj)) {
			if ($this->checkinObj->id) {
				print_message('<table border="0"><tr><td><i class="icon-ok"></i>&nbsp;</td><td>'.$this->venue->getValue('thanks_message').'</table>', 'success', true);
				?>
				<p class="center"><a href="<?php echo build_url(Array()); ?>">Return to check-in page</a></p>
				<?php
				return;
			} else {
				// There was some error which should have been displayed already.
			}
		}
		?>
		<p><i>Please supply either email or phone number</i></p>
		<form method="post" class="form">
		<input name="venueid" type="hidden" value="<?php echo (int)$_REQUEST['venueid']; ?>" />
		<div class="control-group">
			<label class="control-label" >Name</label>
			<div class="controls">
				<?php print_widget('name', Array('type' => 'text', 'allow_empty' => false), array_get($_COOKIE, 'checkin_name', '')); ?>
			</div>
		</div>

		<div class="control-group">
			<label class="control-label" >Phone</label>
			<div class="controls">
				<?php
				$formats = ifdef('MOBILE_TEL_FORMATS', '')."\n".ifdef('WORK_TEL_FORMATS', '');
				print_widget('tel', Array('type' => 'phone', 'formats' => $formats), array_get($_COOKIE, 'checkin_tel', '')); ?>
			</div>
		</div>

		<div class="control-group">
			<label class="control-label" >Email</label>
			<div class="controls">
				<?php print_widget('email', Array('type' => 'email'), array_get($_COOKIE, 'checkin_email', '')); ?>
			</div>
		</div>

		<div class="control-group">
			<label class="control-label" >How many people are you checking in today?</label>
			<div class="controls">
				<div class="input-append">
					<?php print_widget('pax', Array('type' => 'int', 'allow_empty' => FALSE, 'attrs' => Array('min' => 1, 'style' => 'width: 4em !important'), 'width' => 2), 1); ?>
					<span class="add-on"> including me</span>
				</div>
			</div>
		</div>

		<div class="control-group">
			<label class="control-label" ></label>
			<div class="controls">
				<label class="checkbox">
				<?php print_widget('remember', Array('type' => 'checkbox'), !empty($_COOKIE['checkin_name'])); ?> Remember me on this device
				</label>
			</div>
		</div>

		<div class="control-group">
			<label class="control-label" ></label>
			<div class="controls">
				<input type="submit" class="btn" />
			</div>
		</div>




		</form>
		<?php


	}

}
