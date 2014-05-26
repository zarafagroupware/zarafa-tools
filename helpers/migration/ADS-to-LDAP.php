<?php
/*  Zarafa LDAP Migration Script
 *
 *  Authors:
 *  Koen ten Berg <koen@tenberg.org>
 *
 *  Date:
 *  26 May 2014
 *
 *  Description:
 *  This script updates the externalid of zarafa users when migrating from LDAP directory to another LDAP directory. 
 *  Please note, this script is preconfigured for Active Directory to LDAP, hence objectGUID and uidNumber. The mail
 *  address of each user in both directories has to match. You cannot have multiple users with the same mail address,
 *  it will cause this script to misbehave (add a check on cn or sn if desired)
 *
 *  Before running this script, please make a dump of your database. Also, configure the correct values in the 
 *  configuration section of this script. If you change the values for LDAP Attributes, you may need to change
 *  other bits and piece in the code of this script.
 *
 *  Please note, that after the migration you have run the addressbook update script, which can be found on the
 *  Zarafa wiki pages.
 */

// Configuration

// Source LDAP Directory (contains users that externid refers to)
$ldapserver = '172.17.0.100';
$ldapuser   = 'cn=zarafaadmin,cn=users,dc=acme,dc=com';
$ldappass   = '';
$ldaptree   = "OU=Domain Users,DC=acme,DC=com";
$ldapattrs  = array("cn", "sn", "objectGUID", "mail");

// New LDAP Directory (contains users with same mail value)
// Externid will be changed to these users' uidNumber value
$localldapserver = '127.0.0.1';
$localldapuser   = '';
$localldappass   = '';
$localldaptree   = "ou=Users,dc=contoso,dc=com";
$localldapattrs  = array("cn", "sn", "uidNumber", "mail");

// Connection details for Zarafa database (MySQL only)
$mysqlserver 	= 'localhost';
$mysqluser	= 'root';
$mysqlpass	= '';
$zarafadb	= 'zarafa';





// Define a way of dealing with errors
error_reporting(E_ALL);
ini_set('error_reporting', E_ALL);
ini_set('display_errors',1);

// Create the external LDAP connection
$ldapconn = ldap_connect($ldapserver) or die("[FAIL] Could not connect to external LDAP server.\n");

if($ldapconn) {
   // binding to ldap server
   $ldapbind = ldap_bind($ldapconn, $ldapuser, $ldappass) or die ("[FAIL] Error trying to bind: ".ldap_error($ldapconn)."\n");
   // verify binding
   if ($ldapbind) {
      echo "[ OK ] Connected to external LDAP Server\n";

      //$result = ldap_search($ldapconn,$ldaptree, "(cn=*)", $ldapattrs) or die ("[FAIL] Error in search query: ".ldap_error($ldapconn)."\n");
      $result = ldap_search($ldapconn,$ldaptree, "(cn=*)") or die ("[FAIL] Error in search query: ".ldap_error($ldapconn)."\n");
      $data = ldap_get_entries($ldapconn, $result);

      // SHOW ALL DATA
      //print_r($data);
      echo "[ OK ] Retrieved all users from external LDAP\n";
   }
}

// Create LDAP connection
$localldapconn = ldap_connect($localldapserver) or die("[FAIL] Could not connect to internal LDAP server.\n");

if($localldapconn) {
   // binding to ldap server
   $localldapbind = ldap_bind($localldapconn, $localldapuser, $localldappass) or die ("[FAIL] Error trying to bind: ".ldap_error($localldapconn)."\n");
   // verify binding
   if ($localldapbind) {
      echo "[ OK ] Connected to internal LDAP Server\n";

      $localresult = ldap_search($localldapconn,$localldaptree, "(cn=*)", $localldapattrs) or die ("[FAIL] Error in search query: ".ldap_error($localldapconn)."\n");
      $localdata = ldap_get_entries($localldapconn, $localresult);

      // SHOW ALL DATA
      //print_r($localdata);
      echo "[ OK ] Retrieved all users from internal LDAP\n";
   }
}

// Connect to the Zarafa database
$con=mysqli_connect($mysqlserver, $mysqluser, $mysqlpass, $zarafadb);

// Check connection
if (mysqli_connect_errno()) {
   die("[FAIL] Unable to connect to MySQL: " . mysqli_connect_error()."\n");
}

$result = mysqli_query($con,"SELECT id, externid FROM users");

if($result === FALSE) {
   die("[FAIL] Error: " . mysql_error(). "\n"); // TODO: better error handling
}

while($row = mysqli_fetch_array($result)) {
   //print($row['id'] . " " . base64_encode($row['externid']) . PHP_EOL);
   $externid = base64_encode($row['externid']);

   if ($externid != "") {
      // The user has an external ID, try to look it up in the LDAP server
      print("[INFO] User " . $row['id'] . " has external ID\n");
      $extfound = FALSE;

      // Search for user in LDAP data
      foreach ($data as $ldapuser) {
         $foundid = base64_encode($ldapuser['objectguid'][0]);

         if ($foundid == $externid) {
            $extfound = TRUE;
            print "[INFO] User " . $row['id'] . " found in external LDAP: ". $ldapuser['mail'][0] ."\n";
            $mail = $ldapuser['mail'][0];
            $intfound = FALSE;

            // Search for user in new LDAP data
	    foreach ($localdata as $localldapuser) {
               $localmail = $localldapuser['mail'][0];
               if ($localmail == $mail) {
                  print "[INFO] User " . $row['id'] . " found in internal LDAP: ". $localldapuser['mail'][0] ."\n";
                  print "[INFO] User " . $row['id'] . " new external ID: ". $localldapuser['uidnumber'][0] ."\n";
                  $intfound = TRUE;
                  //print ("UPDATE users SET externid = '" . $localldapuser['uidnumber'][0] . "' WHERE id='" . $row['id'] . "'\n");
		  mysqli_query($con,"UPDATE users SET externid = '" . $localldapuser['uidnumber'][0] . "' WHERE id='" . $row['id'] . "'");
               }
            }
            if (!$extfound) {
               print "[WARN] User " . $row['id'] . " not in inernal LDAP, will be orphaned!\n";
            }
         }
      }
      if (!$extfound) {
         print "[WARN] User " . $row['id'] . " is orphaned\n";
      }
   } else {
      print("[INFO] User " . $row['id'] . " is internal to Zarafa\n");
   }
   print("----------------------------------------------------------\n");
}

mysqli_close($con);
print("[ OK ] Done converting users to internal LDAP\n");
?> 
