<?php
/** @var $rallocs
 * @var $Personid
 */
?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Jethro/Jethro//NONSGML v1.0//EN
<?php
    foreach ($assignments as $date => $allocs) {
        foreach ($allocs as $alloc) {
            $uid = $personid . '_' . $alloc['id'] . "_" . date('Ymd', strtotime($date));
            $starttime = Service::getMeetingDateTime(strtotime($date), $alloc['meeting_time']);
            $endtime = $starttime;
            $timeSpecified = 0;
            // Check if the meeting time looks reasonable
            if ($alloc['meeting_time'] != NULL && preg_match('/^\\d\\d\\d\\d$/', $alloc['meeting_time'])) {
                // Guess end time as start time + 1 hour (3600 seconds)
                $endtime += 3600;
                $timeSpecified = 1;
            }
            else {
                // Guess end time as start time + 1 day (86400 seconds)
                $endtime += 86400;                
            }
            ?>
BEGIN:VEVENT
UID:<?php echo $uid ?>@jethro_roster
<?php
            if (defined('MEMBER_REGO_EMAIL_FROM_NAME') && strlen(MEMBER_REGO_EMAIL_FROM_NAME)) {
                $fromName = MEMBER_REGO_EMAIL_FROM_NAME;
            }
            else {
                $fromName = 'Jethro Admin';
            }
            if (defined('MEMBER_REGO_EMAIL_FROM_ADDRESS') && strlen(MEMBER_REGO_EMAIL_FROM_ADDRESS)) { ?>
ORGANIZER;CN=<?php echo $fromName; ?>:MAILTO:<?php echo MEMBER_REGO_EMAIL_FROM_ADDRESS; ?>

<?php
            }
            if ($timeSpecified) {
                // Use GMT, with time
?>
DTSTART;VALUE=DATE:<?php echo gmdate('Ymd\THis\Z', $starttime); ?>

DTEND;VALUE=DATE:<?php echo gmdate('Ymd\THis\Z', $endtime); ?>
                
<?php        } else {
                // Use local time, with date only
?>
DTSTART;VALUE=DATE:<?php echo date('Ymd', $starttime); ?>

DTEND;VALUE=DATE:<?php echo date('Ymd', $endtime); ?>
                
<?php        }
?>
SUMMARY:<?php echo $alloc['title']; ?>

DESCRIPTION:Roster assignment: <?php
            if ($alloc['cong'] )
            echo $alloc['title'] . ', ' . $alloc['cong'] . ', ' . SYSTEM_NAME;
?>

 <?php echo 'Role description: ' . BASE_URL . '/public/?view=display_role_description&role=' . $alloc['id'] . ' ';
?>

 Note that start/end time is approximate.
END:VEVENT
<?php
        }
    }
?>
END:VCALENDAR
