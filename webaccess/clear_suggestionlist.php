#!/usr/bin/php
<?

// comment this line for debugging
error_reporting(0);

// mapi include files
include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

// config options
$ADMINUSERNAME = "SYSTEM";
$ADMINPASSWORD = "";
$SERVER = "file:///var/run/zarafa";
$USAGEMESSAGE = "Usage:\n\tfor selected users \n\t\tphp clear_suggestionlist.php -u <username1> <username2> ...\n\tfor all users \n\t\tphp clear_suggestionlist.php -a\n";

if($argc < 2) {
    exit($USAGEMESSAGE);
}

// use SYSTEM account for login
$session = mapi_logon_zarafa($ADMINUSERNAME, $ADMINPASSWORD, $SERVER);
if(!$session) {
    exit("Can't login into zarafa server\n");
}

// get all stores of SYSTEM account
$msgStoresTable = mapi_getmsgstorestable($session);
$msgStores = mapi_table_queryallrows($msgStoresTable, array(PR_DEFAULT_STORE, PR_ENTRYID));

// get default store
foreach ($msgStores as $row) {
    if($row[PR_DEFAULT_STORE]) {
        $storeEntryid = $row[PR_ENTRYID];
    }
}

if(!$storeEntryid) {
    exit("Can't find default store\n");
}

// open default store
$store = mapi_openmsgstore($session, $storeEntryid);
if(!$store) {
    exit("Unable to open system store\n");
}

if(strcasecmp($argv[1], "-a") == 0) {
    // get all zarafa users and remove property data
    $userList = array();

    // for multi company setup
    $companyList = mapi_zarafa_getcompanylist($store);
    if(mapi_last_hresult() == NOERROR && is_array($companyList)) {
        // multi company setup, get all users from all companies
        foreach($companyList as $companyName => $companyData) {
            $userList = array_merge($userList, mapi_zarafa_getuserlist($store, $companyData["companyid"]));
        }
    } else {
        // single company setup, get list of all zarafa users
        $userList = mapi_zarafa_getuserlist($store);
    }

    if(count($userList) <= 0) {
        exit("Unable to get user list\n");
    }

    foreach($userList as $userName => $userData) {
        // check for valid users
        if($userName == "SYSTEM") {
            continue;
        }

        $result = clearSuggestionList($session, $store, $userName);
        if($result) {
            print "Suggestion list cleared for user - " . $userName . "\n\n";
        } else {
            print "Not able to clear suggestion list for user - " . $userName . "\n\n";
        }
    }
} else if(strcasecmp($argv[1], "-u") == 0) {
    // only clear properties for selected users
    if($argc == 2) {
        exit("No user specified\n\n" . $USAGEMESSAGE);
    }

    for($index = 2; $index < $argc; $index++) {     // start with argv[2]
        $result = clearSuggestionList($session, $store, $argv[$index]);
        if($result) {
            print "Suggestion list cleared for user - " . $argv[$index] . "\n\n";
        } else {
            print "Not able to clear suggestion list for user - " . $argv[$index] . "\n\n";
        }
    }
} else {
    exit("Unknown option specified\n\n" . $USAGEMESSAGE);
}

/**************** Internal Functions ******************/

function clearSuggestionList($session, $store, $userName)
{
    // create entryid of user's store
    $userStoreEntryId = mapi_msgstore_createentryid($store, $userName);
    if(!$userStoreEntryId) {
        print "Error in creating entryid for user's store - " . $userName . "\n";
        return false;
    }

    // open user's store
    $userStore = mapi_openmsgstore($session, $userStoreEntryId);
    if(!$userStore) {
        print "Error in opening user's store - " . $userName . "\n";
        return false;
    }

    // we are not checking here that property exists or not because it could happen that getprops will return
    // MAPI_E_NOT_ENOUGH_MEMORY for very large property, if property does not exists then it will be created

    // remove property data, overwirte existing data with a blank string (PT_STRING8)
    mapi_setprops($userStore, array(PR_EC_RECIPIENT_HISTORY => ""));
    $result = mapi_last_hresult();

    if($result == NOERROR) {
        // Save changes
        mapi_savechanges($userStore);
        return (mapi_last_hresult() == NOERROR) ? true : false;
    }

    return false;
}
?>
