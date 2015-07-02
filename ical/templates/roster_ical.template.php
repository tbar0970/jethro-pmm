<?php
$personid = $GLOBALS['roster_personid'];

    header('Content-type: text/calendar');
    header('Content-Disposition: inline; filename=roster.ics'); ?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Jethro/Jethro//NONSGML v1.0//EN
<?php
    $rallocs = $GLOBALS['roster_assignments'];
    foreach ($rallocs as $date => $allocs) { 
        foreach ($allocs as $alloc) {
            $uid = $personid . '_' . $alloc['id'] . "_" . date('Ymd', strtotime($date));
            // Check if the meeting time looks reasonable
            $starttime = $alloc['meeting_time'];
            // Guess end date as $date + 1 day (86400 seconds)
            $enddate = strtotime($date) + 86400;
            ?>
BEGIN:VEVENT
UID:<?php echo $uid ?>@jethro
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
?>
DTSTART;VALUE=DATE:<?php echo date('Ymd', strtotime($date)); ?>

DTEND;VALUE=DATE:<?php echo date('Ymd', $enddate); ?>

SUMMARY:Roster Assignments
DESCRIPTION:<?php
            echo $alloc['cong'].' '.$alloc['title'];
?>

END:VEVENT
<?php
        }
    }
?>
END:VCALENDAR
