<?php

include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

include('/usr/share/php/mapi/class.recurrence.php');
include('/usr/share/php/mapi/class.freebusypublish.php');


// config
$zarafa_user = "username";
$zarafa_sock = "file:///var/run/zarafa";

$hard_delete_messages = true;

$folder_to_process = 'Sent Items';

// global delete counter
$total_deleted = 0;
 

/*
 * Begin functions 
 */

function delete_messages( $folder, $messages ) 
{
  global $hard_delete_messages;

  if( $hard_delete_messages ) {
    $result = mapi_folder_deletemessages( $folder, $messages, DELETE_HARD_DELETE );
  }
  else {
    $result = mapi_folder_deletemessages( $folder, $messages );
  }
  
  if( $result == false ) {
    echo " [-] Failed to delete message\n";
  }
}


function delete_duplicate_messages($store, $entryid)
{
    global $total_deleted; 

    $folder = mapi_msgstore_openentry($store, $entryid);
    if(!$folder) { print "Unable to open folder."; return false; }

    $table = mapi_folder_getcontentstable($folder);
    if(!$table) { print "Unable to open table."; return false; }

    $org_hash = null;
    $dup_messages = array();
    $dup_count = 0;

    $result = mapi_table_sort( $table, array( PR_SUBJECT => TABLE_SORT_ASCEND ) );
    if( $result == false ) {
      die( "Could not sort table\n" );
    }

    while(1) {
    // query messages from folders content table
        $rows = mapi_table_queryrows($table, array(PR_MESSAGE_SIZE, PR_CLIENT_SUBMIT_TIME, PR_BODY, PR_HTML, PR_ENTRYID, PR_SUBJECT ), 0, 50 );

        if(count($rows) == 0) break;
    
    // we got the messages 
        foreach($rows as $row) {
        // hash message body (plaintext + html + subject)
        $md5_subject = md5( $row[PR_SUBJECT] );
        $md5_body    = md5( $row[PR_BODY] ); 
        $md5_html    = md5( $row[PR_HTML] );
        $md5_eid     = md5( $row[PR_ENTRYID] );
        
        // concat hashes, just in case there are messages with 
        // no HTML or plaintext content.
        $cur_hash = $md5_body . $md5_html . $md5_subject;
        
        // when we have accumulated enough messages, perform a burst delete 
        if( $dup_count == 50 ) {
            echo " [i] Deleting $dup_count duplicates...";
            delete_messages( $folder, $dup_messages );

            // reset the delete-queue
            $dup_messages = array();
            $dup_count    = 0;
            $total_deleted += 100;

            echo "done.\n";
            echo "Deleted $total_deleted messages so far.\n\n";
        }
        
        // duplicate messages are adjacent, so we push the first message with
        // a distinct hash and mark all following messages with this hash 
        // for deletion.
        if( $org_hash != $cur_hash ) {
            $org_hash = $cur_hash;
        }
        else {
            $dup_messages[] = $row[PR_ENTRYID];
            $dup_count++;
            echo " [i] For {$org_hash} adding DUP $md5_eid to delete list\n"; 
        }
        }
    }

    // final cleanup
    $dup_count = count( $dup_messages );
    if( $dup_count ) {
    $total_deleted += $dup_count;
        echo " [i] Finally deleting $dup_count duplicates. \n";
    delete_messages( $folder, $dup_messages );
        $dup_messages = array();
    echo "Deleted $total_deleted messages so far.\n\n";
   }

}


/*
 *  END FUNCTIONS
 */

$session = mapi_logon_zarafa("$zarafa_user","", "$zarafa_sock");
if(!$session) { print "Unable to open session\n"; exit(1); }

$msgstorestable = mapi_getmsgstorestable($session);
if(!$msgstorestable) { print "Unable to open message stores table\n"; exit(1); }

$msgstores = mapi_table_queryallrows($msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID));

foreach ($msgstores as $row) {
  if($row[PR_DEFAULT_STORE]) {
      $default_store_entry_id = $row[PR_ENTRYID];
      
  }
}

$default_store = mapi_openmsgstore($session, $default_store_entry_id );
if(!$default_store) { print "Unable to open default store\n"; exit(1); }

$root = mapi_msgstore_openentry($default_store);

// get folders
$folders = mapi_folder_gethierarchytable($root, CONVENIENT_DEPTH);

// loop over every folder
while(1) {
    $rows = mapi_table_queryrows($folders, array(PR_DISPLAY_NAME, PR_FOLDER_TYPE, PR_ENTRYID), 0, 100);

    if(count($rows) == 0)
        break;

    foreach($rows as $row) {
        // skip searchfolders
        if(isset($row[PR_FOLDER_TYPE]) && $row[PR_FOLDER_TYPE] == FOLDER_SEARCH) continue;

        // operate only on folders, whose name is specified in the config section.
        // Like 'Sent Objects'.
        if( $row[PR_DISPLAY_NAME] == $folder_to_process ) {
            delete_duplicate_messages( $default_store, $row[PR_ENTRYID] );
        }
    }
}

// done
?>
