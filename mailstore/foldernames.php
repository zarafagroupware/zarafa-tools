#!/usr/bin/php
<?PHP

$LOCALE_PATH = '/usr/share/locale';

function isUnicodeStore($store) {
	$supportmask = mapi_getprops($store, array(PR_STORE_SUPPORT_MASK));
	if (isset($supportmask[PR_STORE_SUPPORT_MASK]) && ($supportmask[PR_STORE_SUPPORT_MASK] & STORE_UNICODE_OK)) {
		print("Store supports properties containing Unicode characters.\n");
		define('STORE_SUPPORTS_UNICODE', true);
	} else {
		print("Store does not support properties containing Unicode characters.\n");
		define('STORE_SUPPORTS_UNICODE', false);
	}
}

function renamefolder($store, $entryid, $name)
{
	if(!$entryid) {
		print("Unable to find $name folder\n");
		return;
	}

	$folder = mapi_msgstore_openentry($store, $entryid);
	if(!$folder) {
		print("Unable to open folder " . bin2hex($entryid) . "\n");
		return;
	}
	mapi_setprops($folder, array(PR_DISPLAY_NAME => $name));

	if(mapi_last_hresult() != 0)
		print("Unable to rename " . bin2hex($entryid) . " to '$name'\n");
	else
		print("Renamed " . bin2hex($entryid) . " to '$name'\n");
}

include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

function translate($lang, $test=0)
{
	global $LOCALE_PATH;
	putenv("LANGUAGE=$lang");
	bindtextdomain("zarafa", "$LOCALE_PATH");
	if (STORE_SUPPORTS_UNICODE == false) {
		bind_textdomain_codeset('zarafa', "windows-1252");
	} else {
		bind_textdomain_codeset('zarafa', "utf-8");
	}
	textdomain('zarafa');
	setlocale(LC_ALL,$lang);
	$trans_array["Sent Items"] = _("Sent Items");
	$trans_array["Outbox"] = _("Outbox");
	$trans_array["Deleted Items"] = _("Deleted Items");
	$trans_array["Inbox"] =  _("Inbox");
	$trans_array["Calendar"] = _("Calendar");
	$trans_array["Contacts"] = _("Contacts");
	$trans_array["Drafts"] = _("Drafts");
	$trans_array["Journal"] = _("Journal");
	$trans_array["Notes"] = _("Notes");
	$trans_array["Tasks"] = _("Tasks");
	$trans_array["Junk E-mail"] = _("Junk E-mail");
	return $trans_array;
}

if(count($argv) != 3) {
	
	print("Usage: foldernames <useraccount> <language>\n");
	print("\n");
	print("To do a test translation, use:  -t <language>\n");
	print("\n");
	print("A <language> could be: nl_NL.UTF-8\n");
	print("\n");
	exit(2);
}

if($argv[1] == "-t") {
	define('STORE_SUPPORTS_UNICODE', true);
	$trans_array=translate($argv[2], 1);
	foreach ($trans_array as $key => $value) {
		echo str_pad($key, 20, " ");
		echo "$value\n";
	}
	exit(0);
}

$session = mapi_logon_zarafa("SYSTEM", "", "file:///var/run/zarafa");
$msgstorestable = mapi_getmsgstorestable($session);
$msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID));

foreach ($msgstores as $row) {
	if($row[PR_DEFAULT_STORE]) {
		$storeentryid = $row[PR_ENTRYID];
	}
}

if(!$storeentryid) {
	print "Can't find default store\n";
	exit(1);
}

$store = mapi_openmsgstore($session, $storeentryid);

if(!$store) {
	print "Unable to open system store\n";
	exit(1);
}

$userstoreentryid = mapi_msgstore_createentryid($store, $argv[1]);
if(!$userstoreentryid) {
	print "Unknown user\n";
	exit(1);
}

$userstore = mapi_openmsgstore($session, $userstoreentryid);
if(!$userstore) {
	print "Unable to open user store\n";
	exit(1);
}

isUnicodeStore($userstore);

$inbox = mapi_msgstore_getreceivefolder($userstore);
$root = mapi_msgstore_openentry($userstore);

$storeprops = mapi_getprops($userstore, array(PR_IPM_SENTMAIL_ENTRYID, PR_IPM_OUTBOX_ENTRYID, PR_IPM_WASTEBASKET_ENTRYID));
$inboxprops = mapi_getprops($inbox, array(PR_ENTRYID, PR_IPM_APPOINTMENT_ENTRYID, PR_IPM_CONTACT_ENTRYID, PR_IPM_DRAFTS_ENTRYID, PR_IPM_JOURNAL_ENTRYID, PR_IPM_NOTE_ENTRYID, PR_IPM_TASK_ENTRYID));
$rootprops = mapi_getprops($root, array(PR_ADDITIONAL_REN_ENTRYIDS));

$trans_array = translate($argv[2]);

renamefolder($userstore, $storeprops[PR_IPM_SENTMAIL_ENTRYID], $trans_array["Sent Items"]);
renamefolder($userstore, $storeprops[PR_IPM_OUTBOX_ENTRYID], $trans_array["Outbox"]);
renamefolder($userstore, $storeprops[PR_IPM_WASTEBASKET_ENTRYID], $trans_array["Deleted Items"]);
renamefolder($userstore, $inboxprops[PR_ENTRYID], $trans_array["Inbox"]);
renamefolder($userstore, $inboxprops[PR_IPM_APPOINTMENT_ENTRYID], $trans_array["Calendar"]);
renamefolder($userstore, $inboxprops[PR_IPM_CONTACT_ENTRYID], $trans_array["Contacts"]);
renamefolder($userstore, $inboxprops[PR_IPM_DRAFTS_ENTRYID], $trans_array["Drafts"]);
renamefolder($userstore, $inboxprops[PR_IPM_JOURNAL_ENTRYID], $trans_array["Journal"]);
renamefolder($userstore, $inboxprops[PR_IPM_NOTE_ENTRYID], $trans_array["Notes"]);
renamefolder($userstore, $inboxprops[PR_IPM_TASK_ENTRYID], $trans_array["Tasks"]);
renamefolder($userstore, $rootprops[PR_ADDITIONAL_REN_ENTRYIDS][4], $trans_array["Junk E-mail"]);

?>
