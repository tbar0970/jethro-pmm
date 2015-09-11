<?php
$personid = Ical_System::get()->getPersonId();

    header('Content-type: text/calendar');
    header('Content-Disposition: inline; filename=services.ics'); ?>
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Jethro/Jethro//NONSGML v1.0//EN
<?php
    $services = Ical_System::get()->getServices();
    foreach ($services as $service)
    {
        $uid = 'service_' . $service->id . '_' . $service->values['date'];
        $congregation = System_Controller::get()->getDBObject('congregation', $service->getValue('congregationid'));
        $starttime = Service::getMeetingDateTime(strtotime($service->getValue('date')), $congregation->getValue('meeting_time'));
        // Guess end date as $date + 1 hour (3600 seconds)
        $endtime = $starttime + 3600;
?>
BEGIN:VEVENT
UID:<?php echo $uid ?>@jethro_service
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
DTSTART;VALUE=DATE:<?php echo gmdate('Ymd\THis\Z', $starttime); ?>

DTEND;VALUE=DATE:<?php echo gmdate('Ymd\THis\Z', $endtime); ?>

SUMMARY:<?php echo $congregation->getValue('name') . " Service";
?>

DESCRIPTION:Note: End time is approximate.
END:VEVENT
<?php
    }
?>
END:VCALENDAR
