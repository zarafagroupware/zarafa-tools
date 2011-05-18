<?php

/**
 * @brief A rudimentary CardDAV server for Zarafa.
 *
 * @file carddav.php
 * @author Rainer Voigt, Amin Baumeler
 * @date February 1st, 2011
 * @version 0.1.5
 */

require_once "HTTP/WebDAV/Server.php";
require_once "System.php";
require_once 'File/IMC.php';

require_once("mapi/mapi.util.php");
require_once("mapi/mapidefs.php");
require_once("mapi/mapitags.php");


/**
 * helper class for parsing REPORT request bodies
 */
class _parse_report
{
	var $success;
	var $props;
	var $sync_token = '';
	var $depth;
	var $mode;
	var $current;

	/**
	 * constructor
	 * 
	 * @param  string path of input stream 
	 * @access public
	 */
	function _parse_report($path) 
	{
		$this->success = true;

		$this->depth = 0;
		$this->props = array();
		$had_input = false;

		$f_in = fopen($path, "r");
		if (!$f_in) {
			$this->success = false;
			return;
		}

		$xml_parser = xml_parser_create_ns("UTF-8", " ");

		xml_set_element_handler($xml_parser,
				array(&$this, "_startElement"),
				array(&$this, "_endElement"));

		xml_set_character_data_handler($xml_parser,
				array(&$this, "_data"));

		xml_parser_set_option($xml_parser,
				XML_OPTION_CASE_FOLDING, false);

		while($this->success && !feof($f_in)) {
			$line = fgets($f_in);
			if (is_string($line)) {
				$had_input = true;
				$this->success &= xml_parse($xml_parser, $line, false);
			}
		} 

		if($had_input) {
			$this->success &= xml_parse($xml_parser, "", true);
		}

		xml_parser_free($xml_parser);

		fclose($f_in);
	}

	/**
	 * tag start handler
	 *
	 * @param  resource  parser
	 * @param  string    tag name
	 * @param  array     tag attributes
	 * @return void
	 * @access private
	 */
	function _startElement($parser, $name, $attrs) 
	{
		if (strstr($name, " ")) {
			list($ns, $tag) = explode(" ", $name);
			if ($ns == "")
				$this->success = false;
		} else {
			$ns = "";
			$tag = $name;
		}

		if ($this->depth == 1) {
			if ($tag == 'sync-token') { // Parse sync token data
				$prop = array("name" => $tag);
				$this->current = array("name" => $tag, "ns" => $ns);
				$this->current["val"] = "";     // default set val
			} else if ($tag == "prop") { // Parse requested properties
				$this->current = array();
			} else if ($tag == "allprop") {
				$this->props = "all";
			} else if ($tag == "propname") {
				$this->props = "names";
			}
		} else if ($this->depth == 2) {
			if (isset($this->current)) {
				error_log("in if...");
				$prop = array("name" => $tag);
				if ($ns)
					$prop["xmlns"] = $ns;
				$this->props[] = $prop;
			}
		}

		$this->depth++;
	}

	/**
	 * tag end handler
	 *
	 * @param  resource  parser
	 * @param  string    tag name
	 * @return void
	 * @access private
	 */
	function _endElement($parser, $name) 
	{
		if (strstr($name, " ")) {
			list($ns, $tag) = explode(" ", $name);
			if ($ns == "")
				$this->success = false;
		} else {
			$ns = "";
			$tag = $name;
		}

		$this->depth--;

		if ($this->depth == 1) {
			if (isset($this->current)) {
				if ($tag == 'sync-token') {
					$this->sync_token = $this->current['val'];
				}
				unset($this->current);
			}
		}
	}

	/**
	 * input data handler
	 *
	 * @param  resource  parser
	 * @param  string    data
	 * @return void
	 * @access private
	 */
	function _data($parser, $data) 
	{
		if (isset($this->current)) { // Concatenate data when one of the parents requested it by creating the 'current' object
			$this->current["val"] .= $data;
		}
	}
}

/**
 * Access Zarafa contacts via WebDAV ('CardDAV')
 */
class HTTP_WebDAV_Server_Zarafa extends HTTP_WebDAV_Server 
{

	var $extension = ".vcf";
	var $mime = "text/x-vcard";

	var $server = "http://localhost:236/zarafa";

	var $zarafa = false;

	var $specialprops = array(
			"fileas" 		=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8005",
			"email1" 		=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8083",
			"business_street"	=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8045",
			"business_postcode"	=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8048",
			"business_city"		=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8046",
			"business_state"	=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8047",
			"business_country"	=> "PT_STRING8:{00062004-0000-0000-C000-000000000046}:0x8049"
			);

	var $etag_salt = "pepper ;-)";

	/**
	 * Serve a webdav request
	 */
	function ServeRequest() 
	{
		$this->http_auth_realm = "Zarafa CardDAV";
		// let the base class do all the work
		parent::ServeRequest();
	}

	/**
	 * Check authentication
	 * @param  string  HTTP Authentication type (Basic, Digest, ...)
	 * @param  string  Username
	 * @param  string  Password
	 * @return bool    true on successful authentication
	 */
	function check_auth($type, $user, $pass) 
	{
		$user = str_replace('%', '@', $user); // This fixes a logon problem with Evolution, login with user%domain instead of user@domain which seems to confuse evolution...
		$user = str_replace('$', '@', $user); // Mac OS X Address Book App fix
		$session = mapi_logon_zarafa($user, $pass, $this->server);
		if (false != $session) {
			$this->zarafa = $this->open_zarafa($session);
			$this->zarafa['user'] = $user;

			$this->specialprops = getPropIdsFromStrings($this->zarafa["store"], $this->specialprops);

			return true;
		}
		return false;
	}


	/**
	 * PROPFIND method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files) 
	{

		$store = $this->zarafa["store"];
		$contacts = $this->zarafa["contacts"];

		$files["files"] = array();
		if ($options["path"] == "/") {
			$global_etag = "";

			$rootOnly = !(($options["depth"] == "infinity") || ($options["depth"] > 0));
			foreach ($contacts as $contact) {
				$message = mapi_msgstore_openentry($store, $contact[PR_ENTRYID]);
				$props = mapi_getprops($message);
				if (!$rootOnly) // If the requested depth was 0, we only compute the global etag for the root
					$files["files"][] = $this->contactinfo($props);
				$etag = $this->get_etag($props);
				$global_etag = $global_etag.$etag;
			}
			$global_etag = sha1($global_etag);

			$info["path"] = $this->zarafa["root"];
			$info["props"] = array();
			$info["props"][] = $this->mkprop("resourcetype", "<collection/><vcard-collection xmlns=\"http://groupdav.org/\"/>");
			$info["props"][] = $this->mkprop("displayname", "ROOT");
			$info["props"][] = $this->mkprop("getetag", '"'.$global_etag.'"');
			$info["props"][] = $this->mkprop("http://calendarserver.org/ns/", "getctag", $global_etag);
			$info["props"][] = $this->mkprop("supported-report-set", 
					"<supported-report><report><sync-collection/></report></supported-report>".
					"<supported-report><report><expand-property/></report></supported-report>"); // We don't actually support this, do we...?

			$files["files"][]  = $info;
		} else {
			$contactprops = $this->get_contact($options["path"]);
			if ($contactprops) {
				$info = $this->contactinfo($contactprops);
				$files["files"][] = $info;
				$global_etag = $this->get_etag($contactprops);
			}
		}
		header("ETAG: \"".$global_etag."\"");

		return true;
	} 

	/**
	 * REPORT request handler
	 *
	 * @param options Request options as parsed by wrapper
	 * @param files Files (i.e. sync-responses). This should be a changeset since the version the client reported as last known state, but we currently only support full resyncs.
	 * @return True on success.
	 */
	function REPORT(&$options, &$files)
	{
		$store = $this->zarafa["store"];
		$contacts = $this->zarafa["contacts"];

		if ($options['sync-token'] != '') {
			/**
			 * We don't keep the sync states for each client, thus we enforce a full resync
			 * even if an older state (known by the client) is specified in the request.
			 */
			$options['sync-token-refresh'] = true;
			return false;
		}

		$files["files"] = array();
		if ($options["path"] == "/") {
			$global_etag = "";

			foreach ($contacts as $contact) {
				$message = mapi_msgstore_openentry($store, $contact[PR_ENTRYID]);
				$props = mapi_getprops($message);
				$files["files"][] = $this->contactinfo($props);
				$etag = $this->get_etag($props);
				$global_etag = $global_etag.$etag;
			}

			$global_etag = sha1($global_etag);
		} else {
			$options['path-not-supported'] = true;
			return false;
		}

		$options['sync-token'] = $global_etag;

		header("ETAG: \"".$global_etag."\"");

		return true;

	}


	/**
	 * get_contact
	 *
	 * @param	string	Path to the contact's vcf file.
	 * @return	array	Array of contact properties or false if not found.
	 */
	function get_contact($path) {
		foreach ($this->zarafa["contacts"] as $contact) {
			$message = mapi_msgstore_openentry($this->zarafa["store"], $contact[PR_ENTRYID]);
			$props = mapi_getprops($message);
			if ($this->zarafa["root"].sha1($props[PR_DISPLAY_NAME]).$this->extension == $path) {
				return $props;
			}
		}
		return false;
	}

	/**
	 * open_zarafa
	 *
	 * @param	descriptor	An open Zarafa session id.
	 * @return	array		Array with session and store information.
	 */
	function open_zarafa($session) {
		$ret = array("root" => "/");

		$storesTable = mapi_getmsgstorestable($session);
		$stores = mapi_table_queryallrows($storesTable, array(PR_ENTRYID, PR_MDB_PROVIDER));
		for($i=0;$i<count($stores); $i++){
			if ($stores[$i][PR_MDB_PROVIDER] == ZARAFA_SERVICE_GUID){
				$storeEntryid = $stores[$i][PR_ENTRYID];
				break;
			}
		}

		if (!isset($storeEntryid))
			trigger_error("Default store not found", PR_USER_ERROR);


		$store = mapi_openmsgstore($session, $storeEntryid);
		$root = mapi_msgstore_openentry($store, null);
		$rootProps = mapi_getprops($root, array(PR_IPM_CONTACT_ENTRYID));

		$folder = mapi_msgstore_openentry($store, $rootProps[PR_IPM_CONTACT_ENTRYID]);

		$table = mapi_folder_getcontentstable($folder);

		$contacts = mapi_table_queryallrows($table);

		$ret["session"] = $session;
		$ret["store"] = $store;
		$ret["contacts"] = $contacts;

		// ZCP 7 and up know unicode...
		$supportmask = mapi_getprops($store, array(PR_STORE_SUPPORT_MASK));
		if (isset($supportmask[PR_STORE_SUPPORT_MASK]) && ($supportmask[PR_STORE_SUPPORT_MASK] & STORE_UNICODE_OK)) {
			$ret["unicode_store"] = true;
  			setlocale(LC_CTYPE,'en_US.utf-8');
		}
		// END ZCP7 and up know unicode
		
		return $ret;
	}

	/**
	 * build_vcard
	 *
	 * @param	array	Contact properties.
	 * @return	object	VCard object.
	 */
	function build_vcard($contactprops) {
		$vcard = File_IMC::build('vCard');
		$vcard->setVersion('2.1');
		$charset = 'UTF-8';

		//// GENERAL INFORMATION
		$vcard->setName(
				$this->toUTF8(isset($contactprops[PR_SURNAME]) 				? $contactprops[PR_SURNAME] 			: ''), 
				$this->toUTF8(isset($contactprops[PR_GIVEN_NAME]) 			? $contactprops[PR_GIVEN_NAME]			: ''), 
				$this->toUTF8(isset($contactprops[PR_MIDDLE_NAME]) 			? $contactprops[PR_MIDDLE_NAME]			: ''), 
				$this->toUTF8(isset($contactprops[PR_DISPLAY_NAME_PREFIX]) 	? $contactprops[PR_DISPLAY_NAME_PREFIX] : ''), 
				'' // Suffix 
			       ); 
		$vcard->addParam('CHARSET',$charset);
		$vcard->setFormattedName($this->toUTF8(isset($contactprops[PR_DISPLAY_NAME]) ? $contactprops[PR_DISPLAY_NAME] : '')); 
		$vcard->addParam('CHARSET',$charset);

		if (isset($contactprops[PR_BIRTHDAY])) 						$vcard->setBirthday			($this->toUTF8(date('Y-m-d', $contactprops[PR_BIRTHDAY])));
		if (isset($contactprops[PR_PROFESSION])) 					$vcard->setRole				($this->toUTF8($contactprops[PR_PROFESSION]));
		if (isset($contactprops[PR_NICKNAME]))						$vcard->addNickname			($this->toUTF8($contactprops[PR_NICKNAME]));
		if (isset($contactprops[PR_COMPANY_NAME]))  				$vcard->addOrganization		($this->toUTF8($contactprops[PR_COMPANY_NAME])); //$this->toUTF8($contactprops[PR_DEPARTMENT_NAME] // how to append this as outlook does and prevent escaping of ';' ??
		if (isset($contactprops[PR_TITLE]))	  						$vcard->setTitle			($this->toUTF8($contactprops[PR_TITLE]));
		if (isset($this->specialprops["email1"]) &&
			isset($contactprops[$this->specialprops["email1"]])) 	$vcard->addEmail			($this->toUTF8($contactprops[$this->specialprops["email1"]]));
		if (isset($contactprops[PR_BUSINESS_HOME_PAGE])) { 			$vcard->setUrl				($this->toUTF8($contactprops[PR_BUSINESS_HOME_PAGE]));	$vcard->addParam('TYPE','WORK'); }
		if (isset($contactprops[PR_COMMENT]))						$vcard->setNote				($this->toUTF8($contactprops[PR_COMMENT]));

		//// HOME ADDRESS
		$v1 = $v2 = $v3 = $v4 = $v5 = $v6 = '';
		if (isset($contactprops[PR_HOME_ADDRESS_POST_OFFICE_BOX]))		$v1 = $contactprops[PR_HOME_ADDRESS_POST_OFFICE_BOX];
		if (isset($contactprops[PR_HOME_ADDRESS_STREET]))				$v2 = $contactprops[PR_HOME_ADDRESS_STREET];
		if (isset($contactprops[PR_HOME_ADDRESS_CITY])) 				$v3 = $contactprops[PR_HOME_ADDRESS_CITY];
		if (isset($contactprops[PR_HOME_ADDRESS_STATE_OR_PROVINCE])) 	$v4 = $contactprops[PR_HOME_ADDRESS_STATE_OR_PROVINCE];
		if (isset($contactprops[PR_HOME_ADDRESS_POSTAL_CODE])) 			$v5 = $contactprops[PR_HOME_ADDRESS_POSTAL_CODE];
		if (isset($contactprops[PR_HOME_ADDRESS_COUNTRY])) 				$v6 = $contactprops[PR_HOME_ADDRESS_COUNTRY];
		if ($v1!='' || $v2!='' || $v3!='' || $v4!='' || $v5!='' || $v6!='') {
			$vcard->addAddress(
					$this->toUTF8($v1), 
					'', // extended address
					$this->toUTF8($v2), 
					$this->toUTF8($v3), 
					$this->toUTF8($v4), 
					$this->toUTF8($v5), 
					$this->toUTF8($v6)); 
			$vcard->addParam('TYPE', 'HOME');
		}

		//// WORK ADDRESS 
		$v1 = $v2 = $v3 = $v4 = $v5 = $v6 = ''; // business post office pox (where to get this from?)
		if (isset($this->specialprops["business_street"]) &&
			isset($contactprops[$this->specialprops["business_street"]])) 	$v2 = $contactprops[$this->specialprops["business_street"]];
		if (isset($this->specialprops["business_city"]) &&
			isset($contactprops[$this->specialprops["business_city"]])) 	$v3 = $contactprops[$this->specialprops["business_city"]]; 
		if (isset($this->specialprops["business_state"]) &&
			isset($contactprops[$this->specialprops["business_state"]])) 	$v4 = $contactprops[$this->specialprops["business_state"]]; 
		if (isset($this->specialprops["business_postcode"]) &&
			isset($contactprops[$this->specialprops["business_postcode"]])) $v5 = $contactprops[$this->specialprops["business_postcode"]]; 
		if (isset($this->specialprops["business_country"]) &&
			isset($contactprops[$this->specialprops["business_country"]]))	$v6 = $contactprops[$this->specialprops["business_country"]];
		if ($v1!='' || $v2!='' || $v3!='' || $v4!='' || $v5!='' || $v6!='') {
			$vcard->addAddress(
					$this->toUTF8($v1), 
					'', // extended address
					$this->toUTF8($v2), 
					$this->toUTF8($v3), 
					$this->toUTF8($v4), 
					$this->toUTF8($v5), 
					$this->toUTF8($v6)); 
			$vcard->addParam('TYPE', 'WORK');
		}

		//// PHONE NUMBERS
		if (isset($contactprops[PR_HOME_TELEPHONE_NUMBER]))			{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_HOME_TELEPHONE_NUMBER]));		$vcard->addParam('TYPE', 'HOME');									}
		if (isset($contactprops[PR_BUSINESS_TELEPHONE_NUMBER]))		{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_BUSINESS_TELEPHONE_NUMBER]));	$vcard->addParam('TYPE', 'WORK');									}
		if (isset($contactprops[PR_HOME2_TELEPHONE_NUMBER]))		{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_HOME2_TELEPHONE_NUMBER]));		$vcard->addParam('TYPE', 'HOME');									}
		if (isset($contactprops[PR_BUSINESS2_TELEPHONE_NUMBER]))	{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_BUSINESS2_TELEPHONE_NUMBER]));	$vcard->addParam('TYPE', 'WORK');									}
		if (isset($contactprops[PR_MOBILE_TELEPHONE_NUMBER]))		{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_MOBILE_TELEPHONE_NUMBER]));		$vcard->addParam('TYPE', 'CELL');									}
		if (isset($contactprops[PR_HOME_FAX_NUMBER]))				{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_HOME_FAX_NUMBER]));				$vcard->addParam('TYPE', 'HOME');	$vcard->addParam('TYPE','FAX');	}
		if (isset($contactprops[PR_BUSINESS_FAX_NUMBER]))			{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_BUSINESS_FAX_NUMBER]));			$vcard->addParam('TYPE', 'WORK');	$vcard->addParam('TYPE','FAX');	}
		if (isset($contactprops[PR_OTHER_TELEPHONE_NUMBER]))		{ $vcard->addTelephone	($this->toUTF8($contactprops[PR_OTHER_TELEPHONE_NUMBER]));																			}

		return $vcard;
	}

	/**
	 * toUTF8
	 *
	 * @param	string	A string to encode.
	 * @return	string	Encoded string.
	 */
	function toUTF8($str) {
		// ZCP 7 supports unicode...
		if ($this->zarafa["unicode_store"] == true) return $str;
		// END ZCP 7 supports unicode...

		return utf8_encode($str);
	}

	/**
	 * Get properties for a single file/resource
	 *
	 * @param  string  resource path
	 * @return array   resource properties
	 */
	function contactinfo($contactprops) 
	{
		// create result array
		$info = array();
		$name = $contactprops[PR_DISPLAY_NAME];
		$info["path"] = $this->zarafa["root"].sha1($name).$this->extension;
		$info["props"] = array();

		// no special beautified displayname here ...
		$info["props"][] = $this->mkprop("displayname", $this->toUTF8($name));

		$info["props"][] = $this->mkprop("resourcetype", "");
		$info["props"][] = $this->mkprop("getcontenttype", $this->mime);
		$etag = $this->get_etag($contactprops);
		$info["props"][] = $this->mkprop("getetag", '"'.$etag.'"');
		$info["status"] = "HTTP/1.1 201 Created";

		return $info;
	}

	/**
	 * get_etag
	 *
	 * @param	array	Contact properties.
	 * @return	string	Unique Etag for contact object.
	 */
	function get_etag($props) {
		return sha1($this->etag_salt.$props[PR_LAST_MODIFICATION_TIME].$props[PR_DISPLAY_NAME]);
	}

	/**
	 * HEAD method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function HEAD(&$options) 
	{
		return false;
	}

	/**
	 * GET method handler
	 * 
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options) 
	{
		if ($options["path"] == "/") {
			$store = $this->zarafa["store"];
			$contacts = $this->zarafa["contacts"];
			$files["files"] = array();
			$global_etag = "";

			$html_content = "<html><head><title>Zarafa Contacts for ".$this->zarafa['user']."</title></head><body><br/>";
			foreach ($contacts as $contact) {
				$message = mapi_msgstore_openentry($store, $contact[PR_ENTRYID]);
				$props = mapi_getprops($message);
				$name = $props[PR_DISPLAY_NAME];
				$html_content .= "<a href=\"".sha1($name).$this->extension."\">".$name."</a><br/>\n";
			}
			$html_content .= "</body></html>";

			$options['mimetype'] = "text/html";
			$options['data'] = $html_content;
			return true;
		}

		$contactprops = $this->get_contact($options["path"]);

		if ($contactprops === false) 
			return false;

		$options['mimetype'] = $this->mime;
		$vcard = $this->build_vcard($contactprops);
		$vcard = $vcard->fetch();
		$etag = $this->get_etag($contactprops);
		$options['data'] = $vcard;
		header("ETAG: \"".$etag."\"");
		return true;
	}

	/**
	 * HTTP REPORT Method Wrapper
	 */
	function http_REPORT()
	{
		$options = Array();
		$files   = Array();

		$options["path"] = $this->path;

		// search depth from header (default is "infinity)
		if (isset($this->_SERVER['HTTP_DEPTH'])) {
			$options["depth"] = $this->_SERVER["HTTP_DEPTH"];
		} else {
			$options["depth"] = "infinity";
		}       

		// analyze request payload
		$propinfo = new _parse_report("php://input");
		if (!$propinfo->success) {
			$this->http_status("400 Error");
			return;
		}
		$options['props'] = $propinfo->props;
		error_log(print_r($options['props'],true));
		$options['sync-token'] = $propinfo->sync_token;

		// call user handler
		if (!$this->REPORT($options, $files)) {
			$files = array("files" => array());
			if ($options['sync-token-refresh']) {
				// Client specified a sync-token. We do not allow this.
				// See http://tools.ietf.org/html/draft-daboo-carddav-01#section-2.3 for error specification.
				//$this->http_status("412 Precondition Failed"); 
				$this->http_status("403 Forbidden"); 
				//$this->http_status("409 Conflict"); 
				header('Content-Type: text/xml; charset="utf-8"');
				echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
				echo "<D:error xmlns:D=\"DAV:\"><D:valid-sync-token/></D:error>\n";
			} elseif ($options['path-not-supported']) {
				// REPORT on root path /
				$this->http_status("404 Not Found");
			} else { 
				$this->http_status("400 Error");
			}
			
			return;
		}

		// collect namespaces here
		$ns_hash = array();

		// Microsoft Clients need this special namespace for date and time values
		$ns_defs = "xmlns:ns0=\"urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/\"";    

		// now we loop over all returned file entries
		foreach ($files["files"] as $filekey => $file) {
			// nothing to do if no properties were returend for a file
			if (!isset($file["props"]) || !is_array($file["props"])) {
				continue;
			}

			// now loop over all returned properties
			foreach ($file["props"] as $key => $prop) {
				// as a convenience feature we do not require that user handlers
				// restrict returned properties to the requested ones
				// here we strip all unrequested entries out of the response

				switch($options['props']) {
					case "all":
						// nothing to remove
						break;

					case "names":
						// only the names of all existing properties were requested
						// so we remove all values
						unset($files["files"][$filekey]["props"][$key]["val"]);
					break;

					default:
					$found = false;

					// search property name in requested properties 
					foreach ((array)$options["props"] as $reqprop) {
						if (!isset($reqprop["xmlns"])) {
							$reqprop["xmlns"] = "";
						}
						if (   $reqprop["name"]  == $prop["name"] 
								&& $reqprop["xmlns"] == $prop["ns"]) {
							$found = true;
							break;
						}
					}

					// unset property and continue with next one if not found/requested
					if (!$found) {
						$files["files"][$filekey]["props"][$key]="";
						continue(2);
					}
					break;
				}

				// namespace handling 
				if (empty($prop["ns"])) continue; // no namespace
				$ns = $prop["ns"]; 
				if ($ns == "DAV:") continue; // default namespace
				if (isset($ns_hash[$ns])) continue; // already known

				// register namespace 
				$ns_name = "ns".(count($ns_hash) + 1);
				$ns_hash[$ns] = $ns_name;
				$ns_defs .= " xmlns:$ns_name=\"$ns\"";
			}

			// we also need to add empty entries for properties that were requested
			// but for which no values where returned by the user handler
			if (is_array($options['props'])) {
				foreach ($options["props"] as $reqprop) {
					if ($reqprop['name']=="") continue; // skip empty entries

					$found = false;

					if (!isset($reqprop["xmlns"])) {
						$reqprop["xmlns"] = "";
					}

					// check if property exists in result
					foreach ($file["props"] as $prop) {
						if (   $reqprop["name"]  == $prop["name"]
								&& $reqprop["xmlns"] == $prop["ns"]) {
							$found = true;
							break;
						}
					}

					if (!$found) {
						if ($reqprop["xmlns"]==="DAV:" && $reqprop["name"]==="lockdiscovery") {
							// lockdiscovery is handled by the base class
							$files["files"][$filekey]["props"][] 
								= $this->mkprop("DAV:", 
										"lockdiscovery", 
										$this->lockdiscovery($files["files"][$filekey]['path']));
						} else {
							// add empty value for this property
							$files["files"][$filekey]["noprops"][] =
								$this->mkprop($reqprop["xmlns"], $reqprop["name"], "");

							// register property namespace if not known yet
							if ($reqprop["xmlns"] != "DAV:" && !isset($ns_hash[$reqprop["xmlns"]])) {
								$ns_name = "ns".(count($ns_hash) + 1);
								$ns_hash[$reqprop["xmlns"]] = $ns_name;
								$ns_defs .= " xmlns:$ns_name=\"$reqprop[xmlns]\"";
							}
						}
					}
				}
			}
		}

		// now we generate the reply header ...
		$this->http_status("207 Multi-Status");
		header('Content-Type: text/xml; charset="utf-8"');

		// ... and payload
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<multistatus xmlns=\"DAV:\">\n";

		foreach ($files["files"] as $file) {
			// ignore empty or incomplete entries
			if (!is_array($file) || empty($file) || !isset($file["path"])) continue;
			$path = $file['path'];                  
			if (!is_string($path) || $path==="") continue;

			echo " <sync-response $ns_defs>\n";

			/* TODO right now the user implementation has to make sure
			   collections end in a slash, this should be done in here
			   by checking the resource attribute */
			$href = $this->_mergePaths($this->_SERVER['SCRIPT_NAME'], $path);

			/* minimal urlencoding is needed for the resource path */
			$href = $this->_urlencode($href);

			echo "  <href>$href</href>\n";

			if (isset($file["status"])) {
				echo "   <status>".$file["status"]."</status>";
			}

			// report all found properties and their values (if any)
			if (isset($file["props"]) && is_array($file["props"])) {
				echo "  <propstat>\n";
				echo "   <prop>\n";

				foreach ($file["props"] as $key => $prop) {

					if (!is_array($prop)) continue;
					if (!isset($prop["name"])) continue;

					if (!isset($prop["val"]) || $prop["val"] === "" || $prop["val"] === false) {
						// empty properties (cannot use empty() for check as "0" is a legal value here)
						if ($prop["ns"]=="DAV:") {
							echo "     <$prop[name]/>\n";
						} else if (!empty($prop["ns"])) {
							echo "     <".$ns_hash[$prop["ns"]].":$prop[name]/>\n";
						} else {
							echo "     <$prop[name] xmlns=\"\"/>";
						}
					} else if ($prop["ns"] == "DAV:") {
						// some WebDAV properties need special treatment
						switch ($prop["name"]) {
							case "getcontenttype":
								echo "     <$prop[name]>$prop[val]</$prop[name]>\n";
							break;
							case "getetag":
								echo "     <$prop[name]>"
								. $this->_prop_encode($prop['val'])
								.     "</$prop[name]>\n";                               
							break;
							default:                                    
							echo "     <$prop[name]>"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								.     "</$prop[name]>\n";                               
							break;
						}
					} else {
						// properties from namespaces != "DAV:" or without any namespace 
						if ($prop["ns"]) {
							echo "     <" . $ns_hash[$prop["ns"]] . ":$prop[name]>"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								. "</" . $ns_hash[$prop["ns"]] . ":$prop[name]>\n";
						} else {
							echo "     <$prop[name] xmlns=\"\">"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								. "</$prop[name]>\n";
						}                               
					}
				}

				echo "   </prop>\n";
				echo "   <status>HTTP/1.1 200 OK</status>\n";
				echo "  </propstat>\n";
			}

			// now report all properties requested but not found
			if (isset($file["noprops"])) {
				echo "  <propstat>\n";
				echo "   <prop>\n";

				foreach ($file["noprops"] as $key => $prop) {
					if ($prop["ns"] == "DAV:") {
						echo "     <$prop[name]/>\n";
					} else if ($prop["ns"] == "") {
						echo "     <$prop[name] xmlns=\"\"/>\n";
					} else {
						echo "     <" . $ns_hash[$prop["ns"]] . ":$prop[name]/>\n";
					}
				}

				echo "   </prop>\n";
				echo "   <status>HTTP/1.1 404 Not Found</status>\n";
				echo "  </propstat>\n";
			}

			echo " </sync-response>\n";
		}
		if (isset($options['sync-token'])) {
			echo "<sync-token>".$options['sync-token']."</sync-token>";
		}

		echo "</multistatus>\n";

	}

	/**
	 * PROPFIND method handler
	 *
	 * @brief  This is almost an identical copy of the http_PROPFIND() function from Server.php of our anchestor class. Changes are marked with '#MOD'
	 * @param  void
	 * @return void
	 */
	function http_PROPFIND() 
	{
		$options = Array();
		$files   = Array();

		$options["path"] = $this->path;

		// search depth from header (default is "infinity)
		if (isset($this->_SERVER['HTTP_DEPTH'])) {
			$options["depth"] = $this->_SERVER["HTTP_DEPTH"];
		} else {
			$options["depth"] = "infinity";
		}       

		// analyze request payload
		$propinfo = new _parse_propfind("php://input");
		if (!$propinfo->success) {
			$this->http_status("400 Error");
			return;
		}
		$options['props'] = $propinfo->props;

		// call user handler
		if (!$this->PROPFIND($options, $files)) {
			$files = array("files" => array());
			if (method_exists($this, "checkLock")) {
				// is locked?
				$lock = $this->checkLock($this->path);

				if (is_array($lock) && count($lock)) {
					$created          = isset($lock['created'])  ? $lock['created']  : time();
					$modified         = isset($lock['modified']) ? $lock['modified'] : time();
					$files['files'][] = array("path"  => $this->_slashify($this->path),
							"props" => array($this->mkprop("displayname",      $this->path),
								$this->mkprop("creationdate",     $created),
								$this->mkprop("getlastmodified",  $modified),
								$this->mkprop("resourcetype",     ""),
								$this->mkprop("getcontenttype",   ""),
								$this->mkprop("getcontentlength", 0))
							);
				}
			}

			if (empty($files['files'])) {
				$this->http_status("404 Not Found");
				return;
			}
		}

		// collect namespaces here
		$ns_hash = array();

		// Microsoft Clients need this special namespace for date and time values
		$ns_defs = "xmlns:ns0=\"urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/\"";    

		// now we loop over all returned file entries
		foreach ($files["files"] as $filekey => $file) {

			// nothing to do if no properties were returend for a file
			if (!isset($file["props"]) || !is_array($file["props"])) {
				continue;
			}

			// now loop over all returned properties
			foreach ($file["props"] as $key => $prop) {
				// as a convenience feature we do not require that user handlers
				// restrict returned properties to the requested ones
				// here we strip all unrequested entries out of the response

				switch($options['props']) {
					case "all":
						// nothing to remove
						break;

					case "names":
						// only the names of all existing properties were requested
						// so we remove all values
						unset($files["files"][$filekey]["props"][$key]["val"]);
					break;

					default:
					$found = false;

					// search property name in requested properties 
					foreach ((array)$options["props"] as $reqprop) {
						if (!isset($reqprop["xmlns"])) {
							$reqprop["xmlns"] = "";
						}
						if (   $reqprop["name"]  == $prop["name"] 
								&& $reqprop["xmlns"] == $prop["ns"]) {
							$found = true;
							break;
						}
					}

					// unset property and continue with next one if not found/requested
					if (!$found) {
						$files["files"][$filekey]["props"][$key]="";
						continue(2);
					}
					break;
				}

				// namespace handling 
				if (empty($prop["ns"])) continue; // no namespace
				$ns = $prop["ns"]; 
				if ($ns == "DAV:") continue; // default namespace
				if (isset($ns_hash[$ns])) continue; // already known

				// register namespace 
				$ns_name = "ns".(count($ns_hash) + 1);
				$ns_hash[$ns] = $ns_name;
				$ns_defs .= " xmlns:$ns_name=\"$ns\"";
			}

			// we also need to add empty entries for properties that were requested
			// but for which no values where returned by the user handler
			if (is_array($options['props'])) {
				foreach ($options["props"] as $reqprop) {
					if ($reqprop['name']=="") continue; // skip empty entries

					$found = false;

					if (!isset($reqprop["xmlns"])) {
						$reqprop["xmlns"] = "";
					}

					// check if property exists in result
					foreach ($file["props"] as $prop) {
						if (   $reqprop["name"]  == $prop["name"]
								&& $reqprop["xmlns"] == $prop["ns"]) {
							$found = true;
							break;
						}
					}

					if (!$found) {
						if ($reqprop["xmlns"]==="DAV:" && $reqprop["name"]==="lockdiscovery") {
							// lockdiscovery is handled by the base class
							$files["files"][$filekey]["props"][] 
								= $this->mkprop("DAV:", 
										"lockdiscovery", 
										$this->lockdiscovery($files["files"][$filekey]['path']));
						} else {
							// add empty value for this property
							$files["files"][$filekey]["noprops"][] =
								$this->mkprop($reqprop["xmlns"], $reqprop["name"], "");

							// register property namespace if not known yet
							if ($reqprop["xmlns"] != "DAV:" && !isset($ns_hash[$reqprop["xmlns"]])) {
								$ns_name = "ns".(count($ns_hash) + 1);
								$ns_hash[$reqprop["xmlns"]] = $ns_name;
								$ns_defs .= " xmlns:$ns_name=\"$reqprop[xmlns]\"";
							}
						}
					}
				}
			}
		}

		// now we generate the reply header ...
		$this->http_status("207 Multi-Status");
		header('Content-Type: text/xml; charset="utf-8"');

		// ... and payload
		echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
		echo "<multistatus xmlns=\"DAV:\">\n";

		foreach ($files["files"] as $file) {
			// ignore empty or incomplete entries
			if (!is_array($file) || empty($file) || !isset($file["path"])) continue;
			$path = $file['path'];                  
			if (!is_string($path) || $path==="") continue;

			echo " <response $ns_defs>\n";

			/* TODO right now the user implementation has to make sure
			   collections end in a slash, this should be done in here
			   by checking the resource attribute */
			$href = $this->_mergePaths($this->_SERVER['SCRIPT_NAME'], $path);

			/* minimal urlencoding is needed for the resource path */
			$href = $this->_urlencode($href);

			echo "  <href>$href</href>\n";

			// report all found properties and their values (if any)
			if (isset($file["props"]) && is_array($file["props"])) {
				echo "  <propstat>\n";
				echo "   <prop>\n";

				foreach ($file["props"] as $key => $prop) {

					if (!is_array($prop)) continue;
					if (!isset($prop["name"])) continue;

					if (!isset($prop["val"]) || $prop["val"] === "" || $prop["val"] === false) {
						// empty properties (cannot use empty() for check as "0" is a legal value here)
						if ($prop["ns"]=="DAV:") {
							echo "     <$prop[name]/>\n";
						} else if (!empty($prop["ns"])) {
							echo "     <".$ns_hash[$prop["ns"]].":$prop[name]/>\n";
						} else {
							echo "     <$prop[name] xmlns=\"\"/>";
						}
					} else if ($prop["ns"] == "DAV:") {
						// some WebDAV properties need special treatment
						switch ($prop["name"]) {
							case "creationdate":
								echo "     <creationdate ns0:dt=\"dateTime.tz\">"
								. gmdate("Y-m-d\\TH:i:s\\Z", $prop['val'])
								. "</creationdate>\n";
							break;
							case "getlastmodified":
								echo "     <getlastmodified ns0:dt=\"dateTime.rfc1123\">"
								. gmdate("D, d M Y H:i:s ", $prop['val'])
								. "GMT</getlastmodified>\n";
							break;
							case "resourcetype":
								echo "     <resourcetype>$prop[val]</resourcetype>\n";
							break;
							case "supportedlock":
								echo "     <supportedlock>$prop[val]</supportedlock>\n";
							break;
							case "lockdiscovery":  
								echo "     <lockdiscovery>\n";
							echo $prop["val"];
							echo "     </lockdiscovery>\n";
							break;
							// the following are non-standard Microsoft extensions to the DAV namespace
							case "lastaccessed":
								echo "     <lastaccessed ns0:dt=\"dateTime.rfc1123\">"
								. gmdate("D, d M Y H:i:s ", $prop['val'])
								. "GMT</lastaccessed>\n";
							break;
							case "ishidden":
								echo "     <ishidden>"
								. is_string($prop['val']) ? $prop['val'] : ($prop['val'] ? 'true' : 'false')
								. "</ishidden>\n";
							break;
							// >> #MOD
							case "getetag":
								echo "     <$prop[name]>"
								. $this->_prop_encode($prop['val'])
								.     "</$prop[name]>\n";                               
							break;
							case "supported-report-set":
								echo "     <$prop[name]>"
								. $prop['val']
								.     "</$prop[name]>\n";
							break;
							// << #MOD
							default:                                    
							echo "     <$prop[name]>"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								.     "</$prop[name]>\n";                               
							break;
						}
					} else {
						// properties from namespaces != "DAV:" or without any namespace 
						if ($prop["ns"]) {
							echo "     <" . $ns_hash[$prop["ns"]] . ":$prop[name]>"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								. "</" . $ns_hash[$prop["ns"]] . ":$prop[name]>\n";
						} else {
							echo "     <$prop[name] xmlns=\"\">"
								. $this->_prop_encode(htmlspecialchars($prop['val']))
								. "</$prop[name]>\n";
						}                               
					}
				}

				echo "   </prop>\n";
				echo "   <status>HTTP/1.1 200 OK</status>\n";
				echo "  </propstat>\n";
			}

			// now report all properties requested but not found
			if (isset($file["noprops"])) {
				echo "  <propstat>\n";
				echo "   <prop>\n";

				foreach ($file["noprops"] as $key => $prop) {
					if ($prop["ns"] == "DAV:") {
						echo "     <$prop[name]/>\n";
					} else if ($prop["ns"] == "") {
						echo "     <$prop[name] xmlns=\"\"/>\n";
					} else {
						echo "     <" . $ns_hash[$prop["ns"]] . ":$prop[name]/>\n";
					}
				}

				echo "   </prop>\n";
				echo "   <status>HTTP/1.1 404 Not Found</status>\n";
				echo "  </propstat>\n";
			}

			echo " </response>\n";
		}

		echo "</multistatus>\n";
	}

}

// Create a server object and handle the request.
$carddav = new HTTP_WebDAV_Server_Zarafa();
$carddav->ServeRequest();

?>
