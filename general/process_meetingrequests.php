#!/usr/bin/php
<?php

if(!isset($argv[1])) {
	print "Usage: process_meetingrequests <username>\n";
	exit(1);
}

// The purpose of this script is to process the unprocessed meeting requests in the inbox folder
$l_sUsername = $argv[1];
$l_sPassword = '';
$l_sServer = 'file:///var/run/zarafa';

// Include MAPI PHP-EXT
define('BASE_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . "/");
set_include_path(BASE_PATH. PATH_SEPARATOR . 
//	BASE_PATH."../../include/" .  PATH_SEPARATOR . // Debug line
	"/usr/share/php/");
require("mapi/mapi.util.php");require("mapi/mapicode.php");require("mapi/mapidefs.php");
require("mapi/mapitags.php");require("mapi/mapiguid.php");
require("mapi/class.meetingrequest.php");require("mapi/class.freebusypublish.php");

define('PR_PROCESSED', mapi_prop_tag(PT_BOOLEAN, 0x7D01));

if ( !function_exists( 'hex2bin' ) ) {
   function hex2bin($data)
   {
       return pack("H*", $data);
   }
}

/**
 * Publishing the FreeBusy information of the default calendar. The 
 * folderentryid argument is used to check if the default calendar 
 * should be updated or not.
 * 
 * @param $store MAPIobject Store object of the store that needs publishing
 * @param $folderentryid binary entryid of the folder that needs to be updated.
 */
function publishFreeBusy($store, $l_rSession, $folderentryid=false){
	// Publish updated free/busy information
	// First get default calendar from the root folder
	$rootFolder = mapi_msgstore_openentry($store, null);
	$rootFolderProps = mapi_getprops($rootFolder, array(PR_IPM_APPOINTMENT_ENTRYID));

	// If no folderentryid supplied or if the supplied entryid matches the default calendar.
	if(!$folderentryid || $rootFolderProps[PR_IPM_APPOINTMENT_ENTRYID] == $folderentryid){
		// Get the calendar and owner entryID
		$calendar = mapi_msgstore_openentry($store, $rootFolderProps[PR_IPM_APPOINTMENT_ENTRYID]);
		$storeProps = mapi_msgstore_getprops($store, array(PR_MAILBOX_OWNER_ENTRYID));
		if (isset($storeProps[PR_MAILBOX_OWNER_ENTRYID])){
			// Lets share!
			$pub = new FreeBusyPublish($l_rSession, $store, $calendar, $storeProps[PR_MAILBOX_OWNER_ENTRYID]);
			$pub->publishFB(time() - (7 * 24 * 60 * 60), 6 * 30 * 24 * 60 * 60); // publish from one week ago, 6 months ahead
		}
	}
}

// Log in to Zarafa server
$l_rSession = mapi_logon_zarafa($l_sUsername, $l_sPassword, $l_sServer);
echo ((mapi_last_hresult()==0)?"Logged in successfully":"Some error in login")."\n";

// Get a table with the message stores within this session
$l_rTableStores = mapi_getmsgstorestable($l_rSession);
echo ((mapi_last_hresult()==0)?"Processing to get data... ":"Some error in processing...")."\n";

// Retrieve the default store by querying the table
$l_aTableRows = mapi_table_queryallrows($l_rTableStores, array(PR_ENTRYID, PR_DEFAULT_STORE));
echo ((mapi_last_hresult()==0)?"Retrieving default store...":"Some error in retrieving default store...")."\n";

$l_bbnEntryID = false;    // Either boolean or binary
// Loop through returned rows
for($i=0;$i<count($l_aTableRows);$i++){
	// Check to see if this entry is the default store
	if(isset($l_aTableRows[$i][PR_DEFAULT_STORE]) && $l_aTableRows[$i][PR_DEFAULT_STORE] == true){
		$l_bbnEntryID = $l_aTableRows[$i][PR_ENTRYID];
		break;
	}
}

// check if default root store's entry id found
if($l_bbnEntryID){

	// Open msg store by using the entryID
	$l_rDefaultStore = mapi_openmsgstore($l_rSession, $l_bbnEntryID);
	echo 'Opening default store result (0=success): ' . mapi_last_hresult() . "\n";

	// Get inbox
	$l_rInbox = mapi_msgstore_getreceivefolder($l_rDefaultStore);
	echo 'Getting entryID of inbox folder result (0=success): ' . mapi_last_hresult() . "\n";

	// Check if folder has been opened
	if($l_rInbox){
		// Open contents table
		$l_rInboxTable = mapi_folder_getcontentstable($l_rInbox);
		echo 'Opening contents table result (0=success): ' . mapi_last_hresult() . "\n";

		// Find the item by restricting all items to the correct ID
		$restrict = Array(RES_AND,
							Array( 
									Array(RES_PROPERTY,
													Array(RELOP => RELOP_NE,
														  ULPROPTAG => PR_PROCESSED,
														  VALUE => array(PR_PROCESSED=>true)
													)
									),
									Array(	// Check if message class starts with "IPM.Schedule.Meeting"
										RES_CONTENT,
										Array(
											FUZZYLEVEL => FL_PREFIX,
											ULPROPTAG => PR_MESSAGE_CLASS,
											VALUE => Array(
												PR_SUBJECT => 'IPM.Schedule.Meeting'
											)
										)
									),
									Array ( // Process only unread messages
										RES_BITMASK,
										Array(
											ULTYPE => BMR_EQZ,
											ULMASK => MSGFLAG_READ,
											ULPROPTAG => PR_MESSAGE_FLAGS
										)
									)
								)
							);



		// Just get all items from table
		$l_aRows = mapi_table_queryallrows($l_rInboxTable, Array(PR_ENTRYID, PR_MESSAGE_CLASS, PR_SUBJECT, PR_PROCESSED), $restrict);
		echo 'Querying contents table result (0=success): ' . mapi_last_hresult() . "\n";
	
//print_r(count($l_aRows));
//exit;
		echo 'Processing messages'."\n";
		$l_iCounter = 0;
		for($i=0;$i<count($l_aRows);$i++){
			$l_sMsgClassSearch = 'IPM.Schedule.Meeting';
			$l_rMessage = mapi_msgstore_openentry($l_rDefaultStore, $l_aRows[$i][PR_ENTRYID]);
			$req = new Meetingrequest($l_rDefaultStore, $l_rMessage, $l_rSession);
			if($req->isMeetingRequest() && !$req->isLocalOrganiser()){
				// Put the item in the calendar 'tentatively'
				$req->doAccept(true, false, false);
				$l_iCounter++;
			}elseif($req->isMeetingCancellation()){
				// Let's do some processing of this Meeting Cancellation Object we received
				$req->processMeetingCancellation();
				$l_iCounter++;
			}
		}
		echo 'Processed '.$l_iCounter.' '.(($l_iCounter==1)?'message':'messages')."\n";

		if($l_iCounter > 0){
			// Publish updated free/busy information
			publishFreeBusy($l_rDefaultStore, $l_rSession);
			echo 'Published FreeBusy information!'."\n";
		}

	}else{
		echo 'Inbox could not be opened!'."\n";
	}

}else{
	echo "No default store found... Terminating process.\n";
}
?>

