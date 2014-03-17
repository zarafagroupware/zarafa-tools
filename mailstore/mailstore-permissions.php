#!/usr/bin/php
<?php
// This script allows you to set the security permissions of folders from the commandline
//
// It only *adds* permissions, and will give an error if there is already a permission for
// the specified user/group.

if(count($argv) != 3) {
    print "Usage: " . $argv[0] . " <user/group> <target>\n";
    print "\n";
    print "user\t\t\tThe user of which the folders should be updated\n";
    print "\n";
    exit(1);
}

// Configure the socket to connect to.
define("SERVER", "file:///var/run/zarafa");

// $argv[1] = user/group to set use for permissions
// $argv[2] = user to set permissions on
$user = $argv[2];
$groupname = $argv[1];

// mapi include files
include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

#define('PR_FREEBUSY_ENTRYIDS',mapi_prop_tag(PT_MV_BINARY, 0x36E4));

// Sets the ACLs for one folder
function SetSecurity($store, $entryid, $acls) {
  if($entryid == "")
    $object = $store;
  else
    $object = mapi_msgstore_openentry($store, $entryid);

  if(!$object) {
    print "Unable to open folder\n";
    return;
  }

  $ret = mapi_zarafa_setpermissionrules($object, $acls);
  if($ret == false)
    print "Unable to set permissions\n";
  else
    print "Permissions successfully set\n";
}

function resolve($session, $name)
{
    $ab = mapi_openaddressbook($session);
    $resolved = mapi_ab_resolvename($ab, array(array(PR_DISPLAY_NAME => $name)), EMS_AB_ADDRESS_LOOKUP);

    $id = false;
    if($resolved) {
        $id = $resolved[0][PR_ENTRYID];
    }
    return $id;
}

// Log on to zarafa as admin user
$session = mapi_logon_zarafa('SYSTEM', '', SERVER);
if(!$session) {
  print "Unable to logon\n";
  return;
}

// Get our stores table
$storetable = mapi_getmsgstorestable($session);
if(!$storetable) {
  print "Unable to get stores list\n";
  return;
}

// Find our default store
$stores = mapi_table_queryallrows($storetable, array(PR_DISPLAY_NAME, PR_ENTRYID, PR_DEFAULT_STORE));

$storenentryid = false; // Set default to false
foreach($stores as $row) {
  if(isset($row[PR_DEFAULT_STORE]) && $row[PR_DEFAULT_STORE]) {
    $storeentryid = $row[PR_ENTRYID];
    //print "Found store " . $row[PR_DISPLAY_NAME] . "\n";
    break;
  }
}

if(!$storeentryid) {
  print "Unable to find default store\n";
  return;
}

// We now have the store entryid for the admin store, so open the store
$store = mapi_openmsgstore($session, $storeentryid);

// Now, open the store of another user
$userstoreentryid = mapi_msgstore_createentryid($store, $user);
if(!$userstoreentryid) {
  print "Unknown user $user\n";
  return;
}

// Open the store here
$userstore = mapi_openmsgstore($session, $userstoreentryid);
if(!$userstore) {
  print "Unable to open store of user $user\n";
  return;
}

// This is the list of entryids for the default folders
$folderentryids = array();

$storeprops = mapi_getprops($userstore, array(PR_IPM_WASTEBASKET_ENTRYID, PR_IPM_SENTMAIL_ENTRYID, PR_IPM_OUTBOX_ENTRYID));
if(isset($storeprops[PR_IPM_WASTEBASKET_ENTRYID]))
  $folderentryids[0] = $storeprops[PR_IPM_WASTEBASKET_ENTRYID];
if(isset($storeprops[PR_IPM_SENTMAIL_ENTRYID]))
  $folderentryids[1] = $storeprops[PR_IPM_SENTMAIL_ENTRYID];
if(isset($storeprops[PR_IPM_OUTBOX_ENTRYID]))
  $folderentryids[2] = $storeprops[PR_IPM_OUTBOX_ENTRYID];

$rootfolder = mapi_msgstore_openentry($userstore);
$rootprops = mapi_getprops($rootfolder, array(PR_FREEBUSY_ENTRYIDS));

if(isset($rootprops[PR_FREEBUSY_ENTRYIDS]))
  $freebusy = $rootprops[PR_FREEBUSY_ENTRYIDS][3];

$inbox = mapi_msgstore_getreceivefolder($userstore);

$inboxprops = mapi_getprops($inbox, array(PR_ENTRYID, PR_IPM_TASK_ENTRYID, PR_IPM_CONTACT_ENTRYID, PR_IPM_NOTE_ENTRYID, PR_IPM_JOURNAL_ENTRYID, PR_IPM_APPOINTMENT_ENTRYID, PR_IPM_DRAFTS_ENTRYID, PR_ADDITIONAL_REN_ENTRYIDS));

if(isset($inboxprops[PR_ENTRYID]))
  $folderentryids[3] = $inboxprops[PR_ENTRYID];
if(isset($inboxprops[PR_IPM_TASK_ENTRYID]))
  $folderentryids[4] = $inboxprops[PR_IPM_TASK_ENTRYID];
if(isset($inboxprops[PR_IPM_CONTACT_ENTRYID]))
  $folderentryids[5] = $inboxprops[PR_IPM_CONTACT_ENTRYID];
if(isset($inboxprops[PR_IPM_NOTE_ENTRYID]))
  $folderentryids[6] = $inboxprops[PR_IPM_NOTE_ENTRYID];
if(isset($inboxprops[PR_IPM_JOURNAL_ENTRYID]))
  $folderentryids[7] = $inboxprops[PR_IPM_JOURNAL_ENTRYID];
if(isset($inboxprops[PR_IPM_APPOINTMENT_ENTRYID]))
  $folderentryids[8] = $inboxprops[PR_IPM_APPOINTMENT_ENTRYID];
if(isset($inboxprops[PR_IPM_DRAFTS_ENTRYID]))
  $folderentryids[9] = $inboxprops[PR_IPM_DRAFTS_ENTRYID];
if(isset($inboxprops[PR_ADDITIONAL_REN_ENTRYIDS]))
  $folderentryids[10] = $inboxprops[PR_ADDITIONAL_REN_ENTRYIDS][4];

// Resolve user from GAB
$id = resolve($session, $groupname);

// Permission schemes
//
// State 1 is 'new', so it will only be added if there is no pre-existing rule.
//
//
// Folder hidden 
$perm_folderinvisible = array("userid" => $id, "type" => 2, "rights" => 0, "state" => 1);
// Folder visible
$perm_foldervisible = array("userid" => $id, "type" => 2, "rights" => 1024, "state" => 1);
// Read items, folder visible
$perm_read = array("userid" => $id, "type" => 2, "rights" => 1025, "state" => 1);
// Full Control
$perm_fullcontrol = array("userid" => $id, "type" => 2, "rights" => 1275, "state" => 1);
// Owner
$perm_owner = array("userid" => $id, "type" => 2, "rights" => 1531, "state" => 1);
// Secretary
$perm_secretary = array("userid" => $id, "type" => 2, "rights" => 1147, "state" => 1);
// Folder visible, read items, edit and delete own items
$perm_alldelown = array("userid" => $id, "type" => 2, "rights" => 1049, "state" => 1);
// Folder visible, read items, edit and delete all items
$perm_alldelall = array("userid" => $id, "type" => 2, "rights" => 1145, "state" => 1);

/* Folders:
 *
 * 0 - Deleted Items
 * 1 - Sent Items
 * 2 - Outbox
 * 3 - Inbox
 * 4 - Tasks
 * 5 - Contacts
 * 6 - Notes
 * 7 - Journal
 * 8 - Calendar
 * 9 - Drafts
 * 10 - Junk mail folder
 */

// Set 'folder visible' on root folder, causes store to be openable 'permanently'
SetSecurity($userstore, "", array($perm_foldervisible));

// Set freebusy (internal) folder on readable by everyone. Make sure this folder always
// has the same security settings as your Calendar. If you don't you will get errors
// stating that the 'free busy information could not be updated' in Outlook.
// SetSecurity($userstore, $freebusy, array($perm_read));

// Examples:
//
// SetSecurity consists of the following 3 parameters:
// - $userstore, no need to change this.
// - $folderentryids[x] this is the folder as shown below
// - permission variable, as shown under permission schemes
//
// Deleted Items
//SetSecurity($userstore, $folderentryids[0], array($perm_folderinvisible));
//
// Sent Items
//SetSecurity($userstore, $folderentryids[1], array($perm_folderinvisible));
//
// Outbox
//SetSecurity($userstore, $folderentryids[2], array($perm_folderinvisible));
//
// Inbox
//SetSecurity($userstore, $folderentryids[3], array($perm_folderinvisible));
//
// Tasks
//SetSecurity($userstore, $folderentryids[4], array($perm_folderinvisible));
//
// Contacts
//SetSecurity($userstore, $folderentryids[5], array($perm_folderinvisible));
//
// Notes
//SetSecurity($userstore, $folderentryids[6], array($perm_folderinvisible));
//
// Journal
//SetSecurity($userstore, $folderentryids[7], array($perm_folderinvisible));
//
// Calendar
//SetSecurity($userstore, $folderentryids[9], array($perm_folderinvisible));
//
// Drafts
//SetSecurity($userstore, $folderentryids[10], array($perm_folderinvisible));

?>
