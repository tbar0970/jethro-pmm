<?php

/*****************************************************************
This script will sync a specified report in Jethro
to a specified list within an account at mailchimp.com,
so that the members of the mailchimp list are the same
as the persons returned by the Jethro report.  It requires
merge vars in the Mailchimp list tagged as CONG, STATUS,
GENDER and AGEBRACKET and it populates/updates them accordingly.
******************************************************************/
if ((php_sapi_name() !== 'cli') && !defined('STDIN')) {
	echo "This script must be run from the command line";
	exit;
}
$fromDB = FALSE;
$api_key = NULL;
$syncs = Array();
if (count($_SERVER['argv']) == 3) {
        list($scriptname, $report_id, $api_key) = $_SERVER['argv'];
        $syncs = Array($report_id => 0);
} else if (count($_SERVER['argv']) == 4) {
        list($scriptname, $report_id, $api_key, $list_id) = $_SERVER['argv'];
        $syncs = Array($report_id => $list_id);
} else if (count($_SERVER['argv']) == 1) {
        $fromDB = TRUE;
} else {
        echo "Usage: php mailchimp_sync.php [REPORT_ID] [API_KEY] [LIST_ID] \n";
        echo "  (Run with no arguments to use api key and list ids from Jethro database)\n";
        exit(1);
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

$DEBUG = 0; // 0 for errors only. 1 for basics. 2 for verbose.
$DRYRUN = 0;
$VERIFY_SSL = TRUE; // This must be true for prod systems. Can be turned off for dev.

define('JETHRO_ROOT', dirname(dirname(__FILE__)));
set_include_path(get_include_path().PATH_SEPARATOR.JETHRO_ROOT);
if (!is_readable(JETHRO_ROOT.'/conf.php')) {
        trigger_error('Jethro configuration file not found.  You need to copy conf.php.sample to conf.php and edit it before Jethro can run', E_USER_ERROR);
        exit();
}

require_once JETHRO_ROOT.'/conf.php';
define('DB_MODE', 'private');
require_once JETHRO_ROOT.'/include/init.php';

require_once JETHRO_ROOT.'/include/user_system.class.php';
$GLOBALS['user_system'] = new User_System();
$GLOBALS['user_system']->setCLIScript();

require_once JETHRO_ROOT.'/include/system_controller.class.php';
$GLOBALS['system'] = System_Controller::get();

if ($fromDB) {
        $reports = $GLOBALS['system']->getDBObjectData('person_query', Array('!mailchimp_list_id' => ''));
        foreach ($reports as $id => $r) {
                $syncs[$id] = $r['mailchimp_list_id'];
        }
        if ($DEBUG > 0 && empty($syncs)) {
                echo "Found no saved reports with list IDs set. Quitting. \n";
        }
        if (empty($syncs)) exit;
        $api_key = ifdef('MAILCHIMP_API_KEY', '');
        if (!strlen($api_key)) {
                trigger_error("API KEY not set in Jethro config", E_USER_ERROR);
        }}

if (empty($api_key)) {
        trigger_error("API key not specified" , E_USER_ERROR);
}

require_once 'vendor/autoload.php';
use \DrewM\MailChimp\MailChimp;
$mc = new MailChimp($api_key);
$mc->verify_ssl = $VERIFY_SSL;

if ($DEBUG > 1) {
        bam("SYNC JOBS:");
        bam($syncs);
}
foreach ($syncs as $report_id => $list_id) {
        run_mc_sync($mc, $report_id, $list_id);
}

function run_mc_sync($mc, $report_id, $list_id)
{
        global $DEBUG;
        global $DRYRUN;
        // Check for / look for a list ID
        if (empty($list_id)) {
                $lists = $mc->get('lists');
                if (($lists == FALSE) || !$mc->success()) {
                        trigger_error("[Syncing report $report_id]: Mailchimp API Error calling lists(): ".$mc->getLastError(), E_USER_ERROR);
                }
                switch (count($lists['lists'])) {
                        case 1:
                                $list_id = $lists['lists'][0]['id'];
                                break;
                        case 0:
                                trigger_error("[Syncing report $report_id] ]No lists found in mailchimp account - create one in mailchimp first", E_USER_ERROR);
                                break;
                        default:
                                echo "Several lists were found in your mailchimp account. \nPlease specify one of the following list IDs as the final command line argument: \n";
                                foreach ($lists['lists'] as $l) {
                                        echo "   ".$l['id'].' : '.$l['name']."\n";
                                }
                                exit;
                }
        }


        // Check that the list has the necessary merge vars
        $vars = array();
        $vars_res = $mc->get('lists/'.$list_id.'/merge-fields');
        if (!$vars_res || !$mc->success()) {
                trigger_error("[Syncing report $report_id to list $list_id] Mailchimp API Error calling lists/merge-fields: ".$mc->getLastError(), E_USER_ERROR);
        }

        foreach (array_get($vars_res, 'merge_fields', Array()) as $var_details) {
                $vars[] = $var_details['tag'];
        }
        $missing_vars = array_diff(Array('STATUS', 'CONG', 'AGEBRACKET', 'GENDER'), $vars);
        if (!empty($missing_vars)) {
                trigger_error("[Syncing report $report_id to list $list_id] Your mailchimp list is missing the merge vars ".implode(', ', $missing_vars).'.  Set these up in Mailchimp then try again.', E_USER_ERROR);
        }

        // Check that we have a report
        if (!(int)$report_id) {
                trigger_error("No Report ID found - correct your config within ".__FILE__, E_USER_ERROR);
        }
        $report = $GLOBALS['system']->getDBObject('person_query', (int)$report_id);
        if (empty($report)) {
                trigger_error("[Syncing report $report_id to list $list_id] Could not find report #$report_id - please check your config in ".__FILE__, E_USER_ERROR);
        }


        // BUSINESS TIME

        // Get the relevant person data from Jethro
        $db =& $GLOBALS['db'];
        $sql = $report->getSQL('TRIM(LOWER(p.email)) as loweremail, p.email, p.first_name, p.last_name, p.gender, p.age_bracketid, p.status, p.congregationid');
        $rres = $db->queryAll($sql, null, null, true);
        unset($rres['']); // with no email.
        $report_members = Array();
        foreach ($rres as $loweremail => $persondata) {
                $report_members[$loweremail] = getMergeVars($persondata);
        }
        if ($DEBUG > 1) {
                bam("========================");
                bam("PERSONS FROM JETHRO REPORT (excl email-less persons):");
                bam(count($report_members));
                bam($report_members);
                bam("========================");
        }

        // Get the existing members of the mailchimp list
        $members_res = $mc->get('lists/'.$list_id.'/members', Array('count' => 10000), 60);
        if ((FALSE === $members_res) || !$mc->success()) {
                trigger_error("Mailchimp API Error fetching list members: ".$mc->getLastError(), E_USER_ERROR);
        }
        $mc_members = Array();
        foreach ($members_res['members'] as $m) {
                $mc_members[strtolower($m['email_address'])] = $m['merge_fields'];
                $mc_members[strtolower($m['email_address'])]['MC_STATUS'] = $m['status'];
                $mc_members[strtolower($m['email_address'])]['EMAIL'] = $m['email_address'];
        }
        if ($DEBUG > 1) {
                bam("========================");
                bam("CURRENT MAILCHIMP LIST MEMBERS:");
                bam(count($mc_members));
                bam($mc_members);
                bam("========================");
        }

        // For each report member, see if they are on the mc list.
        //    If found, update details if needed
        //    If not found, add to list
        $batch = $mc->new_batch();
        foreach ($report_members as $loweremail => $persondata) {
                if (empty($mc_members[$loweremail])) {
                        // Add them to the list
                        $postdata = Array(
                                'status_if_new' => 'subscribed',
                                                                'status' => 'subscribed',
                                'email_address' => $persondata['EMAIL'],
                                'merge_fields' => $persondata,
                        );
                        unset($postdata['merge_fields']['EMAIL']);
                        if ($DEBUG > 0) {
                                bam("ADDING:");
                                bam($postdata);
                        }
                                                // We have to use PUT, because this email address may already exist in another list
                        $batch->put('add-'.$loweremail, 'lists/'.$list_id.'/members/'.md5($loweremail), $postdata);
                } else {
                        // See if an update is needed
                        if (needsUpdate($persondata, $mc_members[$loweremail])) {
                                if ($DEBUG > 0) {
                                        bam("UPDATE NEEDED: JETHRO HAS ");
                                        bam($persondata);
                                        bam("MAILCHIMP HAS ");
                                        bam($mc_members[$loweremail]);
                                }
                                $putdata = Array(
                                        'merge_fields' => $persondata,
                                        'email_address' => $persondata['EMAIL']
                                );
                                unset($putdata['merge_fields']['EMAIL']);
                                $batch->put('update-'.$loweremail, 'lists/'.$list_id.'/members/'.md5($loweremail), $putdata);
                        }
                }
        }

        // For each MC member, see if they are in the report.  If not, remove them from the MC list.
        foreach ($mc_members as $loweremail => $details) {
                // We only delete them from the list if they are still subscribed.
                // If they have unsubscribed, we leave them there to make sure
                // they always stay unsubscribed.
                if (!isset($report_members[$loweremail]) && $details['MC_STATUS'] == 'subscribed') {
                        if ($DEBUG > 0) {
                                bam("REMOVING $loweremail");
                        }
                        $batch->delete('delete-'.$loweremail, 'lists/'.$list_id.'/members/'.md5($loweremail));
                }
        }

        if ($batch->get_operations()) {

                if ($DRYRUN) {
                        bam("OPERATIONS THAT WOULD BE PERFORMED:");
                        bam($batch->get_operations());
                        exit;
                }

                if ($DEBUG > 0) bam("SUBMITTING BATCH TO MAILCHIMP API...");

                $result = $batch->execute();
                $started = time();
                $batch_res_summary = NULL;
                while (true) {
                        $batch_res_summary = $batch->check_status();
                        if (($batch_res_summary['status'] == 'finished')
                                        && !empty($batch_res_summary['response_body_url'])) {
                                break;
                        }
                        if (time() - $started > 60*5) {
                                $batch_id = $batch_res_summary['id'];
                                trigger_error("[Syncing report $report_id to list $list_id] Batch $batch_id has been running for more than 5 minutes. Giving up.", E_USER_ERROR);
                                exit;
                        }
                        if ($DEBUG > 1) bam($batch_res_summary);
                        sleep(4);
                }

                if (empty($batch_res_summary)) {
                        trigger_error("[Syncing report $report_id to list $list_id] Failed to get batch status", E_USER_ERROR);
                } else if ($batch_res_summary['status'] != 'finished') {
                        trigger_error("[Syncing report $report_id to list $list_id] Batch did not finish", E_USER_ERROR);
                } else {
                                                $all_failures = Array();
                                                if (($DEBUG > 1) || ($batch_res_summary['errored_operations'] > 0)) {
                                                        // Fetch the results from mailchimp and unpack them
                                                        $fp = fopen($batch_res_summary['response_body_url'], 'r');
                                                        $batch_results = stream_get_contents($fp);
                                                        fclose($fp);
                                                        $dir = sys_get_temp_dir().'/jethro-mc-'.time();
                                                        $tgzfile = $dir.'/response.tgz';
                                                        @mkdir($dir);
                                                        file_put_contents($tgzfile, $batch_results);
                                                        extract_tgz_to_dir($tgzfile, $dir);
                                                        foreach (glob($dir.'/*.json') as $jsonfile) {
                                                                $resps = json_decode(file_get_contents($jsonfile));
                                                                foreach ($resps as $resp) {
                                                                        $resp->response = json_decode($resp->response);
                                                                        // Special handling for 'member in compliance state' unavoidable error
                                                                        // See https://github.com/tbar0970/jethro-pmm/issues/656
                                                                        if (is_object($resp->response) && ($resp->response->status == 400) && ($resp->response->title == 'Member In Compliance State' || $resp->response->title == 'Forgotten Email Not Subscribed')) {
                                                                                $pemail = substr($resp->operation_id, 4); // chop of 'add-'
                                                                                $bpersons = $GLOBALS['system']->getDBObjectData('person', Array('email' => $pemail));
                                                                                foreach ($bpersons as $pid => $pdetail) {
                                                                                        $n = new Person_Note();
                                                                                        $n->setValue('personid', $pid);
                                                                                        $n->setValue('subject', 'Mailchimp sync error');
                                                                                        $n->setValue('details', '[SYSTEM NOTE] Email address '.$pemail.' could not be synced to Mailchimp. Mailchimp said: '.$resp->response->detail);
                                                                                        $n->setValue('creator', $pid);
                                                                                        $n->createIfNew();
                                                                                }
                                                                        } else if ($resp->status_code != 200) {
                                                                                // Unknown error - report it to the console
                                                                                $all_failures[] = $resp;
                                                                        }
                                                                }
                                                        }
                                                        rmdir_recursive($dir);
                                                }
                                                if ($all_failures) {
                                                        trigger_error("[Syncing report $report_id to list $list_id] ".$batch_res_summary['errored_operations']." mailchimp operations failed.");
                                                        bam($all_failures);
                                                } else if ($DEBUG > 0) {
                                                        bam($batch_res_summary['total_operations'].' mailchimp operations completed succesfully.');
                                                }
                }
        } else {
                if ($DEBUG > 0) {
                        echo "[Syncing report $report_id to list $list_id] Nothing to do. Seeya. \n";
                }
        }
}

//// HELPER FUNCTION ////

function getMergeVars($person_data, $email=NULL) {
        static $dummy_person = null;
        if (is_null($dummy_person)) {
                $GLOBALS['system']->includeDBClass('person');
                $dummy_person = new Person();
        }
        $dummy_person->populate(0, $person_data);
        $res = Array(
                'EMAIL' => $dummy_person->getFormattedValue('email'),
                'FNAME' => $dummy_person->getFormattedValue('first_name'),
                'LNAME' => $dummy_person->getFormattedValue('last_name'),
                'GENDER' => $dummy_person->getFormattedValue('gender'),
                'CONG' => $dummy_person->getFormattedValue('congregationid'),
                'STATUS' => $dummy_person->getFormattedValue('status'),
                'AGEBRACKET' => $dummy_person->getFormattedValue('age_bracketid'),
        );
        if ($email) $res['EMAIL'] = $email;
        return $res;
}

# MailChimp returns errors in a tgz file containing a spurious '.' entry, which breaks the PHP extracTo() function.
# This is workaround. See https://github.com/tbar0970/jethro-pmm/issues/870
function extract_tgz_to_dir($tgzfile, $dir) {
        $p = new PharData($tgzfile, RecursiveDirectoryIterator::SKIP_DOTS);
        $p->convertToData(Phar::ZIP);
        $createdZipArchive = str_replace('tgz', 'zip', $tgzfile);
        $zip = new ZipArchive;
        $res = $zip->open($createdZipArchive);
        if ($res == TRUE) {
                echo "Extracting Mailchimp tgz response ".$tgzfile." to " . $dir . "/" . $createdZipArchive . PHP_EOL;
                $zip->extractTo($dir);
                echo "Successfully extracted to " . $dir . PHP_EOL;
        } else {
                trigger_error("Could not extract Mailchimp response");
        }

        $zip->close();
}

function rmdir_recursive($dir) {
        $it = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
        $it = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $file) {
                if ($file->isDir()) rmdir($file->getPathname());
                else unlink($file->getPathname());
        }
        rmdir($dir);
}

function needsUpdate($jethroData, $mcData)
{
        foreach ($jethroData as $k => $v) {
                if ($k == 'EMAIL') continue;
                if (trim($mcData[$k]) != trim($v)) return TRUE;
        }
        return FALSE;
}
