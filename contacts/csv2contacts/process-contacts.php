#!/usr/bin/php
<?php
/**
* CSV contacts to own contacts folder
*
* Made by Michael Erkens (m.erkens@zarafa.com)
* Extended by Manfred Kutas (manfred@zarafabrasil.com.br)
*/

if (!isset($argv[1]) || !isset($argv[2]) || !isset($argv[3])) {
	print "Usage php csv2contacts.php username password name.csv\n";
	exit (1);
}
printf("Argument 1 <$argv[1]>\n");
printf("Argument 2 <$argv[2]>\n");
printf("Argument 3 <$argv[3]>\n");



// set username/password and the server location
$username=$argv[1];
$password=$argv[2];
#define("SERVER", "file:///var/run/zarafa");
define("SERVER", "http://localhost:236/zarafa");

// if EMPTY_FOLDER is true, than all current contacts are deleted
define("EMPTY_FOLDER", true);


// CSV options
define("CSV_FILE", "test.csv"); // the csv file that we use
$csv_file="/tmp/".$argv[3];
if (!file_exists($csv_file)) {
	print "File for import {$csv_file} not found. The script will exit now\n";
	exit (1);
}
printf("csv file <$csv_file>\n");
define("CSV_DELIMITER", ",");
define("CSV_ENCLOSURE", "\"");
define("CSV_MAX_LENGTH", 4096); // needed for multiline csv support
ini_set("auto_detect_line_endings", true); // when set to false only the line ends of the running system are used (so don't change this)

define("CSV_CHARSET", "UTF-8"); // The charset of the CSV input files
//set it to true if the first line of the csv file contains field names, it will be skipped then.
define("FIELD_NAMES", true);
//the format of date values: true if they are as unix timestamps, false otherwise
define("DATES_AS_TIMESTAMPS", false);

// mapping for the csv column number to contact field (first field is 0)
$csv_mapping = array(
                        "given_name" 					=> 0,
                        "middle_name"					=> 1,
                        "surname"						=> 2,
                        "display_name_prefix"			=> 3, //title
                        "webpage"						=> 6,
                        "birthday"						=> 8,
                        "wedding_anniversary"			=> 9,
                        "notes"							=> 13,
                        "email_address_1"				=> 14, //email address only
                        "email_address_2"				=> 15,
                        "email_address_3"				=> 16,
                        "home_telephone_number"			=> 18,
                        "home2_telephone_number"		=> 19,
                        "cellular_telephone_number"		=> 20,
                        "pager_telephone_number"		=> 21,
                        "home_fax_number"				=> 22,
                        "home_address"					=> 23,
                        "home_address_street"			=> 24,
                        "home_address_street2"			=> 25,
                        "home_address_street3"			=> 26,
                        "home_address_pobox"			=> 27,
                        "home_address_city"				=> 28,
                        "home_address_state"			=> 29,
                        "home_address_postal_code"		=> 30,
                        "home_address_country"			=> 31,
                        "spouse_name"					=> 32,
                        "manager_name"					=> 34,
                        "assistant"						=> 35,
                        "company_telephone_number"		=> 37,
                        "office_telephone_number"		=> 38,
                        "business2_telephone_number"	=> 39,
                        "business_fax_number"			=> 40,
                        "assistant_telephone_number"	=> 41,
                        "company_name"					=> 42,
                        "job_title"						=> 43,
                        "department_name"				=> 44,
                        "office_location"		    	=> 45,
                        "profession"					=> 47,
                        "business_address"				=> 49,
                        "business_address_street"		=> 50,
                        "business_address_street2"		=> 51,
                        "business_address_street3"		=> 52,
                        "business_address_pobox"		=> 53,
                        "business_address_city"			=> 54,
                        "business_address_state"		=> 55,
                        "business_address_postal_code"	=> 56,
                        "business_address_country"		=> 57,
                        "other_telephone_number"		=> 58,
                        "other_address"					=> 60,
                        "other_address_street"			=> 61,
                        "other_address_street2"			=> 62,
                        "other_address_street3"			=> 63,
                        "other_address_pobox"			=> 64,
                        "other_address_city"			=> 65,
                        "other_address_state"			=> 66,
                        "other_address_postal_code"		=> 67,
                        "other_address_country"			=> 68,
                        "callback_telephone_number"		=> 69,
                        "car_telephone_number"			=> 70,
                        "isdn_number"					=> 71,
                        "radio_telephone_number"		=> 72,
                        "ttytdd_telephone_number"		=> 73,
                        "telex_telephone_number"		=> 74,
                        "sensitivity"					=> 84,
                        "categories"					=> 87, //semicolon separated string
);



##########################
## end of configuration ##
##########################
error_reporting(E_ALL);
ini_set("display_errors", true);
ini_set("html_errors", false);
mapidefs();
mapitags();

$session = mapi_logon_zarafa($username, $password, SERVER);
if (mapi_last_hresult()!=0)
	trigger_error(sprintf("MAPI Error: 0x%x", mapi_last_hresult()), E_USER_ERROR);

$storesTable = mapi_getmsgstorestable($session);
$stores = mapi_table_queryallrows($storesTable, array(PR_ENTRYID, PR_MDB_PROVIDER));
for($i=0;$i<count($stores); $i++){
	if ($stores[$i][PR_MDB_PROVIDER] == ZARAFA_SERVICE_GUID){
		$storeEntryid = $stores[$i][PR_ENTRYID];
		break;
	}
}

if (!isset($storeEntryid))
	trigger_error("Default store not found", E_USER_ERROR);


$store = mapi_openmsgstore($session, $storeEntryid);
$root = mapi_msgstore_openentry($store, null);
$rootProps = mapi_getprops($root, array(PR_IPM_CONTACT_ENTRYID));

$folder = mapi_msgstore_openentry($store, $rootProps[PR_IPM_CONTACT_ENTRYID]);

isUnicodeStore($store);


// open the csv file and start reading
$fh = fopen($csv_file, "r");
if (!$fh)
	trigger_error("Can't open CSV file \"".$csv_file."\"", E_USER_ERROR);

// empty folder if needed
if (EMPTY_FOLDER){
	mapi_folder_emptyfolder($folder, DEL_ASSOCIATED);
}

$properties = array();
loadProperties($properties);
$properties = getPropIdsFromStrings($store, $properties);

//composed properties which require more work
$special_properties = array ("email_address_1", "email_address_2", "email_address_3");

$i = 1;
while(!feof($fh)){
	$line = fgetcsv($fh, CSV_MAX_LENGTH, CSV_DELIMITER, CSV_ENCLOSURE);
print_r($line);
	if (!$line) continue;


	if ($i==1 && defined('FIELD_NAMES') && FIELD_NAMES) {
	    $i++;
	    continue;
	}

	$props = array();

	//set "simple" properties
	foreach ($csv_mapping as $property => $cnt) {
		if (!(in_array($property, $special_properties))) setProperty($property, $line[$csv_mapping[$property]], $props, $properties);
	}

	// set display name
	if (isset($csv_mapping["display_name"]) && isset($line[$csv_mapping["display_name"]])){
		$name = to_windows1252($line[$csv_mapping["display_name"]]);
		$props[$properties["display_name"]] = $props[$properties["subject"]] = $props[$properties["fileas"]] = $name;
		$props[$properties["fileas_selection"]] = -1;
	}
	else {
		$props[$properties["display_name"]] = $props[$properties["subject"]] = $props[$properties["fileas"]] = "";

		if (isset($props[$properties["given_name"]])) {
			$props[$properties["display_name"]] .= $props[$properties["given_name"]];
				$props[$properties["subject"]] .= $props[$properties["given_name"]];
				$props[$properties["fileas"]] .= $props[$properties["given_name"]];
		}
	if (isset($props[$properties["surname"]])) {
			if (strlen($props[$properties["display_name"]]) > 0) {
				$props[$properties["display_name"]] .= " ".$props[$properties["surname"]];
				$props[$properties["subject"]] .= " ".$props[$properties["surname"]];
				$props[$properties["fileas"]] .= " ".$props[$properties["surname"]];
			}
			else {
				$props[$properties["display_name"]] .= $props[$properties["surname"]];
				$props[$properties["subject"]] .= $props[$properties["surname"]];
				$props[$properties["fileas"]] .= $props[$properties["surname"]];
			}
		}
	}

	//set email addresses
	if (isset($line[$csv_mapping["email_address_1"]]) || isset($line[$csv_mapping["email_address_2"]]) || isset($line[$csv_mapping["email_address_3"]])) {
		$nremails = array();
		$abprovidertype = 0;

		setEmailAddress($line[$csv_mapping["email_address_1"]], $props[$properties["display_name"]], 1, $props, $properties, $nremails, $abprovidertype);
		setEmailAddress($line[$csv_mapping["email_address_2"]], $props[$properties["display_name"]], 2, $props, $properties, $nremails, $abprovidertype);
		setEmailAddress($line[$csv_mapping["email_address_3"]], $props[$properties["display_name"]], 3, $props, $properties, $nremails, $abprovidertype);


		if (!empty($nremails)) $props[$properties["address_book_mv"]] = $nremails;
		$props[$properties["address_book_long"]] = $abprovidertype;
	}

	//set addresses
	if (isset($csv_mapping["home_address_street2"])) mergeStreet("home", $line[$csv_mapping["home_address_street2"]], $props, $properties);
	if (isset($csv_mapping["home_address_street3"])) mergeStreet("home", $line[$csv_mapping["home_address_street3"]], $props, $properties);
	if (!isset($props[$properties["home_address"]])) buildAddressString("home", $props[$properties["home_address_street"]], $props[$properties["home_address_postal_code"]], $props[$properties["home_address_city"]], $props[$properties["home_address_state"]], $props[$properties["home_address_country"]], $props, $properties);

	if (isset($csv_mapping["business_address_street2"])) mergeStreet("business", $line[$csv_mapping["business_address_street2"]], $props, $properties);
	if (isset($csv_mapping["business_address_street3"])) mergeStreet("business", $line[$csv_mapping["business_address_street3"]], $props, $properties);
	if (!isset($props[$properties["business_address"]])) buildAddressString("business", $props[$properties["business_address_street"]], $props[$properties["business_address_postal_code"]], $props[$properties["business_address_city"]], $props[$properties["business_address_state"]], $props[$properties["business_address_country"]], $props, $properties);

	if (isset($csv_mapping["other_address_street2"])) mergeStreet("other", $line[$csv_mapping["other_address_street2"]], $props, $properties);
	if (isset($csv_mapping["other_address_street3"])) mergeStreet("other", $line[$csv_mapping["other_address_street3"]], $props, $properties);
	if (!isset($props[$properties["other_address"]])) buildAddressString("other", $props[$properties["other_address_street"]], $props[$properties["other_address_postal_code"]], $props[$properties["other_address_city"]], $props[$properties["other_address_state"]], $props[$properties["other_address_country"]], $props, $properties);

	if (isset($props[$properties["business_address"]])) {
		$props[$properties["mailing_address"]] = 2;
		setMailingAdress($props[$properties["business_address_street"]], $props[$properties["business_address_postal_code"]], $props[$properties["business_address_city"]], $props[$properties["business_address_state"]], $props[$properties["business_address_country"]], $props[$properties["business_address"]], $props, $properties);
	}
	elseif (isset($props[$properties["home_address"]])) {
		$props[$properties["mailing_address"]] = 1;
		setMailingAdress($props[$properties["home_address_street"]], $props[$properties["home_address_postal_code"]], $props[$properties["home_address_city"]], $props[$properties["home_address_state"]], $props[$properties["home_address_country"]], $props[$properties["home_address"]], $props, $properties);

	}
	elseif (isset($props[$properties["other_address"]])) {
		$props[$properties["mailing_address"]] = 3;
		setMailingAdress($props[$properties["other_address_street"]], $props[$properties["other_address_postal_code"]], $props[$properties["other_address_city"]], $props[$properties["other_address_state"]], $props[$properties["other_address_country"]], $props[$properties["other_address"]], $props, $properties);

	}

	// if the display name is set, then it is a valid contact: save it to the folder
	if (isset($props[$properties["display_name"]])){
		$props[$properties["message_class"]] = "IPM.Contact";
		$props[$properties["icon_index"]] = "512";
		$message = mapi_folder_createmessage($folder);
		mapi_setprops($message, $props);
		mapi_savechanges($message);
		printf("New contact added \"%s\".\n", $props[$properties["display_name"]]);
	}

	$i++;

}

// EOF


function getPropIdsFromStrings($store, $mapping)
{
	$props = array();

	$ids = array("name"=>array(), "id"=>array(), "guid"=>array(), "type"=>array()); // this array stores all the information needed to retrieve a named property
	$num = 0;

	// caching
	$guids = array();

	foreach($mapping as $name=>$val){
		if(is_string($val)) {
			$split = explode(":", $val);

			if(count($split) != 3){ // invalid string, ignore
				trigger_error(sprintf("Invalid property: %s \"%s\"",$name,$val), E_USER_NOTICE);
				continue;
			}

			if(substr($split[2], 0, 2) == "0x") {
				$id = hexdec(substr($split[2], 2));
			} else {
				$id = $split[2];
			}

			// have we used this guid before?
			if (!defined($split[1])){
				if (!array_key_exists($split[1], $guids)){
					$guids[$split[1]] = makeguid($split[1]);
				}
				$guid = $guids[$split[1]];
			}else{
				$guid = constant($split[1]);
			}

			// temp store info about named prop, so we have to call mapi_getidsfromnames just one time
			$ids["name"][$num] = $name;
			$ids["id"][$num] = $id;
			$ids["guid"][$num] = $guid;
			$ids["type"][$num] = $split[0];
			$num++;
		}else{
			// not a named property
			$props[$name] = $val;
		}
	}

	if (count($ids["id"])==0){
		return $props;
	}

	// get the ids
	$named = mapi_getidsfromnames($store, $ids["id"], $ids["guid"]);
	foreach($named as $num=>$prop){
		$props[$ids["name"][$num]] = mapi_prop_tag(constant($ids["type"][$num]), mapi_prop_id($prop));
	}

	return $props;
}


function makeGuid($guid)
{
	// remove the { and } from the string and explode it into an array
	$guidArray = explode('-', substr($guid, 1,strlen($guid)-2));

	// convert to hex!
	$data1[0] = intval(substr($guidArray[0], 0, 4),16); // we need to split the unsigned long
	$data1[1] = intval(substr($guidArray[0], 4, 4),16);
	$data2 = intval($guidArray[1], 16);
	$data3 = intval($guidArray[2], 16);

	$data4[0] = intval(substr($guidArray[3], 0, 2),16);
	$data4[1] = intval(substr($guidArray[3], 2, 2),16);

	for($i=0; $i < 6; $i++)
	{
		$data4[] = intval(substr($guidArray[4], $i*2, 2),16);
	}

	return pack("vvvvCCCCCCCC", $data1[1], $data1[0], $data2, $data3, $data4[0],$data4[1],$data4[2],$data4[3],$data4[4],$data4[5],$data4[6],$data4[7]);
}


function mapidefs(){
	define("ZARAFA_SERVICE_GUID"                     , makeguid("{3C253DCA-D227-443C-94FE-425FAB958C19}"));    // default store
	define("ZARAFA_STORE_PUBLIC_GUID"                , makeguid("{D47F4A09-D3BD-493C-B2FC-3C90BBCB48D4}"));    // public store
	define('RES_PROPERTY'                            , 4);
	define('RELOP_EQ'                                , 4);
	define('VALUE'                                   , 0);        // propval
	define('RELOP'                                   , 1);        // compare method
	define('ULPROPTAG'                               , 6);        // property
	define('MV_FLAG'                                 , 0x1000);
	define('PT_STRING8'                              , 30);    /* Null terminated 8-bit character string */
	define('PT_TSTRING'                              , PT_STRING8);
	define('PT_LONG'                                 ,  3);    /* Signed 32-bit value */

	define('PT_BINARY'                               , 258);   /* Uninterpreted (counted byte array) */
	define('PT_MV_LONG'                              , (MV_FLAG | PT_LONG));
	define('DEL_ASSOCIATED'                          , 0x00000008);

	define('PT_BOOLEAN'                              , 11);    /* 16-bit boolean (non-zero true) */
	define('PT_SYSTIME'                              , 64);    /* FILETIME 64-bit int w/ number of 100ns periods since Jan 1,1601 */
	define('PT_MV_STRING8'                           ,(MV_FLAG | PT_STRING8));
	define('PT_MV_BINARY'                            ,(MV_FLAG | PT_BINARY));

	define('PSETID_Address'                          , makeguid("{00062004-0000-0000-C000-000000000046}"));
	define('PSETID_Common'                           , makeguid("{00062008-0000-0000-C000-000000000046}"));
	define('PS_PUBLIC_STRINGS'                       , makeguid("{00020329-0000-0000-C000-000000000046}"));
	define('STORE_UNICODE_OK'                        ,0x00040000); // The message store supports properties containing Unicode characters.
}


function mapitags(){
	define('PR_ENTRYID'                                   ,mapi_prop_tag(PT_BINARY,      0x0FFF));
	define('PR_MDB_PROVIDER'                              ,mapi_prop_tag(PT_BINARY,      0x3414));
	define('PR_IPM_CONTACT_ENTRYID'                       ,mapi_prop_tag(PT_BINARY,      0x36D1));
	define('PR_IPM_PUBLIC_FOLDERS_ENTRYID'                ,mapi_prop_tag(PT_BINARY,      0x6631));
	define('PR_DISPLAY_NAME'                              ,mapi_prop_tag(PT_TSTRING,     0x3001));
	define('PR_SUBJECT'                                   ,mapi_prop_tag(PT_TSTRING,     0x0037));
	define('PR_COMPANY_NAME'                              ,mapi_prop_tag(PT_TSTRING,     0x3A16));
	define('PR_BUSINESS_TELEPHONE_NUMBER'                 ,mapi_prop_tag(PT_TSTRING,     0x3A08));
	define('PR_OFFICE_TELEPHONE_NUMBER'                   ,PR_BUSINESS_TELEPHONE_NUMBER);
	define('PR_MOBILE_TELEPHONE_NUMBER'                   ,mapi_prop_tag(PT_TSTRING,     0x3A1C));
	define('PR_CELLULAR_TELEPHONE_NUMBER'                 ,PR_MOBILE_TELEPHONE_NUMBER);
	define('PR_BUSINESS_FAX_NUMBER'                       ,mapi_prop_tag(PT_TSTRING,     0x3A24));
	define('PR_MESSAGE_CLASS'                             ,mapi_prop_tag(PT_TSTRING,     0x001A));
	define('PR_ICON_INDEX'                                ,mapi_prop_tag(PT_LONG,        0x1080));

	define('PR_GIVEN_NAME'                                ,mapi_prop_tag(PT_TSTRING,     0x3A06));
	define('PR_MIDDLE_NAME'                               ,mapi_prop_tag(PT_TSTRING,     0x3A44));
	define('PR_SURNAME'                                   ,mapi_prop_tag(PT_TSTRING,     0x3A11));
	define('PR_HOME_TELEPHONE_NUMBER'                     ,mapi_prop_tag(PT_TSTRING,     0x3A09));
	define('PR_TITLE'                                     ,mapi_prop_tag(PT_TSTRING,     0x3A17));
	define('PR_DEPARTMENT_NAME'                           ,mapi_prop_tag(PT_TSTRING,     0x3A18));
	define('PR_OFFICE_LOCATION'                           ,mapi_prop_tag(PT_TSTRING,     0x3A19));
	define('PR_PROFESSION'                                ,mapi_prop_tag(PT_TSTRING,     0x3A46));
	define('PR_MANAGER_NAME'                              ,mapi_prop_tag(PT_TSTRING,     0x3A4E));
	define('PR_ASSISTANT'                                 ,mapi_prop_tag(PT_TSTRING,     0x3A30));
	define('PR_NICKNAME'                                  ,mapi_prop_tag(PT_TSTRING,     0x3A4F));
	define('PR_DISPLAY_NAME_PREFIX'                       ,mapi_prop_tag(PT_TSTRING,     0x3A45));
	define('PR_SPOUSE_NAME'                               ,mapi_prop_tag(PT_TSTRING,     0x3A48));
	define('PR_GENERATION'                                ,mapi_prop_tag(PT_TSTRING,     0x3A05));
	define('PR_BIRTHDAY'                                  ,mapi_prop_tag(PT_SYSTIME,     0x3A42));
	define('PR_WEDDING_ANNIVERSARY'                       ,mapi_prop_tag(PT_SYSTIME,     0x3A41));
	define('PR_SENSITIVITY'                               ,mapi_prop_tag(PT_LONG,        0x0036));
	define('PR_BUSINESS_HOME_PAGE'                        ,mapi_prop_tag(PT_TSTRING,     0x3A51));
	define('PR_LAST_MODIFICATION_TIME'                    ,mapi_prop_tag(PT_SYSTIME,     0x3008));
	define('PR_ASSISTANT_TELEPHONE_NUMBER'                ,mapi_prop_tag(PT_TSTRING,     0x3A2E));
	define('PR_BUSINESS2_TELEPHONE_NUMBER'                ,mapi_prop_tag(PT_TSTRING,     0x3A1B));
	define('PR_CALLBACK_TELEPHONE_NUMBER'                 ,mapi_prop_tag(PT_TSTRING,     0x3A02));
	define('PR_CAR_TELEPHONE_NUMBER'                      ,mapi_prop_tag(PT_TSTRING,     0x3A1E));
	define('PR_COMPANY_MAIN_PHONE_NUMBER'                 ,mapi_prop_tag(PT_TSTRING,     0x3A57));
	define('PR_HOME2_TELEPHONE_NUMBER'                    ,mapi_prop_tag(PT_TSTRING,     0x3A2F));
	define('PR_HOME_FAX_NUMBER'                           ,mapi_prop_tag(PT_TSTRING,     0x3A25));
	define('PR_ISDN_NUMBER'                               ,mapi_prop_tag(PT_TSTRING,     0x3A2D));
	define('PR_OTHER_TELEPHONE_NUMBER'                    ,mapi_prop_tag(PT_TSTRING,     0x3A1F));
	define('PR_PAGER_TELEPHONE_NUMBER'                    ,mapi_prop_tag(PT_TSTRING,     0x3A21));
	define('PR_PRIMARY_FAX_NUMBER'                        ,mapi_prop_tag(PT_TSTRING,     0x3A23));
	define('PR_PRIMARY_TELEPHONE_NUMBER'                  ,mapi_prop_tag(PT_TSTRING,     0x3A1A));
	define('PR_RADIO_TELEPHONE_NUMBER'                    ,mapi_prop_tag(PT_TSTRING,     0x3A1D));
	define('PR_TELEX_NUMBER'                              ,mapi_prop_tag(PT_TSTRING,     0x3A2C));
	define('PR_TTYTDD_PHONE_NUMBER'                       ,mapi_prop_tag(PT_TSTRING,     0x3A4B));
	define('PR_HOME_ADDRESS_STREET'                       ,mapi_prop_tag(PT_TSTRING,     0x3A5D));
	define('PR_HOME_ADDRESS_CITY'                         ,mapi_prop_tag(PT_TSTRING,     0x3A59));
	define('PR_HOME_ADDRESS_STATE_OR_PROVINCE'            ,mapi_prop_tag(PT_TSTRING,     0x3A5C));
	define('PR_HOME_ADDRESS_POSTAL_CODE'                  ,mapi_prop_tag(PT_TSTRING,     0x3A5B));
	define('PR_HOME_ADDRESS_COUNTRY'                      ,mapi_prop_tag(PT_TSTRING,     0x3A5A));
	define('PR_OTHER_ADDRESS_STREET'                      ,mapi_prop_tag(PT_TSTRING,     0x3A63));
	define('PR_OTHER_ADDRESS_CITY'                        ,mapi_prop_tag(PT_TSTRING,     0x3A5F));
	define('PR_OTHER_ADDRESS_STATE_OR_PROVINCE'           ,mapi_prop_tag(PT_TSTRING,     0x3A62));
	define('PR_OTHER_ADDRESS_POSTAL_CODE'                 ,mapi_prop_tag(PT_TSTRING,     0x3A61));
	define('PR_OTHER_ADDRESS_COUNTRY'                     ,mapi_prop_tag(PT_TSTRING,     0x3A60));
	define('PR_COUNTRY'                                   ,mapi_prop_tag(PT_TSTRING,     0x3A26));
	define('PR_LOCALITY'                                  ,mapi_prop_tag(PT_TSTRING,     0x3A27));
	define('PR_POSTAL_ADDRESS'                            ,mapi_prop_tag(PT_TSTRING,     0x3A15));
	define('PR_POSTAL_CODE'                               ,mapi_prop_tag(PT_TSTRING,     0x3A2A));
	define('PR_STATE_OR_PROVINCE'                         ,mapi_prop_tag(PT_TSTRING,     0x3A28));
	define('PR_STREET_ADDRESS'                            ,mapi_prop_tag(PT_TSTRING,     0x3A29));
	define('PR_BODY'                                      ,mapi_prop_tag(PT_TSTRING,     0x1000));

	define('PR_STORE_SUPPORT_MASK'                        ,mapi_prop_tag(PT_LONG,        0x340D));
}


function loadProperties(&$properties) {
	$properties["subject"] = PR_SUBJECT;
	$properties["icon_index"] = PR_ICON_INDEX;
	$properties["message_class"] = PR_MESSAGE_CLASS;
	$properties["display_name"] = PR_DISPLAY_NAME;
	$properties["given_name"] = PR_GIVEN_NAME;
	$properties["middle_name"] = PR_MIDDLE_NAME;
	$properties["surname"] = PR_SURNAME;
	$properties["home_telephone_number"] = PR_HOME_TELEPHONE_NUMBER;
	$properties["cellular_telephone_number"] = PR_CELLULAR_TELEPHONE_NUMBER;
	$properties["office_telephone_number"] = PR_OFFICE_TELEPHONE_NUMBER;
	$properties["business_fax_number"] = PR_BUSINESS_FAX_NUMBER;
	$properties["company_name"] = PR_COMPANY_NAME;
	$properties["title"] = PR_TITLE;
	$properties["department_name"] = PR_DEPARTMENT_NAME;
	$properties["office_location"] = PR_OFFICE_LOCATION;
	$properties["profession"] = PR_PROFESSION;
	$properties["manager_name"] = PR_MANAGER_NAME;
	$properties["assistant"] = PR_ASSISTANT;
	$properties["nickname"] = PR_NICKNAME;
	$properties["display_name_prefix"] = PR_DISPLAY_NAME_PREFIX;
	$properties["spouse_name"] = PR_SPOUSE_NAME;
	$properties["generation"] = PR_GENERATION;
	$properties["birthday"] = PR_BIRTHDAY;
	$properties["wedding_anniversary"] = PR_WEDDING_ANNIVERSARY;
	$properties["sensitivity"] = PR_SENSITIVITY;
	$properties["fileas"] = "PT_STRING8:PSETID_Address:0x8005";
	$properties["fileas_selection"] = "PT_LONG:PSETID_Address:0x8006";
	$properties["email_address_1"] = "PT_STRING8:PSETID_Address:0x8083";
	$properties["email_address_display_name_1"] = "PT_STRING8:PSETID_Address:0x8080";
	$properties["email_address_display_name_email_1"] = "PT_STRING8:PSETID_Address:0x8084";
	$properties["email_address_type_1"] = "PT_STRING8:PSETID_Address:0x8082";
	$properties["email_address_2"] = "PT_STRING8:PSETID_Address:0x8093";
	$properties["email_address_display_name_2"] = "PT_STRING8:PSETID_Address:0x8090";
	$properties["email_address_display_name_email_2"] = "PT_STRING8:PSETID_Address:0x8094";
	$properties["email_address_type_2"] = "PT_STRING8:PSETID_Address:0x8092";
	$properties["email_address_3"] = "PT_STRING8:PSETID_Address:0x80a3";
	$properties["email_address_display_name_3"] = "PT_STRING8:PSETID_Address:0x80a0";
	$properties["email_address_display_name_email_3"] = "PT_STRING8:PSETID_Address:0x80a4";
	$properties["email_address_type_3"] = "PT_STRING8:PSETID_Address:0x80a2";
	$properties["home_address"] = "PT_STRING8:PSETID_Address:0x801a";
	$properties["business_address"] = "PT_STRING8:PSETID_Address:0x801b";
	$properties["other_address"] = "PT_STRING8:PSETID_Address:0x801c";
	$properties["mailing_address"] = "PT_LONG:PSETID_Address:0x8022";
	$properties["im"] = "PT_STRING8:PSETID_Address:0x8062";
	$properties["webpage"] = "PT_STRING8:PSETID_Address:0x802b";
	$properties["business_home_page"] = PR_BUSINESS_HOME_PAGE;
	$properties["email_address_entryid_1"] = "PT_BINARY:PSETID_Address:0x8085";
	$properties["email_address_entryid_2"] = "PT_BINARY:PSETID_Address:0x8095";
	$properties["email_address_entryid_3"] = "PT_BINARY:PSETID_Address:0x80a5";
	$properties["address_book_mv"] = "PT_MV_LONG:PSETID_Address:0x8028";
	$properties["address_book_long"] = "PT_LONG:PSETID_Address:0x8029";
	$properties["oneoff_members"] = "PT_MV_BINARY:PSETID_Address:0x8054";
	$properties["members"] = "PT_MV_BINARY:PSETID_Address:0x8055";
	$properties["private"] = "PT_BOOLEAN:PSETID_Common:0x8506";
	$properties["contacts"] = "PT_MV_STRING8:PSETID_Common:0x853a";
	$properties["contacts_string"] = "PT_STRING8:PSETID_Common:0x8586";
	$properties["categories"] = "PT_MV_STRING8:PS_PUBLIC_STRINGS:Keywords";
	$properties["last_modification_time"] = PR_LAST_MODIFICATION_TIME;

	// Detailed contacts properties
	// Properties for phone numbers
	$properties["assistant_telephone_number"] = PR_ASSISTANT_TELEPHONE_NUMBER;
	$properties["business2_telephone_number"] = PR_BUSINESS2_TELEPHONE_NUMBER;
	$properties["callback_telephone_number"] = PR_CALLBACK_TELEPHONE_NUMBER;
	$properties["car_telephone_number"] = PR_CAR_TELEPHONE_NUMBER;
	$properties["company_telephone_number"] = PR_COMPANY_MAIN_PHONE_NUMBER;
	$properties["home2_telephone_number"] = PR_HOME2_TELEPHONE_NUMBER;
	$properties["home_fax_number"] = PR_HOME_FAX_NUMBER;
	$properties["isdn_number"] = PR_ISDN_NUMBER;
	$properties["other_telephone_number"] = PR_OTHER_TELEPHONE_NUMBER;
	$properties["pager_telephone_number"] = PR_PAGER_TELEPHONE_NUMBER;
	$properties["primary_fax_number"] = PR_PRIMARY_FAX_NUMBER;
	$properties["primary_telephone_number"] = PR_PRIMARY_TELEPHONE_NUMBER;
	$properties["radio_telephone_number"] = PR_RADIO_TELEPHONE_NUMBER;
	$properties["telex_telephone_number"] = PR_TELEX_NUMBER;
	$properties["ttytdd_telephone_number"] = PR_TTYTDD_PHONE_NUMBER;
	// Additional fax properties
	$properties["fax_1_address_type"] = "PT_STRING8:PSETID_Address:0x80B2";
	$properties["fax_1_email_address"] = "PT_STRING8:PSETID_Address:0x80B3";
	$properties["fax_1_original_display_name"] = "PT_STRING8:PSETID_Address:0x80B4";
	$properties["fax_1_original_entryid"] = "PT_BINARY:PSETID_Address:0x80B5";
	$properties["fax_2_address_type"] = "PT_STRING8:PSETID_Address:0x80C2";
	$properties["fax_2_email_address"] = "PT_STRING8:PSETID_Address:0x80C3";
	$properties["fax_2_original_display_name"] = "PT_STRING8:PSETID_Address:0x80C4";
	$properties["fax_2_original_entryid"] = "PT_BINARY:PSETID_Address:0x80C5";
	$properties["fax_3_address_type"] = "PT_STRING8:PSETID_Address:0x80D2";
	$properties["fax_3_email_address"] = "PT_STRING8:PSETID_Address:0x80D3";
	$properties["fax_3_original_display_name"] = "PT_STRING8:PSETID_Address:0x80D4";
	$properties["fax_3_original_entryid"] = "PT_BINARY:PSETID_Address:0x80D5";

	// Properties for addresses
	// Home address
	$properties["home_address_street"] = PR_HOME_ADDRESS_STREET;
	$properties["home_address_city"] = PR_HOME_ADDRESS_CITY;
	$properties["home_address_state"] = PR_HOME_ADDRESS_STATE_OR_PROVINCE;
	$properties["home_address_postal_code"] = PR_HOME_ADDRESS_POSTAL_CODE;
	$properties["home_address_country"] = PR_HOME_ADDRESS_COUNTRY;
	// Other address
	$properties["other_address_street"] = PR_OTHER_ADDRESS_STREET;
	$properties["other_address_city"] = PR_OTHER_ADDRESS_CITY;
	$properties["other_address_state"] = PR_OTHER_ADDRESS_STATE_OR_PROVINCE;
	$properties["other_address_postal_code"] = PR_OTHER_ADDRESS_POSTAL_CODE;
	$properties["other_address_country"] = PR_OTHER_ADDRESS_COUNTRY;
	// Business address
	$properties["business_address_street"] = "PT_STRING8:PSETID_Address:0x8045";
	$properties["business_address_city"] = "PT_STRING8:PSETID_Address:0x8046";
	$properties["business_address_state"] = "PT_STRING8:PSETID_Address:0x8047";
	$properties["business_address_postal_code"] = "PT_STRING8:PSETID_Address:0x8048";
	$properties["business_address_country"] = "PT_STRING8:PSETID_Address:0x8049";
	// Mailing address
	$properties["country"] = PR_COUNTRY;
	$properties["city"] = PR_LOCALITY;
	$properties["postal_address"] = PR_POSTAL_ADDRESS;
	$properties["postal_code"] = PR_POSTAL_CODE;
	$properties["state"] = PR_STATE_OR_PROVINCE;
	$properties["street"] = PR_STREET_ADDRESS;
	// Special Date such as birthday n anniversary appoitment's entryid is store
	$properties["birthday_eventid"] = "PT_BINARY:PSETID_Address:0x804D";
	$properties["anniversary_eventid"] = "PT_BINARY:PSETID_Address:0x804E";

	$properties["notes"] = PR_BODY;
}


function to_windows1252($string)
{
	//Zarafa 7 supports unicode chars, convert properties to utf-8 if it's another encoding
	if (defined('STORE_SUPPORTS_UNICODE') && STORE_SUPPORTS_UNICODE == true) {
		if (CSV_CHARSET == "UTF-8") {
			return $string;
		}
		if (function_exists("iconv")){
			return iconv(CSV_CHARSET, "UTF-8", $string);
		}else if (strpos(strtoupper($in_charset), "ISO-8859") !== false || strpos(strtoupper($in_charset), "WINDOWS") !== false) {
			return utf8_encode($string);
		} else {
			return $string;
		}
	}

	if (function_exists("iconv")){
		return iconv(CSV_CHARSET, "Windows-1252//TRANSLIT", $string);
	}else if ($in_charset == "UTF-8") {
		return utf8_decode($string); // no euro support here
	} else {
		return $string;
	}
}


function setProperty($property, $value, &$props, $properties) {
	if (isset($value) && isset($properties[$property])){
		//multi values have to be saved as an array
		if ($properties[$property] & MV_FLAG) {
			$value = explode(';', $value);
		}

		if (mapi_prop_type($properties[$property]) == PT_SYSTIME && !DATES_AS_TIMESTAMPS) {
			$value = strtotime($value);
		}

		if(mapi_prop_type($properties[$property]) != PT_BINARY && mapi_prop_type($properties[$property]) != PT_MV_BINARY) {
			if(is_array($value))
				$props[$properties[$property]] = array_map("to_windows1252", $value);
			else
				$props[$properties[$property]] = to_windows1252($value);
		}
		else {
			$props[$properties[$property]] = to_windows1252($value);
		}
	}
}


function setEmailAddress($emailAddress, $displayName, $cnt, &$props, $properties, &$nremails, &$abprovidertype){
	if (isset($emailAddress)){
		$email = to_windows1252($emailAddress);
		if (isset($displayName)){
			$name = to_windows1252($displayName); // adding a email address requires a name
		}else{
			$name = $email;
		}
		$props[$properties["email_address_$cnt"]] = $email;
		$props[$properties["email_address_display_name_email_$cnt"]] = $email;
		$props[$properties["email_address_display_name_$cnt"]] = $name;
		$props[$properties["email_address_type_$cnt"]] = "SMTP";
		$props[$properties["email_address_entryid_$cnt"]] = mapi_createoneoff($name, "SMTP", $email);
		$nremails[] = $cnt - 1;
		$abprovidertype |= 2 ^ ($cnt - 1);
	}
}


function mergeStreet($addressType, $value, &$props, $properties) {
	//if address has some street already, append this part after a space
	//else set it to this address
	if (isset($value)) {
		$props[$properties[$addressType."_address_street"]] =
			(isset($props[$properties[$addressType."_address_street"]])) ?
				$props[$properties[$addressType."_address_street"]]." ".to_windows1252($value) : to_windows1252($value);
	}
}


function setMailingAdress($street, $zip, $city, $state, $country, $address, &$props, $properties) {
	if (isset($street)) $props[$properties["street"]] = $street;
	if (isset($city)) $props[$properties["city"]] = $city;
	if (isset($country)) $props[$properties["country"]] = $country;
	if (isset($zip)) $props[$properties["postal_code"]] = $zip;
	if (isset($state)) $props[$properties["state"]] = $state;
	if (isset($address)) $props[$properties["postal_address"]] = $address;
}


function buildAddressString($type, $street, $zip, $city, $state, $country, &$props, $properties) {
	$out = "";

	if (isset($country) && $street != "") $out = $country;

	$zcs = "";
	if (isset($zip) && $zip != "") $zcs = $zip;
	if (isset($city) && $city != "") $zcs .= (($zcs)?" ":"") . $city;
	if (isset($state) && $state != "") $zcs .= (($zcs)?" ":"") . $state;
	if ($zcs) $out = $zcs . "\r\n" . $out;

	if (isset($street) && $street != "") $out = $street . (($out)?"\r\n\r\n". $out: "") ;

	if ($out) {
		$props[$properties[$type."_address"]] = $out;
	}
}


function isUnicodeStore($store) {
    $supportmask = mapi_getprops($store, array(PR_STORE_SUPPORT_MASK));
    if (isset($supportmask[PR_STORE_SUPPORT_MASK]) && ($supportmask[PR_STORE_SUPPORT_MASK] & STORE_UNICODE_OK)) {
        print "Store supports properties containing Unicode characters.\n";
        define('STORE_SUPPORTS_UNICODE', true);
        //setlocale to UTF-8 in order to support properties containing Unicode characters
        setlocale(LC_CTYPE, "en_US.UTF-8");
    }
}
