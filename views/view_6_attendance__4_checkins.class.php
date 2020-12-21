<?php
class View_Attendance__Checkins extends View
{

	static function getMenuPermissionLevel()
	{
		return PERM_VIEWATTENDANCE;
	}

	function getTitle()
	{
		return _('Manage Venue Check-ins');
	}

	function processView()
	{


	}

	function printView()
	{
		?>
		<p class="text alert alert-info">
			After you add a <i>check-in venue</i> below, people can check in to that venue using the public URL or QR code supplied. A check-in is a stand-alone record which may not correspond to any person record in Jethro.  However, if you enable the "set attendance" option for a venue, Jethro will record congregational attendance for any persons who match the phone number or email address used to check in.
		</p>

		<?php
		if (!PUBLIC_AREA_ENABLED) {
			print_message("You must enable the public area (via Admin > System Configuration) for checkins to work", 'error');
		}
		?>

		<table class="table table-bordered">
		<?php
		$venues = $GLOBALS['system']->getDBObjectData('venue');
		$venue = new Venue();
		foreach ($venues as $venueID => $vData) {
			$venue->populate($venueID, $vData);
			$class = $venue->getValue('is_archived') ? 'class="archived"' : '';
			$publicURL = BASE_URL.'public/?view=check_in&venueid='.$venueID;
			// TODO: make this a setting
			$QRURL = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data='.urlencode($publicURL);
			?>
			<tr <?php echo $class; ?>>
				<td>
					<?php
					$venue->printFieldValue('name');
					if ($venue->getValue('is_archived')) echo ' [Archived]';
					?>
				</td>
				<td class="narrow"><a href="?view=_edit_venue&venueid=<?php echo $venueID; ?>">Edit details</a></td>
				<td class="narrow"><a href="<?php echo $publicURL; ?>" target="checkin">Go to check-in page</a></td>
				<td class="narrow"><a href="<?php echo $QRURL; ?>" class="med-popup">Get QR code</a></td>
				<td class="narrow"><a href="?view=_export_checkins&venueid=<?php echo $venueID; ?>">Export check-ins</a></td>
			</tr>
			<?php
		}
		?>
		</table>
		<p><a href="?view=_add_venue"><i class="icon-plus-sign"></i>Add venue</a></p>
		<?php
	}
}