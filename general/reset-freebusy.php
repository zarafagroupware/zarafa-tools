<PRE>
<?

/*
 * Updates all users' free/busy information
 */

include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

include('/usr/share/php/mapi/class.recurrence.php');
include('/usr/share/php/mapi/class.freebusypublish.php');

// Update F/B for user specified by $entryid
function UpdateFB($ab, $session, $rootstore, $entryid)
{
  $abentry = mapi_ab_openentry($ab, $entryid);
  if(!$abentry) { print "Unable to open entry in addressbook\n"; return false; }
  
  $abprops = mapi_getprops($abentry, array(PR_ACCOUNT));
  
  $storeid = mapi_msgstore_createentryid($rootstore, $abprops[PR_ACCOUNT]);
  if(!$storeid) { print "Unable to get store entryid\n"; return false; }
  
  $store = mapi_openmsgstore($session, $storeid);
  if(!$store) { print "Unable to open store\n"; return false; }
  
  $root = mapi_msgstore_openentry($store);
  if(!$root) { print "Unable to open root folder\n"; return false; }
  
  $rootprops = mapi_getprops($root, array(PR_IPM_APPOINTMENT_ENTRYID));
  
  $calendar = mapi_msgstore_openentry($store, $rootprops[PR_IPM_APPOINTMENT_ENTRYID]);

  $fbupdate = new FreeBusyPublish($session, $store, $calendar, $entryid);
  
  $fbupdate->PublishFB(time() - (7 * 24 * 60 * 60), 6 * 30 * 24 * 60 * 60); // publish from one week ago, 6 months ahead

  return true;  
}

$session = mapi_logon_zarafa("SYSTEM", "", "file:///var/run/zarafa");
if(!$session) { print "Unable to open session\n"; exit(1); }

$msgstorestable = mapi_getmsgstorestable($session);
if(!$msgstorestable) { print "Unable to open message stores table\n"; exit(1); }

$msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID));

foreach ($msgstores as $row) {
    if($row[PR_DEFAULT_STORE]) {
        $storeentryid = $row[PR_ENTRYID];
    }
}

if(!$storeentryid) { print "Can't find default store\n"; exit(1); }

$store = mapi_openmsgstore($session, $storeentryid);
if(!$store) { print "Unable to open default store\n"; exit(1); }

$ab = mapi_openaddressbook($session);
if(!$ab) { print "Unable to open addressbook\n"; exit(1); }

$gabid = mapi_ab_getdefaultdir($ab);
if(!$gabid) { print "Unable to get default dir\n"; exit(1); }

$gab = mapi_ab_openentry($ab, $gabid);
if(!$gab) { print "Unable to open GAB $gabid\n"; exit(1); }

$table = mapi_folder_getcontentstable($gab);
if(!$table) { print "Unable to get GAB table\n"; exit(1); }

$rows = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_DISPLAY_NAME, PR_OBJECT_TYPE));

foreach($rows as $row) {
  if($row[PR_OBJECT_TYPE] == MAPI_MAILUSER) {
    print "Processing user " . $row[PR_DISPLAY_NAME] . "\n";
    
    if(UpdateFB($ab, $session, $store, $row[PR_ENTRYID]) == false) {
      print "Unable to update F/B for user " . $row[PR_DISPLAY_NAME]. "\n";
    }
  }
}


?>