<?php
$l_sUsername = $argv[1];
$l_sPassword = '';
$l_sServer = 'file:///var/run/zarafa';
// enter the number of days here, messages before this number of days will get deleted 
$daysBeforeDeleted = 10;

define('PT_BOOLEAN'                              , 11);    /* 16-bit boolean (non-zero true) */
define('PT_STRING8'                              , 30);    /* Null terminated 8-bit character string */
define('PT_TSTRING'                              ,PT_STRING8);
define('PT_BINARY'                               ,258);   /* Uninterpreted (counted byte array) */
define('PT_SYSTIME'                              , 64);    /* FILETIME 64-bit int w/ number of 100ns periods since Jan 1,1601 */
define('PR_DEFAULT_STORE'                             ,mapi_prop_tag(PT_BOOLEAN,     0x3400));
define('PR_ENTRYID'                                   ,mapi_prop_tag(PT_BINARY,      0x0FFF));
define('MV_FLAG'                                 ,0x1000);
define('PT_MV_BINARY'                            ,(MV_FLAG | PT_BINARY));
define('PR_ADDITIONAL_REN_ENTRYIDS'                   ,mapi_prop_tag(PT_MV_BINARY,   0x36D8));
define('PR_CREATION_TIME'                             ,mapi_prop_tag(PT_SYSTIME,     0x3007));


function greaterDate($start_date, $daysBeforeDeleted){
	return (strtotime($start_date)-strtotime(date('Y-m-d G:i:s', strtotime("-$daysBeforeDeleted days"))) < 0) ? 1 : 0;
}

// Log in to Zarafa server
$l_rSession = mapi_logon_zarafa($l_sUsername, $l_sPassword, $l_sServer);
echo ((mapi_last_hresult()==0)?"Logged in successfully":"Some error in login")."\n";

// Get a table with the message stores within this session
$l_rTableStores = mapi_getmsgstorestable($l_rSession);
echo ((mapi_last_hresult()==0)?"Processing to get data... ":"Some error in processing...")."\n";

// Retrieve the default store by querying the table
$l_aTableRows = mapi_table_queryallrows($l_rTableStores, array(PR_ENTRYID, PR_DEFAULT_STORE));
echo ((mapi_last_hresult()==0)?"Fetching Junk Folder...":"Some error in fetching...")."\n";

$l_bbnEntryID = false;    // Either boolean or binary
// Loop through returned rows
for($i=0;$i<count($l_aTableRows);$i++){
	// Check to see if this entry is the default store
	if(isset($l_aTableRows[$i][PR_DEFAULT_STORE]) && $l_aTableRows[$i][PR_DEFAULT_STORE] == true){
		$storeEntryId = $l_aTableRows[$i][PR_ENTRYID];
		break;
	}
}

// check if default root store's entry id found
if($storeEntryId){

	$store = mapi_openmsgstore($l_rSession, $storeEntryId);

	$root = mapi_msgstore_openentry($store, null);

	$spamStoreProps = mapi_getprops($root, array(PR_ADDITIONAL_REN_ENTRYIDS));

	$spamFolder = mapi_msgstore_openentry($store,$spamStoreProps[PR_ADDITIONAL_REN_ENTRYIDS][4]);

	$table = mapi_folder_getcontentstable($spamFolder);

	$spamRows = mapi_table_queryallrows($table, array(PR_ENTRYID, PR_CREATION_TIME));
	echo ((mapi_last_hresult()==0)?"Fetching messages from Junk Folder...":"Some error in fetching...")."\n";
	if(count($spamRows) > 0){
		$spamEntryIds = array();
		echo "\nTotal messages in Junk folder found are : ".count($spamRows)."\n";
		for($i=0; $i<count($spamRows); $i++){
			if(greaterDate(date("Y-m-d G:i:s", $spamRows[$i][PR_CREATION_TIME]), $daysBeforeDeleted)){
				array_push($spamEntryIds, $spamRows[$i][PR_ENTRYID]);
			}
		}
		
		if(count($spamEntryIds) > 0){
			echo "\nDeleting all ". count($spamEntryIds) . " message(s)...\n";
			mapi_folder_deletemessages($spamFolder, $spamEntryIds);
			
			echo "<b>".((mapi_last_hresult()==0)?"\nHooray! there is no spam.":"Some error in deleting... There are still some spam messages.")."<b>\n";
		}else{
			echo "\n<b>No message found before ".$daysBeforeDeleted." days in Junk Folder.</b>";
		}

	}else{
		echo "\n<b>No message found in Junk Folder.</b>";
	}
}else{
	echo "No default store found... Terminating process.\n";
}
?>

