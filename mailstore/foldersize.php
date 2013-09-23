#!/usr/bin/php

<?

function t() {
  return gettimeofday(true);
}

function prettyprint($size) {
    return floor($size/(1024)) . "KB";
}

$times = array();
$stime = t();

include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

include('/usr/share/php/mapi/class.recurrence.php');

function foldersize($store, $entryid)
{
    $size = 0;
    $folder = mapi_msgstore_openentry($store, $entryid);
    if(!$folder) { print "Unable to open folder."; return false; }
    
    $table = mapi_folder_getcontentstable($folder);
    if(!$table) { print "Unable to open table."; return false; }
    
    while(1) {
        $rows = mapi_table_queryrows($table, array(PR_MESSAGE_SIZE), 0, 100);
     
        if(count($rows) == 0) break;   
        foreach($rows as $row) {
            if(isset($row[PR_MESSAGE_SIZE])) { $size += $row[PR_MESSAGE_SIZE]; }
        }
    }
    
    return $size;
}

$times["load"] = t();

$session = mapi_logon_zarafa($argv[1], "", "file:///var/run/zarafa");

$times["logon"] = t();

$msgstorestable = mapi_getmsgstorestable($session);

$msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID));

$times["stores"] = t();

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
    print "Unable to open store\n";
    exit(1);
}

$root = mapi_msgstore_openentry($store);

if(!$root) {
    print "Unable to open root folder\n";
    exit(1);
}

$folders = mapi_folder_gethierarchytable($root, CONVENIENT_DEPTH);

$total = 0;
while(1) {
    $rows = mapi_table_queryrows($folders, array(PR_DISPLAY_NAME, PR_FOLDER_TYPE, PR_ENTRYID), 0, 100);
    
    if(count($rows) == 0)
        break;
        
    foreach($rows as $row) {
        // Skip searchfolders
        if(isset($row[PR_FOLDER_TYPE]) && $row[PR_FOLDER_TYPE] == FOLDER_SEARCH) continue;
         
        print isset($row[PR_DISPLAY_NAME]) ? $row[PR_DISPLAY_NAME] : "<Unknown>";
        print ": ";
        $size = foldersize($store, $row[PR_ENTRYID]);
        print prettyprint($size) . "\n";
        $total += $size;
    }
}    

print "Total: " . prettyprint($total) . "\n";

?>
