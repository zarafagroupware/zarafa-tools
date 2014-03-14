#!/usr/bin/php
<?
/* reset-freebusy.php
 *
 * Updates all users' free/busy information
 *
 *
 * Copyright 2005 - 2014  Zarafa B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation with the following additional
 * term according to sec. 7:
 *
 * According to sec. 7 of the GNU Affero General Public License, version
 * 3, the terms of the AGPL are supplemented with the following terms:
 *
 * "Zarafa" is a registered trademark of Zarafa B.V. The licensing of
 * the Program under the AGPL does not imply a trademark license.
 * Therefore any rights, title and interest in our trademarks remain
 * entirely with us.
 *
 * However, if you propagate an unmodified version of the Program you are
 * allowed to use the term "Zarafa" to indicate that you distribute the
 * Program. Furthermore you may use our trademarks where it is necessary
 * to indicate the intended purpose of a product or service provided you
 * use it in accordance with honest practices in industrial or commercial
 * matters.  If you want to propagate modified versions of the Program
 * under the name "Zarafa" or "Zarafa Server", you may only do so if you
 * have a written permission by Zarafa B.V. (to acquire a permission
 * please contact Zarafa at trademark@zarafa.com).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * The GNU Affero General Public License can be found online at
 * <http://www.gnu.org/licenses/>.
 */

include('/usr/share/php/mapi/mapi.util.php');
include('/usr/share/php/mapi/mapidefs.php');
include('/usr/share/php/mapi/mapicode.php');
include('/usr/share/php/mapi/mapitags.php');
include('/usr/share/php/mapi/mapiguid.php');

include('/usr/share/php/mapi/class.recurrence.php');
include('/usr/share/php/mapi/class.freebusypublish.php');

// Constant definitions
define("RESULT_OK",                              0 );
define("RESULT_ERROR_OPEN_MESSAGE_STORE_TABLE",  1 );
define("RESULT_ERROR_OPTIONS",                   2 );
define("RESULT_ERROR_NOCOMPANYSPECIFIED",        3 );
define("RESULT_ERROR_LOG_FAILURE",               4 );
define("RESULT_ERROR_NO_DEFAULT_STORE",          5 );
define("RESULT_ERROR_OPEN_DEFAULT_STORE",        6 );
define("RESULT_ERROR_USERLIST",                  7 );
define("RESULT_ERROR_ADDRESSBOOK",               8 );
define("RESULT_ERROR_DEFAULTDIR",                8 );
define("RESULT_ERROR_OPEN_GAB",                  9 );
define("RESULT_ERROR_GET_GAB_TABLE",            10 );
define("RESULT_ERROR_GET_USER",                 11 );
define("RESULT_ERROR_NOLOGINUSER",              12 );
define("RESULT_ERROR_NOPASSWORD",               13 );
define("RESULT_WARNING_PARTIAL_SUCCESS",       101 );
define("RESULT_WARNING_NOTHINGDONE",           102 );
define("PROGRAM_VERSION",                   '1.1.0');

class programoptions
{
    public $user      = "";             // The user of whom we want to reset freebusy data
    public $company   = "";             // The tennant of which we want to reset freebusy data
    public $all       = false;          // Reset freebusy data of all users, either in all companies or in $company
    public $loginuser = "";             // The user we want to log in with. Must always be set
    public $password  = "";             // Password of the user we want to log in with

    // Constructor
    function __construct()
    {
        $this->parsecommandline();
    }

    public static function printversion()
    {
        printf("reset-freebusy.php version %s. (C) Copyright 2014, Zarafa B.V.\n", PROGRAM_VERSION);
    }

    public static function usage()
    {
        programoptions::printversion();
        print "Usage: reset-freebusy.php --loginuser llll --password pppp --user uuuu|--all [--company cccc] [--version]\n";
        print "Arguments and options:\n\t--loginuser\t-l\tUser name to be used to log in to server\n";
        print "\t--password\t-p\tPassword belonging to login user\n";
        print "\t--company\t-c\tCompany to which resetting free\/busy data applies\n";
        print "\t--user\t\t-u\tUser name of user of whom free\/busy data must be reset\n";
        print "\t--all\t\t-a\tReset free\/busy data of all users\n";
        print "\t--version\t-V\tPrint version information and exit\n";
        exit(RESULT_OK);
    }

   function parsecommandline()
    {
        $shortopts  = "c:"; // Option needs value; same as --company
        $shortopts .= "u:"; // Option needs value; same as --user
        $shortopts .= "l:"; // Option needs value; same as --loginuser
        $shortopts .= "p:"; // Option needs value; same as --password
        $shortopts .= "a";  // Boolean option, same as --all
        $shortopts .= "V";  // Boolean option, same as --version

        $longopts  = array
		(
            "company:",     // Option needs value
            "loginuser:",   // Option needs value
            "password:",    // Option needs value
            "user:",        // Option needs value
            "all",          // Boolean option
            "version"       // Boolean option
        );

        $options = getopt($shortopts, $longopts);   // parse command line for options

        // Version
        if (isset($options["version"]) || isset($options["V"]))
        {
            $this->printversion();
            exit(RESULT_OK);
        }
        // $user
        if (isset($options["user"]))
        {
            $this->user = $options["user"];
        }
        if (isset($options["u"]))
        {
            $this->user = $options["u"];
        }
        // $company
        if (isset($options["company"]))
        {
            $this->company = $options["company"];
        }
        if (isset($options["c"]))
        {
           $this->company = $options["c"];
        }
        // $loginuser
        if (isset($options["loginuser"]))
        {
            $this->loginuser= $options["loginuser"];
        }
        if (isset($options["l"]))
        {
            $this->loginuser= $options["l"];
        }
        if ($this->loginuser == '')
        {
            print "Missing argument for login user\n";
            exit(RESULT_ERROR_NOLOGINUSER);
        }
        // $password
        if (isset($options["password"]))
        {
            $this->password= $options["password"];
        }
        if (isset($options["p"]))
        {
            $this->password= $options["p"];
        }
        if ($this->password == '')
        {
            print "Missing argument for password\n";
            exit(RESULT_ERROR_NOPASSWORD);
        }

        // $all
        $this->all = isset($options["all"]) || isset($options["a"]);

        // sanity checks
        if ($this->all && $this->user != '')
        {
            print "Options --all (-a) and --user (-u) are mutually exclusive. Don't know what to do.\n";
            exit(RESULT_ERROR_OPTIONS);
        }
        if (!$this->all && $this->user == '')
        {
            print "Neither option --all (-a) nor --user (-u) is set. Don't know what to do.\n";
            exit(RESULT_ERROR_OPTIONS);
        }
    }
}

class resetfreebusy
{
    public $options;
    public $session;
    public $msgstorestable;
    public $msgstores;
    public $storeentryid;
    public $defaultstore;
    public $userlist = array();
    public $companylist = array();
    public $addressbook;
    public $gabid;
    public $globaladdressbook;
    public $gabtable;

    function checkcommandline()
    {
        global $argc;
        if ($argc < 2)
        {
            programoptions::usage();
            exit(RESULT_OK);
        }
        else
        {
            $this->options = new programoptions;
        }
    }

    function logon()
    {
        $this->session = mapi_logon_zarafa($this->options->loginuser, $this->options->password, "file:///var/run/zarafa");
        if (!$this->session)
        {
            print "Logon failure\n";
            exit(RESULT_ERROR_LOG_FAILURE);
        }
    }

    function getdefaultstore()
    {
        $this->msgstorestable = mapi_getmsgstorestable($this->session);
        if (!$this->msgstorestable)
        {
            print "Unable to open message stores table\n";
            exit(RESULT_ERROR_OPEN_MESSAGE_STORE_TABLE);
        }

        $this->msgstores = mapi_table_queryallrows($this->msgstorestable, array(PR_DEFAULT_STORE, PR_ENTRYID));
        
        foreach ($this->msgstores as $row)
        {
            if ($row[PR_DEFAULT_STORE])
            {
                $this->storeentryid = $row[PR_ENTRYID];
            }
        }
        if (!$this->storeentryid)
        {
            print "Can't find default store\n";
            exit(RESULT_ERROR_NO_DEFAULT_STORE);
        }
    }

    function opendefaultstore()
    {
        $this->defaultstore = mapi_openmsgstore($this->session, $this->storeentryid);
        if (!$this->defaultstore)
        {
            print "Unable to open default store\n";
            exit(RESULT_ERROR_OPEN_DEFAULT_STORE);
        }
    }

    function getuserlist()
    {
        $this->companylist = mapi_zarafa_getcompanylist($this->defaultstore);
        if (mapi_last_hresult() == NOERROR && is_array($this->companylist))
        {
            // multi company setup, get all users from all companies
            if ($this->options->company != "")
            {
                foreach($this->companylist as $companyName => $companyData)
                {
                    if ($companyName == $this->options->company)
                    {
                        $this->userlist = mapi_zarafa_getuserlist($this->defaultstore, $companyData["companyid"]);
                        break;
                    }
                }
            }
            else
            {   // Sanity check: multi-company setup, but no company in options
               print "Running in a multi-company environment, but no company specified in command line.\n";
               exit(RESULT_ERROR_NOCOMPANYSPECIFIED);
            }
        }
        else
        {
            // single company setup, get list of all zarafa users
            print "Getting user list\n";
            $this->userlist = mapi_zarafa_getuserlist($this->defaultstore);
        }

        if (count($this->userlist) <= 0)
        {
            print "Unable to get user list\n";
            exit(RESULT_ERROR_USERLIST);
        }
    }

    function openaddressbook()
    {
        $this->addressbook = mapi_openaddressbook($this->session);
        if (!$this->addressbook)
        {
            print "Unable to open addressbook\n";
            exit(RESULT_ERROR_ADDRESSBOOK);
        }
    }

    function openglobaladdressbook()
    {
        $this->gabid = mapi_ab_getdefaultdir($this->addressbook);
        if (!$this->gabid)
        {
            print "Unable to get default dir\n";
            exit(RESULT_ERROR_DEFAULTDIR);
        }
        $this->globaladdressbook = mapi_ab_openentry($this->addressbook, $this->gabid);
        if (!$this->globaladdressbook)
        {
            print "Unable to open GAB $gabid\n";
            exit(1);
        }
    }

    function getgabfolder()
    {
        $this->gabtable = mapi_folder_getcontentstable($this->globaladdressbook);
        if (!$this->gabtable)
        {
            print "Unable to get GAB table\n";
            exit(1);
        }
    }

    function getusernamebyfullname($fullname)
    {
        $retval = '';
        if (is_array($this->userlist))
        {
            foreach ($this->userlist as $user)
            {
                if ($user['fullname'] == $fullname)
                {
                    $retval = $user['username'];
                    break;
                }
            }
        }
        return $retval;
    }
    
    function run()
    {
        $retval = RESULT_ERROR_GET_USER;
        $this->checkcommandline();
        $this->logon();
        $this->getdefaultstore();
        $this->opendefaultstore();
        $this->getuserlist();
        $this->openaddressbook();
        $this->openglobaladdressbook();
        $this->getgabfolder();

        $rows = mapi_table_queryallrows($this->gabtable, array(PR_ENTRYID, PR_MDB_PROVIDER, PR_DISPLAY_NAME, PR_OBJECT_TYPE));

        if (is_array($rows))
        {
            $retval = RESULT_OK;
            $anythingdone = false;
            foreach($rows as $row)
            {
                if ($row[PR_OBJECT_TYPE] == MAPI_MAILUSER && $row[PR_DISPLAY_NAME] != "SYSTEM")
                {
                    $mustprocess = $this->options->all;
                    $storename = $this->getusernamebyfullname($row[PR_DISPLAY_NAME]);
                    if (!$mustprocess)
                    {
                        $mustprocess = $storename == $this->options->user;
                    }
                    if ($mustprocess)
                    {
                        printf("Processing user '%s'\n", $storename);
  
                        if (!$this->UpdateFB($this->addressbook, $this->session, $this->defaultstore, $row[PR_ENTRYID]))
                        {
                            print "Unable to update F/B for user " . $storename . "\n";
                        }
                        else
                        {
                            $anythingdone = true;
                        }
                    }
                }
            }
            if (!$anythingdone)
            {
                print "No applicable users found.\n";
                $retval = RESULT_WARNING_NOTHINGDONE;
            }
        }
        else
        {
            print "No applicable users found.\n";
        }
        return $retval;
    }

    // Update F/B for user specified by $entryid
    function UpdateFB($addressbook, $session, $rootstore, $entryid)
    {
        $abentry = mapi_ab_openentry($addressbook, $entryid);
        if (!$abentry)
        {
            print "Unable to open entry in addressbook\n";
            return false;
        }

        $abprops = mapi_getprops($abentry, array(PR_ACCOUNT));

        $storeid = mapi_msgstore_createentryid($rootstore, $abprops[PR_ACCOUNT]);
        if (!$storeid)
        {
            print "Unable to get store entryid\n";
            return false;
        }

        $store = mapi_openmsgstore($session, $storeid);
        if (!$store)
        {
            print "Unable to open store\n";
            return false;
        }

        $root = mapi_msgstore_openentry($store);
        if (!$root)
        {
            print "Unable to open root folder\n";
            return false;
        }

        $rootprops = mapi_getprops($root, array(PR_IPM_APPOINTMENT_ENTRYID));

        $calendar = mapi_msgstore_openentry($store, $rootprops[PR_IPM_APPOINTMENT_ENTRYID]);

        $fbupdate = new FreeBusyPublish($session, $store, $calendar, $entryid);

        $fbupdate->publishFB(time() - (7 * 24 * 60 * 60), 6 * 30 * 24 * 60 * 60); // publish from one week ago, 6 months ahead

        return true;
    }
}

# Program entry point here ==>
error_reporting(E_ALL ^ E_WARNING);
$program = new resetfreebusy;
return $program->run();
?>
